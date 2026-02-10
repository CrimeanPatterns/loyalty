<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Engine\MonthTranslate;

class StepAwayToCompleteYourRedemption extends \TAccountChecker
{
    public $mailFiles = "airasia/it-10582965.eml, airasia/it-13035068.eml";
    public $reFrom = "@airasia.com";
    public $reSubject = [
        "en"=> "You're a step away to complete your redemption!",
    ];
    public $reBody = 'AirAsia';
    public $reBody2 = [
        "en"=> "Your travel itinerary will not be issued until your payment has been completed.",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[" . $this->eq("Booking number") . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if (!$rl = $this->http->FindSingleNode("./td[5]", $root, true, "#^[A-Z\d]{6}$#")) {
                $this->logger->info("RL not matched");

                return;
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq("Traveler :") . "]/following::text()[normalize-space(.)][1]");

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            if (count($roots) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("./td[6]/descendant::text()[normalize-space(.)][1]", $roots[0]));

                // BaseFare
                // Currency
                $it['Currency'] = $this->currency($this->http->FindSingleNode("./td[6]/descendant::text()[normalize-space(.)][1]", $roots[0]));
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            if ($this->http->FindPreg("#Your travel itinerary will not be issued until your payment has been completed#")) {
                $it['Status'] = "not completed";
            }

            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($roots as $root) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(.*?) »#");

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#» (.+)#");

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = MISSING_DATE;

                // AirlineName
                if ($this->http->XPath->query("//text()[contains(normalize-space(.),'We have deducted the following credits from your AirAsia Asean Pass')]")) {
                    $itsegment['AirlineName'] = 'AK';
                }//AirAsia
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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

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
        //		 $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4})\s*(\d+:\d+) hrs \(\d+:\d+ [AP]M\)$#", //Tuesday, 30 Jan 2018 18:00 hrs (6:00 PM)
        ];
        $out = [
            "$1 $2",
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
            '₹'=> 'INR',
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
