<?php

namespace AwardWallet\Engine\ccaribbean\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "ccaribbean/it-11668907.eml, ccaribbean/it-11703853.eml, ccaribbean/it-11732803.eml, ccaribbean/it-12447886.eml";
    public $reFrom = "email@e.cheapcaribbean.com";
    public $reSubject = [
        "en"=> "CheapCaribbean.com Booking Confirmation",
    ];
    public $reBody = 'Thank you for booking with CheapCaribbean';
    public $reBody2 = [
        "en" => "Please print this confirmation",
        "en2"=> "Please print out this confirmation",
    ];

    public static $dictionary = [
        "en" => [
            "DEPARTING"      => ["DEPARTING", "RETURNING"],
            "Passenger list:"=> ["Passenger list:", "Adults:"],
        ],
    ];

    public $lang = "en";

    public function parseHtml()
    {
        $itineraries = [];

        //#################
        //##   FLIGHT   ###
        //#################
        $xpath = "//text()[" . $this->eq($this->t("DEPARTING")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]//tr[not(.//tr)]/..";
        $nodes = $this->http->XPath->query($xpath);

        if (count($nodes) > 0) {
            $it = [];

            $it['Kind'] = "T";

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts("BOOKING ID:") . "]", null, true, "#BOOKING ID:\s*(.+)#");

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
            if ($dateStr = $this->http->FindSingleNode("//text()[" . $this->starts("CREATED") . "]", null, true, "#CREATED (.+)#")) {
                $it['ReservationDate'] = strtotime($this->normalizeDate($dateStr));
            }

            // NoItineraries
            // TripCategory

            foreach ($nodes as $root) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[1]/td[2]", $root)));
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]/td[5]", $root, true, "#^.*?\s+(\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./tr[2]/td[2]", $root), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[1]/td[4]", $root, true, "#\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[1]/td[4]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./tr[2]/td[3]", $root), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[1]/td[5]", $root, true, "#^(.*?)\s+\d+$#");

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode("./tr[2]/td[4]", $root, true, "#^Operated by\s+(.+)$#");

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

                // RecordLocator
                $it['RecordLocator'] = $this->http->FindSingleNode("./preceding::text()[" . $this->starts("Airline Record Locator:") . "][1]", $root, true, "#Airline Record Locator:\s*(.+)#");

                // Passengers
                $it['Passengers'] = array_filter(array_map(function ($s) { return preg_match("#(.*?)(?: \(|$| \*)#", $s, $m) ? trim($m[1]) : null; }, explode(",",
                        $this->http->FindSingleNode("(./preceding::text()[{$this->eq($this->t("Passenger list:"))}][1]/following::text()[normalize-space(.)][1])[1]", $root))));

                $finded = false;

                foreach ($itineraries as $key => $value) {
                    if ($value['Kind'] == 'T' && $value['RecordLocator'] == $it['RecordLocator']) {
                        $itineraries[$key]['TripSegments'][] = $itsegment;
                        $finded = true;

                        break;
                    }
                }

                if ($finded == false) {
                    $it['TripSegments'][] = $itsegment;
                    $itineraries[] = $it;
                }
            }
        }

        //################
        //##   HOTEL   ###
        //################
        $xpath = "//text()[" . $this->eq("Hotel Information") . "]/following::table[string-length(normalize-space(.))>1][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i=>$root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts("BOOKING ID:") . "]", null, true, "#BOOKING ID:\s*(.+)#");

            // ConfirmationNumbers

            // HotelName
            $it['HotelName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root);

            // 2ChainName

            if (preg_match("#(.*?) / (.*?), (\d{4})$#", $this->http->FindSingleNode(".//td[" . $this->eq("Checking In / Out:") . "]/following-sibling::td[2]", $root), $m)) {
                // CheckInDate
                $it['CheckInDate'] = strtotime($this->normalizeDate($m[1] . ', ' . $m[3]));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->normalizeDate($m[2] . ', ' . $m[3]));
            }

            // Address
            $it['Address'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root);

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Tel.") . "]", $root, true, "#Tel\.\s*(.+)#");

            // Fax
            // GuestNames
            if (empty($it['GuestNames'] = array_filter(array_map(function ($s) { return preg_match("#(.*?)(?: \(|$| \*)#", $s, $m) ? trim($m[1]) : null; }, explode(",", $this->nextText("Room " . ($i + 1) . ":")))))) {
                $it['GuestNames'] = array_filter(array_map(function ($s) { return preg_match("#(.*?)(?: \(|$| \*)#", $s, $m) ? trim($m[1]) : null; }, explode(",", $this->nextText($this->t("Passenger list:")))));
            }

            // Guests
            $it['Guests'] = $this->http->FindSingleNode(".//td[" . $this->eq("Room Information:") . "]/following-sibling::td[2]", $root, true, "#(\d+) Total Guests#");

            // Kids
            // Rooms
            $it['Rooms'] = $this->http->FindSingleNode(".//td[" . $this->eq("Room Information:") . "]/following-sibling::td[2]", $root, true, "#(\d+) Reserved Room#");

            // Rate
            $it['Rate'] = $this->http->FindSingleNode(".//td[" . $this->eq("Rate Code:") . "]/following-sibling::td[2]", $root);

            // RateType
            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode(".//td[" . $this->eq("Room Type:") . "]/following-sibling::td[2]", $root, true, "#(.*?)(?: \(|$)#");

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
            if ($dateStr = $this->http->FindSingleNode("//text()[" . $this->starts("CREATED") . "]", null, true, "#CREATED (.+)#")) {
                $it['ReservationDate'] = strtotime($this->normalizeDate($dateStr));
            }

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
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
                'TotalCharge' => [
                    "Amount"   => $this->amount($this->nextText("Total Reservation Cost:")),
                    "Currency" => $this->currency($this->nextText("Total Reservation Cost:")),
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
        $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^([^\s\d]+) (\d+), (\d{4})$#", //Mar 02, 2017
            "#^[^\s\d]+ ([^\s\d]+) (\d+), (\d{4})$#", //Thursday March 2 2017
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
