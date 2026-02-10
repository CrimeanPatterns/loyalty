<?php

namespace AwardWallet\Engine\expresstg\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "expresstg/it-262327874.eml, expresstg/it-264073386.eml, expresstg/it-267469335.eml, expresstg/it-375750816.eml, expresstg/it-380592189.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
        ],
    ];

    private $detectSubjectRe = [
        // en
        '/Itinerary for .+ \| Reloc: [A-Z\d]{5,7} \| Depart: .+/',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@expresstickets.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjectRe as $dSubject) {
            if (preg_match($dSubject, $headers["subject"])) {
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
        $pos1 = $this->strposAll($text, 'TRAVEL ITINERARY');
        $pos2 = $this->strposAll($text, 'EQUIPMENT');
        $pos3 = $this->strposAll($text, 'DISTANCE');
        $pos4 = $this->strposAll($text, 'LOCATOR');

        if ($pos1 === false || $pos2 === false || $pos3 === false || $pos4 === false) {
            return false;
        }

        $part = substr($text, 0, $pos2 + 20);

        if (preg_match("/\n *TICKET NO {3,}ISSUE DATE {3,}.*\bLOCATOR\b.*/", $part)
            && preg_match("/\n.* {3,}DISTANCE {0,2}\\/ {0,2}DURATION {3,}EQUIPMENT\n/", $part)
        ) {
            return true;
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $textPdf = preg_replace("/\n *ISSUING AGENCY {5,}EMAIL {5,}.+\n+.+/", '', $textPdf);
        $email->obtainTravelAgency();

        $its = $this->split("/\n *([A-Z].+\s*\n *TICKET NO +ISSUE DATE)/", "\n\n" . $textPdf);

        foreach ($its as $i => $part) {
            $tableText = $this->re("/\n( *TICKET NO {3,}ISSUE DATE {3,}.*\n+.+(?:\n.+)?)\n\n/", $part);
            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

            $mailInfo = implode("\n", preg_replace("/\s+/", ' ', $table));

            $conf = $this->re("/^\s*(?:GDS RECORD )?LOCATOR +([A-Z\d]{5,7})\s*$/m", $mailInfo);

            $fountIt = false;

            foreach ($email->getItineraries() as $it) {
                if (in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                    $f = $it;
                    $fountIt = true;

                    break;
                }
            }

            if ($fountIt === false) {
                $f = $email->add()->flight();

                $f->general()
                    ->confirmation($conf);

                $f->general()
                    ->date($this->normalizeDate($this->re("/^\s*ISSUE DATE +(.+)\s*$/m", $mailInfo)));
            }

            $f->issued()
                ->ticket($this->re("/^\s*TICKET NO +([\d\-]{10,})\s*$/m", $mailInfo), false);

            $ac = $this->re("/^ *AIRLINE REFERENCE +(.+) *$/m", $mailInfo);

            if (preg_match("/^\s*([A-Z\d]{5,7})\s*$/", $ac, $m)) {
                $airlineConf = $m[1];
            } elseif (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\*(?<conf>[A-Z\d]{5,7})\s*$/", $ac, $m)) {
                $airlineConf[$m['al']] = $m['conf'];
            }

            $f->general()
                ->traveller(preg_replace(['/\s+(mr|ms|mrs|mstr|dr|miss)\s*$/i', "/(.+?) *\/ *(.+)/"], ['', "$2 $1"],
                    trim($this->re("/^\s*(.+)\n/", $part))));

            // Segments
            $segments = $this->split("/\n(.+\((?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5}\)(?:.*\n+){0,2}?.*CABIN {3,}STATUS\n)/",
                $part);
//            $this->logger->debug('$segments = '.print_r( $segments,true));

            $airlines = [];

            foreach ($segments as $segment) {
//                $this->logger->debug('$segment = '.print_r( $segment,true));
                $s = $f->addSegment();

                if (preg_match("/^\s*[^\(]+?\((?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) *(?<fn>\d{1,5})\)(?:\s*Operated by\s+(?<operator>.+))?/", $segment, $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);

                    if (!empty($m['operator'])) {
                        $s->airline()
                            ->operator($m['operator']);
                    }
                    $airlines[] = $m['al'];

                    if (isset($airlineConf[$m['al']])) {
                        $s->airline()
                            ->confirmation($airlineConf[$m['al']]);
                    }
                }
                $tableText = $this->re("/^\s*.+?(?:Operated by\s+.+)?\n([\s\S]+?\bDURATION {3,}EQUIPMENT\n+[\s\S]+?)(?:\n\n|\n *OTHER SERVICES)/",
                    $segment);
                $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

                if (count($table) === 5 && preg_match("/^\s*(\d+)\s*stop/", $table[1], $ms)) {
                    unset($table[1]);
                    $table = array_values($table);
                    $s->extra()
                        ->stops($ms[1]);
                }

                if (count($table) !== 4) {
                    break;
                }

                $re = "/^\s*(?<name>.*?)\s*\((?<code>[A-Z]{3})\)\s+(?<date>[^\n]+)\n(?<name2>.+?Airport)\s*(?<terminal>\n(?i).*\bterminal\b.*)?\s*$/s";

                // Departure
                if (preg_match($re, $table[0], $m)) {
                    $s->departure()
                        ->name(preg_replace("/\s+/", ' ', trim($m['name2']) . ' ' . trim($m['name'])))
                        ->code($m['code'])
                        ->date($this->normalizeDate($m['date']))
                        ->terminal(preg_replace("/\s*\bterminal\b\s*/i", '', trim($m['terminal'] ?? '')), true, true);
                }
                // Arrival
                if (preg_match($re, $table[1], $m)) {
                    $s->arrival()
                        ->name(preg_replace("/\s+/", ' ', trim($m['name2']) . ' ' . trim($m['name'])))
                        ->code($m['code'])
                        ->date($this->normalizeDate($m['date']))
                        ->terminal(preg_replace("/\s*\bterminal\b\s*/i", '', trim($m['terminal'] ?? '')), true, true);
                }

                // Extra
                if (preg_match("/CABIN\s+(?<cabin>.*)\n\s*DISTANCE *\\/ *DURATION\s+(?<miles>.+)\\/(?<duration>.+)/s",
                    $table[2], $m)) {
                    if (preg_match("/^\s*(?<cabin>.+?)\s*\((?<code>[A-Z]{1,2})\)\s*$/s", $m['cabin'], $mat)) {
                        $s->extra()
                            ->cabin($mat['cabin'])
                            ->bookingCode($mat['code']);
                    } elseif (preg_match("/^\s*\((?<code>[A-Z]{1,2})\)\s*$/", $m['cabin'], $mat)) {
                        $s->extra()
                            ->bookingCode($mat['code']);
                    } else {
                        $s->extra()
                            ->cabin($mat['cabin']);
                    }
                    $s->extra()
                        ->miles(trim($m['miles']))
                        ->duration(trim($m['duration']));
                }

                if (preg_match("/STATUS\s+(?<status>.*)\n\s*EQUIPMENT\s+(?<aircraft>.+)/s", $table[3], $m)) {
                    $s->extra()
                        ->status(preg_replace("/\s+/", ' ', trim($m['status'])))
                        ->aircraft(preg_replace("/\s+/", ' ', trim($m['aircraft'])));
                }

                if (preg_match("/\| *SEAT *- *(?<seat>\d{1,3}[A-Z]) *(?: {4,}| *\||\n|$)/", $segment, $m)) {
                    // OTHER SERVICES
                    // BAGGAGE - 2 PIECE - CONFIRMED | SEAT - 03A
                    $s->extra()
                        ->seat($m['seat']);
                } elseif (strpos($part, 'SEAT:') !== false
                    && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                    && !empty($s->getDepCode()) && !empty($s->getArrCode())
                    && preg_match("/DEPARTURE AIRPORT.*\n.+ - {$s->getAirlineName()} ?{$s->getFlightNumber()} +.*\({$s->getDepCode()}\).*\({$s->getArrCode()}\)(?:.*\n+){2,4} *SEAT: [^\d\n]* - (?<seat>\d{1,3}[A-Z])\s*(?:\n|$)/", $part, $m)
                ) {
                    $s->extra()
                        ->seat($m['seat']);
                }

                $fsegments = $f->getSegments();

                foreach ($fsegments as $seg) {
                    if ($seg->getId() !== $s->getId()) {
                        if (
                            serialize(array_diff_key($seg->toArray(), ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))
                            || (serialize(array_diff_key($seg->toArray(), ['seats' => [], 'confirmation' => ''])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))
                                && $seg->getConfirmation() === $airlineConf)
                        ) {
                            if (!empty($s->getSeats())) {
                                $seg->extra()->seats(array_unique(array_merge($seg->getSeats(),
                                    $s->getSeats())));
                            }
                            $f->removeSegment($s);

                            break;
                        }
                    }
                }
            }

            $airlines = array_unique($airlines);

            if (is_string($airlineConf) && !empty($airlineConf) && count($airlines) === 1) {
                foreach ($f->getSegments() as $s) {
                    $s->airline()
                        ->confirmation($airlineConf);
                }
            }

            // Price

            if ($this->strposAll($textPdf, 'PAYMENT') !== false) {
                $price = $this->re("/\n *TOTAL +(.+?)(?:\n| {2,})/", $part);

                if (preg_match("/^\s*(?<currency>[A-Z]{3}) *(?<amount>\d[\d,. ]*)\s*$/", $price, $m)
                    && ($amount = PriceHelper::parse($m['amount'], $m['currency'])) !== null
                ) {
                    if (!$f->getPrice()) {
                        $f->price()
                            ->total($amount)
                            ->currency($m['currency']);
                    } elseif ($f->getPrice()->getTotal() !== null && $f->getPrice()->getCurrencyCode() === $m['currency']) {
                        $f->price()
                            ->total($f->getPrice()->getTotal() + $amount)
                            ->currency($m['currency']);
                    } else {
                        $f->price()
                            ->total(null);
                    }
                } else {
                    $f->price()
                        ->total(null)
                    ;
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

    private function strposAll($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $pos = strpos($text, $n);

                if ($pos !== false) {
                    return $pos;
                }
            }
        } elseif (is_string($needle)) {
            return strpos($text, $needle);
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

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // Sat, 01 Jul 2023 at 05:15AM
            '/^\s*[[:alpha:]\-]+[\s,]+(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // 2022-12-08 | 02:14 PM
            '/^\s*(\d{4}-\d{2}-\d{2})\s*\|\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1, $2',
        ];
        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date end = ' . print_r( $date, true));

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
