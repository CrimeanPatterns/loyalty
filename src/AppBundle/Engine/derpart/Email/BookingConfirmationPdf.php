<?php

namespace AwardWallet\Engine\derpart\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmationPdf extends \TAccountChecker
{
    // the same as derpart/BookingConfirmation (parse html)
    public $mailFiles = "derpart/it-136514711.eml, derpart/it-136517383.eml";

    private $detectFrom = "@derpart.com";
    private $detectSubject = [
        // de
        'DERPART Buchungsbestätigung', // DERPART Buchungsbestätigung NAETSCHER/CHRISTINA MRS - 11.03.2022 - W9WY5I
    ];
    private $detectBody = [
        'de' => [
            'Bitte überprüfen Sie die Buchungsbestätigung umgehend auf die Korrektheit und Vollständigkeit der Daten und Termine',
        ],
    ];

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'de' => [
            "Flug" => 'Flug',
            "Von" => 'Von',
            "Abflug" => 'Abflug',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers["subject"], 'Derpart') === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if (stripos($text, '@derpart.com') === false) {
                    continue;
                }
                if ($this->AssignLang($text)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if ($this->AssignLang($text)) {
                    $this->parsePdf($email, $text);
                    $this->logger->debug('$text = ' . print_r($text, true));
                }
            }
        }

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

    private function parsePdf(Email $email, $text)
    {
//        $this->logger->debug('$text = ' . print_r($text, true));

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("/".$this->opt($this->t("Buchungscode Reisebüro:"))."\s*([A-Z\d]{5,7})\s*\n/u", $text))
        ;

        $travellersText = $this->re("/\n\s*".$this->opt($this->t("Reisender / Reisende"))." {2,}".$this->opt($this->t("Ticketnummer")).".*\s*(\n *\S[\s\S]+?)\n\n/u", $text);
        $rows = $this->split("/\n( {0,10\S+})/", $travellersText);
        foreach ($rows as $row) {
            $table = $this->SplitCols($row, $this->ColsPos($this->inOneRow($row)));
            if (isset($table[0])) {
                $table[0] = preg_replace(["/^\s*(.+?)\s*\/\s*(.+?)(\s+Mrs|Mr)?\s*$/s", "/\s+/"], ['$2 $1', ' '], trim($table[0]));
                $f->general()
                    ->traveller($table[0], true);
            }
            if (isset($table[1])) {
                $this->logger->debug('$table[1] = ' . "\n" . print_r($table[1], true));
                $f->issued()
                    ->tickets(preg_split("/\s*,\s*/", trim($table[1])), false);
            }
            if (isset($table[2])) {
                $f->program()
                    ->accounts(preg_split("/\s*,\s*/", trim($table[2])), false);
            }
        }



        // Segments
        $segments = $this->split("/\n( *{$this->opt($this->t('Flug'))} {2,}{$this->opt($this->t('Von'))} {2,}{$this->opt($this->t('Abflug'))}\s*\n)/", $text);
