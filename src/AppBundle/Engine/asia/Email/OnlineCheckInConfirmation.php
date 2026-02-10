<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Engine\MonthTranslate;

class OnlineCheckInConfirmation extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "onlinecheckin@cathaypacific.com";
    public $reSubject = [
        "en"=> "Cathay Pacific Online Check-In Confirmation",
    ];
    public $reBody = 'Cathay Pacific Airways';
    public $reBody2 = [
        "en"=> "Online Check-In Confirmation",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = $this->text;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Booking Reference:\s*(.+)#", $text);

        // TripNumber
        // Passengers
        // TicketNumbers
        // AccountNumbers
        $it['AccountNumbers'] = [$this->re("#Frequent Flyer:\s+(.+)#", $text)];

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

        $itsegment = [];

        if (preg_match("#Flight No/date:\s+(?<AirlineName>\w{2})(?<FlightNumber>\d+)/(?<Date>.+)#", $text, $m)) {
            $date = strtotime($this->normalizeDate($m['Date']));
            // FlightNumber
            $itsegment['FlightNumber'] = $m['FlightNumber'];

            // AirlineName
            $itsegment['AirlineName'] = $m['AirlineName'];

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#Departure:\s+(.+)#", $text), $date));

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#Arrival:\s+(.+)#", $text), $date));
        }

        if (preg_match("#From:\s+(?<Name>.*?)\s*\((?<Code>[A-Z]{3})\)#", $text, $m)) {
            // DepCode
            $itsegment['DepCode'] = !empty($m['Code']) ? $m['Code'] : $m['Code2'];

            // DepName
            $itsegment['DepName'] = $m['Name'];

            // DepartureTerminal
        }

        if (preg_match("#To:\s+(?<Name>.*?)\s*\((?<Code>[A-Z]{3})\)#", $text, $m)) {
            // ArrCode
            $itsegment['ArrCode'] = !empty($m['Code']) ? $m['Code'] : $m['Code2'];

            // ArrName
            $itsegment['ArrName'] = $m['Name'];

            // ArrivalTerminal
        }
        // Operator
        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        $itsegment['Cabin'] = $this->re("#Class:\s+(.+)#", $text);

        // BookingClass
        // PendingUpgradeTo
        // Seats
        if (preg_match_all("#Flight/Seat:\s+" . $itsegment['AirlineName'] . $itsegment['FlightNumber'] . "/(.+)#", $text, $m)) {
            $itsegment['Seats'] = $m[1];
        }

        // Duration
        // Meal
        $itsegment['Meal'] = $this->re("#Meal:\s+(.+)#", $text);

        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;
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
        $this->text = $parser->getPlainBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

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
            "#^([^\s\d]+)\s+(\d+),\s+(\d+:\d+)\s+([ap])\.m\.$#", //August 23, 7:50 p.m.
        ];
        $out = [
            "$2 $1 $year, $3 $4m",
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
