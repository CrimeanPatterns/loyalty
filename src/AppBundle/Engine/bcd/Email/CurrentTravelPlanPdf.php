<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Engine\MonthTranslate;

class CurrentTravelPlanPdf extends \TAccountChecker
{
    public $mailFiles = "bcd/it-11194904.eml, bcd/it-11230853.eml, bcd/it-11248813.eml, bcd/it-11343641.eml";

    public $reFrom = "@bcdtravel.";
    public $reProvider = "@bcdtravel.";
    public $reSubject = [
        "de" => "Ihr aktueller Reiseplan für",
    ];

    public $reBody = ['BCD Travel', '.bcdtravel.', 'Reiseplan:'];

    public $reBody2 = [
        "de" => "Reiseplan",
    ];
    public $pdfPattern = "Reise-.*.pdf";

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "en";
    public $text;
    public $passenger;

    public function parsePdf(&$its)
    {
        $stext = $this->text;
        $pos = strpos($stext, 'Zusatzdaten');

        if (!empty($pos)) {
            $stext = substr($stext, 0, $pos);
        }

        if (preg_match("#\n\s*(?:Herr|Frau)\s+([\w\-, ]+)#u", $stext, $m)) {
            $this->passenger = explode('  ', trim($m[1]))[0];
        }

        $segments = $this->split("#(?:^|\n)[ ]*((?:Flug|Bahn|Hotel|Mietwagen)\s*-\s*(?:Onlinebuchung|Reisebürobuchung))#", $stext);

        foreach ($segments as $text) {
            $text = preg_replace("#\n[ ]+Seite[ \d/]+#", '', $text);

            if (strncmp($text, 'Bahn', 4) === 0) {
                $this->parseRail($text, $its);

                continue;
            }

            if (strncmp($text, 'Flug', 4) === 0) {
                $this->parseFlight($text, $its);

                continue;
            }

            if (strncmp($text, 'Hotel', 5) === 0) {
                $this->parseHotel($text, $its, count($segments));

                continue;
            }

            if (strncmp($text, 'Mietwagen', 9) === 0) {
                $this->parseCar($text, $its);

                continue;
            }
        }
    }

