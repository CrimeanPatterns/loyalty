<?php

namespace AwardWallet\Engine\ccaribbean\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "ccaribbean/it-11812622.eml, ccaribbean/it-11822197.eml, ccaribbean/it-12502828.eml, ccaribbean/it-12531624.eml";
    public $reFrom = "@e.cheapcaribbean.com";
    public $reSubject = [
        "en"=> "CheapCaribbean.com Booking Confirmation",
    ];
    public $reBody = 'cheapcaribbean.com';
    public $reBody2 = [
        "en"=> "You can never have too much beach",
    ];

    public static $dictionary = [
        "en" => [
            "DEPART"=> ["DEPART", "RETURN"],
        ],
    ];

    public $lang = "en";

    public function parseHtml()
    {
        $itineraries = [];

        //#################
        //##   FLIGHT   ###
        //#################
        $xpath = "//text()[" . $this->starts("FLIGHT") . "]/ancestor::tr[./td[3] and contains(translate(./td[2], '0123456789', 'dddddddddd'), 'dd:dd')][1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./preceding::text()[" . $this->eq(["Record Locator:", "RECORD LOCATOR:"]) . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root);

            if (empty($rl)) {
                $rl = $this->http->FindSingleNode("./preceding::text()[" . $this->starts(["Record Locator:", "RECORD LOCATOR:"]) . "]/ancestor::td[1]", $root, true, "#:\s*([A-Z\d]+)#");
            }

            if (empty($rl)) {
                $rl = CONFNO_UNKNOWN;
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = str_replace(".", "-", $this->http->FindSingleNode("//text()[" . $this->starts("BOOKING ID:") . "]", null, true, "#BOOKING ID:\s*(.+)#"));

            // Passengers
            if (empty($it['Passengers'] = $this->http->FindNodes("./preceding::img[contains(@src, '/icon-plane.jpg')][1]/ancestor::table[1]/following-sibling::table[1]//text()[" . $this->eq("TRAVELERS:") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][position()>1]", $segments[0]))) {
                $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq("TRAVELER LIST:") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]");
            }

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

            foreach ($segments as $root) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./ancestor::table[1]/preceding-sibling::table[" . $this->eq($this->t("DEPART")) . "][1]/following-sibling::table[1]/descendant::text()[normalize-space(.)][2]", $root)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^FLIGHT (\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // ArrivalTerminal
                // ArrDate

                if (!empty($this->http->FindSingleNode("./td[3]//img[contains(@src, 'OvernightFlight')]/@src", $root))) {
                    $nextDate = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]/following::text()[" . $this->starts("Overnight Flight") . "]/ancestor::td[1]", $root, true, "#Arrives\s*(.+)#")));

                    if (!empty($nextDate)) {
                        $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root), $nextDate);
                    }
                }

                if (empty($itsegment['ArrDate'])) {
                    $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root), $date);
                }

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root);

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#Operated by\s+(.+)#");

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

        //################
        //##   HOTEL   ###
        //################
        $xpath = "//text()[" . $this->eq("HOTEL INFORMATION") . "]/ancestor::table[2]/following-sibling::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;

            // TripNumber
            $it['TripNumber'] = str_replace(".", "-", $this->http->FindSingleNode("//text()[" . $this->starts("BOOKING ID:") . "]", null, true, "#BOOKING ID:\s*(.+)#"));

            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->http->FindSingleNode("./preceding-sibling::table[1]/descendant::text()[normalize-space(.)][1]", $root);

            // 2ChainName

            if (preg_match("#(.*?)\s*/\s*(.*?), (\d{4})$#", $this->http->FindSingleNode(".//td[" . $this->eq("CHECK IN / OUT:") . "]/following-sibling::td[1]", $root), $m)) {
                // CheckInDate
                $it['CheckInDate'] = strtotime($this->normalizeDate($m[1] . ', ' . $m[3]));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->normalizeDate($m[2] . ', ' . $m[3]));
            }

            // Address
            $it['Address'] = $this->http->FindSingleNode("./preceding-sibling::table[1]/descendant::text()[normalize-space(.)][1]", $root);

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->http->FindSingleNode("./preceding-sibling::table[1]//text()[" . $this->starts("Tel.") . "]", $root, true, "#Tel\.\s*(.+)#");

            // Fax
            // GuestNames
            $it['GuestNames'] = $this->http->FindNodes("//text()[" . $this->eq("TRAVELER LIST:") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]");

            // Guests
            $it['Guests'] = $this->http->FindSingleNode(".//td[" . $this->eq("ROOM INFORMATION:") . "]/following-sibling::td[1]", $root, true, "#(\d+) Total Guests#");

            // Kids
            // Rooms
            $it['Rooms'] = $this->http->FindSingleNode(".//td[" . $this->eq("ROOM INFORMATION:") . "]/following-sibling::td[1]", $root, true, "#(\d+) Reserved Room#");

            // Rate
            $it['Rate'] = $this->http->FindSingleNode(".//td[" . $this->eq("RATE CODE:") . "]/following-sibling::td[1]", $root);

            // RateType

            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode(".//td[" . $this->eq("ROOM TYPE:") . "]/following-sibling::td[1]", $root);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        return $itineraries;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		if(strpos($headers["from"], $this->reFrom)===false)
        //			return false;

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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
                'TotalCharge' => [
                    "Amount"   => $this->amount($this->nextText("TOTAL COST:")),
                    "Currency" => $this->currency($this->nextText("TOTAL COST:")),
                ],
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
            "#^[^\s\d]+ ([^\s\d]+) (\d+), (\d{4})$#", //Monday June 18, 2018
            "#^[^\s\d]+, ([^\s\d]+) (\d+), (\d{4})$#", //Monday June 18, 2018
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $3",
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
