<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class BookingConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-12661122.eml, flightcentre/it-12661372.eml, flightcentre/it-12661404.eml, flightcentre/it-12661634.eml, flightcentre/it-12661640.eml, flightcentre/it-76573614.eml";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $reFrom = "@flightcentre.com.au";
    private $reSubject = [
        "en"=> "Booking confirmation",
    ];
    private $reBody = 'FLIGHT CENTRE';
    private $reBody2 = [
        "en" => "Booking",
    ];
    private $pdfPattern = ".+\.pdf";

    private $date = null;

    public function parsePdf()
    {
        $itineraries = [];
        $text = $this->text;
        $PassengerPricingTable = $this->re("#Passenger Pricing Details\n(.*?)\n\n#s", $text);
        $PassengerPricingTable = $this->splitCols($PassengerPricingTable, $this->colsPos($PassengerPricingTable, 10));

        $clean = preg_replace(["#\n[A-Z]+ [\d ]+\s+Page \d+#",
            "#\n\s+Issue Date\s+\d+[^\s\d]+\d{2}\n" .
            "\s+Booking Number\s+\w{2}/\w+\n" .
            "\s+Agent Code\s+\w+" .
            "(?:\n\s+Your Reference\s+[A-Z\d]+)?[ ]*\n#s", ], "", $text);
        // echo $clean;
        // die();

        $partsByDates = $this->split("#\n([A-Z]+ \d+ [A-Z]+ \d{4}\n)#", $this->re("#\nItinerary\n(.+)#s", $clean));

        if (count(array_filter($partsByDates)) == 0) {
            $partsByDates = $this->split("#\n([A-Z]+ \d+ [A-Z]+ \d{4}\n)#", $this->re("#\n\s*Have an enjoyable trip, and thank you for booking with Flight Centre Hol idays\n(.+)#s", $clean));
        }
        $airs = [];
        $hotels = [];
        $cars = [];
        $tours = [];

        foreach ($partsByDates as $dtext) {
            $date = $this->re("#^(.*?)\n#", $dtext);
            $segments = $this->split("#\n([^\n\S]*(?:Flight - Departing|Flight - Arriving|Car Rental|Accommodation|Tour|Transfer))#u", $dtext);

            foreach ($segments as $stext) {
                $type = $this->re("#^\s*(.*?)(?:\s{2,}|\n)#u", $stext);

                switch ($type) {
                    case 'Flight - Departing':
                        if (!$rl = $this->re("#Reservation Number:.*\s([A-Z\d]+)\n#", $stext)) {
                            if (!$rl = $this->re("#Booking Number\s+(\w{2}/\w+)\n#", $text)) {
                                $this->logger->info("RL not matched");

                                return;
                            }
                        }
                        $airs[$rl][] = [[$date, $stext]];
                        $lastDepAir = [$rl, count($airs[$rl]) - 1];

                    break;

                    case 'Flight - Arriving':
                        if (!isset($lastDepAir)) {
                            $this->logger->info("lastDepAir not found");

                            return;
                        }

                        if (!isset($airs[$lastDepAir[0]][$lastDepAir[1]])) {
                            $this->logger->info("airs[lastDepAir] not found");

                            return;
                        }
                        $airs[$lastDepAir[0]][$lastDepAir[1]][] = [$date, $stext];
                        unset($lastDepAir);

                    break;

                    case 'Car Rental':
                        $cars[] = [$date, $stext];

                    break;

                    case 'Accommodation':
                        $hotels[] = [$date, $stext];

                    break;

                    case 'Tour':
                        $tours[] = [$date, $stext];

                    break;

                    case 'Transfer':
                        //transfer depart/arrive is bad
                    break;

                    default:
                        $this->logger->info("unknown type " . $type);

                        return;
                }
            }
        }

        //##################
        //##   FLIGHTS   ###
        //##################
        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->re("#Booking Number\s+(\w{2}/\w+)\n#", $text);

            // Passengers
            if (count($PassengerPricingTable) == 7) {
                $it['Passengers'] = array_filter(explode("\n", $this->re("#Passenger\n(.+)#", $PassengerPricingTable[0])));
            }

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            if (count($airs) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->re("#Air\n.*?([^\n]+)$#s", $PassengerPricingTable[3]));

                // BaseFare
                // Currency
                if ($cur = $this->re("#(AUD)[$]#", $text)) {
                    $it['Currency'] = $cur;
                } else {
                    $it['Currency'] = $this->currency($this->re("#Air\n.*?([^\n]+)$#s", $PassengerPricingTable[3]));
                }
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($segments as $data) {
                $dep = $data[0];
                $arr = $data[1];

                $deptable = $this->re("#(.*?)\n\n#s", $dep[1]);
                $deptable = $this->splitCols($deptable, $this->colsPos($deptable, 10));

                if (count($deptable) != 2 && count($deptable) != 3) {
                    $this->logger->info("incorrect parse deptable flight");

                    return;
                }
                $arrtable = $this->re("#(.*?)(?:\n\n|\s*$)#s", $arr[1]);
                $arrtable = $this->splitCols($arrtable, $this->colsPos($arrtable, 10));

                if (count($arrtable) != 2) {
                    $this->logger->info("incorrect parse arrtable flight");

                    return;
                }

                $depdate = $this->normalizeDate($dep[0]);
                $arrdate = $this->normalizeDate($arr[0]);

                $itsegment = [];
                // Depart KOH SAMUI on BANGKOK AIRLINES flight PG963 ECO
                if (preg_match("#Depart (?<Name>.*?) on .*? flight (?<AirlineName>[A-Z\d]{2})(?<FlightNumber>\d+)#s", $deptable[1], $m)) {
                    // FlightNumber
                    $itsegment['FlightNumber'] = $m['FlightNumber'];

                    // AirlineName
                    $itsegment['AirlineName'] = $m['AirlineName'];

                    // DepCode
                    if (!$itsegment['DepCode'] = $this->re("#\((A-Z){3}\)#", $deptable[1])) {
                        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                    }

                    // DepName
                    $itsegment['DepName'] = $m['Name'];
                }

                if (preg_match("#Arrives (?<Name>[A-Z ]+)#", $arrtable[1], $m)) {
                    // ArrCode
                    if (!$itsegment['ArrCode'] = $this->re("#\((A-Z){3}\)#", $arrtable[1])) {
                        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }

                    // ArrName
                    $itsegment['ArrName'] = $m['Name'];
                }

                if (preg_match("#Flight - Departing\n(?<Time>\d+:\d+[AP]M)#", $deptable[0], $m)) {
                    // DepDate
                    $itsegment['DepDate'] = $this->normalizeDate($m['Time'], $depdate);
                }

                if (preg_match("#Flight - Arriving\n(?<Time>\d+:\d+[AP]M)#", $arrtable[0], $m)) {
                    // ArrDate
                    $itsegment['ArrDate'] = $this->normalizeDate($m['Time'], $arrdate);
                }
                // DepartureTerminal
                $itsegment['DepartureTerminal'] = trim(str_ireplace("TERMINAL", "", $this->re("#Departure Terminal:\s+(.+)#", $dep[1])));
                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = trim(str_ireplace("TERMINAL", "", $this->re("#Arrival Terminal:\s+(.+)#", $arr[1])));
                // BookingClass
                $itsegment['BookingClass'] = $this->re("#Booking Class:\s+(.+)#", $dep[1]);

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }

        //#################
        //##   HOTELS   ###
        //#################
        foreach ($hotels as $data) {
            $date = $this->normalizeDate($data[0]);
            $htext = $data[1];
            $mainTable = $this->re("#Accommodation\n(.*?(?:TEL:[^\n]+|\n\n))#s", $htext);
            $mainTable = $this->splitCols($mainTable, $this->colsPos($mainTable, 10));

            if (count($mainTable) != 3) {
                $this->logger->info("incorrect parse mainTable hotel");

                return;
            }

            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            if (!$it['ConfirmationNumber'] = $this->re("#Supplier Reference:\s+(.+)#", $htext)) {
                $it['ConfirmationNumber'] = $this->re("#Booking Number\s+(\w{2}/\w+)\n#", $text);
            }

            // TripNumber
            $it['TripNumber'] = $this->re("#Booking Number\s+(\w{2}/\w+)\n#", $text);

            // HotelName
            $it['HotelName'] = $this->re("#^([^\n]+)#", $mainTable[0]);

            // 2ChainName
            if (preg_match("#For \d+ nights? In: (?<in>\d+ [A-Z]+) Out: (?<out>\d+ [A-Z]+)#", $htext, $m)) {
                // CheckInDate
                $it['CheckInDate'] = $this->normalizeDate($m['in'], $date);

                // CheckOutDate
                $it['CheckOutDate'] = $this->normalizeDate($m['out'], $date);
            }

            if ($outtime = $this->re("#Checkout required by (\d+:\d+) hrs#", $htext)) {
                $it['CheckOutDate'] = $this->normalizeDate($outtime, $it['CheckOutDate']);
            }

            // Address
            $it['Address'] = implode(", ", array_filter(explode("\n", $this->re("#^[^\n]+\n(.+)#s", $mainTable[0]))));

            // Phone
            $it['Phone'] = $this->re("#TEL:\s+(.+)#", $mainTable[1]);

            // GuestNames
            if (count($PassengerPricingTable) == 7) {
                $it['GuestNames'] = array_filter(explode("\n", $this->re("#Passenger\n(.+)#", $PassengerPricingTable[0])));
            }

            // CancellationPolicy
            $it['CancellationPolicy'] = implode("\n", array_map('trim', explode("\n", $this->re("#Cancellation Policy\n\n(.*?)(?:\n\n|$)#s", $htext))));

            // RoomType
            $it['RoomType'] = $this->re("#In a (.+)#", $htext);

            // Total
            $it['Total'] = $this->amount($mainTable[2]);

            $itineraries[] = $it;
        }

        //###############
        //##   CARS   ###
        //###############
        foreach ($cars as $data) {
            $date = $this->normalizeDate($data[0]);
            $ctext = $data[1];
            $ptable = $this->re("#\n([^\n\S]*Pick up from.*?)\n\n#s", $text);
            $ptable = $this->splitCols($ptable, $this->colsPos($ptable));

            $it = [];

            $it['Kind'] = "L";

            // Number
            $it['Number'] = $this->re("#(?:Supplier Reference:|Booking Code:)\s+(.+)#", $ctext);

            // TripNumber
            $it['TripNumber'] = $this->re("#(?:Booking Number|Booking|Quote Number)\s+(\w{2}/\w+)\n#", $text);

            if (preg_match("#Vehicle to be picked up at (?<Time>\d+:\d+[ap]m) on (?<Date>\d+ [^\s\d]+) in (?<Location>.*?)(?:\s{2,}|\n)#", $ctext, $m)) {
                // PickupDatetime
                $it['PickupDatetime'] = $this->normalizeDate($m['Date'] . ', ' . $m['Time'], $date);

                // PickupLocation
                $it['PickupLocation'] = $m['Location'];
            }

            if (preg_match("#Vehicle to be returned at (?<Time>\d+:\d+[ap]m) on (?<Date>\d+ [^\s\d]+) in (?<Location>.*?)(?:\s{2,}|\n)#", $ctext, $m)) {
                // DropoffDatetime
                $it['DropoffDatetime'] = $this->normalizeDate($m['Date'] . ', ' . $m['Time'], $date);

                // DropoffLocation
                $it['DropoffLocation'] = $m['Location'];
            }

            if (count($ptable) == 3) {
                // PickupLocation
                $it['PickupLocation'] = str_replace("\n", ", ", $ptable[1]);

                // PickupPhone
                $it['PickupPhone'] = $this->re("#TEL:\s+(.+)#", $ptable[2]);
            }

            if (count($ptable) == 2 && !empty($this->re('/^(Pick up from)\:/', $ptable[0]))) {
                // PickupLocation
                $it['PickupLocation'] = str_replace("\n", ", ", $this->re('/^Pick up from\:\s+(.+)/s', $ptable[0]));

                // PickupPhone
                $it['PickupPhone'] = $this->re("#TEL:\s+(.+)#", $ptable[1]);
            }

            // RentalCompany
            $it['RentalCompany'] = $this->re("#Car Type:[^\n]+\n\n\s*(.*?) - #", $ctext);

            if (empty($it['RentalCompany'])) {
                $it['RentalCompany'] = $this->re("#^(\D+)\s\-#", $it['PickupLocation']);
            }

            // RenterName
            if (count($PassengerPricingTable) == 7) {
                $pass = array_filter(explode("\n", $this->re("#Passenger\n(.+)#", $PassengerPricingTable[0])));

                if (count($pass) == 1) {
                    $it['RenterName'] = array_shift($pass);
                }
            }

            if (count(array_filter($PassengerPricingTable)) == 0) {
                if (preg_match_all("/Itinerary for\s*([[:alpha:]][-.'[:alpha:] ]*[[:alpha:]])\s+Booking/", $text, $m)) {
                    $it['RenterName'] = array_filter(array_unique($m[1]));
                }
            }

            // CarType
            $it['CarType'] = $this->re("#A (.*?) for \d+ day#", $ctext);

            if (empty($it['CarType'])) {
                $it['CarType'] = $this->re("#\s*(.*?) for \d+ day#", $ctext);
            }

            // CarModel
            $it['CarModel'] = $this->re("#Car Type:\s+(.+)#", $ctext);

            if (empty($it['CarModel'])) {
                $it['CarModel'] = $this->re("#Vehicle details:\n\s*[*]\s*(.+)\n#", $ctext);
            }

            if (preg_match("#Vehicle to be picked up at .*?\s{2,}([\d,.]+)\n#", $ctext, $m)) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($m[1]);

                // Currency
                $it['Currency'] = $this->re("#(AUD)[$]#", $ctext);
            }

            $itineraries[] = $it;
        }

        //################
        //##   TOURS   ###
        //################
        foreach ($tours as $data) {
            $date = $this->normalizeDate($data[0]);
            $etext = $data[1];

            $it = [];
            $it['Kind'] = "E";
            // ConfNo
            if (!$it['ConfNo'] = $this->re("#Supplier Reference:\s+(.+)#", $etext)) {
                $it['ConfNo'] = CONFNO_UNKNOWN;
            }

            // TripNumber
            $it['TripNumber'] = $this->re("#Booking Number\s+(\w{2}/\w+)\n#", $text);

            // Name
            $it['Name'] = $this->re("#Tour\n\s+(.*?)\s{2,}#", $etext);

            // StartDate
            $it['StartDate'] = $date;

            if ($time = $this->re("#DEPARTS\n.*at (\d+[:.]\d+[ap]m)#", $etext)) {
                $it['StartDate'] = $this->normalizeDate($time, $date);
            }

            // EndDate
            // Address
            $it['Address'] = $it['Name'];

            // Phone
            $it['Phone'] = $this->re("#TEL:\s+(.+)#", $etext);

            // DinerName
            // Guests
            $it['Guests'] = $this->re("#For (\d+) adult#", $etext);

            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->re("#Tour\n[^\n]+\n.*\s([\d,.]+)\n#", $etext));

            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            // EventType
            $it['EventType'] = EVENT_EVENT;

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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parsePdf(),
                'TotalCharge' => [
                    "Amount"   => $this->amount($this->re("#Total Due\s+(.+)#", $this->text)),
                    "Currency" => $this->currency($this->re("#Total Due\s+(.+)#", $this->text)),
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

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->http->log($instr);
        $in = [
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //SUN 28 JUN 2015
            "#^(\d+)\.(\d+[ap]m)$#", //6.45pm
        ];
        $out = [
            "$1",
            "$1:$2",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
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
}
