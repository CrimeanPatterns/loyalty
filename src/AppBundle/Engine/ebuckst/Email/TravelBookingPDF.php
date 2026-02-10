<?php

namespace AwardWallet\Engine\ebuckst\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelBookingPDF extends \TAccountChecker
{
    public $mailFiles = "ebuckst/it-236504567.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            //            'eBucks Ref:' => ['eBucks Ref:', 'eBucks reference:'],
        ],
    ];

    private $detectSubject = [
        // en
        'Travel Booking Confirmation',
    ];

    private $detectCompany = [
        '@ebucks.com',
    ];
    private $detectBody = [
        'en' => [
            'Your Travel Itinerary',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'no-reply@ebucks.com') !== false;
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

            if ($this->detectPdf($text)) {
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

            if ($this->detectPdf($text)) {
                $this->parsePdf($email, $text);
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

    public function detectPdf($text)
    {
        if ($this->containsText($text, $this->detectCompany) !== true) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->containsText($text, $detectBody) === true) {
                return true;
            }
        }

        return false;
    }

    private function parsePdf(Email $email, ?string $textPdf = null)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->re("/\n\s*{$this->opt($this->t("Agency Reference Number:"))} *([\dA-Z]{5,})\n/", $textPdf));

        // Flight
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $travellersText = $this->re("/\n *{$this->opt($this->t("Travellers"))} {3,}.+\n+(( *\*.+\n+)+)\n\s*\n/", $textPdf);

        if (preg_match_all("/^ *\*(.+?)(?:\(| {3,}|$)/m", $travellersText, $m)) {
            $m[1] = preg_replace("/ ?(MR|MISS|MRS)$/", '', $m[1]);
            $m[1] = preg_replace("/^\s*(.+?)\s*\\/\s*(.+?)\s*$/", '$2 $1', $m[1]);
            $f->general()
                ->travellers($m[1]);
        }
        // Issued
        if (preg_match_all("/^ {0,10}\*.+ {3,}(\d{13})(?:\(Electronic| {3,}|$)/m", $textPdf, $m)) {
            $f->issued()
                ->tickets(array_unique($m[1]), false);
        }

        $segments = $this->split("/\n(.*\S.+\n+ {0,10}Flight +)/", $textPdf);
//        $this->logger->debug('$segments = '.print_r( $segments,true));
        foreach ($segments as $sText) {
            $s = $f->addSegment();
            // Airline
            if (preg_match("/\n.*Flight +(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<fn>\d{1,5})(?: - | {3}|\n)/", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            if (preg_match("/\n *Confirmation Number For .* {3,}(?<conf>[A-Z\d]{5,7})\n/", $sText, $m)) {
                $s->airline()
                    ->confirmation($m['conf'])
                ;
            }

            if (preg_match("/, Operated By (.+?) {3,}/", $sText, $m)) {
                $s->airline()
                    ->operator($m[1])
                ;
            }

            $date = $this->re("/^(.+)/", $sText);

            if (preg_match("/\n *Departs +(?<time>\d{1,2}:\d{2}) +.+? {3,}(?<code>[A-Z]{3})(?<terminal> *.*Terminal.*)? *\n/iu", $sText, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date(!empty($date) ? $this->normalizeDate($date . ', ' . $m['time']) : null)
                    ->terminal(trim(preg_replace("/\s*terminal\s*/i", '', $m['terminal'] ?? '')), true, true)
                ;
            }

            if (preg_match("/\n *Arrives +(?<time>\d{1,2}:\d{2}) +.+? {3,}(?<code>[A-Z]{3})(?<terminal> *.*Terminal.*)? *\n/iu", $sText, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date(!empty($date) ? $this->normalizeDate($date . ', ' . $m['time']) : null)
                    ->terminal(trim(preg_replace("/\s*terminal\s*/i", '', $m['terminal'] ?? '')), true, true)
                ;
            }

            if (preg_match("/\n *Class +([A-Z]{1,2}) - ([[:alpha:] ]+?) {3,}/", $sText, $m)) {
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin($m[2])
                ;
            }

            if (preg_match("/\n *Flying Time\s+(\d{1,2}:\d{2})(?: {3,}|\n)/", $sText, $m)) {
                $s->extra()
                    ->duration($m[1])
                ;
            }

            if (preg_match("/\n *Equipment\s+(\S.+?)(?: {3,}|\n)/", $sText, $m)) {
                $s->extra()
                    ->aircraft($m[1])
                ;
            }

            $seatsText = $this->re("/\n *\S.+ {2,}\S.+ {2,}Seat.+((?:\n+ *\*.+)+)\n/", $sText);

            if (preg_match_all("/^ *\*.+ {2,}\d{13}(?: ?\S)+ {2,}(\d{1,3}[A-Z])(?: {3,}|$)/m", $seatsText, $m)) {
                $s->extra()
                    ->seats($m[1]);
            }
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

        if (empty($textRows)) {
            return '';
        }
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

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // 16:35 Fri, 21 Oct '22
            '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s+[[:alpha:]\-]+\s*,\s+(\d+)\s+([[:alpha:]]+)\s+\'\s*(\d{2})\s*$/ui',
            // Fri, 13 Jan '23 | 09:15
            '/^\s*[[:alpha:]\-]+\s*,\s+(\d+)\s+([[:alpha:]]+)\s+\'\s*(\d{2})\s*\|\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2 $3 20$4, $1',
            '$1 $2 20$3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
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
