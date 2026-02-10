<?php

namespace AwardWallet\Engine\flair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationPdf extends \TAccountChecker
{
    public $mailFiles = "flair/it-387502186.eml, flair/it-389199458.eml, flair/it-394483958.eml, flair/it-404257733.eml, flair/it-407810162.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Your reservation is now confirmed' => 'Your reservation is now confirmed',
            'Flight Itinerary'                  => 'Flight Itinerary',
        ],
    ];

    private $detectFrom = "donotreply@flyflair.com";
    private $detectSubject = [
        // en
        'FlairAir - RESERVATION #',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]flyflair\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], 'FlairAir') === false
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
        if ($this->containsText($text, ['Flair Airlines Ltd', 'flyflair.com']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your reservation is now confirmed'])
                && $this->containsText($text, $dict['Your reservation is now confirmed']) === true
                && !empty($dict['Flight Itinerary'])
                && $this->containsText($text, $dict['Flight Itinerary']) === true
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

        // General
        $f->general()
            ->confirmation($this->re("/ {5,}{$this->opt($this->t('Reservation Number:'))}\s*\n.* {30,} {5,}([A-Z\d]{5,7})\n/", $textPdf))
        ;

        $passengerTableText = $this->re("/\n(.+ {5,}{$this->opt($this->t('Passengers'))}\n[\s\S]+?)\n+ *{$this->opt($this->t('Flight Itinerary'))}/", $textPdf);
        $pos = strlen($this->re("/^(.+ {5,})  {$this->opt($this->t('Passengers'))}/", $passengerTableText));
        $table = $this->createTable($passengerTableText, [0, $pos]);

        $passengerBlock = $table[1]; // use in segment for parsing seats
        $passengers = preg_replace("/^\s*(.+?)\s*,\s*(.+?)\s*$/", '$2 $1',
            preg_replace("/^ *(.+?) {3,}.*$/", '$1',
                preg_split("/\s*\n/", $this->re("/^ *\S.+\n+ *\S+.+\n([\s\S]+)$/", trim($passengerBlock)))));

        $f->general()
            ->travellers(array_filter($passengers), true);

        // Segments
        $segmentsText = $this->re("/\n *{$this->opt($this->t('Flight Itinerary'))}\s*\n( *\S[\s\S]+?)\n+ *(?:{$this->opt($this->t('All charges and payments appear in'))}|{$this->opt($this->t('Online Check-in and Boarding gate'))})/", $textPdf);
        $headerPos = $this->rowColumnPositions($this->re("/^(.+)/", $segmentsText));
        $tableText = $this->re("/^.+\s*\n([\s\S]+)/", $segmentsText);

        $segments = $this->split("/\n( {0,10}\d+ {2,})/", "\n\n" . $tableText);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $table = $this->createTable($sText, $headerPos);

            // Airline
            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s*$/", $table[1] ?? '', $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $re = "/^\s*(?<time>\d{1,2}:\d{2}(?: *[apAP][mM])?)\s*-\s*(?<name>.+)\s*-\s*(?<code>[A-Z]{3})\s*\n *(?<date>\S.+)\s*$/i";
            // Departure
            if (preg_match($re, $table[2] ?? '', $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date(strtotime($m['date'] . ', ' . $m['time']))
                    ->strict()
                ;
            }
            // Arrival
            if (preg_match($re, $table[3] ?? '', $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date(strtotime($m['date'] . ', ' . $m['time']))
                    ->strict()
                ;
            }

            // Extra
            $s->extra()
                ->aircraft(trim($table[4] ?? ''))
                ->status(trim($table[5] ?? ''))
            ;

            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                && preg_match_all("/\b{$s->getAirlineName()}{$s->getFlightNumber()} (\d{1,3}[A-Z]) *(?:,|$|\n)/", $passengerBlock, $m)
            ) {
                $s->extra()
                    ->seats($m[1]);
            }
        }

        // Price
        $priceTableText = $this->re("/\n *{$this->opt($this->t('Purchase Summary'))}\s*\n( *\S[\s\S]+?)\s*\n {20,}{$this->opt($this->t('Total'))} {2,}.+\n/", $textPdf);
        $currency = $this->re("/\n+ *{$this->opt($this->t('All charges and payments appear in'))} *: *([A-Z]{3})\s*\n/", $textPdf);

        if (!empty($currency)) {
            $f->price()
                ->currency($currency)
            ;

            if (preg_match("/\n +{$this->opt($this->t('Total'))} {3,}(.+?) {3,}(.+?) {3,}(.+?)\s*\n/", $textPdf, $m)) {
                $m = preg_replace("/(^\D+|\D+$)/", '', $m);
                $f->price()
                    ->total(PriceHelper::parse($m[3], $currency))
                    ->tax(PriceHelper::parse($m[2], $currency))
                ;
            }

            $tableHeaderPos = $this->rowColumnPositions($this->inOneRow($priceTableText));

            if (count($tableHeaderPos) !== 6) {
                $tableHeaderPos = null;
            }
            $headerPos = $this->rowColumnPositions($this->re("/^(.+)/", $priceTableText));

            if (count($headerPos) !== 6) {
                $tableHeaderPos = null;
            }
            $rows = $this->split("/\n( {0,10}\d+ {3,})/", "\n\n" . $this->re("/^.+(\n+[\S\s]+)/", $priceTableText));
            $containsFare = false;
            $currentPassenger = '';
            $fare = 0.0;
            $fees = [];

            foreach ($rows as $row) {
                $priceTable = $this->createTable($row, $this->rowColumnPositions($this->inOneRow($row)));

                if (count($priceTable) !== 6 && !empty($tableHeaderPos)) {
                    $priceTable = $this->createTable($row, $tableHeaderPos);
                }

                if (count($priceTable) !== 6 && !empty($headerPos)) {
                    $priceTable = $this->createTable($row, $headerPos);
                }

                if (count($priceTable) !== 6) {
                    $this->logger->debug('error count $priceTable');
                    $f->price()
                        // for error
                        ->cost(null);

                    break;
                }

                $priceTable = preg_replace('/\s+/', ' ', array_map('trim', $priceTable));

                if ($currentPassenger !== $priceTable[0] . $priceTable[1]) {
                    $containsFare = false;
                    $currentPassenger = $priceTable[0] . $priceTable[1];
                }

                $amount = PriceHelper::parse(preg_replace("/(^\D+|\D+$)/", '', $priceTable[3]), $currency);

                if (!is_numeric($amount)) {
                    $this->logger->debug('$amount is nor numeric');
                    $f->price()
                        // for error
                        ->cost(null);

                    break;
                }

                if ($amount == 0) {
                    continue;
                }

                if ($containsFare === false && preg_match("/^\s*[A-Z]{1,2} - .+\s*$/", $priceTable[2])) {
                    $fare += $amount;
                    $containsFare = true;
                } else {
                    if (isset($fees[$priceTable[2]])) {
                        $fees[$priceTable[2]] += $amount;
                    } else {
                        $fees[$priceTable[2]] = $amount;
                    }
                }
            }

            if (!empty($fare)) {
                $f->price()
                    ->cost($fare)
                ;
            }

            foreach ($fees as $name => $amount) {
                $f->price()
                    ->fee($name, $amount)
                ;
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
        if (empty(trim($text))) {
            return '';
        }
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
