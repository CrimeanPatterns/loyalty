<?php

namespace AwardWallet\Engine\amtrak\Email;

class ReservationExchangeConfirmation extends \TAccountChecker
{
    public $mailFiles = "amtrak/it-1968229.eml";
    public $reFrom = "DoNotReply@amtrak.com";
    public $reSubject = [
        "en"=> "Amtrak Reservation Exchange Confirmation",
    ];
    public $reBody = 'Amtrak';
    public $reBody2 = [
        "en"=> "ITINERARY",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = implode("\n", $this->http->FindNodes("//text()[normalize-space(.)!='']"));
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Reservation Number:\s+(\w+)#", $text);

        // Cancelled
        // TripNumber
        // Passengers
        preg_match_all("#Passenger\s+\d+:\s+(.*?)\n#", $text, $Passengers);
        $it['Passengers'] = array_unique($Passengers[1]);

        // TicketNumbers
        // AccountNumbers
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
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        preg_match_all("#\nTrain:\s+(?<Type>.*?)\n" .
                        "Duration:\s+(?<Duration>.*?)\n" .
                        "Departs:\n" .
                        "(?<DepName>.*?)\n" .
                        "(?<DepDate>.*?)\n" .
                        "Arrives:\n" .
                        "(?<ArrName>.*?)\n" .
                        "(?<ArrDate>.*?)\n#ms", $text, $segments, PREG_SET_ORDER);

        foreach ($segments as $segment) {
            $keys = [
                "DepName",
                "ArrName",
                "Type",
                "Duration",
            ];

            $itsegment = [];

            foreach ($keys as $key) {
                if (isset($segment[$key])) {
                    $itsegment[$key] = $segment[$key];
                }
            }

            $itsegment['FlightNumber'] = $this->re("#(\d+)#", $segment['Type']);
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            $itsegment['DepDate'] = strtotime($this->normalizeDate($segment['DepDate']));
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($segment['ArrDate']));

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

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => 'ReservationExchangeConfirmation' . ucfirst($this->lang),
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

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][{$n}]", $root);
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
            "#^[^\d\s]+\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+(\d+:\d+[AP]M)$#", //Saturday February 16, 2013 7:54PM
        ];
        $out = [
            "$2 $1 $3, $4",
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
