<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Engine\MonthTranslate;

class DigitalBoardingPass extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-8263219.eml";
    public $reFrom = "@ito.it";
    public $reSubject = [
        "en"=> "Carta d'imbarco",
    ];
    public $reBody = 'www.alitalia.com';
    public $reBody2 = [
        "it"=> "Departure airport",
    ];

    public static $dictionary = [
        "it" => [],
    ];

    public $lang = "it";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->nextText("Name")];

        // TicketNumbers
        $it['TicketNumbers'] = [$this->nextText("Ticket number")];

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
        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)$#", $this->nextText("Flight"));

        // DepCode
        $itsegment['DepCode'] = $this->nextText("Departure airport");

        // DepName
        // DepartureTerminal
        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText("Date") . ', ' . $this->nextText("Departure time")));

        // ArrCode
        $itsegment['ArrCode'] = $this->nextText("Arrival airport");

        // ArrName
        // ArrivalTerminal
        // ArrDate
        $itsegment['ArrDate'] = MISSING_DATE;

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+$#", $this->nextText("Flight"));

        // Operator
        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        $itsegment['Cabin'] = $this->nextText("Class");

        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = $this->nextText("Seat number");

        // Duration
        // Meal
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
        $html = str_ireplace(['&zwnj;', '&8203;', '​'], '', $this->http->Response["body"]); // Zero-width
        $this->http->SetBody($html);

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
        //		 $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\s\d]+)\s+(\d{2}),\s+(\d+):(\d+)$#", //11 ​mag​ 16, 10​:​25
        ];
        $out = [
            "$1 $2 20$3, $4:$5",
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
