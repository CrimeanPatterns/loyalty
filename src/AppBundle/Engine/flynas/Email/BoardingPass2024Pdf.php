<?php

namespace AwardWallet\Engine\flynas\Email;

// TODO: delete what not use
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass2024Pdf extends \TAccountChecker
{
    public $mailFiles = "flynas/it-704418765.eml, flynas/it-704464869.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            // Pdf
            'Boarding Pass' => 'Boarding Pass',
            'Booking Ref.'  => 'Booking Ref.', // + html
            'Flight No.'    => 'Flight No.', // + html
            'Seat'          => 'Seat',
            'Terminal'      => 'Terminal',

            // Html
            'Departure' => 'Departure',
        ],
        'ar' => [
            // Pdf
            'Boarding Pass' => 'Boarding Pass',
            'Booking Ref.'  => 'رقم الحجز', // + html
            'Flight No.'    => 'رقم الرحلة', // + html
            // 'Seat' => '',
            // 'Terminal' => '',

            // Html
            'Departure' => 'المغادرة',
        ],
    ];

    private $detectFrom = "no-reply@flynas.com";
    private $detectSubject = [
        // en, ar
        'Boarding Pass for PNR ',
    ];
    private $detectBody = [
        'en' => [
            'you have been checked-in',
        ],
        'ar' => [
            'على متن طيران ناس',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]flynas\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//node()[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }
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
        if ($this->containsText($text, ['flynas']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Booking Ref.']) && !empty($dict['Boarding Pass'])
                && $this->containsText($text, $dict['Booking Ref.']) === true
                && $this->containsText($text, $dict['Boarding Pass']) === true
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
                $this->parseEmailPdf($email, $text);
            }
        }

        if (count($pdfs) === 0) {
            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['Booking Ref.']) && !empty($dict['Flight No.'])
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Booking Ref.'])}]/following::text()[{$this->contains($dict['Flight No.'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    break;
                }
            }
            $this->parseEmailHtml($email);
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
        return count(self::$dictionary) * 2; // html + pdf
    }

    public function niceTravellers($name)
    {
        return preg_replace("/^\s*(Mr|Ms|Mstr|Miss|Mrs)\.?\s+/i", '', $name);
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $bps = $this->split("/\n( *{$this->opt($this->t('Booking Ref.'))}.+\n+ {0,5}{$this->opt($this->t('Boarding Pass'))}\n)/u", "\n\n" . $textPdf);

        foreach ($bps as $bpText) {
            unset($f);
            $conf = $this->re("/{$this->opt($this->t('Booking Ref.'))} *([A-Z\d]{5,7})\n/u", $bpText);

            foreach ($email->getItineraries() as $it) {
                if ($it->getType() === 'flight' && in_array($conf, array_column($it->getConfirmationNumbers(), 0)) === true) {
                    $f = $it;

                    break;
                }
            }

            if (!isset($f)) {
                $f = $email->add()->flight();

                $f->general()
                    ->confirmation($conf);
            }

            $traveller = $this->niceTravellers($this->re("/\n {0,5}{$this->opt($this->t('Boarding Pass'))}\n+ *(.+)\n/u", $bpText));

            if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
                $f->general()
                    ->traveller($traveller, true);
            }
            // Segments
            $s = $f->addSegment();

            // Airline
            if (preg_match("/\n {0,5}{$this->opt($this->t('Flight No.'))} +{$this->opt($this->t('Seat'))}.*\n+ {0,5}(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) {0,3}(?<fn>\d{1,5}) {3,}(?<seat>\d{1,3}[A-Z])( {3,}|\n)/", $bpText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                $s->extra()
                    ->seat($m['seat'], true, true, $traveller);
            }

            $routeTableText = $this->re("/\n {0,5}{$this->opt($this->t('Boarding Pass'))}\n+ *.+\n([\s\S]+?\n) {0,5}{$this->opt($this->t('Flight No.'))}/", $bpText);
            $routeTable = $this->createTable($routeTableText, $this->rowColumnPositions($this->inOneRow($routeTableText)));
            $this->logger->debug('$routeTable = ' . print_r($routeTable, true));

            $re = "/^\s*(?<date>.+)\n+(?<code>[A-Z]{3})\n+(?<city>.+)\n+(?<airport>[\s\S]+)\s*$/u";

            // Departure
            if (preg_match($re, $routeTable[0] ?? '', $m)) {
                if (preg_match("/^([\s\S]*)\n(.*{$this->opt($this->t('Terminal'))}.*)\s*$/", "\n" . $m['airport'], $mat)) {
                    $m['airport'] = trim($mat[1]);
                    $s->departure()
                        ->terminal(preg_replace("/\s*\b{$this->opt($this->t('Terminal'))}\b\s*/", '', $mat[2]));
                }
                $s->departure()
                    ->code($m['code'])
                    ->name(implode(", ", array_filter([$m['city'], $m['airport']])))
                    ->date($this->normalizeDate($m['date']))
                ;
            }

            // Arrival
            if (preg_match($re, $routeTable[2] ?? '', $m)) {
                if (preg_match("/^([\s\S]*)\n(.*{$this->opt($this->t('Terminal'))}.*)\s*$/", "\n" . $m['airport'], $mat)) {
                    $m['airport'] = trim($mat[1]);
                    $s->arrival()
                        ->terminal(preg_replace("/\s*\b{$this->opt($this->t('Terminal'))}\b\s*/", '', $mat[2]));
                }
                $s->arrival()
                    ->code($m['code'])
                    ->name(implode(", ", array_filter([$m['city'], $m['airport']])))
                    ->date($this->normalizeDate($m['date']))
                ;
            }

            // Extra
            $s->extra()
                ->duration($routeTable[1] ?? '');

            foreach ($f->getSegments() as $key => $seg) {
                if ($seg->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($seg->toArray(),
                            ['seats' => [], 'assignedSeats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => [], 'assignedSeats' => []]))) {
                        if (!empty($s->getAssignedSeats())) {
                            foreach ($s->getAssignedSeats() as $seat) {
                                $seg->extra()
                                    ->seat($seat[0], false, false, $seat[1]);
                            }
                        } elseif (!empty($s->getSeats())) {
                            $seg->extra()->seats(array_unique(array_merge($seg->getSeats(),
                                $s->getSeats())));
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
            $this->logger->debug('$bpText = ' . print_r($bpText, true));
        }
        $this->logger->debug('Pdf text = ' . print_r($textPdf, true));

        return $email;
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        if (in_array($this->lang, ['ar'])) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Ref.'))}]/preceding::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/");
        } else {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Ref.'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/");
        }

        $f->general()
            ->confirmation($conf);

        $xpath = "//tr[*[{$this->eq($this->t('Flight No.'))}]][*[4][{$this->eq($this->t('Departure'))}]]/following-sibling::tr[normalize-space()]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $values = [];

            foreach ($this->http->XPath->query("*", $root) as $r) {
                $values[] = implode(' ',
                    $this->http->FindNodes("descendant::text()[normalize-space()]", $r));
            }

            if (in_array($this->lang, ['ar'])) {
                $values = array_reverse($values);
            }

            // Airline
            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $values[0] ?? '', $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            // Departure
            $s->departure()
                ->name($this->re("/^\s*(.+?)\s+[A-Z]{3}\s*$/", $values[1] ?? ''))
                ->code($this->re("/^\s*.+?\s+([A-Z]{3})\s*$/", $values[1] ?? ''))
                ->date($this->normalizeDate($values[4] ?? ''))
            ;
            // Arrival
            $s->arrival()
                ->name($this->re("/^\s*(.+?)\s+[A-Z]{3}\s*$/", $values[2] ?? ''))
                ->code($this->re("/^\s*.+?\s+([A-Z]{3})\s*$/", $values[2] ?? ''))
                ->date($this->normalizeDate($values[4] ?? ''))
            ;

            // Extra
            $s->extra()
                ->seats(preg_split('/\s*,\s*/', $values[5] ?? ''));
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            //            // 20 Jul 24 12:05
            '/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{2})\s*[,\s]\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1 $2 20$3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("/^\s*(\d+\s+)([[:alpha:]]+)(\s+\d{4}.*)/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $date = $m[1] . $en . $m[3];
            }
        }

        $this->logger->debug('date end = ' . print_r($date, true));

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
}
