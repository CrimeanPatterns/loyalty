<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FliggyAirTickets extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-631057341-zh.eml, ctrip/it-637019200-zh.eml";

    public $lang = '';

    public static $dictionary = [
        'zh' => [
            'model'                   => '机型',
            'airlineBookingReference' => ['航司预订号:', '航司预订号 :', '航司预订号：'],
            'adult'                   => ['普通成人', '成人'],
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
        return preg_match('/[.@](fliggy|alitrip)\.com$/i', $from) > 0;
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
        $textHtml = $this->http->Response['body'];

        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && stripos($textHtml, '.taobao.com/') === false && stripos($textHtml, 'm.taobao.com') === false
            && strpos($textHtml, '飞猪旅行') === false
        ) {
            return false;
        }

        $textHtml = preg_replace('/(<meta\b.*?charset\s*=\s*[\'"])\s*.*?\s*([\'"].*?\/?>)/is', '$1$2', $textHtml);
        $this->http->SetEmailBody($textHtml);

        return $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textHtml = $this->http->Response['body'];
        $textHtml = preg_replace('/(<meta\b.*?charset\s*=\s*[\'"])\s*.*?\s*([\'"].*?\/?>)/is', '$1$2', $textHtml);
        $this->http->SetEmailBody($textHtml);

        $this->assignLang();
        $email->setType('FliggyAirTickets' . ucfirst($this->lang));

        $patterns = [
            'date'           => '\b\d{1,2}月\d{1,2}日\s+[[:alpha:]]+?', // 03月19日 星期二
            'time'           => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName'  => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+', // KOH / KIM LENG MR
            'eTicket'        => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $airportCodes = [];
        $headerTexts = $this->http->FindNodes("//text()[{$this->contains(['(', '（'])} and {$this->contains(['-', '–'])} and {$this->contains([')', '）'])}]");

        foreach ($headerTexts as $s) {
            if (preg_match("/^(?<name1>.+?)\s*[\(（]\s*(?<code1>[A-Z]{3})\s*[\)）]\s*[-–]+\s*(?<name2>.+?)\s*[\(（]\s*(?<code2>[A-Z]{3})\s*[\)）]/u", $s, $m)) {
                // 成都（CTU） - 开罗（CAI）往返 航班
                $airportCodes[$m['name1']] = $m['code1'];
                $airportCodes[$m['name2']] = $m['code2'];

                break;
            }
        }

        $bookedDate = strtotime($this->http->FindSingleNode("//text()[{$this->contains($this->t('预订的订单'))}]", null, true, '/\b\d{4}-\d{1,2}-\d{1,2}\b/'));
        $f->general()->date($bookedDate);
        $year = date('Y', $bookedDate);

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('预订的订单'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($otaConfirmation) {
            $email->ota()->confirmation($otaConfirmation);
        }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('adult'))}]", null, "/^({$patterns['travellerName']}|{$patterns['travellerName2']})(?:\s*[(（]|$)/u"));
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

        $totalPrice = $this->http->FindSingleNode("//*[ count(descendant::text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($this->t('总计'))}] ]/descendant::text()[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // ¥7772
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//*[ count(descendant::text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($this->t('票面价'))}] ]/descendant::text()[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $baseFare, $m)) {
                $email->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $taxes = $this->http->FindSingleNode("//*[ count(descendant::text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($this->t('税费'))}] ]/descendant::text()[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $taxes, $m)) {
                $email->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        if (count($airportCodes) === 0) {
            $this->logger->debug('Airport codes not found!');

            return $email;
        }

        $segConfNoStatuses = [];

        $sections = $this->http->XPath->query("//*[{$this->eq($this->t('航班信息'))}]/following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->contains(array_keys($airportCodes))}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->xpath['time']}] ]");

        foreach ($sections as $sect) {
            $sectDate = $sectDateText = $sectFrom = $sectTo = null;
            $sectionHeader = $this->http->FindSingleNode("*[normalize-space()][1]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()][1]", $sect);

            if (preg_match("/^(?<date>{$patterns['date']})\s+(?<route>.+)$/u", $sectionHeader, $m)) {
                // 03月19日 星期二 开罗 - 成都
                if (preg_match("/^(?<date>\d{1,2}\s*月\s*\d{1,2}\s*日)\s+(?<wday>[[:alpha:]]+)$/u", $m['date'], $m2)) {
                    $dateNormal = $this->normalizeDate($m2['date']);
                    $weekDateNumber = WeekTranslate::number1($m2['wday'], $this->lang);

                    if ($dateNormal && $weekDateNumber && $year) {
                        $sectDate = EmailDateHelper::parseDateUsingWeekDay($dateNormal . '/' . $year, $weekDateNumber);
                    }
                }
                $sectDateText = $m['date'];

                if (preg_match('/^(.+?)\s*[-–]+\s*(.+)/u', $m['route'], $m2)) {
                    $sectFrom = $m2[1];
                    $sectTo = $m2[2];
                }
            }

            $segments = $this->findSegments($sect);

            foreach ($segments as $i => $root) {
                $s = $f->addSegment();

                $timeDepVal = $this->http->FindSingleNode("*[1]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[1]", $root);
                $duration = $this->http->FindSingleNode("*[1]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[2]", $root, true, "/^{$this->opt($this->t('飞行约'))}\s*(\d.+)$/");
                $s->extra()->duration($duration, false, true);
                $timeArrVal = $this->http->FindSingleNode("*[1]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[3]", $root);

                if (preg_match($pattern = "/^(?<date>.{3,}?)\s+(?<time>{$patterns['time']})/", $timeDepVal, $m)) {
                    $dateDepNormal = $this->normalizeDate($m['date']);

                    if (!preg_match('/\b\d{4}$/', $dateDepNormal) && $sectDate && $dateDepNormal) {
                        $dateDep = EmailDateHelper::parseDateRelative($dateDepNormal, $sectDate, true, '%D%/%Y% ' . $m['time']);
                        $s->departure()->date($dateDep);
                    }
                }

                if (preg_match($pattern, $timeArrVal, $m)) {
                    $dateArrNormal = $this->normalizeDate($m['date']);

                    if (!preg_match('/\b\d{4}$/', $dateArrNormal) && $sectDate && $dateArrNormal) {
                        $dateArr = EmailDateHelper::parseDateRelative($dateArrNormal, $sectDate, true, '%D%/%Y% ' . $m['time']);
                        $s->arrival()->date($dateArr);
                    }
                }

                $airportDep = $this->http->FindSingleNode("*[3]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[1]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()][1]", $root);
                $flight = $this->http->FindSingleNode("*[3]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[1]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()][2]", $root);
                $cabin = $this->http->FindSingleNode("*[3]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[2]/descendant-or-self::*[ *[2] ][1]/*[2]", $root, false);
                $s->extra()->cabin($cabin, false, true);
                $airportArr = $this->http->FindSingleNode("*[3]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[3]", $root);

                if (preg_match('/(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\s*[\|｜]|$)/u', $flight, $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                }

                if (preg_match('/^[^\|｜]+[\|｜]+\s*([^|｜]+)$/u', $flight, $m)) {
                    $s->extra()->aircraft($m[1]);
                }

                if (preg_match($pattern = "/^(?<name>.{2,}?(?:\s+|[^A-z]))T[-\s]*(?<terminal>[A-Z\d]|\d+)$/", $airportDep, $m)) {
                    $s->departure()->name(rtrim($m['name']))->terminal($m['terminal']);
                } else {
                    $s->departure()->name($airportDep);
                }

                if (preg_match($pattern, $airportArr, $m)) {
                    $s->arrival()->name(rtrim($m['name']))->terminal($m['terminal']);
                } else {
                    $s->arrival()->name($airportArr);
                }

                if ($i === 0 && !empty($sectFrom) && array_key_exists($sectFrom, $airportCodes)) {
                    $s->departure()->code($airportCodes[$sectFrom]);
                } elseif ($airportDep) {
                    $s->departure()->noCode();
                }

                if ($i === ($segments->length - 1) && !empty($sectTo) && array_key_exists($sectTo, $airportCodes)) {
                    $s->arrival()->code($airportCodes[$sectTo]);
                } elseif ($airportArr) {
                    $s->arrival()->noCode();
                }

                $xpathTransitValue = "[{$this->eq($this->t('中转'))}]/ancestor-or-self::node()[ following-sibling::node()[normalize-space()] ][1]/following-sibling::node()[normalize-space()][1]";
                $transitFrom = $this->http->FindSingleNode("preceding::text()[normalize-space()][position()<7]" . $xpathTransitValue, $root);
                $transitTo = $this->http->FindSingleNode("following::text()[normalize-space()][1]" . $xpathTransitValue, $root);
                $segFrom = $transitFrom ?? $sectFrom;
                $segTo = $transitTo ?? $sectTo;

                if (empty($sectDateText) || empty($segFrom) || empty($segTo)) {
                    $segConfNoStatuses[] = false;
                    $this->logger->debug('Word set `DATE+FROM+TO` not found!');

                    continue;
                }

                $bookingReferences = array_filter($this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($i + 1 . ' ' . $sectDateText . ' ' . $segFrom . ' ' . $segTo, "translate(.,'、,-–—',' ')")}] ]/*[normalize-space()][2]/descendant::*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('airlineBookingReference'))}] ]/node()[normalize-space()][2]", null, '/^[A-Z\d]{5,}$/'));

                if (count(array_unique($bookingReferences)) === 1) {
                    $bookingReference = array_shift($bookingReferences);
                    $s->airline()->confirmation($bookingReference);
                    $segConfNoStatuses[] = true;
                } elseif (count(array_unique($bookingReferences)) > 1) {
                    $segConfNoStatuses[] = false;
                }
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
        return $this->http->XPath->query("descendant::*[ count(*)=3 and *[1]/descendant::text()[{$this->xpath['time']}][2] and *[2][normalize-space()=''] and *[3]/descendant::text()[normalize-space()][2] ]", $root);
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['model']) || empty($phrases['airlineBookingReference'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['model'])} or {$this->contains($phrases['airlineBookingReference'])}]")->length > 0) {
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
        if (preg_match('/^(\d{1,2})\s*月\s*(\d{1,2})\s*日$/', $text, $m)) {
            // 03月19日
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})\s*[-–]\s*(\d{1,2})$/', $text, $m)) {
            // 03-19
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