//        $this->logger->debug('$segments = ' . print_r($segments, true));
        foreach ($segments as $stext) {

            $s = $f->addSegment();

            // Departure
            $dep = $this->re("/\s*.+ {2,}{$this->opt($this->t('Abflug'))}\s*\n([\s\S]+?)\n.* {2,}{$this->opt($this->t('Nach'))} {2,}/u", $stext);
            $table = $this->SplitCols($dep, $this->ColsPos($this->inOneRow($dep)));

            // Airline
            if (preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d]) *(\d{1,5})\s+/", $table[0] ?? '', $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $confirmation = $this->re("/\n *".$this->opt($this->t("Airline ref."))." *([A-Z\d]{5,7})\s+/", $stext);
            if (!in_array($confirmation, array_column($f->getConfirmationNumbers(), 0))) {
                $s->airline()
                    ->confirmation($confirmation);
            }

            if (preg_match("/^\s*(.+?)\s*\(([A-Z]{3})\)(\s+\S.*)?\s*$/", $table[1] ?? '', $m)) {
                $s->departure()
                    ->code($m[2])
                    ->name($m[1])
                ;
                if (!empty($m[3])) {
                    $s->departure()->terminal(preg_replace("/\s*\bterminal\b\s*/iu", '', $m[3]));
                }
            }
            $s->departure()
                ->date($this->normalizeDate($table[2] ?? null));

            // Arrival
            $arr = $this->re("/\s*.+ {2,}{$this->opt($this->t('Nach'))} {2,}.*\s*\n([\s\S]+?)\n.* {$this->opt($this->t('Airline ref.'))} +/u", $stext);
            $table = $this->SplitCols($arr, $this->ColsPos($this->inOneRow($arr)));
            if (preg_match("/^\s*(.+?)\s*\(([A-Z]{3})\)(\s+\S.*)?\s*$/", $table[1] ?? '', $m)) {
                $s->arrival()
                    ->code($m[2])
                    ->name($m[1])
                ;
                if (!empty($m[3])) {
                    $s->arrival()->terminal(preg_replace("/\s*\bterminal\b\s*/iu", '', $m[3]));
                }
            }
            $s->arrival()
                ->date($this->normalizeDate($this->re("/(.+?)\s*".$this->opt($this->t("Dauer:"))."/", $table[2] ?? null)));
            $s->extra()
                ->duration($this->re("/".$this->opt($this->t("Dauer:"))."\s*(.+)/", $table[2] ?? null));

            $operator = '';
            if (preg_match("/\s+{$this->opt($this->t('Durchgeführt von'))}\s*.+{$this->opt($this->t('Ankunft'))} {2,}(.+)/", $stext, $m)) {
                $operator = $m[1];
                if (!empty($table[3])) {
                    $operator .= "\n".$table[3];
                }
            }
            $this->logger->debug('$operator = ' . "\n" . print_r($operator, true));
            if (preg_match("/^\s*(.+?)(\s+(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) *(?<fn>\d{1,5}))?\s*$/s", $operator, $m)) {
                $s->airline()
                    ->operator(preg_replace("/\s+/", ' ', $m[1]))
                ;
                if (!empty($m['al'])) {
                    $s->airline()
                        ->carrierName($m['al'])
                        ->carrierNumber($m['fn'])
                    ;
                }
            }

            // Extra
            $s->extra()
                ->aircraft($this->re("/\s+".$this->opt($this->t("Fluggerät"))." +(.+)/", $stext))
                ->cabin($this->re("/\s+".$this->opt($this->t("Buchungsklasse"))." +(.+?) *\([A-Z]{1,2}\)/", $stext))
                ->bookingCode($this->re("/\s+".$this->opt($this->t("Buchungsklasse"))." +.+? *\(([A-Z]{1,2})\)/", $stext))
                ->meal($this->re("/\s+".$this->opt($this->t("An Bord"))." +(.+)/", $stext))
            ;
        }
        return true;
    }


    private function assignLang($text)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Flug"], $dict["Von"], $dict["Abflug"])) {
                if ($this->striposAll($text, $dict["Flug"]) !== false && $this->striposAll($text, $dict["Von"]) !== false ) {
                    $pos = $this->striposAll($text, $dict["Abflug"]);
                    if ($pos === false) {
                        return false;
                    }
                    $substr = substr($text, ($pos > 100)? $pos-100 : 0, 500);
                    if (preg_match("/\n *{$this->opt($dict['Flug'])} {2,}{$this->opt($dict['Von'])} {2,}{$this->opt($dict['Abflug'])}\s*\n/", $substr)) {
                        $this->lang = $lang;
                        return true;
                    }
                }
            }
        }
        return false;
    }



    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }
        return self::$dictionary[$this->lang][$s];
    }

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // Mi., 02. Feb. 2022 07:40
            '/^\s*[\w\-]+[,.\s]+(\d{1,2})[.]?\s+(\w+)[.]?\s+(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $date = $m[1] . $en . $m[3];
            }
        }

        return strtotime($date);
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }

    private function striposAll($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }
        if (is_array($needle)) {
            foreach ($needle as $n) {
                $v = stripos($text, $n);
                if ( $v !== false) {
                    return $v;
                }
            }
        } elseif (is_string($needle)) {
            return stripos($text, $needle);
        }
        return false;
    }


    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
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

    private function colsPos($table, $correct = 5)
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

    private function splitCols($text, $pos = false)
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

}