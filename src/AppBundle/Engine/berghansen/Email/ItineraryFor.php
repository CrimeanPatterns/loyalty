<?php

namespace AwardWallet\Engine\berghansen\Email;

use AwardWallet\Engine\MonthTranslate;

class ItineraryFor extends \TAccountChecker
{
    public $mailFiles = "berghansen/it-12145751.eml, berghansen/it-12184733.eml, berghansen/it-12207262.eml, berghansen/it-12207675.eml, berghansen/it-25206089.eml";

    public $reSubject = [
        "en" => "Itinerary for",
        "no" => "Reiserute for",
        "da" => "Rejseplan for",
    ];
    public $reBody = 'Berg-Hansen';
    public $reBody2 = [
        "en" => "here to see your itinerary", //"Click here to see your itinerary",
        "no" => "Klikk her for å se din reiserute",
        "da" => "Klik her for at få vist din rejserute",
    ];

    public static $dictionary = [
        "en" => [
            //			"Air" => "",
            //			"Hotel" => "",
            //			"Car" => "",
            //			"Calendar" => "",
            //			"IMPORTANT INFORMATION" => "",
            //			"Reference" => "",
            //			"Fare" => "",
            //			"Status" => "",
            // Airs
            //			"Class" => "",
            //			"Seat" => "",
            //			"Duration" => "",
            //			"Connections" => "",
            //			"TOTAL AIR FARE" => "",
            // Hotels
            //			"Period" => "",
            //			"Address" => "",
            //			"Phone" => "",
            //			"Room type" => "",
            // Cars
            //			"Vehicle types" => "",
        ],
        "no" => [
            "Air"                   => "Fly",
            "Hotel"                 => "Hotell",
            "Car"                   => "Leiebil",
            "Calendar"              => "Kalender",
            "IMPORTANT INFORMATION" => "VIKTIG INFORMASJON OM BESTILLINGEN",
            "Reference"             => "Referanse",
            "Fare"                  => "Pris",
            "Status"                => "Status",
            //			 Airs
            "Class"          => "Klasse",
            "Seat"           => "Sete",
            "Duration"       => "Reisetid",
            "Connections"    => "Mellomlandinger",
            "TOTAL AIR FARE" => "TOTAL FLYPRIS",
            //			 Hotels
            "Period"    => "Tidsrom",
            "Address"   => "Adresse",
            "Phone"     => "Telefon",
            "Room type" => "Romtype",
            //			 Cars
            "Vehicle types" => "Biltype",
        ],
        "da" => [
            "Air"                   => "Fly",
            "Hotel"                 => "Hotellet", //?? no examples
            "Car"                   => "Udlejningsbil",
            "Calendar"              => "Kalender",
            "IMPORTANT INFORMATION" => "VIGTIG INFORMATION OM DIN ORDRE",
            "Reference"             => "Reference",
            "Fare"                  => "Pris",
            "Status"                => "Status",
            //			 Airs
            "Class"          => "Klasse",
            "Seat"           => "plads",
            "Duration"       => "Rejsetid",
            "Connections"    => "Mellemlandinger",
            "TOTAL AIR FARE" => "TOTAL FLYPRIS",
            //			 Hotels - need check translate for hotel
            "Period"    => "Tidsrom",
            "Address"   => "Adresse",
            "Phone"     => "Telefon",
            "Room type" => "Romtype",
            //			 Cars
            "Vehicle types" => "Biltype",
        ],
    ];

    public $lang = "en";

    public function parseHtml($body, &$its)
    {
        $segments = $this->split("#\n\s*((?:" . $this->opt([$this->t("Air"), $this->t("Hotel"), $this->t("Car")]) . "):.+-\s*(?:" . $this->opt($this->t("Calendar")) . "))#", $body);
        $airs = [];
        $hotels = [];
        $cars = [];

        foreach ($segments as $key => $segment) {
            $type = substr($segment, 0, stripos($segment, ':'));

            switch ($type) {
                case $this->t("Air"):
                    $airs[] = $segment;

break;

                case $this->t("Hotel"):
                    $hotels[] = $segment;

break;

                case $this->t("Car"):
                    $cars[] = $segment;

break;

                default:
                    $this->http->Log("segments type not detected", LOG_LEVEL_NORMAL);

                    return false;
            }
        }

        if (preg_match("#([a-zA-Z-\. ]+)\s+(?:" . $this->opt($this->t("IMPORTANT INFORMATION")) . ")#", $body, $m)) {
            $passenger = trim($m[1]);
        } else {
            $passenger = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Reference")) . "][1]/preceding::h2[1]");
        }

