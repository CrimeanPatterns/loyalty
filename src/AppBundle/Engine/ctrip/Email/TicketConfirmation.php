<?php

namespace AwardWallet\Engine\ctrip\Email;

class TicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-7935900.eml";

    public $reFrom = "ctrip.com";
    public $reBody = [
        'zh' => ['订单已经出票成功', '在携程旅行网预订的机票'],
    ];
    public $reSubject = [
        '机票确认单',
    ];
    public $lang = '';
    public static $dict = [
        'zh' => [
            'passport' => ['身份证', '护照'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'ctrip.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === true) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'订单号')]/following::text()[normalize-space(.)][1]");
        $passengers = $this->http->FindNodes("//text()[{$this->starts($this->t('passport'))}]/preceding::text()[normalize-space()][1]", null, '/^[[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]]$/u');
        $it['Passengers'] = array_filter($passengers);
        $it['ReservationDate'] = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'预订日期')]/following::text()[normalize-space(.)][1]"));
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'总金额')]/following::strong[1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $xpath = "//text()[starts-with(normalize-space(.),'→')]/ancestor::tr[1][not(contains(.,':'))]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $date = null;
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $routeText = '';
            $routeNodes = $this->http->XPath->query('td[2]/descendant::text()', $root);

            foreach ($routeNodes as $routeNode) {
                $routeText .= $routeNode->nodeValue === ' ' ? "\n" : $routeNode->nodeValue;
            }

            if (preg_match("/^(.+?)[ ]*→[ ]*(.+?)[ ]*\n+[ ]*(.{6,})$/", trim($routeText), $m)) {
                $seg['DepName'] = $m[1];
                $seg['ArrName'] = $m[2];
                $date = strtotime($this->normalizeDate($m[3]));
            }

            $seg['DepDate'] = strtotime($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)][1]//td[count(descendant::td)=0])[2]", $root), $date);
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)][1]//td[count(descendant::td)=0])[4]", $root), $date);
            $seg['Duration'] = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)][1]//td[count(descendant::td)=0])[3]", $root, true, "#[-→]+\s*(.+)#u");

            $node = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)][1]//td[count(descendant::td)=0])[5]", $root);

            if (preg_match("#(.+?)\s*(?:T(.+))?$#u", $node, $m)) {
                if (isset($seg['DepName'])) {
                    $seg['DepName'] .= ' - ' . $m[1];
                } else {
                    $seg['DepName'] = $m[1];
                }

                if (isset($m[2]) && !empty($m[2])) {
                    $seg['DepartureTerminal'] = $m[2];
                }
            }
            $node = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)][1]//td[count(descendant::td)=0])[6]", $root);

            if (preg_match("#(.+?)\s*(?:T(.+))?$#u", $node, $m)) {
                if (isset($seg['ArrName'])) {
                    $seg['ArrName'] .= ' - ' . $m[1];
                } else {
                    $seg['ArrName'] = $m[1];
                }

                if (isset($m[2]) && !empty($m[2])) {
                    $seg['ArrivalTerminal'] = $m[2];
                }
            }
            $node = implode("\n", $this->http->FindNodes("(./following-sibling::tr[normalize-space(.)][1]//td[count(descendant::td)=0])[1]//text()[normalize-space(.)]", $root));

            if (preg_match("#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s+(.+)#u", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['Cabin'] = $m[3];
            }

            if (preg_match("#计划机型\s+(.+)#", $node, $m)) {
                $seg['Aircraft'] = $m[1];
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug($date);
        $in = [
            //2017年7月30日
            '#^\s*(\d{4})年(\d+)月(\d+)日\s*$#u',
        ];
        $out = [
            '$3-$2-$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
