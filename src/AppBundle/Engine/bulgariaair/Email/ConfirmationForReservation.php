<?php

namespace AwardWallet\Engine\bulgariaair\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationForReservation extends \TAccountChecker
{
    public $mailFiles = "bulgariaair/it-6381223.eml, bulgariaair/it-6381230.eml, bulgariaair/it-6381247.eml, bulgariaair/it-6410397.eml";
    public $reFrom = "@air.bg";
    public $reSubject = [
        "en"=> "Confirmation for reservation",
    ];
    public $reBody = 'Bulgaria Air';
    public $reBody2 = [
        "en"=> "YOUR TRIP SUMMARY",
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
        $it['RecordLocator'] = $this->re("#" . $this->opt($this->t("Booking reservation number:")) . "\s+(\w+)#", $text);

        // TripNumber
        // Passengers
        preg_match_all("#\n(M[ris]+\s+.*?)\n#", substr($text, strpos($text, 'TRAVELLER INFORMATION'), strpos($text, 'Contact Information')), $Passengers);
        $it['Passengers'] = array_unique($Passengers[1]);

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#\n(.*?)Total for all travellers#i", $text));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->re("#\n(.*?)Total for all travellers#i", $text));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $segments = $this->split("#(Flight\s+\d+\s+-)#", $text);
        $test = substr_count($text, $this->t("Aircraft"));

        if (count($segments) != $test) {
            return;
        }

        foreach ($segments as $stext) {
            $date = strtotime($this->normalizeDate($this->re("#Flight\s+\d+\s+-\s*(.+)#", $stext)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#Airline\s*:\s*.*?\s+\w{2}(\d+)\n#", $stext);

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->re("#Departure\s*:\s*\d+:\d+\s*-\s*(.*?)(,\s+terminal|\n)#", $stext);

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->re("#Departure\s*:\s*.*?,\s+(terminal.+)#", $stext);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->re("#Departure\s*:\s*(\d+:\d+)#", $stext), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->re("#Arrival\s*:\s*\d+:\d+\s*-\s*(.*?)(,\s+terminal|\n)#", $stext);

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->re("#Arrival\s*:\s*.*?,\s+(terminal.+)#", $stext);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->re("#Arrival\s*:\s*(\d+:\d+)#", $stext), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#Airline\s*:\s*.*?\s+(\w{2})\d+\n#", $stext);

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->re("#Aircraft\s*:\s*(.+)#", $stext);

            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#Fare type\s*:\s*(.*?)/#", $stext);

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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->setBody($parser->getPlainBody());

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
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
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)$#", //Friday, 20 May
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+-\s+[^\d\s]+,\s+\d+\s+[^\d\s]+$#", //Friday, 20 May - Saturday, 21 May
        ];
        $out = [
            "$1 $2 $year",
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|[^A-Z])([A-Z]{3})(?:$|[^A-Z])#", $s)) {
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
