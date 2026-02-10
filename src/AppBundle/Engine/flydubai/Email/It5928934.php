<?php

namespace AwardWallet\Engine\flydubai\Email;

class It5928934 extends \TAccountChecker
{
    public $mailFiles = "flydubai/it-5918809.eml, flydubai/it-5928934.eml, flydubai/it-5978446.eml, flydubai/it-6069439.eml, flydubai/it-6145171.eml";
    public $reFrom = "confirmation@flydubai.com";
    public $reSubject = [
        "en"=> "Booking Receipt",
    ];
    public $reBody = 'flydubai';
    public $reBody2 = [
        "en"=> "Departing",
    ];

    public static $dictionary = [
        "en" => [
            "Booking reference:"=> ["Your booking reference:", "Booking reference:"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        if (!$it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference:")) . "]", null, true, "#(?:" . implode("|", (array) $this->t("Booking reference:")) . ")\s+(\w+)#")) {
            $it['RecordLocator'] = $this->nextText($this->t("Booking reference:"));
        }

        // TripNumber
        // Passengers
        $it['Passengers'] = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Passengers")) . "]/ancestor::tr[1]/following-sibling::tr/td[1]/descendant::text()[normalize-space(.)][1]"));

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        if (!$total = $this->nextText($this->t("Total:"))) {
            $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total:")) . "]", null, true, "#(?:" . implode("|", (array) $this->t("Total:")) . ")\s+(.+)#");
        }
        $it['TotalCharge'] = $this->amount($this->re("#[A-Z]{3}\s+([\d\,\.]+)#", $total));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->re("#[A-Z]{3}\s+([\d\,\.]+)#", $this->nextText($this->t("Airfare:"))));

        // Currency
        $it['Currency'] = $this->re("#([A-Z]{3})\s+[\d\,\.]+#", $total);

        // Tax
        $it['Tax'] = $this->amount($this->re("#[A-Z]{3}\s+([\d\,\.]+)#", $this->nextText($this->t("Tax:"))));

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $it['TripSegments'] = [];

        $xpath = "//text()[" . $this->eq($this->t("Departing")) . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s*-\s*(\d+)(?:(/\\d+)+)?$#"); //FZ-798/681/682

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)(?:\s+" . $this->t("Terminal") . "|$)#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#" . $this->t("Terminal") . "\s+(\w+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][position()=2 or position()=3]", $root))));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)(?:\s+" . $this->t("Terminal") . "|$)#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#" . $this->t("Terminal") . "\s+(\w+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./td[3]/descendant::text()[normalize-space(.)][position()=2 or position()=3]", $root))));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\s*-\s*\d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./following::table[1]//tr[2]/td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\w+#");

            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./td[4]", $root);

            // Meal
            // Smoking
            // Stops
            /* for email 8052109
                FZ-798/681/682	Bucharest Henri Coanda 14:25  28  November  2017	Dubai Intl Airport Terminal 2 21:15  28  November  2017	4 Hours  50 Minutes
                FZ-798	Bucharest Henri Coanda 14:25  28  November  2017	Dubai Intl Airport Terminal 2 21:15  28  November  2017	4 Hours  50 Minutes
             */
            $finded = false;

            foreach ($it['TripSegments'] as $key => $value) {
                if ($value['FlightNumber'] == $itsegment['FlightNumber'] && $value['DepName'] == $itsegment['DepName'] && $value['DepDate'] == $itsegment['DepDate']) {
                    $finded = true;
                }
            }

            if ($finded == false) {
                $it['TripSegments'][] = $itsegment;
            }
        }
        $itineraries[] = $it;
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
            "#^(\d+:\d+),\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //22:50, 22 October 2015
        ];
        $out = [
            "$2 $3 $4, $1",
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