        foreach ($airs as $stext) {
            $rl = '';

            if (preg_match("#\b(?:" . $this->opt($this->t("Reference")) . "):[ ]*([A-Z\d]{5,7})\b#", $stext, $m)) {
                $rl = $m[1];
            }

            $seg = [];
            // FlightNumber
            if (preg_match("#^.+?:\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d{1,5})\b#", $stext, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match_all("#^(.+?\b\d{4}\b.+\d{1,2}:\d{1,2})[ ]+(.+?)\(([A-Z]{3})\)(?:,\s+Terminal:[ ]*([^,\n]+))?#m", $stext, $m) && count($m[0]) == 2) {
                // DepName
                $seg['DepName'] = trim($m[2][0]);

                // DepCode
                $seg['DepCode'] = $m[3][0];

                // DepDate
                $seg['DepDate'] = strtotime($this->normalizeDate($m[1][0]));

                // DepartureTerminal
                if (!empty($m[4][0])) {
                    $seg['DepartureTerminal'] = trim($m[4][0]);
                }

                // ArrName
                $seg['ArrName'] = trim($m[2][1]);

                // ArrCode
                $seg['ArrCode'] = $m[3][1];

                // ArrDate
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[1][1]));

                // ArrivalTerminal
                if (!empty($m[4][1])) {
                    $seg['ArrivalTerminal'] = trim($m[4][1]);
                }
            }

            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            if (preg_match("#\s+(?:" . $this->opt($this->t("Class")) . "):\s*([A-Z]{1,2})\s+#", $stext, $m)) {
                $seg['BookingClass'] = $m[1];
            }
            // PendingUpgradeTo
            // Seats
            if (preg_match("#\s+(?:" . $this->opt($this->t("Seat")) . "):\s*(\d{1,3}[A-Z])\s+#", $stext, $m)) {
                $seg['Seats'][] = $m[1];
            }

            // Duration
            if (preg_match("#\s+(?:" . $this->opt($this->t("Duration")) . "):\s*(.+?)(\s+-|\n)#", $stext, $m)) {
                $seg['Duration'] = $m[1];
            }

            // Meal
            // Smoking
            // Stops
            if (preg_match("#\s+(?:" . $this->opt($this->t("Connections")) . "):\s*(\d+)(\s+|$)#", $stext, $m)) {
                $seg['Stops'] = $m[1];
            }
            // Operator
            // Gate
            // ArrivalGate
            // BaggageClaim

            $finded = false;

