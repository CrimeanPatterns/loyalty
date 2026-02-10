<?php

namespace AwardWallet\Engine\airindia\Email;

use AwardWallet\Engine\MonthTranslate;

class IsCheckedIn extends \TAccountChecker
{
    public $mailFiles = "airindia/it-9871997.eml, airindia/it-9901046.eml, airindia/it-9916291.eml";
    public $reFrom = "no_reply@airindia.in";
    public $reSubject = [
        "en"=> "is checked in",
    ];
    public $reBody = ['AirIndia', 'FLYING WITH AIR INDIA'];
    public $reBody2 = [
        "en"=> "Your boarding pass is attached to this email.",
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
        $it['RecordLocator'] = $this->nextText("PNR");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->starts("Dear ") . "]", null, "#Dear (.*?),#");

        // TicketNumbers
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[" . $this->eq("TKNE") . "]/ancestor::td[1]/following-sibling::td[1]");

        // AccountNumbers
        $it['AccountNumbers'] = $this->http->FindNodes("//text()[" . $this->eq("FQTV") . "]/ancestor::td[1]/following-sibling::td[1]");

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

        $date = strtotime($this->normalizeDate($this->nextText("Date")));
        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->http->FindSingleNode("//text()[" . $this->eq("Date") . "]/ancestor::tr[1]/preceding::tr[1]/td[1]", null, true, "#^\w{2}(\d+)$#");

        // DepCode
        $itsegment['DepCode'] = $this->http->FindSingleNode("//text()[" . $this->eq("Date") . "]/ancestor::tr[1]/preceding::tr[1]/td[2]", null, true, "#^([A-Z]{3}) to [A-Z]{3}$#");

        // DepName
        // DepartureTerminal
        $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("//text()[" . $this->starts("All AI International") . "]", null, true, "#(T\d+) at {$itsegment['DepCode']}#");

        // DepDate
        $time = $this->http->FindSingleNode("//text()[" . $this->eq("Depart") . "]/ancestor::td[1]/following-sibling::td[1]", null, true, "/^[\W\d]+$/");
        if (!empty($date)) {
            if (!empty($time)) {
                $itsegment['DepDate'] = strtotime($time, $date);
            } else {
                $itsegment['DepDate'] = MISSING_DATE;
            }
        }


        // ArrCode
        $itsegment['ArrCode'] = $this->http->FindSingleNode("//text()[" . $this->eq("Date") . "]/ancestor::tr[1]/preceding::tr[1]/td[2]", null, true, "#^[A-Z]{3} to ([A-Z]{3})$#");

        // ArrName
        // ArrivalTerminal
        $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("//text()[" . $this->starts("All AI International") . "]", null, true, "#(T\d+) at {$itsegment['ArrCode']}#");

        // ArrDate
        if ($time = $this->http->FindSingleNode("//text()[" . $this->eq("Arrive") . "]/ancestor::td[1]/following-sibling::td[1]")) {
            if (!empty($date)) {
                $itsegment['ArrDate'] = strtotime($time, $date);
            }
        } else {
            $itsegment['ArrDate'] = MISSING_DATE;
        }

        // AirlineName
        $itsegment['AirlineName'] = $this->http->FindSingleNode("//text()[" . $this->eq("Date") . "]/ancestor::tr[1]/preceding::tr[1]/td[1]", null, true, "#^(\w{2})\d+$#");

        // Operator
        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        $itsegment['Cabin'] = $this->http->FindSingleNode("//text()[" . $this->eq("Cabin") . "]/ancestor::td[1]/following-sibling::td[1]");

        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = [$this->http->FindSingleNode("//text()[" . $this->eq("Seat") . "]/ancestor::td[1]/following-sibling::td[1]")];

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

        $detectedProvider = false;
        foreach ($this->reBody as $re) {
            if (strpos($body, $re) !== false) {
                $detectedProvider = true;
                break;
            }
        }
        if ($detectedProvider === false) {
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
        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\s\d]+)$#", //02Jun
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
