<?php

namespace AwardWallet\Engine\atpi\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Invoice extends \TAccountChecker
{
    public $mailFiles = "atpi/it-13004386.eml, atpi/it-13004392.eml";

    private $reFrom = "@atpi.com";
    private $reSubject = [
        "en"  => "ATPI invoice",
        "en2" => "ATPI credit note",
    ];
    private $reBody = 'ATPI';
    private $reBody2 = [
        "en"  => "RECEIPT DATE",
        "en2" => "CREDIT NOTE RECEIPT",
    ];
    private $pdfPattern = ".+\.pdf";

    private static $dictionary = [
        "en" => [],
    ];

    private $lang = "en";

    private $text;

    public function flight(Email $email)
    {
        $text = $this->text;

        $f = $email->add()->flight();

        // General
        $f->general()->confirmation($this->re("#\s+Reference[ ]+:[ ]*([A-Z\d]{5,7})\s+#", $text), 'Reference');

        $travellers = explode(",", trim($this->re("#\s+Passenger\(s\)[ ]+:[ ]*(.+)\n#", $text))); //no examples for 2 or more passengers
        $f->general()->travellers($travellers);

        // Issued
        $tickets = explode(",", trim($this->re("#\s+Ticket no\.[ ]+:[ ]*(.+)\n#", $text))); //no examples for 2 or more passengers
        $f->issued()->tickets($tickets, false);

        // Price
        if (preg_match("#\n\s*Excluding VAT[ ]+\-?(\d[\d,. ]+)[ ]*([A-Z]{3})\s*\n#", $text, $m)) {
            $f->price()
                ->cost($this->amount($m[1]))
                ->currency($m[2]);
        }

        if (preg_match("#\n\s*VAT[ ]+-?(\d[\d,. ]+)[ ]*([A-Z]{3})\s*\n#", $text, $m)) {
            $f->price()
                ->tax($this->amount($m[1]))
                ->currency($m[2]);
        }

        if (preg_match("#\n\s*Total[ ]+\-?(\d[\d,. ]+)[ ]*([A-Z]{3})\s*\n#", $text, $m)) {
            $f->price()
                ->total($this->amount($m[1]))
                ->currency($m[2]);
        }

        $pos = stripos($text, 'Air ticket');

        if (!empty($pos)) {
            $segmentsText = substr($text, $pos);
        } else {
            return $email;
        }
        preg_match_all("#^(\s*[A-Z\d]{2}\s*\d+.*\d+:\d+.+)#m", $segmentsText, $matches);

        if (empty($matches[0])) {
            return $email;
        }

        foreach ($matches[0] as $stext) {
            $s = $f->addSegment();

            if (preg_match("#^\s*(?<al>[A-Z\d]{2})\s*(?<fn>\d{1,5})\s+(?<date>\d+/\d+/\d+)\s+(?<dName>.+)\s{2,}-\s*(?<aName>.+)\s+(?<dTime>\d+:\d+)\s*-\s*(?<aTime>\d+:\d+)#", $stext, $m)) {
                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                // Departure
                $s->departure()
                    ->noCode()
                    ->name($m['dName'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['dTime']));

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($m['aName'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['aTime']));
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		if (strpos($headers["from"], $this->reFrom)===false)
        //			return false;

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
        $text = '';

        foreach ($pdfs as $pdf) {
            if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return false;
            }
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

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return $email;
        }

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            foreach ($this->reBody2 as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }
            $this->flight($email);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $email->ota()->code('atpi');

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
        $in = [
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s+(\d{1,2}:\d{1,2})\s*$#", // 08/01/2018 18:30
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
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
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }
}
