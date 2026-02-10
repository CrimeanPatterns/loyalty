<?php

namespace AwardWallet\Engine\ctmanagement\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class InvoicePdf2 extends \TAccountChecker
{
    public $mailFiles = "ctmanagement/it-10589465.eml, ctmanagement/it-10622481.eml, ctmanagement/it-10623529.eml, ctmanagement/it-10624014.eml, ctmanagement/it-10641600.eml, ctmanagement/it-10644783.eml, ctmanagement/it-10647238.eml, ctmanagement/it-10691677.eml, ctmanagement/it-58819668.eml";

    public $reFrom = "@cn.wtltravel.com";
    public $reSubject = [
        "en"=> ["Invoice_", "Ticket Updated -[#"],
    ];
    public $reBody = ['CTM Travel', 'Connexus Travel Beijing'];
    public $reBody2 = [
        "en"=> "Service",
    ];
    public $pdfPattern = ".*.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    public $text;

    public function parsePdf(Email $email)
    {
        $text = $this->text;

        $flight = $email->add()->flight();

        // Confirmation number
        // Travellers
        $flight->general()
            ->confirmation($this->re("#Booking Ref:\s*(.+)#", $text), 'Booking Ref')
            ->travellers(array_filter([$this->re("#Passenger:\s*(.*?)\s{2,}#", $text)]));

        // TicketNumbers
        if (preg_match_all("#\s{1,}(\d{3}-\d+): #", $text, $m)) {
            $flight->setTicketNumbers(array_filter($m[1]), false);
        }

        // AccountNumbers
        $accountNumber = array_filter([$this->re("#Frequent Flyer No:\s+(\w+)#", $text)]);

        if (!empty($accountNumber)) {
            $flight->ota()
                ->accounts($accountNumber, false);
        }

        // Price
        $flight->price()
            ->total($this->amount($this->re("#Total\([A-Z]{3}\):\s+([\d.,]+)#", $text)))
            ->currency($this->re("#Total\(([A-Z]{3})\):#", $text));

        //Cost
        $cost = $this->amount($this->re("#Per\s+Adult\s+Fare\([A-Z]{3}\):(?:\s+)?([\d.,]+)#", $text));

        if (!empty($cost)) {
            $flight->price()
                ->cost($cost);
        }

        // Tax
        $tax = $this->amount($this->re("#Tax\:\s+([\d.,]+)#", $text));

        if (!empty($tax)) {
            $flight->price()
                ->tax($tax);
        }

        $fee = $this->amount($this->re("#Handling\s+Fee\:\s+([\d.,]+)#", $text));

        if (!empty($fee)) {
            $flight->price()
                ->fee('Handling Fee', $fee);
        }

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $segments = $this->split("#\n([^\n\S]*[A-Z ]+\n[^\n\S]*[A-Z\d]{2}\d+\s{2,})#", $this->re("#Service\s+From[^\n]+\n(.*?)Identity No:#ms", $text));

        foreach ($segments as $stext) {
            $table = $this->re("#(.*?)\n\n\n#ms", $stext);
            $table = $this->splitCols($table, $this->ColsPos($table));

            if (count($table) != 4) {
                $this->logger->info("incorrect parse table");
                //return;
            }

            $s = $flight->addSegment();

            if (preg_match("#\n(?<AirlineName>[A-Z\d]{2})(?<FlightNumber>\d+)\n(?<BookingClass>[A-Z]) (?<Cabin>.+)#", $table[0], $m)) {
                // FlightNumber
                // AirlineName
                $s->airline()
                    ->number($m['FlightNumber'])
                    ->name($m['AirlineName']);

                // Cabin
                // BookingCode
                $s->extra()
                    ->cabin($m['Cabin'])
                    ->bookingCode($m['BookingClass']);
            }

            if (preg_match("#(?<Name>[A-Z]{2}[^\n]+)\n\((?<Code>[A-Z]{3})\)\s+(?<Date>\d+[^\s\d]+\d+ \d+:\d+)#ms", $table[1], $m)) {
                // Departure
                $s->departure()
                    ->code($m['Code'])
                    ->name($m['Name'])
                    ->date(strtotime($this->normalizeDate($m['Date'])));

                $depTerminal = $this->re("#Departure Gate At Terminal (\w+)#", $stext);

                if (!empty($depTerminal)) {
                    $s->departure()
                        ->terminal($depTerminal);
                }
            }

            if (empty($s->getDepCode())) {
                if (preg_match("#(?<Name>\D+)\s\(\D+\n\s+(?<Date>\d+[^\s\d]+\d+ \d+:\d+)#ms", $table[1], $m)) {
                    // Departure
                    $s->departure()
                        ->name($m['Name'])
                        ->noCode()
                        ->date(strtotime($this->normalizeDate($m['Date'])));

                    $depTerminal = $this->re("#Departure Gate At Terminal (\w+)#", $stext);

                    if (!empty($depTerminal)) {
                        $s->departure()
                            ->terminal($depTerminal);
                    }
                }
            }

            if (preg_match("#(?<Name>[A-Z]{2}[^\n]+)\n\((?<Code>[A-Z]{3})\)\s+(?<Date>\d+[^\s\d]+\d+ \d+:\d+)#ms", $table[2], $m)) {
                // Arrival
                $s->arrival()
                    ->code($m['Code'])
                    ->name($m['Name'])
                    ->date(strtotime($this->normalizeDate($m['Date'])));

                $arrTerminal = $this->re("#Arrival Gate At Terminal (\w+)#", $stext);

                if (!empty($arrTerminal)) {
                    $s->arrival()
                        ->terminal($arrTerminal);
                }
            }

            if (empty($s->getArrCode())) {
                if (preg_match("#(?<Name>\D+)\s\(\D+\n\s+(?<Date>\d+[^\s\d]+\d+ \d+:\d+)#ms", $table[2], $m)) {
                    // Arrival
                    $s->arrival()
                        ->name($m['Name'])
                        ->noCode()
                        ->date(strtotime($this->normalizeDate($m['Date'])));

                    $arrTerminal = $this->re("#Arrival Gate At Terminal (\w+)#", $stext);

                    if (!empty($arrTerminal)) {
                        $s->arrival()
                            ->terminal($arrTerminal);
                    }
                }
            }

            // Operator
            // Aircraft
            $aircraft = $this->re("#Equipment:[^\n\S]*([^\n]+)#", $table[3]);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            // TraveledMiles
            // AwardMiles
            // PendingUpgradeTo
            // Seats
            // Duration
            $duration = $this->re("#Equipment:[^\n]*\n(.+)#", $table[3]);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }
        }

        return true;
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

        foreach ($this->reSubject as $lang => $reSubject) {
            foreach ($reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
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

        if (strpos($text, $this->reBody[0]) === false && strpos($text, $this->reBody[1]) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        //$itineraries = array();

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

        $this->parsePdf($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
            "#^(\d+)([^\s\d]+)(\d{2}) (\d+:\d+)$#", //04Jan18 10:15
        ];
        $out = [
            "$1 $2 $3, $4",
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
