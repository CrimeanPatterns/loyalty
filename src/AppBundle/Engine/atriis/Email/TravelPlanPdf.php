<?php

namespace AwardWallet\Engine\atriis\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class TravelPlanPdf extends \TAccountChecker
{
    public $mailFiles = "atriis/it-417023143-2.eml, atriis/it-422710891-2.eml, atriis/it-424896632-2.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Duration:'  => 'Duration:',
            'Distance:'  => 'Distance:',
            'CO2:'       => 'CO2:',
            'Equipment:' => 'Equipment:',
            'Class:'     => 'Class:',
        ],
    ];

    private $detectFrom = "@gtp-marketplace.com";
    private $detectSubject = [
        // en
        'Travel plan For ',
    ];

    private static $detectProvider = [
        'travexp' => [
            '@travelexperts.be',
            'Travel Experts',
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]gtp-marketplace\.com$/", $from) > 0;
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
            if (!empty($dict['Duration:'])) {
                $pos = $this->striposArray($text, $dict['Duration:']);

                if (!empty($pos)) {
                    $textPart = substr($text, $pos - 100, 200);

                    if (!empty($dict['Distance:'])
                        && !empty($dict['CO2:'])
                        && !empty($dict['Equipment:'])
                        && !empty($dict['Class:'])
                        && preg_match("/\n *{$dict['Duration:']} {2,}{$dict['Distance:']} {2,}{$dict['CO2:']} {2,}{$dict['Equipment:']} {2,}{$dict['Class:']} {2,}/", $textPart)
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            //$this->logger->debug($text);

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);

                foreach (self::$detectProvider as $code => $prDetect) {
                    //$this->logger->debug('$prDetect = ' . print_r($prDetect, true));
                    //$this->logger->debug('$text = ' . print_r($text, true));

                    if ($this->striposArray($text, $prDetect) !== false) {
                        $email->setProviderCode($code);

                        break;
                    }
                }
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
        // Travel Agency
        $email->ota()
            ->confirmation($this->re("/{$this->opt($this->t('Trip Number:'))} *(.+)/", $textPdf));

        //remove lines were duplicated
        $textPdf = preg_replace("/(Flight\n+[A-Z]{3}\s+[­]\s+[A-Z]{3}\s+Agency Booking Reference:\s+[A-Z\d]{6}\n)(Flight\n+[A-Z]{3}\s+[­]\s+[A-Z]{3}\s+Agency Booking Reference:\s+[A-Z\d]{6}\n)/u", "$1", $textPdf);

        $segments = $this->split("/\n {0,5}({$this->opt($this->t('Flight'))}|{$this->opt($this->t('Hotel'))})\n/", $textPdf);

        foreach ($segments as $segment) {
            if (preg_match("/^{$this->opt($this->t('Flight'))}/", $segment)) {
                if (!isset($flightsIt)) {
                    $flightsIt = $email->add()->flight();
                }
                $this->parseFlight($flightsIt, $segment);
            } else {
                $email->add()->hotel();
            }
        }

        return $email;
    }

    private function parseFlight(Flight $f, $sText)
    {
        $conf = $this->re("/{$this->opt($this->t('Agency Booking Reference:'))} *(.+)/", $sText);

        if (!in_array($conf, array_column($f->getConfirmationNumbers(), 0))) {
            $f->general()
                ->confirmation($conf);
        }

        $routeTableText = $this->re("/^.+\n\s*\S.+\s*\n\n([\s\S]+?)\n *{$this->opt($this->t('Duration:'))}/", $sText);
        $routeTable = $this->createTable($routeTableText, $this->rowColumnPositions($this->inOneRow($routeTableText)));
        // $this->logger->debug('$routeTable = '.print_r( $routeTable,true));
        // $this->logger->debug('$routeTableText = '.print_r( $routeTableText,true));

        $s = $f->addSegment();

        // Airline
        if (preg_match("/\b(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\W?(?<fn>\d{1,5})\n/us", $routeTable[0] ?? '', $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);
        }

        if (preg_match("/{$this->opt($this->t('Operated by'))} .+ (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s*$/s", $routeTable[0] ?? '', $m)) {
            $s->airline()
                ->carrierName($m['al'])
                ->carrierNumber($m['fn']);
        } elseif (preg_match("/{$this->opt($this->t('Operated by'))} (.+?)\s*$/s", $routeTable[0] ?? '', $m)) {
            $s->airline()
                ->operator($m[1]);
        }

        // Departure
        if (preg_match("/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\n(?<date>.+)\n\s*Terminal\s*(?:N\\/A|(?<terminal>.+))\s*$/s", $routeTable[1] ?? '', $m)
            || preg_match("/^(?<code>[A-Z]{3})\s*\n(?<date>.+)\n\s*Terminal\s*(?:N\\/A|(?<terminal>.+))\s*$/s", $routeTable[1] ?? '', $m)
         ) {
            $s->departure()
                ->code($m['code'])
                ->date($this->normalizeDate($m['date']))
                ->terminal($m['terminal'] ?? null, true, true);

            $depName = trim($m['name'], ',');

            if (!empty($depName)) {
                $s->departure()
                    ->name($depName);
            }
        }

        // Arrival
        if (preg_match("/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\n(?<date>.+)\n\s*Terminal\s*(?:N\\/A|(?<terminal>.+))\s*$/s", $routeTable[3] ?? '', $m)
        || preg_match("/^(?<code>[A-Z]{3})\s*\n(?<date>.+)\n\s*Terminal\s*(?:N\\/A|(?<terminal>.+))\s*$/s", $routeTable[3] ?? '', $m)) {
            $s->arrival()
                ->code($m['code'])
                ->date($this->normalizeDate($m['date']))
                ->terminal($m['terminal'] ?? null, true, true);

            $arrName = trim($m['name'], ',');

            if (!empty($arrName)) {
                $s->arrival()
                    ->name($arrName);
            }
        }

        $infoTableText = $this->re("/\n( *{$this->opt($this->t('Duration:'))}.+\n(?:.+\n){1,5})\n\s*{$this->opt($this->t('Passenger Name'))}/", $sText);
        $infoTable = $this->createTable($infoTableText, $this->rowColumnPositions($this->inOneRow($infoTableText)));
        // $this->logger->debug('$infoTable = '.print_r( $infoTable,true));
        //$this->logger->error('$routeTableText = '.print_r( $infoTableText,true));

        // Extra
        $s->extra()
            ->duration($this->re("/:\s*(.+?)\s*$/s", $infoTable[0] ?? ''))
            ->miles($this->re("/:\s*(.+?)\s*$/s", $infoTable[1] ?? ''))
            ->aircraft($this->re("/:\s*(.+?)\s*$/s", $infoTable[3] ?? ''), true)
            ->cabin($this->re("/:\s*(.+?)\s*\(\s*[A-Z]{1,2}\s*\)\s*$/s", $infoTable[4] ?? ''))
            ->bookingCode($this->re("/:\s*.+?\s*\(\s*([A-Z]{1,2})\s*\)\s*$/s", $infoTable[4] ?? ''))
            ->status($this->re("/:\s*(.+?)\s*$/s", $infoTable[6] ?? ''))
        ;

        $s->airline()
            ->confirmation($this->re("/:\s*([A-Z\d]+?)\s*$/s", $infoTable[5] ?? ''));

        // General (Travellers)
        $passengerTableText = $this->re("/\n( *{$this->opt($this->t('Passenger Name'))}[\s\S]+?)(?:\n\n\n|\s*$)/", $sText);
        $passengerTable = $this->createTable($passengerTableText, $this->rowColumnPositions($this->inOneRow($passengerTableText)));
        // $this->logger->debug('$passengerTable = '.print_r( $passengerTable,true));
        // $this->logger->debug('$passenderTableText = '.print_r( $passenderTableText,true));

        $travellers = array_filter(array_map('trim', preg_split("/\([^()]+\)\s*\n/",
            preg_replace("/^ *.+\s*/", '', $passengerTable[0] ?? '') . "\n\n")));

        foreach ($travellers as $traveller) {
            if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
                $f->general()
                    ->traveller($traveller, true);
            }
        }

        // Program
        $accounts = array_filter(array_map('trim', preg_split("/\n+/",
            preg_replace("/^ *.+\s*/", '', $passengerTable[1] ?? ''))));

        foreach ($accounts as $account) {
            if (!in_array($account, array_column($f->getAccountNumbers(), 0))) {
                $f->program()
                    ->account($account, false);
            }
        }

        // Issued
        $tickets = array_filter(array_map('trim', preg_split("/\n+/",
            preg_replace("/^ *.+\s*/", '', $passengerTable[2] ?? ''))));

        foreach ($tickets as $ticket) {
            if (preg_match("/^[\d\W]+$/", $ticket)
                && !in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                $f->issued()
                    ->ticket($ticket, false);
            }
        }

        // Segments / Extra
        if (preg_match("/^\s*{$this->opt($this->t('Meal'))}\n([\s\S]+)/", $passengerTable[3] ?? '', $m)) {
            $meals = array_filter(array_map('trim', preg_split("/\n+/", trim($m[1]))));

            if (!empty($meals)) {
                $s->extra()
                    ->meals($meals);
            }
        }

        if (preg_match("/^\s*{$this->opt($this->t('Seat'))}\n([\s\S]+)/", $passengerTable[4] ?? '', $m)) {
            $seats = array_filter(array_map(function ($v) {
                if (preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $v, $m)) {
                    return $m[1];
                }

                return null;
            }, preg_split("/\n+/", trim($m[1]))));

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
        }
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));
        $in = [
            //            // Thu,23­Nov­2023 10:55
            '/^\s*[[:alpha:]\-]+,\s*(\d{1,2})\W?([[:alpha:]]+)\W?(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
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
            $pos = stripos($haystack, $needle);

            if ($pos !== false) {
                return $pos;
            }
        }

        return false;
    }
}