            foreach ($its as $key => $itG) {
                if (isset($rl) && $itG['RecordLocator'] == $rl) {
                    $finded2 = false;

                    foreach ($itG['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            if (isset($seg['Seats'])) {
                                $its[$key]['TripSegments'][$key2]['Seats'] = (isset($value['Seats'])) ? array_merge($value['Seats'], $seg['Seats']) : $seg['Seats'];
                                $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter($its[$key]['TripSegments'][$key2]['Seats']));
                            }
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            if ($finded == false) {
                $it = [];

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $rl;

                // TripNumber
                // Passengers
                if (!empty($passenger)) {
                    $it['Passengers'][] = $passenger;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        if (count($its) == 1 && preg_match("#\s+(?:" . $this->opt($this->t("TOTAL AIR FARE")) . ").* ([A-Z]{3})[ ]*(\d[\d., ]+)\n#", $body, $m)) {
            $its[0]['TotalCharge'] = $this->amount($m[2]);
            $its[0]['Currency'] = $m[1];
        }

        foreach ($hotels as $stext) {
            $it = [];
            $it['Kind'] = "R";

            // ConfirmationNumber
            if (preg_match("#\b(?:" . $this->opt($this->t("Reference")) . "):[ ]*([A-Z\d\-]{5,})\b#", $stext, $m)) {
                $it['ConfirmationNumber'] = $m[1];
            }

            // TripNumber
            // ConfirmationNumbers
            // HotelName
            if (preg_match("#^.*?:\s*(.+?)\s+-\s+#", $stext, $m)) {
                $it['HotelName'] = $m[1];
            }

            // 2ChainName

            // CheckInDate
            // CheckOutDate
            if (preg_match("#\s+(?:" . $this->opt($this->t("Period")) . "):(.+?)[ ]*-[ ]*(.+)#", $stext, $m)) {
                $it['CheckInDate'] = strtotime($this->normalizeDate($m[1]));
                $it['CheckOutDate'] = strtotime($this->normalizeDate($m[2]));
            }

            // Address
            if (preg_match("#\s+(?:" . $this->opt($this->t("Address")) . "):(.+)#", $stext, $m)) {
                $it['Address'] = trim($m[1]);
            }

            // DetailedAddress

            // Phone
            if (preg_match("#\s+(?:" . $this->opt($this->t("Phone")) . "):(.+)#", $stext, $m)) {
                $it['Phone'] = trim($m[1]);
            }

            // Fax
            // GuestNames
            if (!empty($passenger)) {
                $it['GuestNames'][] = $passenger;
            }

            // Guests
            $it['Guests'] = count($it['GuestNames']);

            // Kids
            // Rooms
            // Rate
            // RateType
            // CancellationPolicy
            // RoomType
            if (preg_match("#\s+(?:" . $this->opt($this->t("Room type")) . "):(.+)#", $stext, $m)) {
                $it['RoomType'] = trim($m[1]);
            }

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            if (preg_match("#\s+(?:" . $this->opt($this->t("Fare")) . "):[ ]*([A-Z]{3})[ ]*(\d[\d., ]+)#", $stext, $m)) {
                $it['Total'] = $this->amount($m[2]);
                $it['Currency'] = $m[1];
            }

            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            if (preg_match("#\s+(?:" . $this->opt($this->t("Status")) . "):(.+)#", $stext, $m)) {
                $it['Status'] = trim($m[1]);
            }
            // Cancelled
            // ReservationDate
            // NoItineraries

            $its[] = $it;
        }

        foreach ($cars as $stext) {
            $it = [];
            $it['Kind'] = "L";

            // Number
            if (preg_match("#\b(?:" . $this->opt($this->t("Reference")) . "):[ ]*([A-Z\d]{5,})\b#", $stext, $m)) {
                $it['Number'] = $m[1];
            }

            // TripNumber

            // PickupDatetime
            // PickupLocation
            // DropoffDatetime
            // DropoffLocation
            if (preg_match_all("#(.+\d{4}.*\d:\d+).*\s+(.+)#", $stext, $m) && count($m[0]) == 2) {
                $it['PickupDatetime'] = strtotime($this->normalizeDate($m[1][0]));
                $it['PickupLocation'] = $m[2][0];
                $it['DropoffDatetime'] = strtotime($this->normalizeDate($m[1][1]));
                $it['DropoffLocation'] = $m[2][1];
            }
            // PickupPhone
            // PickupFax
            // PickupHours
            // DropoffPhone
            // DropoffHours
            // DropoffFax
            // RentalCompany
            if (preg_match("#^.*?:\s*(.+?)\s+-\s+#", $stext, $m)) {
                $it['RentalCompany'] = $m[1];
            }

            // CarType
            if (preg_match("#\s+(?:" . $this->opt($this->t("Vehicle types")) . "):(.+)#", $stext, $m)) {
                $it['CarType'] = $m[1];
            }

            // CarModel
            // CarImageUrl
            // RenterName
            if (!empty($passenger)) {
                $it['RenterName'] = $passenger;
            }

            // PromoCode
            // BaseFare

            // TotalCharge
            // Currency
            if (preg_match("#\s+(?:" . $this->opt($this->t("Fare")) . "):[ ]*([A-Z]{3})[ ]*(\d[\d., ]+)#", $stext, $m)) {
                $it['TotalCharge'] = $this->amount($m[2]);
                $it['Currency'] = $m[1];
            }

            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            if (preg_match("#\s+(?:" . $this->opt($this->t("Status")) . "):(.+)#", $stext, $m)) {
                $it['Status'] = trim($m[1]);
            }

            // ServiceLevel
            // Cancelled
            // PricedEquips
            // Discount
            // Discounts
            // Fees
            // PaymentMethod
            // ReservationDate
            // NoItineraries

            $its[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@berg-hansen.no') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

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
            if (strpos($body, $re) !== false
                || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $re . '")]')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false
                || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $re . '")]')->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }
        $its = [];
        $body = str_ireplace("</p>", '</p>' . "\n\n", $body);
        $body = str_ireplace("</b>", '</b>' . "\n", $body);
        $body = strip_tags(preg_replace("#(<br\s*[^>]*?\s*>)#i", "\n", $body));
        $this->parseHtml($body, $its);
        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
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
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{1,2})[ \.]+(\w+)[ .]+(\d{4}),.+\s+(\d+:\d+)\s*$#", // 26. apr 2018, at 12:55; 03. des 2018, kl. 08:55
            "#^\s*(\d{1,2})[ \.]+(\w+)[ .]+(\d{4})\s*$#", // 03. mar 2017
        ];
        $out = [
            "$1 $2 $3 $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }

            if (strtotime($str) == false) {
                if ($en = MonthTranslate::translate($m[1], 'no')) {
                    $str = str_replace($m[1], $en, $str);
                }
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
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.\s]+)#", $s);

        foreach ($sym as $f=> $r) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
