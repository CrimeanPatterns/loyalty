<?php

namespace AwardWallet\Engine\aeroflot\Email;

use AwardWallet\Engine\MonthTranslate;

class ElectronicBoardingPass extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-7174379.eml, aeroflot/it-7196873.eml";
    public $reFrom = "@aeroflot.ru";
    public $reSubject = [
        "en"=> "Aeroflot Electronic Boarding Pass",
    ];
    public $reBody = 'Aeroflot';
    public $reBody2 = [
        "en"=> "Please review boarding pass information",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = $this->http->Response['body'];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#PNR:\s+(\w+)#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->re("#\n(.*?)\nPNR:#", $text)];

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

        $date = strtotime($this->normalizeDate($this->re("#PNR:\s+.*?(\d+[^\d\s]+)\n#", $text)));

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#\s+\w{2}\s+(\d+)\s+#", $this->re("#PNR:\s+(.+)#", $text));

        // DepCode
        $itsegment['DepCode'] = $this->re("#\s+([A-Z]{3})-[A-Z]{3}\s+#", $this->subject);

        // DepName
        $itsegment['DepName'] = $this->re("#From:\s+(.*?)\s+at\s+#", $text);

        // DepartureTerminal
        // DepDate
        $itsegment['DepDate'] = strtotime($this->re("#From:\s+.*?\s+at\s+(.+)#", $text), $date);

        // ArrCode
        $itsegment['ArrCode'] = $this->re("#\s+[A-Z]{3}-([A-Z]{3})\s+#", $this->subject);

        // ArrName
        $itsegment['ArrName'] = $this->re("#To:\s+(.*?)(?:\s+at\s+|\n)#", $text);

        // ArrivalTerminal
        // ArrDate
        if ($time = $this->re("#To:\s+.*?\s+at\s+(.+)#", $text)) {
            $itsegment['ArrDate'] = strtotime($time, $date);
        } else {
            $itsegment['ArrDate'] = MISSING_DATE;
        }

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#\s+(\w{2})\s+(\d+)\s+#", $this->re("#PNR:\s+(.+)#", $text));

        // Operator
        $itsegment['Operator'] = $this->re("#Operated by\s+(\w{2})\s+#", $this->re("#PNR:\s+(.+)#", $text));

        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        // BookingClass
        $itsegment['BookingClass'] = $this->re("#Class:\s+(\w)\n#", $text);

        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = $this->re("#Seat:\s+(\d+\w)\n#", $text);

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
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

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
        $this->subject = $parser->getSubject();
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                return false;
            }
        }

        $this->http->SetEmailBody($parser->getPlainBody());

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => 'ElectronicBoardingPass' . ucfirst($this->lang),
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
            "#^(\d+)([^\d\s]+)$#", //21MAR
        ];
        $out = [
            "$1 $2 $year",
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
