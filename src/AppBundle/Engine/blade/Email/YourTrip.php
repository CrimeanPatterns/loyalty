<?php

namespace AwardWallet\Engine\blade\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourTrip extends \TAccountChecker
{
    public $mailFiles = "blade/it-637073097.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'departs'        => ['Departs'],
            'arrives'        => ['Arrives'],
            'statusPhrases'  => 'Your order is',
            'statusVariants' => 'confirmed',
        ],
    ];

    private $subjects = [
        'en' => [
            'BLADE - Order #',
            'Reminder: your trip to',
            'booked you on their flight to',
            'Thank you for booking your trip to',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@blade.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".blade.com/") or contains(@href,"www.blade.com") or contains(@href,".flyblade.com/") or contains(@href,"blade.flyblade.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"Copyright") and contains(normalize-space(),"BLADE Urban Air Mobility")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourTrip' . ucfirst($this->lang));

        $patterns = [
            'dateDetailed'  => '\b(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>[[:alpha:]]+\s+\d{1,2})\b', // Tuesday, Feb 27
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.)(\'’[:alpha:] ]*[[:alpha:]]', // Mr. Sven (Chris) Torbjorn Ohlund
        ];

        $t = $email->add()->transfer();

        if (preg_match("/\b(Order\s*#)\s*([-A-Z\d]{5,})\b/i", $parser->getSubject(), $m)) {
            $t->general()->confirmation($m[2], $m[1]);
        } else {
            $orderIdValues = array_filter($this->http->FindNodes("//a[contains(@href,'order_id=')]/@href", null, "/order_id=([-A-Z\d]{5,})(?:&|$)/"));

            if (count(array_unique($orderIdValues)) === 1) {
                $orderId = array_shift($orderIdValues);
                $t->general()->confirmation($orderId);
            }
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $t->general()->status($status);
        }

        $year = $this->http->FindSingleNode("//text()[starts-with(translate(normalize-space(),'0123456789','∆∆∆∆∆∆∆∆∆∆'),'Copyright ∆')]", null, true, "/^Copyright\s+(\d{4})\b/i");

        $s = $t->addSegment();

        $dateDep = $dateArr = $timeDep = $timeArr = null;
        $dateDepVal = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->starts($this->t('departs'))}]", null, true, "/^{$this->opt($this->t('departs'))}[:\s]+(.{4,})$/");
        $dateArrVal = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->starts($this->t('arrives'))}]", null, true, "/^{$this->opt($this->t('arrives'))}[:\s]+(.{4,})$/");

        if (preg_match($pattern = "/^{$patterns['dateDetailed']}\s+(?<time>{$patterns['time']})/", $dateDepVal, $m)) {
            $weekDateNumber = WeekTranslate::number1($m['wday']);
            $dateDepNormal = $this->normalizeDate($m['date']);

            if ($dateDepNormal && $year && $weekDateNumber) {
                $dateDep = EmailDateHelper::parseDateUsingWeekDay($dateDepNormal . ' ' . $year, $weekDateNumber);
            }

            $timeDep = $m['time'];
        }

        if ($dateDep && $timeDep) {
            $s->departure()->date(strtotime($timeDep, $dateDep));
        }

        if (preg_match($pattern, $dateArrVal, $m)) {
            $weekDateNumber = WeekTranslate::number1($m['wday']);
            $dateArrNormal = $this->normalizeDate($m['date']);

            if ($dateArrNormal && $year && $weekDateNumber) {
                $dateArr = EmailDateHelper::parseDateUsingWeekDay($dateArrNormal . ' ' . $year, $weekDateNumber);
            }

            $timeArr = $m['time'];
        } elseif (preg_match("/^{$patterns['time']}/", $dateArrVal, $m)) {
            $dateArr = $dateDep;
            $timeArr = $m[0];
        }

        if ($dateArr && $timeArr) {
            $s->arrival()->date(strtotime($timeArr, $dateArr));
        }

        $xpathRoute = "//tr[ count(*)=3 and *[1][normalize-space()] and *[2][normalize-space()='']/descendant::img and *[3][normalize-space()] ]";

        $nameDep = $this->http->FindSingleNode($xpathRoute . "/*[1]/descendant::text()[normalize-space()][1]/ancestor::*[ following-sibling::node()[normalize-space()] ][1]");
        $addressDep = preg_replace($pattern = "/^(.{3,}?)[,(\s]+{$this->opt($this->t('view map'))}.*/i", '$1', implode(', ', $this->http->FindNodes($xpathRoute . "/*[1]/descendant::text()[normalize-space() and not(ancestor::*[self::h1 or self::h2])]", null, '/^[\s,;]*(.{2,}?)[\s,;]*$/')));

        if (preg_match('/^[A-Z]{3}$/', $nameDep)) {
            $s->departure()->code($nameDep);
        } else {
            $s->departure()->name($nameDep);
        }

        $s->departure()->address($addressDep);

        $nameArr = $this->http->FindSingleNode($xpathRoute . "/*[3]/descendant::text()[normalize-space()][1]/ancestor::*[ following-sibling::node()[normalize-space()] ][1]");
        $addressArr = preg_replace($pattern, '$1', implode(', ', $this->http->FindNodes($xpathRoute . "/*[3]/descendant::text()[normalize-space() and not(ancestor::*[self::h1 or self::h2])]", null, '/^[\s,;]*(.{2,}?)[\s,;]*$/')));

        if (preg_match('/^[A-Z]{3}$/', $nameArr)) {
            $s->arrival()->code($nameArr);
        } else {
            $s->arrival()->name($nameArr);
        }

        $s->arrival()->address($addressArr);

        $vehicle = $this->http->FindSingleNode($xpathRoute . "/following::tr[not(.//tr) and normalize-space()][1][ following::tr[not(.//tr)][normalize-space() or descendant::img][1][normalize-space()='' and descendant::img] ]", null, true, "/^(.{2,}?)[:\s]*(?:{$this->opt($this->t('Operated by'))}|$)/i");
        $image = $this->http->FindSingleNode($xpathRoute . "/following::tr[not(.//tr) and normalize-space()][1]/following::tr[not(.//tr)][normalize-space() or descendant::img][1][normalize-space()='']/descendant::img[@src]/@src");
        $s->extra()->model($vehicle, false, true)->image($image, false, true);

        $travellers = $this->http->FindNodes("//*[{$this->eq($this->t('Passengers'))}]/following::text()[normalize-space()][1]/ancestor::ol[1]/li[normalize-space()]", null, "/^({$patterns['travellerName']})[*\s★]*$/u");

        if ($this->http->XPath->query("//*[{$this->eq($this->t('Passengers'))}]")->length > 0) {
            $t->general()->travellers(array_unique($travellers), true);
        }

        $xpathTotal = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Order Total:'))}]";
        $totalPrice = $this->http->FindSingleNode("//tr[{$xpathTotal}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $785.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $t->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $xpathCost = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Subtotal:'))}]";
            $baseFare = $this->http->FindSingleNode("//tr[{$xpathCost}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)) {
                $t->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $discountAmounts = [];

            $feeRows = $this->http->XPath->query("//tr[{$xpathCost}]/following-sibling::tr[ *[normalize-space()][2] and following::tr[{$xpathTotal}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^[-–]+\s*(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    // -$490.00
                    $discountAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                } elseif (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $t->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            if (count($discountAmounts) > 0) {
                $t->price()->discount(array_sum($discountAmounts));
            }
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['departs']) || empty($phrases['arrives'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr[ *[{$this->starts($phrases['departs'])}] and *[{$this->starts($phrases['arrives'])}] ]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]+)\s+(\d{1,2})$/u', $text, $m)) {
            // Feb 27
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)$/u', $text, $m)) {
            // 27 Feb
            $day = $m[1];
            $month = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
