<?php

namespace AwardWallet\Engine\aurinko\Email;

class PaymentInformation extends \TAccountChecker
{
    public $mailFiles = "aurinko/it-12146966.eml, aurinko/it-12270506.eml";

    public $reFrom = "@aurinkomatkat.fi";
    public $reSubject = [
        "fi" => "MATKAVAHVISTUS",
    ];
    public $reBody = 'Aurinkomatkat';
    public $reBody2 = [
        "fi" => "MATKAVAHVISTUS",
    ];
    public $date;
    public $text;
    public $pdfPattern = ".+.pdf";

    public static $dictionary = [
        "fi" => [],
    ];

    public $lang = "en";
    public $its;

    public function parsePdf()
    {
        $itsL = [];
        $text = $this->text;
        $fileRL = [];

        if (preg_match("#(?:Vahvistusnumero|Varausnumero):[ ]*([\dA-Z]{5,})\b#", $text, $m)) {
            $tripNumber = $m[1];
        }

        if (preg_match("#Varauspäivä:[ ]*(.+)\b#", $text, $m)) {
            $reservationDate = strtotime($m[1]);
        }

        if (preg_match("#\n([ ]+Etunimi[ ]*Sukunimi)[\s\S]+?(\n[ ]*Veroerittely:)#", $text, $m, PREG_OFFSET_CAPTURE)) {
            $reservationText = substr($text, 0, $m[1][1]);
            $passengersText = substr($text, $m[1][1], $m[2][1] - $m[1][1]);
        } else {
            $this->its[] = [];

            return false;
        }

        $reservationText = preg_replace("#((?:^|\n)[ ]*MATKAVAHVISTUS[\s\S]+?Varauspäivä:.+)#", '', $reservationText);

        $passTableHead = $this->TableHeadPos(explode("\n", $passengersText)[0]);
        array_unshift($passTableHead, 0);

        if (count($passTableHead) < 4) {
            $this->its[] = [];

            return false;
        }

        $passTableHead[count($passTableHead) - 1] = (int) (($passTableHead[count($passTableHead) - 1] + $passTableHead[count($passTableHead) - 2] + 4) / 2);
        $passTable = $this->SplitCols($passengersText, $passTableHead);

        if (preg_match("#(?:Varaustunnus|Lentoyhtiöviite)\s+([A-Z\d]{5,7}\b)#", $passTable[3], $m)) {
            $rl = $m[1];
        }

        if (preg_match_all("#^\s*(\d[\d., ]+)$#m", $passTable[count($passTable) - 1], $m)) {
            $total = 0;

            foreach ($m[1] as $value) {
                $total += $this->amount($value);
            }
        }
        $names = explode("\n", $passTable[1]);
        array_shift($names);
        $surnames = explode("\n", $passTable[2]);
        array_shift($surnames);

        if (!empty($names) && count($names) == count($surnames)) {
            foreach ($names as $key => $value) {
                $passengers[] = $names[$key] . ' ' . $surnames[$key];
            }
        }

        $segments = $this->split("#(?:^|\n)([ ]{0,10}\d+\.\d+\.\d+(?:[ ]*-[ ]*\d+\.\d+\.\d+)?[ ]{3,})#", $reservationText);

        foreach ($segments as $key => $stext) {
            $table = $this->SplitCols($stext);

            if (count($table) !== 3) {
                $this->its[] = [];

                return false;
            }
            // Air
            if (preg_match("#Kuljetus\s*([A-Z\d]{2})\s*(\d{1,5})\b#", $table[1], $m)) {
                $seg = [];
                // FlightNumber
                $seg['FlightNumber'] = $m[2];

                if (preg_match("#^\s*(.+?) (\d+:\d+)\s+(.+?) ([\d. ]+ )?(\d+:\d+)\s+#", $table[2], $m)) {
                    // DepName
                    $seg['DepName'] = trim($m[1]);
                    // DepCode
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    // DepDate
                    $seg['DepDate'] = strtotime(trim($table[0]) . ' ' . $m[2]);

                    // ArrName
                    $seg['ArrName'] = trim($m[3]);
                    // ArrCode
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    // ArrDate
                    if (!empty($m[4])) {
                        $seg['ArrDate'] = strtotime($m[4] . ' ' . $m[5]);
                    } else {
                        $seg['ArrDate'] = strtotime(trim($table[0]) . ' ' . $m[5]);
                    }
                }

                // AirlineName
                $seg['AirlineName'] = $m[1];

                $finded = false;

                foreach ($this->its as $key => $itG) {
                    if ($itG['Kind'] == 'T' && empty($it['TripCategory'])) {
                        $this->its[$key]['TripSegments'][] = $seg;
                        $finded = true;
                    }
                }

                if ($finded == false) {
                    $it = ['Kind' => 'T'];
                    // RecordLocator
                    if (!empty($rl)) {
                        $it['RecordLocator'] = $rl;
                    }
                    // TripNumber
                    if (!empty($tripNumber)) {
                        $it['TripNumber'] = $tripNumber;
                    }
                    // ConfirmationNumbers
                    // Passengers
                    if (!empty($passengers)) {
                        $it['Passengers'] = $passengers;
                    }

                    // AccountNumbers
                    // TripSegments
                    // Cancelled
                    // TotalCharge
                    // BaseFare
                    // Currency
                    // Tax
                    // Fees
                    // SpentAwards
                    // EarnedAwards
                    // Status
                    // ReservationDate
                    if (!empty($reservationDate)) {
                        $it['ReservationDate'] = $reservationDate;
                    }
                    // NoItineraries
                    // TripCategory
                    // TicketNumbers

                    $it['TripSegments'][] = $seg;
                    $this->its[] = $it;
                }

                continue;
            }

            // Hotel
            if (preg_match("#([\d\.]+)[ ]*-[ ]*([\d\.]+)#", $table[0], $mat)) {
                $it = ["Kind" => "R"];
                // ConfirmationNumber
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;

                // TripNumber
                if (!empty($tripNumber)) {
                    $it['TripNumber'] = $tripNumber;
                }

                // ConfirmationNumbers
                // HotelName
                if (preg_match("#^\s*(.+)#", $table[2], $m)) {
                    $it['HotelName'] = $m[1];
                }
                // 2ChainName
                // CheckInDate
                $it['CheckInDate'] = strtotime($mat[1]);
                // CheckOutDate
                $it['CheckOutDate'] = strtotime($mat[2]);
                // Address
                if (!empty($it['HotelName'])) {
                    $it['Address'] = $it['HotelName'] . ', ' . trim($table[1]);
                }
                // DetailedAddress
                // Phone
                // Fax
                // GuestNames
                if (!empty($passengers)) {
                    $it['GuestNames'] = $passengers;
                }
                // Guests
                if (preg_match("#Aikuinen[ ]*\([ ]*(\d+)[ ]*x#", $table[2], $seg)) {
                    $it['Guests'] = $m[1];
                }
                // Kids
                // Rooms
                // Rate
                // RateType
                // CancellationPolicy
                // RoomType
                if (preg_match("#^\s*.+\s+(.+)#", $table[2], $m)) {
                    $it['RoomType'] = $m[1];
                }
                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                // Currency
                if (preg_match("#\n[ ]*(\d[\d\,\. ]+)\s*$#", $table[2], $m)) {
                    $it['Total'] = $this->amount($m[1]);
                    $it['Currency'] = 'EUR';
                }

                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                // Cancelled
                // ReservationDate
                if (!empty($reservationDate)) {
                    $it['ReservationDate'] = $reservationDate;
                }
                // NoItineraries
                $this->its[] = $it;

                continue;
            }
        }

        if (!empty($total)) {
            $count = 0;

            foreach ($this->its as $key => $itG) {
                if ($itG['Kind'] == 'T' && empty($it['TripCategory'])) {
                    $Tkey = $key;
                    $count++;
                }
            }

            if ($count == 1) {
                $this->its[$Tkey]['TotalCharge'] = $total;
                $this->its[$Tkey]['Currency'] = 'EUR';
            }
        }
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
        $text = '';

        foreach ($pdfs as $pdf) {
            if (!empty($text .= \PDF::convertToText($parser->getAttachmentBody($pdf)))) {
                continue;
            }
        }

        if (stripos($text, $this->reBody) === false) {
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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $this->its = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->reBody2 as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
            $this->parsePdf();
        }

        $result = [
            'emailType'  => 'PaymentInformation' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->its,
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
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

    private function inOneRow($text)
    {
        $textRows = explode("\n", $text);
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                if (isset($row[$l]) && (trim($row[$l]) !== '')) {
                    $notspace = true;
                    $oneRow[$l] = $row[$l];
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
