<?php

namespace AwardWallet\Engine\airarabia\Email;

use AwardWallet\Engine\MonthTranslate;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "airarabia/it-6863157.eml";
    public $reFrom = "reservations@airarabia.com";
    public $reSubject = [
        "en"=> "Itinerary for the Reservation",
    ];
    public $reBody = 'airarabia.com';
    public $reBody2 = [
        "en"=> "Origin / Destination",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[" . $this->eq("Seat Id") . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);
        $codes = [];
        $seats = [];

        foreach ($nodes as $root) {
            if ($fl = $this->http->FindSingleNode("./td[3]", $root, true, "#^\w{2}(\d+)$#")) {
                $codes[$fl]['Dep'] = $this->http->FindSingleNode("./td[2]", $root, true, "#^([A-Z]{3})/[A-Z]{3}$#");
                $codes[$fl]['Arr'] = $this->http->FindSingleNode("./td[2]", $root, true, "#^[A-Z]{3}/([A-Z]{3})$#");
                $seats[$fl][] = $this->http->FindSingleNode("./td[5]", $root);
            }
        }
        // print_r($codes);
        // die();
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("RESERVATION NUMBER");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq("Passenger Name(s)") . "]/ancestor::tr[1]/following-sibling::tr/td[1]");
        $it['Passengers'] = preg_replace("/^\s*(Child\.|Baby\.|Ребенок |Enfant |Bébé )\s*/", '', $it['Passengers']);
        $it['Passengers'] = preg_replace("/^\s*((MS|MRS|MR|DR|MISS|MSTR) )/", '', $it['Passengers']);
        $it['Passengers'] = preg_replace("/^\s*(.+?)\s*Passport No\..*/", '$1', $it['Passengers']);

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = array_sum(array_map([$this, 'amount'], $this->http->FindNodes("//text()[" . $this->contains("T O T A L") . "]/ancestor::td[1]/following-sibling::td[1]", null, "#[\d\,\.]+#")));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("(//text()[" . $this->contains("T O T A L") . "])[1]/ancestor::td[1]/following-sibling::td[1]"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->nextText("DATE OF BOOKING")));

        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->eq("Departure") . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[8]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = isset($codes[$itsegment['FlightNumber']]) ? $codes[$itsegment['FlightNumber']]['Dep'] : TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#(.*?)\s+/#");

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./td[position()=2 or position()=3]", $root))));

            // ArrCode
            $itsegment['ArrCode'] = isset($codes[$itsegment['FlightNumber']]) ? $codes[$itsegment['FlightNumber']]['Arr'] : TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\s+/\s+(.+)#");

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./td[position()=4 or position()=5]", $root))));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[8]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[9]", $root);

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
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d+:\d+)$#", //Fri, 27 Mar 2015, 09:00
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
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
