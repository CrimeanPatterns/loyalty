<?php

namespace AwardWallet\Engine\airbaltic\Email;

// TODO: delete what not use
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassPdf3 extends \TAccountChecker
{
    public $mailFiles = "";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Boarding pass'      => 'Boarding pass',
            'Booking reference:' => 'Booking reference:',
            'Ticket number:'     => 'Ticket number:',
            'airBaltic Club No:' => 'airBaltic Club No:',
            'Full itinerary:'    => 'Full itinerary:',
            'Date'               => ['Date', 'Departure date'],
            'Operated by'        => 'Operated by',
            'Dep. terminal'      => 'Dep. terminal',
            'Class'              => 'Class',
            'Seat'               => 'Seat',
            'Departure'          => 'Departure',
            'Arrival'            => 'Arrival',
            'Arr. terminal'      => 'Arr. terminal',
        ],
    ];

    private $detectFrom = "noreply@airbaltic.com";
    private $detectSubject = [
        'Your boarding pass(es)',
        'Jūsų  įlaipinimo bilietas (-ai)',
        'Iekāpšanas karte(-s)',
        'Oma(t) tarkastuskorttisi',
    ];
    private $jpgFileNames = [];

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

    public function detectPdf($text)
    {
        // detect provider
        if ($this->containsText($text, ['airBaltic Club No', '+371 67280422']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Boarding pass'])
                && $this->containsText($text, $dict['Boarding pass']) === true
                && !empty($dict['Full itinerary:'])
                && $this->containsText($text, $dict['Full itinerary:']) === true
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
                $jpgs = $parser->searchAttachmentByName('Barcode.*jpg');

                foreach ($jpgs as $jpg) {
                    $this->jpgFileNames[] = $this->getAttachmentNameJpg($parser, $jpg);
                }
                $this->jpgFileNames = array_filter($this->jpgFileNames);

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
        $f = $email->add()->flight();

        $segments = $this->split("/(?:^|\n)( *{$this->opt($this->t('Boarding pass'))}(?: {3,}|\n))/", $textPdf);

        if (count($segments) !== count($this->jpgFileNames)) {
            $this->jpgFileNames = [];
        }
//        $this->logger->debug('$segments = ' . print_r($segments, true));
        foreach ($segments as $stext) {
            $traveller = null;
            $confirmation = $this->re("/ {2,}{$this->opt($this->t('Booking reference:'))} {0,5}([A-Z\d]{5,7})( {3,}|\n)/", $stext);

            if (!in_array($confirmation, array_column($f->getConfirmationNumbers(), 0))) {
                $f->general()
                    ->confirmation($confirmation);
            }

            $ticket = $this->re("/ {2,}{$this->opt($this->t('Ticket number:'))} {0,5}(\d{8,})( {3,}|\n)/", $stext);

            if (!in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                $f->issued()
                    ->ticket($ticket, false);
            }

            $account = $this->re("/ {2,}{$this->opt($this->t('airBaltic Club No:'))} {0,5}(\d{5})( {3,}|\n)/", $stext);

            if (!empty($account) && !in_array($account, array_column($f->getAccountNumbers(), 0))) {
                $f->program()
                    ->account($account, false);
            }

            // Segments
            $s = $f->addSegment();

            $part = $this->re("/{$this->opt($this->t('Full itinerary:'))}.*\n([\s\S]+)\n {0,10}{$this->opt($this->t('Date'))}/", $stext);
            $part = preg_replace("/^ {30}.*/m", '', $part);
            $part = preg_replace("/^ {0,20}(\S.*?) {3,}.*/m", '$1', $part);
            $re = "/^\s*(?<dName>[\s\S]+?)\s*\((?<dCode>[A-Z]{3})\) - (?<aName>[\s\S]+?)\s*\((?<aCode>[A-Z]{3})\)\s*"
                . "(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<fn>\d{1,5})(?: *- *{$this->opt($this->t('Operated by'))} *(?<operator>.+))?\n+(?<traveller>[A-Z \W]+)\s*$/";

            if (preg_match($re, $part, $m)) {
                $s->departure()
                    ->code($m['dCode'])
                    ->name($this->nice($m['dName']))
                ;
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($this->nice($m['aName']))
                ;

                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                if (!empty($m['operator'])) {
                    $s->airline()
                        ->operator($m['operator']);
                }

                $traveller = trim($m['traveller']);

                if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
                    $f->general()
                        ->traveller($traveller, true);
                }
            }
            $part = $this->re("/\n( {0,20}{$this->opt($this->t('Date'))}.*\n[\s\S]+\n {0,10}{$this->opt($this->t('Departure'))}.*\n+.+)(\n *\*.+)?\n\n/", $stext);
            $table = $this->createTable($part, $this->rowColumnPositions($this->inOneRow($part)));

            $date = strtotime($this->re("/^\s*{$this->opt($this->t('Date'))}\n\s*(.+)/", $table[0] ?? ''));
            $time = $this->re("/\s*{$this->opt($this->t('Departure'))}\n\s*(.+)/", $table[0] ?? '');

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            }
            $time = $this->re("/\s*{$this->opt($this->t('Arrival'))}\n\s*(.+)/", $table[1] ?? '');

            if (!empty($date) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($time, $date));
            }

            if (preg_match("/{$this->opt($this->t('Dep. terminal'))}\n{1,3}(\S.*)/", $table[0] ?? '', $m)) {
                $s->departure()
                    ->terminal($m[1]);
            }

            if (preg_match("/{$this->opt($this->t('Arr. terminal'))}\n{1,3}(\S.*)/", $table[2] ?? '', $m)) {
                $s->departure()
                    ->terminal($m[1]);
            }

            $s->extra()
                ->cabin($this->re("/{$this->opt($this->t('Class'))}\n+(.+)(\n.*)*{$this->opt($this->t('Seat'))}/", $table[1] ?? ''))
                ->seat($this->re("/{$this->opt($this->t('Seat'))}\s*(\d{1,3}[A-Z])\s*(?:\n|$)/", $table[1] ?? ''))
            ;

            foreach ($this->jpgFileNames as $fName) {
                // Barcode_NCMURG_LIUTVINSKAITETAMULE_DIANA_BT961_1682016192437.jpg
                if (!empty($confirmation) && !empty($traveller) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                    && preg_match("/^Barcode_{$confirmation}_" . preg_replace('/\s+/', '_?', $traveller) . "_{$s->getAirlineName()}{$s->getFlightNumber()}_\d+\./", $fName)
                ) {
                    $bp = $email->add()->bpass();

                    $bp
                        ->setRecordLocator($confirmation)
                        ->setAttachmentName($fName)
                        ->setDepCode($s->getDepCode())
                        ->setDepDate($s->getDepDate())
                        ->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber())
                        ->setTraveller($traveller);

                    break;
                }
            }

            $fsegments = $f->getSegments();

            foreach ($fsegments as $seg) {
                if ($seg->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($seg->toArray(),
                            ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                        if (!empty($s->getSeats())) {
                            $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
        }

        return $email;
    }

    private function getAttachmentNameJpg(\PlancakeEmailParser $parser, $jpg)
    {
        $header = $parser->getAttachmentHeader($jpg, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.jpg)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
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

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
