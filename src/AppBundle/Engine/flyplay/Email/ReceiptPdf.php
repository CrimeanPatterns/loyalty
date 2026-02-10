<?php

namespace AwardWallet\Engine\flyplay\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            //            'Confirmation' => 'Confirmation',
        ],
    ];

    private $detectFrom = "noreply@flyplay.com";
    private $detectSubject = [
        // en
        '[PLAY] Receipt - Booking Ref:',
    ];
    private $detectBody = [
        'en' => [
            'Receipt Number',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]flyplay\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], '[PLAY]') === false
        ) {
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

    public function detectPdf($text)
    {
        // detect provider
        if ($this->containsText($text, ['Thank you for flying with PLAY.']) === false) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->containsText($text, $detectBody) === true) {
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

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $conf = $this->re("/\n\s*{$this->opt($this->t('Booking number'))} +([A-Z\d]{5,7})\n/", $textPdf);

        foreach ($email->getItineraries() as $it) {
            if (in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                $f = $it;
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->confirmation($conf);
        }

        // General
        $f->general()
            ->traveller(preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", "$2 $1",
                $this->re("/\n(.+?) *\[.*\]\s*\n\s*{$this->opt($this->t('Booking number'))}/", $textPdf)));

        // Segments
        $segments = $this->split("/((?:\n *[-+]\d)?\n +\d{1,2}:\d+.*(?:\n+.*){0,3}\d{1,2}:\d+)/", $textPdf);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $tableText = $this->re('/^([\s\S]+?)\n.+\([A-Z]{1,2}\)/', $sText);
            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

            if (count($table) !== 3) {
                continue;
            }
            $date = null;

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s+(?<date>.+)\s*$/", $table[1], $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $date = $m['date'];
            }
            $re = "/^\s*(?<overnight>[-+]\d\n)?\s*(?<time>\d+:\d+.*)\n\s*(?<name>[\s\S]+?)\s+(?<code>[A-Z]{3})\s*$/";

            if (preg_match($re, $table[0], $m)) {
                if (preg_match("/^(.+)([-+]\d)\s*$/", $m['time'], $mt)) {
                    $m['time'] = $mt[1];
                    $m['overnight'] = $mt[2];
                }
                $m = preg_replace('/\s+/', ' ', $m);
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($date . ', ' . $m['time']))
                ;

                if (!empty($m['overnight']) && !empty($s->getDepDate())) {
                    $s->departure()
                        ->date(strtotime(trim($m['overnight']) . ' day', $s->getDepDate()));
                }
            }

            if (preg_match($re, $table[2], $m)) {
                if (preg_match("/^(.+)([-+]\d)\s*$/", $m['time'], $mt)) {
                    $m['time'] = $mt[1];
                    $m['overnight'] = $mt[2];
                }
                $m = preg_replace('/\s+/', ' ', $m);
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($date . ', ' . $m['time']))
                ;

                if (!empty($m['overnight']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime(trim($m['overnight']) . ' day', $s->getArrDate()));
                }
            }

            $segments = $f->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($segment->toArray(),
                            ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                        if (!empty($s->getSeats())) {
                            $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                                $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }

        // Price
        if (preg_match("/ {5,}{$this->opt($this->t('Total:'))} *(?<currency>[A-Z]{3}) ?(?<amount>\d[^[:alpha:]\n]*)\n/", $textPdf, $m)) {
            if ($f->getPrice()) {
                $f->price()
                    ->total($f->getPrice()->getTotal() ?? 0.0 + PriceHelper::parse($m['amount'], $m['currency']));
            } else {
                $f->price()
                    ->total(PriceHelper::parse($m['amount'], $m['currency']))
                    ->currency($m['currency'])
                ;
            }
        } else {
            $f->price()
                ->total(null)
            ;
        }

        return $email;
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

    private function createTable(?string $text, $pos = []): array
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

    private function rowColumnPositions(?string $row): array
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
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

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date)
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            // 28-05-2023 Sunday, 06:00
            '/^\s*(\d{1,2})-(\d{2})-(\d{4})\s+[[:alpha:]]+\s*,\s*(\d{1,2}:\d{2}(?:[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1.$2.$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date end = ' . print_r($date, true));

        if (preg_match("/^\s*\d{1,2}\.\d{2}\.\d{4},\s*\d{1,2}:\d{2}(?:[ap]m)?\s*$/", $date)) {
            return strtotime($date);
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
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

    private function striposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
