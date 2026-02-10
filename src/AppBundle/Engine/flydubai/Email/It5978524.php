<?php

namespace AwardWallet\Engine\flydubai\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;

class It5978524 extends \TAccountChecker
{
    public $mailFiles = "flydubai/it-12362145.eml, flydubai/it-12391123.eml, flydubai/it-269472372.eml, flydubai/it-5978524.eml";
    public $reFrom = "no-reply@flydubai.com";
    public $reSubject = [
        "en"=> "Your flydubai booking is on hold",
    ];
    public $reBody = 'flydubai';
    public $reBody2 = [
        "en"=> "Departing",
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
        if (!$it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference:")) . "]", null, true, "#" . $this->t("Booking reference:") . "\s+(\w+)#")) {
            $it['RecordLocator'] = $this->nextText($this->t("Booking reference:"));
        }

        // TripNumber
        // Passengers
        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // BaseFare

        // Currency
        // TotalCharge
        $it['Currency'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total Payment Due:")) . "]", null, true, "#" . $this->t("Total Payment Due:") . "\s+([A-Z]{3})\s+[\d\,\.]+#");
        $it['TotalCharge'] = PriceHelper::parse($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total Payment Due:")) . "]", null, true, "#" . $this->t("Total Payment Due:") . "\s+[A-Z]{3}\s+([\d\,\.]+)\.#"),
            $it['Currency']);

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->eq($this->t("Departing")) . "]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./th[1]//p[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s*-\s*(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./th[2]//p[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)(?:\s+" . $this->t("Terminal") . "|$)#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./th[2]//p[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#" . $this->t("Terminal") . "\s+(\w+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./th[2]//p[2]/descendant::text()[normalize-space(.)][2]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./th[3]//p[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)(?:\s+" . $this->t("Terminal") . "|$)#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./th[3]//p[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#" . $this->t("Terminal") . "\s+(\w+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./th[3]//p[2]/descendant::text()[normalize-space(.)][2]", $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./th[1]//p[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\s*-\s*\d+(?:/\d+)?$#");

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
            'emailType'  => 'reservations',
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
            "#^(\d+:\d+)\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //22:50 22 October 2015
        ];
        $out = [
            "$2 $3 $4, $1",
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
