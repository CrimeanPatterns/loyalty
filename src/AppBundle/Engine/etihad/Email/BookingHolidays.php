<?php

namespace AwardWallet\Engine\etihad\Email;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class BookingHolidays extends \TAccountChecker
{
    public $mailFiles = "etihad/it-410049227.eml, etihad/it-413893440.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            //            'Confirmation' => 'Confirmation',
        ],
    ];

    private $detectFrom = "@etihadholidays.com";
    private $detectSubject = [
        // en
        'Etihad Holidays - Booking Confirmation ID - ',
    ];
    private $detectBody = [
        'en' => [
            'HOTEL VOUCHER',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]etihadholidays\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], 'Etihad Holidays') === false
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
        if ($this->containsText($text, ['Etihad Holidays']) === false) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->containsText($text, $detectBody) !== false) {
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
        $textPdf = strstr($textPdf, '© Copyright', true);

        $email->obtainTravelAgency();

        $email->ota()
            ->confirmation($this->re('/\n *Booking Reference +([A-Z\d]{5,})\n/', $textPdf), 'Booking Reference')
            ->confirmation($this->re('/\n *Etihad Abu Dhabi P2P Booking ID +([A-Z\d]{5,})\n/', $textPdf), 'Etihad Abu Dhabi P2P Booking ID')
        ;

        $h = $email->add()->hotel();

        $hotelTableText = $this->re('/\n( *Hotel Address[\s\S]+?\n) *\* ?Notes/', $textPdf);

        $table = ['', ''];

        if (preg_match("/^(.+) Check-in Date/m", $hotelTableText, $m)) {
            $table = $this->createTable($hotelTableText, [0, strlen($m[1])]);
        }
        // General
        $h->general()
            ->confirmation($this->re('/\n *Hotel Confirmation *([\w\-]{5,})\n/', $table[0]))
        ;
        $travellerText = trim($this->re('/\n *# {2,}Name {2,}Guest Type *((\n\s* *\d+ ?\..+)+)/', $textPdf));
        $travellers = preg_split("/\s*\n\s*/", $travellerText);
        $travellers = preg_replace(["/^\s*\d+ ?\. +/", '/ {2,}\w+\s*$/'], '', $travellers);
        $h->general()
            ->travellers($travellers, true);

        $cancellation = array_unique(preg_replace('/\s+/', ' ', $this->res("/\n *Cancellation Policy {2}(.+(?:\n {20,}.+)*)/", $textPdf)));
        $h->general()
            ->cancellation(implode('. ', $cancellation));

        // Hotel
        $h->hotel()
            ->name($this->re('/\n *Hotel Name: *(.+)\n/', $textPdf))
            ->address(preg_replace('/\s+/', ' ', $this->re('/Hotel Address *([\s\S]+?)\n {0,5}\S/', $table[0])))
        ;
        // Booking
        $h->booked()
            ->checkIn($this->normalizeDate($this->re('/Check-in Date: *(.+)/', $table[1])))
            ->checkOut($this->normalizeDate($this->re('/Check-out Date: *(.+)/', $table[1])))
        ;

        $roomsTexts = $this->res("/\n( *Room Type[\s\S]+?)\n *Cancellation Policy/", $textPdf);
        $h->booked()
            ->guests(array_sum($this->res('/Number of Guests .* +(\d+) Adult/', $roomsTexts[0])));

        foreach ($roomsTexts as $roomsText) {
            $table = ['', ''];

            if (preg_match("/^(.+) Room Confirmation/m", $roomsText, $m)) {
                $table = $this->createTable($roomsText, [0, strlen($m[1])]);
            }
            $r = $h->addRoom();

            $r->setType($this->re('/Room Type *([\s\S]+?)\n {0,5}\S/', $table[0]));
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

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
                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            // 14/11/2023 - 2.00 PM
            '/^\s*(\d{1,2})\\/(\d{1,2})\\/(\d{4})\s*-\s*(\d{1,2})\.(\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1.$2.$3, $4:$5',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r($date, true));

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
