<?php

namespace AwardWallet\Engine\fbusiness\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class ElectronicTicketReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "fbusiness/it-12103341.eml";

    public $reFrom = "first-business-travel.de";
    public $reSubject = [
        "en"=> "Electronic Ticket Receipt für",
    ];
    public $reBody = 'first-business-travel.de';
    public $reBody2 = [
        "en"=> "Please print this receipt",
    ];
    public $pdfPattern = "\d{8}_[A-Z\d_]+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        if ($date = $this->re("#Datum: (\d+\.\d+\.\d{4})#", $text)) {
            $this->date = strtotime($date);
        }

        preg_match_all("#\b([A-Z\d]{6})/([A-Z\d]{2})\b#", $text, $ms, PREG_SET_ORDER);
        $rls = [];

        foreach ($ms as $m) {
            $rls[$m[2]] = $m[1];
        }

        preg_match_all("#^([^\n\S]*Flight\s+Date.*?)\n\n#ms", $text, $segments);
        $airs = [];

        foreach ($segments[1] as $stext) {
            $table = $this->re("#(.*?)\n\* operated#s", $stext);
            $table = $this->splitCols($table);

            if (count($table) != 8) {
                $this->logger->info("incorrect parse table");

                return;
            }

            if (!$airline = $this->re("#\n([A-Z\d]{2}) \d+(\n|\*| )#", $table[0])) {
                $this->logger->info("airline not matched");

                return;
            }

            if (isset($rls[$airline])) {
                $airs[$rls[$airline]][] = [$table, $stext];
            } elseif ($rl = $this->re("#Booking reference/\s+([A-Z\d]{6})\n#", $text)) {
                $airs[$rl][] = [$table, $stext];
            } else {
                $this->logger->info("rl not not found {$airline}");

                return;
            }
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = [$this->re("#Passenger/\s+(.*?)\s{2,}#", $text)];

            // TicketNumbers
            $it['TicketNumbers'] = [$this->re("#Ticket number/\s+([\d-]+)#", $text)];

            // AccountNumbers
            // Cancelled
            if (count($airs) == 1) {
                $tot = $this->re("#Grand Total/Gesamtsumme:\s+(.+)#", $text);
                // TotalCharge
                $it['TotalCharge'] = $this->amount($tot);

                // BaseFare
                // Currency
                $it['Currency'] = $this->currency($tot);
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            $it['ReservationDate'] = $this->normalizeDate($this->re("#Date of issue/\s+(.+)#", $text));

            // NoItineraries
            // TripCategory

            foreach ($segments as $data) {
                $table = $data[0];
                $stext = $data[1];
                $date = $this->normalizeDate($this->re("#\n[A-Z\d]{2} \d+\*?\s+(.*?)\s{2,}#", $stext));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\n[A-Z\d]{2} (\d+)(\n|\*| )#", $table[0]);

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = str_replace("\n", " ", $this->re("#von\n([^\n]+\n[^\n]+)#", $table[2]));

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#TERMINAL\s+(\w+)#", $table[2]);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->re("#Abflug\n(.+)#", $table[3]), $date);

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = str_replace("\n", " ", $this->re("#nach\n([^\n]+\n[^\n]+)#", $table[4]));

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#TERMINAL\s+(\w+)#", $table[4]);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->re("#Ankunft\n(.+)#", $table[5]), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#\n([A-Z\d]{2}) \d+(\n|\*| )#", $table[0]);

                // Operator
                $itsegment['Operator'] = $this->re("#durchgeführt von:\s+(.*?)\s{2,}#", $stext);

                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                $itsegment['BookingClass'] = $this->re("#Klasse\n(.+)#", $table[7]);

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
        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);
        $this->logger->info('Relative date: ' . date('r', $this->date));

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

        $this->parsePdf($itineraries);
        $tot = $this->re("#Grand Total/Gesamtsumme:\s+(.+)#", $this->text);
        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => [
                    "Amount"   => $this->amount($tot),
                    "Currency" => $this->currency($tot),
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

    private function normalizeDate($instr)
    {
        $this->http->log($instr);
        $in = [
            "#^(\d+)([^\s\d]+)$#", //30JAN
            "#^(\d+)([^\s\d]+)(\d{2})$#", //29JAN16
        ];
        $out = [
            "$1 $2 %Y%",
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $this->date, true, $str);
        }

        return strtotime($str);
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
}
