<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Engine\MonthTranslate;

class CheckInForYourFlight extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-8790379.eml, alitalia/it-9524485.eml, alitalia/it-9524713.eml";
    public $reFrom = "noreply@alitalia.com";
    public $reSubject = [
        "en"=> "Check-in for your flight now",
        "it"=> "Fai il check-in del tuo volo",
    ];
    public $reBody = 'alitalia.com';
    public $reBody2 = [
        "en"=> "check in now for your flight",
        "it"=> "check-in online del tuo volo",
    ];

    public static $dictionary = [
        "en" => [],
        "it" => [
            "Reservation" => ["PNR", "PRENOTAZIONE"],
            "Dear"        => "Gentile",
            "ticket"      => "BIGLIETTO",
            "flight"      => ["volo", "Numero Volo"],
            "from"        => ["da", "DA"],
            "date"        => ["data", "Data"],
            "departure"   => ["partenza", "Partenza"],
            "to"          => ["a", "A"],
        ],
    ];

    public $lang = "";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->nextText($this->t("Dear"))];

        // TicketNumbers
        $it['TicketNumbers'] = [$this->http->FindSingleNode("//text()[" . $this->eq($this->t("ticket")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[3]")];

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

        $nodes = $this->http->XPath->query("//td[" . $this->eq($this->t("departure")) . "]/ancestor::tr[2]");

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            // AirlineName
            $node = $this->http->FindSingleNode("./descendant::td[" . $this->eq($this->t("flight")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root);

            if (preg_match("#[A-Z]#", $node)) {
                $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)$#", $node);
                $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+$#", $node);
            } else {
                $itsegment['FlightNumber'] = $node;

                if ($this->http->XPath->query("//a[contains(@href,'.alitalia.com') and contains(normalize-space(),'CHECK-IN')]")->length > 0) {
                    $itsegment['AirlineName'] = 'AZ';
                } else {
                    $itsegment['AirlineName'] = AIRLINE_UNKNOWN;
                }
            }

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./descendant::td[" . $this->eq($this->t("from")) . "]/ancestor::tr[1]/following-sibling::tr[1]", $root);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::td[" . $this->eq($this->t("date")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[3]", $root) . ', ' . $this->http->FindSingleNode("./descendant::td[" . $this->eq($this->t("departure")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[5]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./descendant::td[" . $this->eq($this->t("to")) . "]/ancestor::tr[1]/following-sibling::tr[1]", $root);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

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
        // $this->http->log($str);
        // $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\.(\d+)\.(\d{2}), (\d+:\d+)$#", //02.10.16, 07:20
        ];
        $out = [
            "$1.$2.20$3, $4",
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

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
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
