<?php

namespace AwardWallet\Engine\campustr\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "campustr/it-28599020.eml, campustr/it-28781851.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = '@campustravel.com.au';
    private $detectSubject = [
        'en' => '​International SOS Itinerary Forwarding Service - Traveller Action Not Required',
    ];
    private $detectCompany = 'Campus Travel';
    private $detectBody = [
        "en" => ['FLIGHT DETAILS', 'Departure - Destination'],
    ];
    private $pdfPattern = '.+\.pdf';
    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (stripos($text, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        $this->lang = $lang;
                        $this->parseEmail($email, $text);

                        continue 3;
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseEmail(Email $email, string $text)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->re("#Booking Number:[ ]*(\d{5,})\s+#", $text), "Booking Number")
            ->phone($this->re("#Campus Travel Groups (?:Team|Contact) Number:[ ]*([\d\+\-\(\) ]{5,})\s*\n#", $text), 'Campus Travel Groups Team Number')
        ;

        if (preg_match("#\n\s*(?<desc>Campus Travel Assist for after hours.*)\s+t\.[ ]*(?<tel1>[\d\+\- ]{5,}) (?<name1>.+?) \| t\.[ ]*(?<tel2>[\d\+\- ]{5,}) (?<name2>.+)#", $text, $m)) {
            $email->ota()
                ->phone($m['tel1'], trim($m['desc']) . ' ' . trim($m['name1']))
                ->phone($m['tel2'], trim($m['desc']) . ' ' . trim($m['name2']))
            ;
        }

        /*
         * FLIGHT
         */

        $f = $email->add()->flight();

        // General
        $conf = explode(",", $this->re("#\n\s*Airline reference:[ ]*([A-Z\d]{5,}(?:,[ ]{0,1}[A-Z\d]{5,})*)\s+#", $text));

        foreach ($conf as $value) {
            $f->general()->confirmation(trim($value), "Airline reference");
        }

        if (preg_match("#\n\s*Title[ ]*First name[ ]*Last name[ ]*Ticket number\s+([\s\S]+?)\n\n#", $text, $m)) {
            $passengers = array_filter(explode("\n", $m[1]));

            foreach ($passengers as $value) {
                if (preg_match("#^\s*(?<name>(?:[A-Z\-\. ]+?[ ]{2,}){1,3})(?<ticket>[\d\-]{9,})(?: \(|$)#", $value, $mat)) {
                    $f->general()->traveller(preg_replace("#\s+#", ' ', $mat['name']), true);
                    $f->issued()->ticket($mat['ticket'], false);
                }
            }
        }

        // Segments
        $segmentsText = $this->re("#\n[ ]*Airline[ ]+Flight(?: no\.)?[ ]+Date[ ]+.+\s*\n([\s\S]+?)\n\n#", $text);

        if (empty($segmentsText)) {
            $this->logger->debug("segments not found");

            return $email;
        }
        $headPos = $this->TableHeadPos($this->re("#\n([ ]*Airline[ ]+Flight( no\.)?[ ]+Date[ ]+.+)#", $text));

        $segments = $this->split("#((?:^|\n)[ ]{0,10}(?:.*\n)?.+ \d{1,2}:\d{1,2}[ ]*-[ ]*\d{1,2}:\d{1,2}.*\n.+)#", $segmentsText);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            $table = $this->SplitCols($stext, $headPos);

            if (count($table) !== 6) {
                $this->logger->info("error in parsing table $stext");

                return $email;
            }

            // Airline
            if (preg_match("#^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\b#", $table[1], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("#Operated By\s*(.+?)(?:Arrives\s+next\s+day|$)#", $table[5], $m)) {
                $s->airline()
                    ->operator(preg_replace("#\s+#", ' ', $m[1]));
            }

            $date = $this->normalizeDate($table[2]);

            if (empty($date)) {
                $this->logger->debug("date not detected");

                return $email;
            }

            // Departure
            // Arrival
            if (preg_match("#(.+)\s+-\s+(.+)#s", $table[3], $m)) {
                $s->departure()
                    ->noCode()
                    ->name(preg_replace("#\s+#", ' ', $m[1]))
                ;
                $s->arrival()
                    ->noCode()
                    ->name(preg_replace("#\s+#", ' ', $m[2]))
                ;
            }

            if (preg_match("#^\s*(\d+:\d+)\s+-\s+(\d+:\d+)\s*$#s", $table[4], $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date))
                ;
                $s->arrival()
                    ->date(strtotime($m[2], $date))
                ;

                if (!empty($s->getArrDate()) && preg_match("#Arrives\s+next\s+day#", $table[5], $m)) {
                    $s->arrival()
                        ->date(strtotime("+1 day", $s->getArrDate()));
                }
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($textPdf, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
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
            "#^\s*[^\d\s]+\s+(\d{1,2})[^\d\s]+\s+([^\d\s]+)\s+(\d{4})\s*$#", // Friday 7th December 2018
        ];
        $out = [
            "$1 $2 $3",
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

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
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
