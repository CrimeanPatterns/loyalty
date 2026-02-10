<?php

namespace AwardWallet\Engine\ccaribbean\Email;

use AwardWallet\Engine\MonthTranslate;

class UpdateForYourUpcomingReservation extends \TAccountChecker
{
    public $mailFiles = "ccaribbean/it-11731059.eml, ccaribbean/it-11812636.eml";
    public $reFrom = "@cheapcaribbean.com";
    public $reSubject = [
        "en"=> "INFORMATION UPDATE FOR YOUR UPCOMING CHEAPCARIBBEAN.COM RESERVATION",
    ];
    public $reBody = 'CHEAPCARIBBEAN.COM';
    public $reBody2 = [
        "en"=> "PLEASE BE ADVISED DUE TO A SCHEDULE CHANGE",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml()
    {
        $itineraries = [];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts("FLIGHT RESERVATION CODE:") . "]", null, true, "#FLIGHT RESERVATION CODE: (.+)#");

        // TripNumber
        $it['TripNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts("Reservation ID") . "]", null, true, "#Reservation ID (.+)#");

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->contains("(adult)") . "]/preceding::text()[normalize-space(.)][1]");

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
        $xpath = "//text()[" . $this->eq("Depart:") . "]/ancestor::tr[1][count(./td)=4]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root)));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[4]", $root, true, "#\(\w{2} (\d+)\)#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./following-sibling::tr[1]/td[3]/descendant::text()[normalize-space(.)][1]", $root), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[4]", $root, true, "#\((\w{2}) \d+\)#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#(\d+.+)#");

            // Meal
            // Smoking
            // Stops
            $itsegment['Stops'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[4]", $root);

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        //################
        //##   HOTEL   ###
        //################

        $xpath = "//text()[" . $this->starts("Room:") . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts("Reservation ID") . "]", null, true, "#Reservation ID (.+)#");

            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->http->FindSingleNode(".//td[" . $this->starts("Destination:") . "]/following-sibling::td[1]", $root);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->starts("Check-in date:") . "]/following-sibling::td[1]", $root)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->starts("Check-out date:") . "]/following-sibling::td[1]", $root)));

            // Address
            $it['Address'] = $this->http->FindSingleNode(".//td[" . $this->starts("Destination:") . "]/following-sibling::td[1]", $root);

            // DetailedAddress

            // Phone
            // Fax
            // GuestNames
            $it['GuestNames'] = $this->http->FindNodes(".//text()[" . $this->contains("(adult)") . "]/preceding::text()[normalize-space(.)][1]", $root);

            // Guests
            $it['Guests'] = $this->http->FindSingleNode(".//td[" . $this->starts("Guests:") . "]/following-sibling::td[1]", $root);

            // Kids
            // Rooms
            $it['Rooms'] = $this->http->FindSingleNode(".//td[" . $this->starts("Reserved rooms:") . "]/following-sibling::td[1]", $root);

            // Rate
            $it['Rate'] = $this->http->FindSingleNode(".//td[" . $this->starts("Rate code:") . "]/following-sibling::td[1]", $root);

            // RateType

            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode(".//td[" . $this->starts("Room:") . "]/following-sibling::td[1]", $root);

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
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->starts("Date requested:") . "]/following-sibling::td[1]", $root)));

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
