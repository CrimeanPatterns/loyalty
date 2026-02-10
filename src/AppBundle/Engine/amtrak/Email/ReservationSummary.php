<?php

namespace AwardWallet\Engine\amtrak\Email;

class ReservationSummary extends \TAccountChecker
{
    public $mailFiles = "amtrak/it-1589930.eml, amtrak/it-1590723.eml, amtrak/it-2.eml";
    public $reFrom = "DoNotReply@amtrak.com";
    public $reSubject = [
        "en"=> "Amtrak Reservation Summary",
    ];
    public $reBody = 'Amtrak';
    public $reBody2 = [
        "en"=> "Departure:",
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts("Reservation Number Is") . "]", null, true, "#Reservation Number Is\s+(\w+)#");

        // TripNumber
        // Passengers
        $it['Passengers'][] = $this->http->FindSingleNode("//text()[" . $this->starts("Reservation Number Is") . "]/following::text()[contains(.,'=======')][1]/following::text()[normalize-space()!=''][1]");
        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#([\d\,\.]+)#", $this->nextText("Total Charges:")));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total Charges:"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        $xpath = "//text()[" . $this->starts("Departure:") . "]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root, true, "#" . $this->t("Train:") . "\s+(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode(".", $root, true, "#" . $this->t("Departure:") . "\s+(.+)#");

            // DepAddress
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::table[1]", $root)));

            if (empty($itsegment['DepDate'])) {
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space()!=''][1]",
                    $root)));
            }

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./following::text()[" . $this->starts("Arrival:") . "][1]", $root, true, "#" . $this->t("Arrival:") . "\s+(.+)#");

            // ArrAddress
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::table[2]", $root)));

            if (empty($itsegment['ArrDate'])) {
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[" . $this->starts("Arrival:") . "][1]/following::text()[normalize-space()!=''][1]",
                    $root)));
            }

            // Type
            $itsegment['Type'] = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root, true, "#" . $this->t("Train:") . "\s+(.+)#");

            // Vehicle
            // TraveledMiles
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
            'emailType'  => 'ReservationSummary' . ucfirst($this->lang),
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
            "#^[^\d\s]+\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+(\d+:\d+[AP]M)$#", //Tuesday July 2, 2013 7:15AM
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
}
