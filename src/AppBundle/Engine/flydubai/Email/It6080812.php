<?php

namespace AwardWallet\Engine\flydubai\Email;

class It6080812 extends \TAccountChecker
{
    public $mailFiles = "flydubai/it-6080812.eml, flydubai/it-7510486.eml";
    public $reFrom = "reservations@flydubai.com";
    public $reSubject = [
        "en" => "Online check-in reminder for",
        "en2"=> "Online Check-In Reminder for",
    ];
    public $reBody = 'flydubai';
    public $reBody2 = [
        "en"=> "Departure",
    ];

    public static $dictionary = [
        "en" => [
            "Flight number:" => ["Flight number:", "Flight no:"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[" . $this->eq($this->t("Flight number:")) . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./tr[2]/td[2]", $root);
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl=>$root) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger name:")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[3]"));

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            // TotalCharge
            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($nodes as $root) {
                if (!$date = $this->http->FindSingleNode("./tr[2]/td[3]", $root)) {
                    $date = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root);
                }
                $date = strtotime($this->normalizeDate($date));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[2]/td[1]", $root, true, "#^\w{2}\s+(\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[5]/td[1]", $root, true, "#\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[5]/td[1]", $root, true, "#(.*?)(?:\s+" . $this->t("Terminal") . "|\s+\([A-Z]{3}\))#");

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./tr[5]/td[1]", $root, true, "#" . $this->t("Terminal") . "\s+(\w+)#");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./tr[4]/td[1]", $root, true, "#:\s+(.*?)\s+\(#"), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[5]/td[2]", $root, true, "#\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[5]/td[2]", $root, true, "#(.*?)(?:\s+" . $this->t("Terminal") . "|\s+\([A-Z]{3}\))#");

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./tr[5]/td[2]", $root, true, "#" . $this->t("Terminal") . "\s+(\w+)#");

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./tr[4]/td[2]", $root, true, "#:\s+(.*?)\s+\(#"), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[2]/td[1]", $root, true, "#^(\w{2})\s+\d+$#");

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+)\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //22:50 22 October 2015
            "#^(\w+),\s+(\d+)\s+([^\d\s]+),\s+(\d{4})$#u", //Saturday, 26 September, 2015
        ];
        $out = [
            "$2 $3 $4, $1",
            "$2 $3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