    public function parseFlight($text, &$its)
    {
        $it = ['Kind' => 'T'];

        // RecordLocator
        if (preg_match("#Buchungsnr[.: ]+([A-Z\d]+)\b#", $text, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        // TripNumber
        // ConfirmationNumbers
        // Passengers
        if (!empty($this->passenger)) {
            $it['Passengers'][] = $this->passenger;
        }
        // AccountNumbers
        // TripSegments
        // Cancelled
        // BaseFare
        // TotalCharge
        // Currency
        if (preg_match("#\n\s*Preis[\s:]*(.+)#", $text, $m)) {
            $it['TotalCharge'] = $this->amount($m[1]);
            $it['Currency'] = $this->currency($m[1]);
        }
        // Tax
        // Fees
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        // TicketNumbers

        $segments = $this->split("#\n([ ]*.+\d{2}:\d{2}.+\|[\s\S]+?\d{2}:\d{2})#", $text);

        foreach ($segments as $segText) {
            $seg = [];

            $pos = strpos($segText, 'Operating Airline');

            if (empty($pos)) {
                $pos = strpos($segText, 'Status');
            }

            if (!empty($pos)) {
                $tableText = substr($segText, 0, $pos);
                $table = $this->SplitCols($tableText);
            } else {
                if (preg_match("#^(.+ \d{2}:\d{2} [\s\S]+? \d{2}:\d{2} [\s\S]+?)\n\n#", $segText, $m)) {
                    $table = $this->SplitCols($m[1]);
                }
            }
            // FlightNumber
            // AirlineName
            // Cabin
            // BookingClass
            if (!empty($table[2]) && preg_match("#^.* ([A-Z\d]{2})(\d{1,5})\n\s+Klasse:\s*(.+),\s*Buchungsklasse\s*([A-Z]{1,2})#", $table[2], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['Cabin'] = $m[3];
                $seg['BookingClass'] = $m[4];
            }

            if (!empty($table[0]) && preg_match("#^\s*(.+)\n\s*(.+)#", $table[0], $m)) {
                $DepDate = trim($m[1]);
                $ArrDate = trim($m[2]);
            }

            // DepDate
            // DepName
            // DepCode
            // DepartureTerminal
            // ArrDate
            // ArrName
            // ArrCode
            // ArrivalTerminal
            if (!empty($table[1]) && preg_match("#^\s*(.+)\n\s*(.+)#", $table[1], $m)) {
                if (!empty($m[1]) && preg_match("#(?<time>\d{2}:\d{2})\s+(?<name>.+)\|\s*(?<code>[A-Z]{3})\s*\((?<airport>.+?)(?:\/?Terminal\s*(?<terminal>.+))?\)#", $m[1], $mat)) {
                    if (!empty($DepDate) && !empty($mat['time'])) {
                        $seg['DepDate'] = strtotime($this->normalizeDate($DepDate . ' ' . $mat['time']));
                    }
                    $seg['DepName'] = trim($mat['name']) . ', ' . trim($mat['airport']);
                    $seg['DepCode'] = $mat['code'];

                    if (!empty($mat['terminal'])) {
                        $seg['DepartureTerminal'] = $mat['terminal'];
                    }
                }

                if (!empty($m[2]) && preg_match("#(?<time>\d{2}:\d{2})\s+(?<name>.+)\|\s*(?<code>[A-Z]{3})\s*\((?<airport>.+?)(?:\/?Terminal\s*(?<terminal>.+))?\)#", $m[2], $mat)) {
                    if (!empty($ArrDate) && !empty($mat['time'])) {
                        $seg['ArrDate'] = strtotime($this->normalizeDate($ArrDate . ' ' . $mat['time']));
                    }
                    $seg['ArrName'] = trim($mat['name']) . ', ' . trim($mat['airport']);
                    $seg['ArrCode'] = $mat['code'];

                    if (!empty($mat['terminal'])) {
                        $seg['ArrivalTerminal'] = $mat['terminal'];
                    }
                }
            }

            // Operator
            if (preg_match("#\n\s*Operating Airline [ ]*(.+)#", $segText, $m)) {
                $seg['Operator'] = trim($m[1]);
            }
            // Aircraft
            if (preg_match("#\n\s*Flugzeug [ ]*(.+)#", $segText, $m)) {
                $seg['Aircraft'] = trim($m[1]);
            }

            // TraveledMiles
            // AwardMiles
            // PendingUpgradeTo
            // Seats
            if (preg_match("#\n\s*Sitzplatz [ ]*(\d{1,3}[A-Z])#", $segText, $m)) {
                $seg['Seats'][] = $m[1];
            }
            // Duration
            // Meal
            if (preg_match("#\n\s*Mahlzeit an Bord[ ]*(.+)#", $segText, $m)) {
                $seg['Meal'] = trim($m[1]);
            }

            // Smoking
            // Stops

            $it['TripSegments'][] = $seg;
        }
        $its[] = $it;
    }

    public function parseHotel($text, &$its, $cntSeg)
    {
        $it = ['Kind' => 'R'];

        // ConfirmationNumber
        if (preg_match("#Reservierungsnr\.[ ]+(\d+)#", $text, $m)) {
            $it['ConfirmationNumber'] = $m[1];
        }
        // TripNumber
        // ConfirmationNumbers

        if (preg_match("#^Hotel.+\n+([ ]*[\s\S]+?)\n+\s+E-Mail#", $text, $m)) {
            $table = $this->SplitCols($m[1]);
        } elseif ($cntSeg === 1 && preg_match("#\n\n([ ]{5,}.+?\n[ ]*Telefon.+?)\n\n[ ]+Hotel -#s", strstr($this->text, 'BCD Travel Direct'), $m)) {
            $table = $this->SplitCols($m[1]);
        }
        // CheckInDate
        // CheckOutDate
        if (isset($table[0]) && preg_match("#\s*(.+)\n(.+)#", $table[0], $m)) {
            $it['CheckInDate'] = strtotime($this->normalizeDate($m[1]));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($m[2]));
        }

        if (isset($table[1])) {
            // HotelName
            // Address
            if (preg_match("#\s*(.+)\n([\s\S]+?)(\n{2,}|Telefon:)#", $table[1], $m)) {
                $it['HotelName'] = trim($m[1]);
                $it['Address'] = trim(str_replace("\n", ', ', preg_replace("/[ ]+/", ' ', $m[2])));
            }
            // Phone
            if (preg_match("#Telefon:[ ]*([\d\-+ \(\)]+)(?:\s+|$)#", $table[1], $m)) {
                $it['Phone'] = trim($m[1]);
            }
            // Fax
            if (preg_match("#Telefax:[ ]*([\d\-+ \(\)]+)(?:\s+|$)#", $table[1], $m)) {
                $it['Fax'] = trim($m[1]);
            }
        }
        // 2ChainName
        // DetailedAddress
        // GuestNames
        if (preg_match("#Weitere Gäste[ ]*([\s\S]+?)\n[ ]{0,10}\S#", $text, $m)) {
            $it['GuestNames'] = array_map('trim', explode(',', trim($m[1])));
        } elseif (!empty($this->passenger)) {
            $it['GuestNames'][] = $this->passenger;
        }

        // Guests
        if (!empty($it['GuestNames'])) {
            $it['Guests'] = count($it['GuestNames']);
        }
        // Kids
        // Rooms
        // Rate
        // RateType
        // CancellationPolicy
        if (preg_match("#Stornobedingungen[ ]*([\s\S]+?)\n[ ]{0,10}\S#", $text, $m)) {
            $it['CancellationPolicy'] = trim(preg_replace("#\s+#", ' ', $m[1]));
        }

        // RoomType
        if (isset($table[2])) {
            if (preg_match("#ab Kategorie:\s*(.+)#", $table[2], $m)) {
                $it['RoomType'] = trim(str_replace("\n", ' ', $m[1]));
            }
        }

        // RoomTypeDescription
        if (preg_match("#\n\s*Hotelrate[ ]*([\s\S]+?)\n[ ]{0,10}\S#", $text, $m)) {
            if (preg_match("#(?:^|\n)\s*Zimmer\s*[\d\-,:]*\s*\n\s*([^:\n]+?):\s*(.{0,70}\n|.+\n.+)#", $m[1], $mat)) {
                $it['RoomType'] = $mat[1];
                $it['RoomTypeDescription'] = trim(preg_replace("#\s+#", ' ', $mat[2]));
            } elseif (preg_match("#(?:^|\n)\s*Zimmer\s*[\d\-,:]*\s*\n\s*([^:]+?)(?::|\n)\s*(.{0,70}\n|[ ]{5,}.+\n[ ]{5,}.+)#", $m[1], $mat)) {
                $it['RoomType'] = $mat[1];
                $mat[2] = $this->re("/(.+?)(?:\n\s*[\w\- ]+:|$)/s", $mat[2]);
                $it['RoomTypeDescription'] = trim(preg_replace("#\s+#", ' ', $mat[2]));
            }
        }

        // Cost
        // Taxes
        // Total
        // Currency
        if (preg_match("#\n\s+Gesamtpreis[ ]*(.+)#", $text, $m)) {
            $it['Total'] = $this->amount($m[1]);
            $it['Currency'] = $this->currency($m[1]);
        }
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries
        $its[] = $it;
    }

    public function parseRail($text, &$its)
    {
        $it = ['Kind' => 'T'];
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        // RecordLocator
        if (preg_match("#Auftragsnummer:[ ]*([A-Z\d]{5,7})\b#", $text, $m)) {
            $it['RecordLocator'] = $m[1];
        }
        // TripNumber
        // ConfirmationNumbers
        // Passengers
        if (!empty($this->passenger)) {
            $it['Passengers'][] = $this->passenger;
        }
        // AccountNumbers
        // TripSegments
        // Cancelled
        // BaseFare
        // TotalCharge
        // Currency
        if (preg_match("#\n\s*Preis[ ]*(.+)#", $text, $m)) {
            $it['TotalCharge'] = $this->amount($m[1]);
            $it['Currency'] = $this->currency($m[1]);
        }
        // Tax
        // Fees
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        // TicketNumbers
        preg_match_all("#\n.*[ ]+\d{2}:\d{2}[ ]+.+\s*\n\s*.*\d{2}:\d{2}.+(?:\s+Sitzplatzreservierung.+)?#", $text, $segments);

        if (!isset($segments[0]) || empty($segments[0])) {
            $its[] = $it;

            return null;
        }

        foreach ($segments[0] as $key => $segText) {
            $seg = [];
            // FlightNumber
            // Type
            // DepCode
            // DepName
            // DepAddress
            // DepDate
            if (preg_match("#^\s*(.*\d{2}:\d{2})[ ]+(.+)[ ]{3,}(.*\D(\d+))\s*\n#", $segText, $m)) {
                $seg['FlightNumber'] = $m[4];
                $seg['Type'] = trim($m[3]);
                $seg['DepName'] = trim($m[2]);
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));
            }
            // ArrCode
            // ArrName
            // ArrAddress
            // ArrDate
            // Cabin
            if (preg_match("#\d{2}:\d{2}[\s\S]+?\n\s*(.*\d{2}:\d{2})[ ]+(.+)[ ]{3,}(.+)\s*#", $segText, $m)) {
                $seg['ArrName'] = trim($m[2]);
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrDate'] = strtotime($this->normalizeDate($m[1]));
                $seg['Cabin'] = trim($m[3]);
            }

            // Vehicle
            // TraveledMiles
            // BookingClass
            // PendingUpgradeTo
            // Seats
            if (preg_match_all("#\b(Wagen\s+\w+,\s*Platz\s+\w+)\b#", $segText, $m)) {
                $seg['Seats'] = $m[1];
            }

            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $seg;
        }
        $its[] = $it;
    }

