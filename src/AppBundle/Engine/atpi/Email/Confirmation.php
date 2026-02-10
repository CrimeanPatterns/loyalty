<?php

namespace AwardWallet\Engine\atpi\Email;

use AwardWallet\Engine\MonthTranslate;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "atpi/it-8715238.eml, atpi/it-8725215.eml, atpi/it-8747545.eml";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    private $reFrom = "@atpi.com";
    private $reSubject = [
        "en"=> "Confirmation for:",
    ];
    private $reBody = 'ATPI';
    private $reBody2 = [
        "en" => ["Your travel itinerary", "YOUR TRAVEL ITINERARY"],
    ];

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

        foreach ($this->reBody2 as $reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang => $reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
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

    private function parseHtml(&$itineraries)
    {
        $xpath = "//img[(contains(@src, 'styles/ATPI/') or contains(@src, 'images/atpi-')) and not(contains(@src, 'logo'))]/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr[1]/descendant-or-self::tr[not(.//tr)][1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//text()[" . $this->eq(["Flight", "Hotel"]) . "]/ancestor::tr[./following-sibling::tr][1]/..";
            $nodes = $this->http->XPath->query($xpath);
        }
        $airs = [];
        $hotels = [];

        foreach ($nodes as $root) {
            $type = $this->http->FindSingleNode("./descendant::tr[count(./td)>2][1]/td[1]", $root);

            switch ($type) {
                case 'Flight':
                    if (!$rl = $this->nextText("Airline reference", $root)) {
                        $this->http->Log("RL not matched");

                        return;
                    }
                    $airs[$rl][] = $root;

                break;

                case 'Hotel':
                    $hotels[] = $root;

                break;

                default:
                    $this->http->Log("Unknown type '{$type}'");

                    return;
            }
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->nextText("ATPI Booking Reference");

            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Travellers']/ancestor::tr[1]/following-sibling::tr/td[1]");

            // TicketNumbers
            $tickets = array_filter($this->http->FindNodes("//td[normalize-space(.)='E-Ticket number']/ancestor::tr[1]/following-sibling::tr/td[2]", null, '/^\s*(\d{13})\s*$/'));

            if (!empty($tickets)) {
                $it['TicketNumbers'] = $tickets;
            }

            // AccountNumbers
            $it['AccountNumbers'] = $this->http->FindNodes("//text()[normalize-space(.)='Travellers']/ancestor::tr[1]/following-sibling::tr/td[2]", null, '/-\s*([A-Z\d]+)\b/');
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
            foreach ($roots as $root) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root)));

                if (!$date) {
                    $date = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::tr[normalize-space(.)][1]", $root)));
                }
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#^\w{2}\s*(\d+)$#", $this->nextText("Flight", $root));

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[3]/td[4]", $root);

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[3]/td[3]", $root);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./tr[3]/td[5]", $root);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText("Departs", $root)), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[4]/td[4]", $root);

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[4]/td[3]", $root);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./tr[4]/td[5]", $root);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->nextText("Arrives", $root)), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#^(\w{2})\s*\d+$#", $this->nextText("Flight", $root));

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->nextText("Equipment", $root);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./tr[2]/td[3]", $root);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = array_filter($this->http->FindNodes("./ancestor::tr[1]/following-sibling::tr[1]//text()[" . $this->eq("Seat") . "]/ancestor::tr[1]/following-sibling::tr/td[3]", $root, '/\b(\d{1,2}[A-Z]{1,2})\b/'));

                // Duration
                $itsegment['Duration'] = $this->nextText("Flight time", $root);

                // Meal
                $itsegment['Meal'] = $this->nextText("Offered meal", $root);

                // Smoking
                // Stops
                $stops = $this->nextText(["Stopover", "= Stopover"], $root);

                if (!empty($this->re("#(Non[ ]*-[ ]*stop)#i", $stops))) {
                    $itsegment['Stops'] = 0;
                } else {
                    $itsegment['Stops'] = $this->re("#^\s*(\d)\b#", $stops);
                }

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }

        //#################
        //##   HOTELS   ###
        //#################
        foreach ($hotels as $root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            if (!$it['ConfirmationNumber'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Confirmation") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[2]", $root)) {
                $it['ConfirmationNumber'] = $this->nextText("ATPI Booking Reference");
            }

            // TripNumber
            $it['TripNumber'] = $this->nextText("ATPI Booking Reference");

            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->nextText("Hotel", $root);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText("Check in date", $root)));

            if ($time = $this->http->FindSingleNode(".//text()[" . $this->starts("Check-in/out times:") . "]", $root, true, "#IN-(\d+:\d+)#")) {
                $it['CheckInDate'] = strtotime($time, $it['CheckInDate']);
            }

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText("Check out date", $root)));

            if ($time = $this->http->FindSingleNode(".//text()[" . $this->starts("Check-in/out times:") . "]", $root, true, "#OUT-(\d+:\d+)#")) {
                $it['CheckOutDate'] = strtotime($time, $it['CheckOutDate']);
            }

            // Address
            $it['Address'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Location") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Phone") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // Fax
            $it['Fax'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Fax") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // GuestNames
            $it['GuestNames'] = $this->http->FindNodes(".//text()[" . $this->eq("Travellers") . "]/ancestor::tr[1]/following-sibling::tr/td[1]");

            // Guests
            // Kids
            // Rooms
            $it['Rooms'] = $this->nextText("Room(s)", $root);

            // Rate
            $it['Rate'] = $this->http->FindSingleNode(".//text()[" . $this->starts("Daily rates:") . "]", $root, true, "#Daily rates:\s+(.+)#");

            // RateType
            $it['RateType'] = $this->re("#(.*?)\s+-\s+#", $this->nextText("Rate and Room Type", $root));

            // CancellationPolicy
            $it['CancellationPolicy'] = $this->http->FindSingleNode(".//text()[" . $this->starts("CANCEL ") . "]", $root);

            // RoomType
            $it['RoomType'] = $this->re("#-\s+(.+)#", $this->nextText("Rate and Room Type", $root));

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
        //$year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4})$#", //Sunday, 08 October 2017
            "#^(\d+:\d+) HRS$#", //18:00 HRS
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4}) - (?:after|before) (\d{2})(\d{2}) hrs$#", //Sunday, 08 October 2017 - after 1500 hrs
        ];
        $out = [
            "$1",
            "$1",
            "$1, $2:$3",
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
