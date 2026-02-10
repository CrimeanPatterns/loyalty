<?php

namespace AwardWallet\Engine\condor\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourBoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "condor/it-380231217.eml, condor/it-674820738.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Boarding Pass' => 'Boarding Pass',
            //            'Name' => '',
            //            'Date' => '',
            //            'Class' => '',
            //            'Booking Ref' => '',
            //            'Ticket' => '',
            //            'FQTV' => '',
            //            'Boarding' => '',
            //            'Terminal' => '',
            //            'Seat' => '',
            'Flight'    => 'Flight',
            'Departure' => 'Departure',
        ],
        'de' => [
            'Boarding Pass' => 'Bordkarte',
            'Name'          => 'Name',
            'Date'          => 'Datum',
            'Class'         => 'Klasse',
            'Booking Ref'   => 'Buchungsnr.',
            'Ticket'        => 'Ticket',
            'FQTV'          => 'FQTV',
            'Boarding'      => 'Boarding',
            'Terminal'      => 'Terminal',
            'Seat'          => ['Sitzplatz', 'Seat'],
            'Flight'        => 'Flug',
            'Departure'     => 'Abflugzeit',
        ],
    ];

    private $detectFrom = "noreply@condor.com";
    private $detectSubject = [
        // en
        'Your boarding pass(es)',
        // de
        'Ihre Bordkarte(n)',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]condor\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

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
        if ($this->containsText($text, ['condor.com/']) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Boarding Pass'])
                && $this->containsText($text, $dict['Boarding Pass']) === true
                && !empty($dict['Flight'])
                && $this->containsText($text, $dict['Flight']) === true
                && !empty($dict['Departure'])
                && $this->containsText($text, $dict['Departure']) === true
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
        $bps = $this->split("/\n *({$this->opt($this->t('Boarding Pass'))}\n)/", "\n\n" . $textPdf);

        foreach ($bps as $bpText) {
            $f = null;
            $conf = $this->re("/ {2,}{$this->opt($this->t('Booking Ref'))} *([A-Z\d]{5,7})\n/", $bpText);
            $ticket = $this->re("/ {2,}{$this->opt($this->t('Ticket'))} *(\d{8,})\n/", $bpText);
            $account = $this->re("/ {2,}{$this->opt($this->t('FQTV'))} *([\dA-Z]{5,})\n/", $bpText);
            $traveller = $this->re("/\n *{$this->opt($this->t('Name'))} *(\S.+?) {3,}/", $bpText);
            $traveller = preg_replace("/^\s*([A-Z][A-Z \-]*?)\s*\\/\s*([A-Z][A-Z \-]*?)(?:(?:\s+DR)?\s+(MRS|MR|MS|MISS|CHD))?\s*$/i",
                '$2 $1', $traveller);

            if (!empty($conf) && !empty($tickets)) {
                foreach ($email->getItineraries() as $it) {
                    /** @var \AwardWallet\Schema\Parser\Common\Flight $it */
                    if ($it->getType() === 'flight' && !empty($iTickets = array_column($it->getTicketNumbers(),
                            0)) && strncasecmp($ticket, $iTickets[0], 3) === 0
                        && !empty($iConf = array_column($it->getConfirmationNumbers(),
                            0)) && $iConf[0] === $conf
                    ) {
                        $f = $it;

                        if (!in_array($ticket, $iTickets)) {
                            $f->issued()->tickets($ticket, false);
                        }

                        if (!in_array($traveller, array_column($it->getTravellers(), 0))) {
                            $f->general()
                                ->traveller($traveller, true);
                        }

                        if (!empty($account) && !in_array($account, array_column($it->getAccountNumbers(), 0))) {
                            $f->program()
                                ->account($account, false);
                        }
                    }
                }
            }

            if (empty($f)) {
                $f = $email->add()->flight();

                // General
                $f->general()
                    ->confirmation($conf)
                    ->traveller($traveller, true);

                // Issued
                $f->issued()
                    ->ticket($ticket, false);

                // Program
                if (!empty($account)) {
                    $f->program()
                        ->account($account, false);
                }
            }

            $s = $f->addSegment();

            $routeText = $this->re("/\n\s*{$this->opt($this->t('Class'))} +.+\n{2,}\s*(\S.+)\n+\s*{$this->opt($this->t('Boarding'))}\b.* +(?:{$this->opt($this->t('Terminal'))}|{$this->opt($this->t('Seat'))})/", $bpText);
            $route = preg_split("/\s{2,}/", trim($routeText));

            if (count($route) === 2) {
                $s->departure()
                    ->noCode()
                    ->name($route[0]);
                $s->arrival()
                    ->noCode()
                    ->name($route[1]);
            }

            $table1 = $this->createTable($this->re("/\n+( *{$this->opt($this->t('Boarding'))}\b.* +(?:{$this->opt($this->t('Terminal'))}|{$this->opt($this->t('Seat'))}) .+\n\s*.+)\n+\s*{$this->opt($this->t('Flight'))} +{$this->opt($this->t('Departure'))}/", $bpText));

            $s->departure()
                ->terminal($this->re("/^\s*{$this->opt($this->t('Terminal'))}\s*\n\s*[\w ]+\s*$/", $table1[1] ?? ''), true, true);

            foreach ([4, 2] as $col) {
                $seat = $this->re("/^\s*{$this->opt($this->t('Seat'))}\s*\n\s*(\d{1,3}[A-Z])\s*$/",
                        $table1[$col] ?? '');

                if (!empty($seat)) {
                    $s->extra()
                        ->seat($seat);
                }
                $addSeat = $this->re("/^.*{$this->opt($this->t('Seat'))}.*\s*\n\s*(\d{1,3}[A-Z])\s*$/",
                    $table1[$col + 1] ?? '');

                if (!empty($addSeat)) {
                    $s->extra()
                        ->seat($addSeat);
                }
            }

            $table2 = $this->createTable($this->re("/\n+( *{$this->opt($this->t('Flight'))} +{$this->opt($this->t('Departure'))}.+\n+\s*.+)\n{2}/", $bpText));

            if (preg_match("/{$this->opt($this->t('Flight'))}\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $table2[0] ?? '', $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            $date = $this->re("/\n *{$this->opt($this->t('Date'))} *(\S.+?) {3,}/", $bpText);

            $s->departure()
                ->date($this->normalizeDate($date . ', ' . $this->re("/^.*\n\s*(\d{1,2}:\d{2})\s*$/", $table2[1] ?? '')));
            $s->arrival()
                ->date($this->normalizeDate($date . ', ' . $this->re("/^.*\n\s*(\d{1,2}:\d{2})\s*$/", $table2[2] ?? '')));

            $s->extra()
                ->cabin($this->re("/\n {0,10}{$this->opt($this->t('Class'))} *(\S.+?) {3,}/", $bpText))
                ->bookingCode($this->re("/^\s*{$this->opt($this->t('Class'))}\s+([A-Z]{1,2})\s*$/", $table2[6] ?? ''))
            ;
        }

        return $email;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
        ];
        $out = [
            // "$2 $3 $4, $1",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
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
