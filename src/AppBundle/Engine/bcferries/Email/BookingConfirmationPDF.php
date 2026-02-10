<?php

namespace AwardWallet\Engine\bcferries\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "bcferries/it-245834034.eml, bcferries/it-247325460.eml, bcferries/it-360899198.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking Reference:' => 'Booking Reference:',
            'Time / Date'        => 'Time / Date',
        ],
    ];

    private $detectSubject = [
        'Booking confirmation for',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bcferries.com') !== false;
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

    public function detectPdf($text)
    {
        if ($this->containsText($text, ['1-888-BC FERRY', 'BCF CUSTOMER SERVICE CENTRE']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Booking Reference:'])
                && $this->containsText($text, $dict['Booking Reference:']) === true
                && !empty($dict['Time / Date'])
                && $this->containsText($text, $dict['Time / Date']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $pos = strpos($textPdf, 'Due at Terminal:');

        if ($pos > 20) {
            $textPdf = substr($textPdf, 0, $pos);
        }
        $b = $email->add()->ferry();

        // General
        $b->general()
            ->confirmation($this->re("/Booking Reference: *([A-Z\d]{5,})\s+/", $textPdf))
            ->traveller($this->re("/Booking Holder: *(.+)/", $textPdf));

        if ($this->containsText($textPdf, ['Booking Cancellation', 'Your reservation has been cancelled']) !== false) {
            $b->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Program
        $account = $this->re("/Customer Number: *(\d{5,})\s+/", $textPdf);

        if (!empty($account)) {
            $b->program()
                ->account($account, false);
        }

        // Price
        if (!$b->getCancelled()) {
            $total = $this->re("/ {3,}(?:Total|Products and Fees): *\\$ ?(\d[\d,.]*)\n/", $textPdf);

            if (!empty($total)) {
                $b->price()
                    ->total(PriceHelper::parse($total, 'USD'))
                    ->currency('USD');
            } else {
                $b->price()
                    ->total(null);
            }
        }

        $textSegment = $this->re("/\n *Departs {2,}Time.+\n([\s\S]+?)\n\n/", $textPdf);

        $table = $this->createTable($textSegment, $this->columnPositions($this->inOneRow($textSegment)));

        if (count($table) == 4) {
            $s = $b->addSegment();

            $s->departure()
                ->name(preg_replace("/\s*\n\s*/", ', ', $table[0]))
                ->date($this->normalizeDate($table[1]))
            ;
            $s->arrival()
                ->name(preg_replace("/\s*\n\s*/", ', ', $table[2]))
                ->date($this->normalizeDate($table[3]))
            ;

            if (!$b->getCancelled()) {
                $s->extra()
                    ->vessel($this->re("/\n {0,10}Ferry {3,30}(.+?)( {3,}|\n)/", $textPdf));
            }

            if (preg_match("/ {2,}(UNDER HEIGHT PASSENGER VEHICLE) {2,}/", $textPdf, $m)) {
                $s->addVehicle()
                    ->setType($m[1]);
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

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // 10:40      30/Dec/2022
            '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s+(\d+)\\/([[:alpha:]]+)\\/(\d{4})\s*$/ui',
        ];
        $out = [
            '$2 $3 $4, $1',
        ];

        $date = preg_replace($in, $out, $date);

        return strtotime($date);
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
