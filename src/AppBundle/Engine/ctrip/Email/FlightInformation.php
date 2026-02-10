<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightInformation extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-640132955-zh.eml, ctrip/it-643679402-zh.eml";

    public $lang = '';

    public static $dictionary = [
        'zh' => [
            'airlineBookingReference' => ['航司预订号:', '航司预订号 :', '航司预订号：'],
            'traveller'               => ['成人', '儿童'],
            'ticketNo'                => ['票号:', '票号 :', '票号：'],
        ],
    ];

    private $subjects = [
        'zh' => ['行程确认单'],
    ];

    private $xpath = [
        'time' => 'contains(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]trip\.com$/i', $from) > 0;
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
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".ctrip.com/") or contains(@href,".ctrip.com%2F") or contains(@href,".ctrip.cn/") or contains(@href,".ctrip.cn%2F") or contains(@href,"flights.ctrip.com") or contains(@href,"t.ctrip.cn")]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('FlightInformation' . ucfirst($this->lang));

        $patterns = [
            'date'           => '\b\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日', // 2024年02月15日
            'time'           => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName'  => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // KOH / KIM LENG MR
            'eTicket'        => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $bookedDate = strtotime($this->http->FindSingleNode("//text()[{$this->contains($this->t('预订的订单'))}]", null, true, '/\b\d{4}-\d{1,2}-\d{1,2}\b/'));
        $f->general()->date($bookedDate);

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('预订的订单'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($otaConfirmation) {
            $email->ota()->confirmation($otaConfirmation);
        }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('traveller'))}]", null, "/^({$patterns['travellerName']}|{$patterns['travellerName2']})(?:\s*[(（]|$)/u"));
        $f->general()->travellers($travellers, true);

        $tickets = [];
        $ticketValues = $this->http->FindNodes("//*[ count(descendant::text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($this->t('ticketNo'))}] ]/descendant::text()[normalize-space()][2]");

        foreach ($ticketValues as $tVal) {
            $tValParts = preg_split('/(\s*[,]+\s*)+/', $tVal);

            foreach ($tValParts as $part) {
                if (preg_match("/^{$patterns['eTicket']}$/", $part)) {
                    $tickets[] = $part;
                }
            }
        }

        if (count($tickets) > 0) {
            $f->issued()->tickets(array_unique($tickets), false);
        }

        $totalPrice = $this->http->FindSingleNode("//text()[normalize-space()][{$this->eq($this->t('总计'))}]/ancestor::*[ descendant::text()[normalize-space()][3] ][1]", null, true, "/^{$this->opt($this->t('总计'))}[:\s]*(.*\d.*)$/");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // ¥11008.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('机票'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $baseFare, $m)) {
                $email->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $taxes = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('税'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $taxes, $m)) {
                $email->price()->fee($this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('税'))}]"), PriceHelper::parse($m['amount'], $currencyCode));
            }

            $discountAmounts = [];

            $feeRows = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('其他服务'))}] ]/*[normalize-space()][2]/tr[count(*[normalize-space()])=2]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?\s*[-–]+\s*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    // ¥-35.00
                    $discountAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                } elseif (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $email->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            if (count($discountAmounts) > 0) {
                $email->price()->discount(array_sum($discountAmounts));
            }
        }

        $segConfNoStatuses = [];

        $segments = $this->findSegments();

        foreach ($segments as $i => $root) {
            $s = $f->addSegment();

            $sectDate = $sectFrom = $sectTo = null;
            $sectionHeader = implode(' ', $this->http->FindNodes("ancestor::table[count(preceding-sibling::*[normalize-space()])=1][1]/preceding-sibling::*[normalize-space()]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?<date>{$patterns['date']})\s+(?<route>.{3,})/", $sectionHeader, $m)) {
                // 2024年02月14日长沙 - 开罗
                $sectDate = strtotime($this->normalizeDate($m['date']));

                if (preg_match('/^(.+?)\s*[-–]+\s*(.+)/u', $m['route'], $m2)) {
                    $sectFrom = $m2[1];
                    $sectTo = $m2[2];
                }
            }

            $dateDepVal = implode(' ', $this->http->FindNodes("tr[1]/*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern = "/^(?<date>\d{1,2}[-–]+\d{1,2})\s+(?<time>{$patterns['time']})/", $dateDepVal, $m) && $sectDate) {
                // 02-15 13:45
                $dateDepNormal = $this->normalizeDate($m['date']);
                $dateDep = EmailDateHelper::parseDateRelative($m['time'] . ' ' . $dateDepNormal, $sectDate, true, '%D%/%Y%');
                $s->departure()->date($dateDep);
            } elseif (preg_match("/^{$patterns['time']}$/", $dateDepVal) && $sectDate) {
                $s->departure()->date(strtotime($dateDepVal, $sectDate));
            }

            $airportDep = $this->http->FindSingleNode("tr[1]/*[normalize-space()][2]", $root, true, "/^(.{2,}?)\s*(?:{$this->opt($this->t('航站楼'))})?$/");
            $dateArrVal = implode(' ', $this->http->FindNodes("tr[3]/*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $dateArrVal, $m) && $sectDate) {
                $dateArrNormal = $this->normalizeDate($m['date']);
                $dateArr = EmailDateHelper::parseDateRelative($m['time'] . ' ' . $dateArrNormal, $sectDate, true, '%D%/%Y%');
                $s->arrival()->date($dateArr);
            }

            $airportArr = $this->http->FindSingleNode("tr[3]/*[normalize-space()][2]", $root, true, "/^(.{2,}?)\s*(?:{$this->opt($this->t('航站楼'))})?$/");

            if (preg_match($pattern = "/^(?<name>.{2,}?(?:\s+|[^A-z]))T[-\s]*(?<terminal>[A-Z\d]|\d+)$/", $airportDep, $m)) {
                // KUL 吉隆坡机场 T2
                $airportDep = $m['name'];
                $s->departure()->terminal($m['terminal']);
            }

            if (preg_match($pattern, $airportArr, $m)) {
                $airportArr = $m['name'];
                $s->arrival()->terminal($m['terminal']);
            }

            if (preg_match($pattern = "/^(?<code>[A-Z]{3})\s+(?<name>.{2,})$/", $airportDep, $m)) {
                // KUL 吉隆坡机场
                $s->departure()->code($m['code'])->name($m['name']);
            } elseif ($airportDep) {
                $s->departure()->name($airportDep)->noCode();
            }

            if (preg_match($pattern, $airportArr, $m)) {
                $s->arrival()->code($m['code'])->name($m['name']);
            } elseif ($airportArr) {
                $s->arrival()->name($airportArr)->noCode();
            }

            $flight = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $root);

            if (preg_match("/(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $duration = $this->http->FindSingleNode("following::text()[normalize-space()][2]", $root, true, "/{$this->opt($this->t('飞行时长'))}[:\s]*(\d.{1,15})$/");
            $s->extra()->duration($duration, false, true);

            $xpathExtra = "following::text()[normalize-space()][1]/ancestor::*[ *[normalize-space()][2][{$this->starts($this->t('飞行时长'))}] ][1]/*[normalize-space()][3][count(descendant::text()[normalize-space()])=2]/descendant::text()[normalize-space()]";

            $cabin = $this->http->FindSingleNode($xpathExtra . '[1]', $root);
            $aircraft = $this->http->FindSingleNode($xpathExtra . '[2]', $root);
            $s->extra()->cabin($cabin, false, true)->aircraft($aircraft, false, true);

            $xpathTransitValue = "[{$this->starts($this->t('中转'))}]";
            $transitFrom = $this->http->FindSingleNode("preceding::text()[normalize-space()][position()<5]" . $xpathTransitValue, $root, true, $pattern = "/{$this->opt($this->t('中转'))}\s+(.+?)\s*(?:{$this->opt($this->t('停留'))}|$)/");
            $transitTo = $this->http->FindSingleNode("ancestor::*[ *[normalize-space()][2] ][1]/following::text()[normalize-space()][1]" . $xpathTransitValue, $root, true, $pattern);
            $segFrom = $transitFrom ?? $sectFrom;
            $segTo = $transitTo ?? $sectTo;

            if (empty($s->getDepDate()) || empty($segFrom) || empty($segTo)) {
                $segConfNoStatuses[] = false;
                $this->logger->debug('Word set `DATE+FROM+TO` not found!');

                continue;
            }

            $dateDep = date('m-d', $s->getDepDate());

            $bookingReferences = array_filter($this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($i + 1 . ' ' . $segFrom . ' - ' . $segTo . ' ' . $dateDep, "translate(.,'、,',' ')")}] ]/*[normalize-space()][2]/descendant::*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('airlineBookingReference'))}] ]/node()[normalize-space()][2]", null, '/^[A-Z\d]{5,}$/'));

            if (count(array_unique($bookingReferences)) === 1) {
                $bookingReference = array_shift($bookingReferences);
                $s->airline()->confirmation($bookingReference);
                $segConfNoStatuses[] = true;
            } elseif (count(array_unique($bookingReferences)) > 1) {
                $segConfNoStatuses[] = false;
            }
        }

        if (count(array_unique($segConfNoStatuses)) === 1 && $segConfNoStatuses[0] === true) {
            $f->general()->noConfirmation();
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

    private function findSegments(?\DOMNode $root = null): \DOMNodeList
    {
        return $this->http->XPath->query("//*[ count(tr)=3 and tr[1]/descendant::text()[normalize-space()][position()<3][{$this->xpath['time']}] and tr[2][normalize-space()=''] and tr[3]/descendant::text()[normalize-space()][position()<3][{$this->xpath['time']}] ]", $root);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['airlineBookingReference'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['airlineBookingReference'])}]")->length > 0) {
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
        if (preg_match('/^(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日$/', $text, $m)) {
            // 2024年02月15日
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
        } elseif (preg_match('/^(\d{1,2})\s*[-–]\s*(\d{1,2})$/', $text, $m)) {
            // 02-15
            $month = $m[1];
            $day = $m[2];
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