    public function parseCar($text, &$its)
    {
        $it = ['Kind' => 'L'];
        // Number
        if (preg_match("#Reservierungsnr\.[ ]+([\dA-Z]+)#", $text, $m)) {
            $it['Number'] = $m[1];
        }

        if (preg_match("#Mietwagen.+\n+([ ]*.*\d{2}:\d{2}[\s\S]+?)\n+([ ]{0,10}\S.*\d{2}:\d{2}[\s\S]+?)\s+Fahrzeuggruppe#", $text, $m)) {
            $tableP = $this->SplitCols($m[1]);
            $tableD = $this->SplitCols($m[2]);
        }

        // TripNumber
        // PickupDatetime
        // PickupLocation
        // PickupPhone
        // PickupFax
        // PickupHours
        if (isset($tableP[1])) {
            if (preg_match("#^(\d{2}:\d{2})\s+([\s\S]+?)(?:Öffnungszeiten|Telefon)#", $tableP[1], $m)) {
                if (!empty($tableP[0])) {
                    $it['PickupDatetime'] = strtotime($this->normalizeDate(trim($tableP[0]) . ' ' . $m[1]));
                }
                $it['PickupLocation'] = trim(preg_replace("#\s+#", ' ', $m[2]));
            }

            if (preg_match("#Telefon:([ ]*[\d \-+\(\)]+)#", $tableP[1], $m)) {
                $it['PickupPhone'] = explode('  ', trim($m[1]))[0];
            }

            if (preg_match("#Öffnungszeiten:\n.+?[ ]{3,}(\d{2}:.+)#", $tableP[1], $m)) {
                $it['PickupHours'] = trim($m[1]);
            }
        }

        // DropoffDatetime
        // DropoffLocation
        // DropoffPhone
        // DropoffHours
        // DropoffFax
        if (isset($tableD[1])) {
            if (preg_match("#^(\d{2}:\d{2})\s+([\s\S]+?)(?:Öffnungszeiten|Telefon)#", $tableD[1], $m)) {
                if (!empty($tableD[0])) {
                    $it['DropoffDatetime'] = strtotime($this->normalizeDate(trim($tableD[0]) . ' ' . $m[1]));
                }
                $it['DropoffLocation'] = trim(preg_replace("#\s+#", ' ', $m[2]));
            }

            if (preg_match("#Telefon:([ ]*[\d \-+\(\)]+)#", $tableD[1], $m)) {
                $it['DropoffPhone'] = explode('  ', trim($m[1]))[0];
            }

            if (preg_match("#Öffnungszeiten:\n.+?[ ]{3,}(\d{2}:.+)#", $tableD[1], $m)) {
                $it['DropoffHours'] = trim($m[1]);
            }
        }
        // RentalCompany
        // CarType
        if (isset($tableP[2]) && preg_match("#Gesellschaft ([\s\S]+?)\s+Kategorie ([\s\S]+?)#", $tableP[2], $m)) {
            $it['RentalCompany'] = trim($m[1]);
            $it['CarType'] = trim($m[2]);
        }

        // CarModel
        if (preg_match("#Beispielfahrzeuge[ ]*(.+)#", $text, $m)) {
            $it['CarModel'] = trim($m[1]);
        }

        // CarImageUrl
        // RenterName
        if (!empty($this->passenger)) {
            $it['RenterName'] = $this->passenger;
        }
        // TripNumber
        // ConfirmationNumbers
        // PromoCode
        // BaseFare
        // TotalCharge
        // Currency
        if (preg_match("#\n\s+Preis[ ]*(.+)#", $text, $m)) {
            $it['TotalCharge'] = $this->amount($m[1]);
            $it['Currency'] = $this->currency($m[1]);
        }
        // TotalTaxAmount
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
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

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reProvider) !== false;
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

        if (!empty($pdfs)) {
            $text = '';

            foreach ($pdfs as $pdf) {
                if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                    continue;
                }
            }
            $finded = false;

            foreach ($this->reBody as $reBody) {
                if (strpos($text, $reBody) !== false) {
                    $finded = true;
                }
            }

            if ($finded == false) {
                return false;
            }

            foreach ($this->reBody2 as $re) {
                if (stripos($text, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->parsePdf($its);
        }
        $a = explode('\\', __CLASS__);
        $result = [
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
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
            "#^\s*[^\d\s]+,\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s*$#", // So, 18.02.2018
            "#^\s*[^\d\s]+,\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d+:\d+)$#", // So, 18.02.2018  07:54
        ];
        $out = [
            "$1.$2.$3",
            "$1.$2.$3 $4",
        ];
        $str = preg_replace($in, $out, $str);

        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
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

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("#", preg_replace("#\s{2,}#", "#", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
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
}
