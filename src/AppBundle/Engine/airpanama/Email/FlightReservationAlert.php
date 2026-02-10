<?php

namespace AwardWallet\Engine\airpanama\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightReservationAlert extends \TAccountChecker
{
    public $mailFiles = "airpanama/it-8015945.eml";
    public $reFrom = "reservaciones@airpanama.com";
    public $reSubject = [
        "en"=> "AirPanama - Flight Reservation Alert",
    ];
    public $reBody = 'www.kuwaitairways.com';
    public $reBody2 = [
        "en"=> "Flight Details",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts("Your RESERVATION CODE is") . "]", null, true, "#:\s*(.+)#");

        // TripNumber
        // Passengers
        $nodes = $this->http->XPath->query("//text()[" . $this->eq("First Name") . "]/ancestor::tr[1]/following-sibling::tr");

        foreach ($nodes as $root) {
            $it['Passengers'][] = $this->http->FindSingleNode("./td[2]", $root) . ' ' . $this->http->FindSingleNode("./td[3]", $root);
        }
        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText("Total Charges"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total Charges"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq("Departure Date") . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[3]", $root);

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root);

            // DepName
            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./td[5]|./td[6]", $root))));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[2]", $root);

            // ArrName
            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./td[7]|./td[8]", $root))));

            // AirlineName
            if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Airpanama will send your confirmation code ")) . "])[1]"))) {
                $itsegment['AirlineName'] = '7P';
            }
            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[4]", $root);

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

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
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

        $this->http->FilterHTML = true;
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

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d{4})-(\d+)-(\d+),\s+(\d+:\d+\s+[AP]M)\s+\([^\s\d]+\)$#", //2014-08-18, 06:30 AM (Mon)
        ];
        $out = [
            "$3.$2.$1, $4",
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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
