<?php

namespace AwardWallet\Engine\amtrav\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class InvoicePdf extends \TAccountChecker
{
    public $mailFiles = "amtrav/it-213520441.eml";

    private $detectFrom = "Support@AmTrav.com";
    private $detectSubject = [
        // en
        'AmTrav for Business Travelers Invoice #'
    ];

    private $detectBody = [
        'en' => [
            'Invoice #',
        ],
    ];

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
//            'Confirmation' => 'Confirmation',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            if ($this->detectPdf($text) == true) {
                return true;
            }
        }
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
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

    private function parseEmailPdf(Email $email, ?string $text = null)
    {
        // Travel Agency
        $email->obtainTravelAgency();

        $email->ota()
            ->confirmation($this->re("/\n *Booking # *(\d{5,})(?: {5,}.*)?\n/", $text));

        // FLIGHT
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->res("/\n *Passenger: *(\S.+?) {2,}/", $text), true);

        // Issued
        $tickets = $this->res("/\s+Ticket No\. {0,10}(\d+)(?: {2,}.*)\n/", $text);
        if (!empty($tickets)) {
            $f->issued()
                ->tickets(array_unique($tickets), true);
        }

        $flightsText = $this->re("/\n\s*AIRLINE +DATE +.+(\n(?:.*\n)+?) *(?:Subtotal|COMMENTS)/", $text);

        $flights = $this->split("/^( *(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) *\# *\d{1,5} +)/m", trim($flightsText));

        foreach ($flights as $sText) {
            $s = $f->addSegment();

            $regexp = "/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) *\# *(?<fn>\d{1,5}) +(?<date>[\d\/]{6,}) {2,}(?<dName>\S.+?) *→ *(?<aName>\S.+?) {2,}(?<dTime>\d{1,2}:\d{2}(?: *[apAP][mM])?) {2,}(?<aTime>\d{1,2}:\d{2}(?: *[apAP][mM])?)(?<overnight>[-+]\d)? {2,}(?<cabin>.+)/";
            if (preg_match($regexp, $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                $s->departure()
                    ->noCode()
                    ->name($m['dName'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['dTime']));

                $s->arrival()
                    ->noCode()
                    ->name($m['aName'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['aTime']));

                if (!empty($m['overnight']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($m['overnight'] . ' day', $s->getArrDate()));
                }

                $s->extra()
                    ->cabin($m['cabin']);
            }
        }

        // Price
        $total = $this->re("/\s+Subtotal +(.+)\n/", $text);
        if (preg_match("/^\s*[\-]/u", $total, $m)) {

        } elseif (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/u", $total, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*\s*$/u", $total, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['amount'], $currency))
            ;
        }
        return $email;
    }

    public function detectPdf($text)
    {
        if ($this->containsText($text, ['AmTrav']) === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->containsText($text, $detectBody) !== false) {
                return true;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);
        if (isset($m[$c])) {
            return $m[$c];
        }
        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }
        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }
        return false;
    }

    // additional methods

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);
        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }
            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);
        return $pos;
    }


    private function createTable(?string $text, $pos = []) : array
    {
        $cols = [];
        $rows = explode("\n", $text);
        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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


    private function rowColumnPositions(?string $row) : array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;
        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }
        return $pos;
    }


    private function inOneRow($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);
        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }
            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);
        return $pos;
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


    private function striposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array)$arrayNeedle;
        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            // 10/13/22, 7:38 pm
            '#^\s*(\d{2})\\/(\d{2})\\/(\d{2})\s*,\s+(\d+:\d+(?:\s*[AP]M)?)\s*$#i',
        ];
        $out = [
            "$2.$1.20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'USD' => ['$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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
}