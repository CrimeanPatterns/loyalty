<?php

namespace AwardWallet\Engine\airindia\Email;

use AwardWallet\Engine\MonthTranslate;

class Booking extends \TAccountChecker
{
    public $mailFiles = "airindia/it-1709643.eml, airindia/it-1886614.eml, airindia/it-2346731.eml";
    public $reFrom = "@airindia.in";
    public $reSubject = [
        "en"=> "Air India - Booking",
    ];
    public $reBody = 'airindia.in';
    public $reBody2 = [
        "en"=> "E-Ticket Itinerary Receipt",
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
        $it['RecordLocator'] = $this->nextText("Booking reference no (PNR):");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq("NAME") . "]/ancestor::tr[1]/following-sibling::tr/td[2]");

        // TicketNumbers
        $it['TicketNumbers'] = array_values(array_unique(array_filter(array_map(function ($s) {
            if (preg_match("#^[A-Z\d\s\-\*Xx\/]+$#", $s)) {
                return $s;
            } else {
                return null;
            }
        }, explode(",", implode(",", $this->http->FindNodes("//text()[" . $this->eq("TICKET NO.(S)") . "]/ancestor::tr[1]/following-sibling::tr/td[6]")))))));

        // AccountNumbers
        $it['AccountNumbers'] = array_values(array_unique(array_filter(array_map(function ($s) {
            if (strlen($s) > 3) {
                return $s;
            } else {
                return null;
            }
        }, $this->http->FindNodes("//text()[" . $this->eq("FREQUENT FLYER NO.") . "]/ancestor::tr[1]/following-sibling::tr/td[3]")))));

        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText("TOTAL TRIP COST"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->nextText("TOTAL TRIP COST"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->nextText("Issued date:")));

        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq("Depart") . "]/ancestor::tr[1]/following-sibling::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $n=>$root) {
            foreach (explode(" / ", $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root)) as $i=>$fl) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\w{2}\s+(\d+)#", $fl);

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][" . (1 + 2 * $i) . "]", $root, true, "#\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][" . (1 + 2 * $i) . "]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][" . (2 + 2 * $i) . "]", $root, true, "#Terminal.+#");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][" . (2 + 2 * $i) . "]", $root, true, "#(.*?)(?:, Terminal|$)#")));

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][" . (1 + 2 * $i) . "]", $root, true, "#\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][" . (1 + 2 * $i) . "]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][" . (2 + 2 * $i) . "]", $root, true, "#Terminal.+#");

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][" . (2 + 2 * $i) . "]", $root, true, "#(.*?)(?:, Terminal|$)#")));

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#(\w{2})\s+\d+#", $fl);

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./td[4]", $root, true, "#^(.*?)\s+-\s+\w$#");

                // BookingClass
                $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[4]", $root, true, "#^.*?\s+-\s+(\w)$#");

                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = implode(", ", $this->http->FindNodes("(//text()[contains(., 'Your flight to')])[" . ($n + 1) . "]/ancestor::tr[1]/following-sibling::tr[position()<=" . count($it['Passengers']) . "]/td[2]/descendant::text()[normalize-space(.)][" . ($i + 1) . "]", null, "#Seat number\s+(\d+\w)$#"));

                // Duration
                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
        ];
        $out = [
            "$2 $3 $4, $1",
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
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#([\d\,\.\s]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|[^A-Z])([A-Z]{3})(?:$|[^A-Z])#", $s)) {
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
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
