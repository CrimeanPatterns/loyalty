<?php

namespace AwardWallet\Engine\friendchips\Email;

// TODO: delete what not use
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPasses extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-324807577.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'YOUR BOARDING PASS'             => 'YOUR BOARDING PASS',
            "YOU'VE CHECKED-IN - WHAT NEXT?" => "YOU'VE CHECKED-IN - WHAT NEXT?",
        ],
    ];

    private $detectFrom = "boardingpass-uk@tui.com";
    private $confirmation;
    private $detectSubject = [
        // en
        'Your TUI Airways boarding passes for booking',
    ];

    private $detectBody = [
        //        'en' => [
        //            '',
        //        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['from'], 'TUI Airways') === true
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
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['YOUR BOARDING PASS'])
                && $this->containsText($text, $dict['YOUR BOARDING PASS']) === true
                && !empty($dict["YOU'VE CHECKED-IN - WHAT NEXT?"])
                && $this->containsText($text, $dict["YOU'VE CHECKED-IN - WHAT NEXT?"]) === true
            ) {
                $this->lang = $lang;

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
                $name = $parser->getAttachmentHeader($pdf, 'Content-Type');

                if (preg_match('/name="BoardingPass-(\d{5,})\.pdf/', $name, $m)) {
                    $this->confirmation = $m[1];
                }

                if (empty($this->confirmation)) {
                    $this->confirmation = $this->re("/Your TUI Airways boarding passes for booking (\d{5,})\s*$/",
                        $parser->getSubject());
                }

                if (empty($this->confirmation)) {
                    $this->confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-in confirmation for booking')]",
                        null, true, "/Check-in confirmation for booking (\d{5,})\s*$/");
                }

                $this->parsePdf($email, $text);
            }

//            $this->logger->debug('$text = ' . print_r($text, true));
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

    private function parsePdf(Email $email, ?string $text = null)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->confirmation);
        $bps = $this->split("/\n( *{$this->opt($this->t('YOUR BOARDING PASS'))}\n)/", "\n\n" . $text);
//        $this->logger->debug('$bps = ' . print_r("\n\n" . $text, true));

        foreach ($bps as $bpText) {
            $s = $f->addSegment();

            $tableText = $this->re("/\n( *{$this->opt($this->t('TRAVEL DATE'))}[\s\S]+)\n *{$this->opt($this->t('BOOKING NUMBER:'))}/", $bpText);
//            $this->logger->debug('$tableText = ' . print_r("\n" . $tableText, true));
            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));
//            $this->logger->debug('$table = ' . print_r($table, true));

            // Airline
            if (preg_match("/{$this->opt($this->t('FLIGHT NUMBER'))}\s+(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]|TOM) *(?<fn>\d{1,5})\n/", $table[1] ?? '', $m)) {
                $s->airline()
                    ->name(($m['al'] == 'TOM') ? 'BY' : $m['al'])
                    ->number($m['fn']);
            }
            $s->airline()
                ->confirmation($this->re("/\n\s*{$this->opt($this->t('BOOKING NUMBER:'))} *([A-Z\d]{5,7})\n/", $bpText));

            // Departure

            $s->departure()
                ->noCode()
                ->date($this->normalizeDate(
                    $this->re("/{$this->opt($this->t('TRAVEL DATE'))}\s+(.+)/", $table[0] ?? '')
                    . ', ' . $this->re("/\n *{$this->opt($this->t('FLIGHT DEPARTS'))}\s+(.+)/", $table[0] ?? '')
                ))
                ->name($this->re("/\n *{$this->opt($this->t('FROM'))}\s+(.+)\n\s*{$this->opt($this->t('PASSENGER'))}/", $table[1] ?? ''))
            ;

            // Arrival
            $s->arrival()
                ->noCode()
                ->noDate()
                ->name($this->re("/\n *{$this->opt($this->t('TO'))}\s+(.+)\n\s*{$this->opt($this->t('SEAT NUMBER'))}/", $table[2] ?? ''))
            ;

            // Extra
            $s->extra()
                ->cabin($this->re("/\n\s*{$this->opt($this->t('CABIN:'))} *(.+)/", $bpText))
            ;
            $infant = false;

            if (preg_match("/\n\s*{$this->opt($this->t('SEAT NUMBER'))}\s+(INF)\s*$/", $table[2] ?? '')) {
                $infant = true;
            } else {
                $s->extra()
                    ->seat($this->re("/\n\s*{$this->opt($this->t('SEAT NUMBER'))}\s+(\d{1,3}[A-Z])\s*$/", $table[2] ?? ''));
            }

            $traveller = trim(preg_replace("/\s+/", ' ',
                $this->re("/\n\s*{$this->opt($this->t('PASSENGER'))}\s+(.+?)\s*$/s", $table[1] ?? '')));

            if ($infant === false && !in_array($traveller, array_column($f->getTravellers(), 0))) {
                $f->general()
                    ->traveller($traveller, true);
            } elseif ($infant === true && !in_array($traveller, array_column($f->getInfants(), 0))) {
                $f->general()
                    ->infant($traveller, true);
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 22/03/2023, 07:30
            "/^\s*(\d{1,2})\\/(\d{2})\\/(\d{4})\s*,\s*(\d{1,2}:\d{2}(\s*[ap]m)?)$/i",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }

        if (preg_match("/^\s*\d{1,2}\.\d{2}\.\d{4}\s*,\s*(\d{1,2}:\d{2}(?: *[ap]m)?)?\s*$/i", $str)) {
            return strtotime($str);
        }

        return null;
    }
}
