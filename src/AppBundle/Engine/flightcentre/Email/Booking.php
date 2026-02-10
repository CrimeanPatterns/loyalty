<?php

namespace AwardWallet\Engine\flightcentre\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-6993265.eml, flightcentre/it-7006343.eml";

    public $reFrom = "flightcentre.com.au";
    public $reBody = [
        'en' => ['Thank you for booking your flights', 'Booking Confirmation'],
    ];
    public $reSubject = [
        'Flight Centre Booking',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'recLoc'     => ['BOOKING NUMBER', 'Flight Centre Booking Reference'],
            '__flight__' => ['OUTBOUND', 'INBOUND'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Booking' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'flightcentre.com.au')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
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
        $rule = implode(" or ", array_map(function ($s) {return "starts-with(translate(normalize-space(.),'" . strtoupper($s) . "','" . strtolower($s) . "'),'" . strtolower($s) . "')"; }, (array) $this->t('recLoc')));
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$rule}]/following::text()[normalize-space(.)][1]");

        $rule = implode(" or ", array_map(function ($s) {return "contains(translate(.,'" . strtoupper($s) . "','" . strtolower($s) . "'),'" . strtolower($s) . "')"; }, (array) $this->t('__flight__')));
        $xpath = "//text()[{$rule}]/ancestor::tr[1]/following-sibling::tr/descendant::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $node = implode("\n", $this->http->FindNodes("./td[normalize-space(.)][1]//text()[normalize-space(.)]", $root));

            if (preg_match("#(.+?)\s+([A-Z]{3})\s+(\d+:\d+.+?\s+\d{4})#s", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[3]));
            }
            $node = implode("\n", $this->http->FindNodes("./td[normalize-space(.)][2]//text()[normalize-space(.)]", $root));

            if (preg_match("#(.+?)\s+([A-Z]{3})\s+(\d+:\d+.+?\s+\d{4})#s", $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[3]));
            }
            $node = $this->http->FindSingleNode("./td[normalize-space(.)][3]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            //8:05AM
            //Friday 13 March 2015
            '#^\s*(\d+:\d+(?:\s*[ap]m)?)\s+\w+\s+(\d+)\s+(\w+)\s+(\d{4})\s*$#i',
        ];
        $out = [
            '$2 $3 $4 $1',
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
                 && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
