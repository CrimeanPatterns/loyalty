<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ChangeOfReservationPdf extends \TAccountChecker
{
    public $mailFiles = "austrian/it-10333200.eml, austrian/it-10431021.eml, austrian/it-13495720.eml, austrian/it-13819299.eml, austrian/it-4053299.eml, austrian/it-4106402.eml, austrian/it-4487696.eml, austrian/it-7627223.eml, austrian/it-9865490.eml, austrian/it-9952958.eml";

    public $lang = "en";

    private $reFrom = "@austrian.com";
    private $reSubject = [
        "en"=> "Change of Reservation",
        "de"=> "Ihre Reisebestätigung",
    ];
    private $reBody = 'Austrian Airlines';
    private $reBody2 = [
        "en"=> ["Flight Dates", "Flight Details"],
        "de"=> ["Flugdaten"],
    ];
    private $pdfPattern = ".*\d+\.pdf";

    private static $dictionary = [
        "en" => [
            "Electronic Ticket Number"=> ["Electronic Ticket Number", "Electronic Ticket Number:", "ElectronicTicket Number"],
            "Name"                    => ["Name", "Name:", "Travel dates for"],
            "Booking Code"            => ["Booking Code", "Booking Code:", "Reservation code:", "Reservation code"],
            "FLIGHT"                  => ["FLIGHT", "Flight"],
            "DEPARTURE"               => ["DEPARTURE", "Departure"],
            "Class"                   => "Economy",
        ],
        "de" => [
            "Electronic Ticket Number"=> ['Ticket number / Ticketnummer', "Elektronische Ticket Nummer", "ElektronischeTicket Nummer:"],
            "Booking Code"            => ["Buchungscode", "Buchungscode:"],
            "Name"                    => ['Name / Name', "Name", "Name:"],
            "FLIGHT"                  => ["FLUG", "Flug"],
            "DEPARTURE"               => ["ABFLUG", "Abflug"],
            // "Terminal"=>"",
            "operated by:"=> "durchgeführt von:",
            "Class"       => "Economy",
        ],
    ];

    private $text;
    private $date = null;

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

    public function detectEmailByBody(PlancakeEmailParser $parser)
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

        foreach ($this->reBody2 as $reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($text, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return $email;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return $email;
        }

        foreach ($this->reBody2 as $lang=>$reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parsePdf($email);

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

    private function parsePdf(Email $email): ?Email
    {
        $text = $this->text;
        $email->setProviderCode('austrian');

        $f = $email->add()->flight();

        $tot = $this->cutText('Invoice amount/', 'Payment Details', $text);

        if (preg_match('/\b([A-Z]{3})\s+([\d\.]+)/', $tot, $m)) {
            $f->price()
                ->currency($m[1])
                ->total($m[2]);
        }

        if (preg_match_all("#" . $this->opt($this->t("Electronic Ticket Number")) . "\s+([\d- ]+)#", $text, $m)) {
            $f->issued()
                ->tickets(array_unique($m[1]), false);
        }

        $f->general()
            ->confirmation($this->re("#" . $this->opt($this->t("Booking Code")) . "\s+([A-Z\d]+)#", $text));

        if (preg_match_all("#" . $this->opt($this->t("Name")) . "\s+([A-Z\/ ]+)#", $text, $m)) {
            $f->general()
                ->travellers(preg_replace(["/ (\/\s*)?(DR )?(MR|MRS|MS|DR)$/", "/^\s*(.+?)\s*\/\s*(.+?)\s*$/"], ["", "$2 $1"], array_unique($m[1])), true);
        }

        if (preg_match_all("#Miles & More-Number\s+([A-Z\d]+)#", $text, $m)) {
            $f->program()->accounts(array_unique($m[1]), true);
        }

        $segments = $this->split("#^([^\n]\s{3,}\d+ [^\s\d]+ \d+\s*,)#ms", $this->re("#\n\s*" . $this->opt($this->t("FLIGHT")) . "\s+" . $this->opt($this->t("DEPARTURE")) . "[^\n]+\n+(.*?)\n\n\n#s", $text));

        foreach ($segments as $i=> $stext) {
            $s = $f->addSegment();
            $table = $this->re("#(.*?)(?:\n\n|$)#s", $stext);
            $table = $this->splitCols($table, $this->colsPos($table));

            if (count($table) != 7 && count($table) != 6) {
                $this->logger->info("incorrect parse table");

                return null;
            }

            $s->departure()
                ->noCode()
                ->name($this->re("#^(.+)#", trim($table[3])))
                ->date($this->normalizeDate(str_replace("\n", "", $this->re("#(.*?\d+:\d+(?: [ap]m)?)#is", $table[1]))));

            if ($terminal = $this->re("#" . $this->opt($this->t("Terminal")) . " (.+)#", $table[3])) {
                $s->departure()->terminal($terminal);
            }
            $s->arrival()
                ->noCode()
                ->name($this->re("#^(.+)#", trim($table[4])))
                ->date($this->normalizeDate(str_replace("\n", "", $this->re("#(.*?\d+:\d+(?: [ap]m)?)#is", $table[2]))));

            if ($terminal = $this->re("#" . $this->opt($this->t("Terminal")) . " (.+)#", $table[4])) {
                $s->arrival()->terminal($terminal);
            }
            $s->airline()
                ->name($this->re("#^([A-Z]{2})\d+#", trim($table[0])))
                ->number($this->re("#^[A-Z]{2}(\d+)#", trim($table[0])));

            if ($operator = $this->re("#" . $this->t("operated by:") . "\s+(.+)#", $stext)) {
                $s->airline()->operator(preg_replace('/(\s{2,}.+)/', '', $operator));
            }

            if ($bc = $this->re('/\b([A-Z])\s+\([A-Z]{2}\)/', $table[5])) {
                $s->extra()
                    ->bookingCode($bc);
            }

            $s->extra()
                ->cabin($this->re("#(" . $this->opt($this->t("Class")) . ")#i", $table[5]), true, true);

            if ($status = $this->re("#(" . $this->opt($this->t("confirmed")) . ")#", $table[5])) {
                $s->extra()->status($status);
            }
        }

        return $email;
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
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
            "#^(\d+) ([^\s\d]+) (\d{2}),(\d+:\d+)$#", //22 Mar 18,11:00
            "#^(\d+) ([^\s\d]+) (\d{2}),(\d+:\d+(?: [AP]M))$#", //22 Mar 18,11:00 AM
        ];
        $out = [
            "$1 $2 20$3, $4",
            "$1 $2 20$3, $4",
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

        return '(?:' . implode("|", array_map(function ($e) { return addcslashes($e, '/'); }, $field)) . ')';
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
