<?php

namespace AwardWallet\Engine\avelo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservationConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "avelo/it-306768641.eml, avelo/it-307092282.eml, avelo/it-354649681.eml, avelo/it-423743171.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
        ],
    ];

    private $detectFrom = "@aveloair.com";
    private $detectSubject = [
        // en
        'Your reservation confirmation #',
        'Your reservation confirmation  #',
    ];
    private $detectBody = [
        'en' => [
            'MANAGE YOUR RESERVATION',
        ],
    ];

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
        if ($this->containsText($text, ['Avelo Airlines']) === false) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->containsText($text, $detectBody) === false) {
                return false;
            }

            if (is_array($detectBody)) {
                foreach ($detectBody as $phrase) {
                    if (strpos($text, $phrase) === false) {
                        continue 2;
                    }
                }
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function isJunk($text)
    {
        $text = $this->re("/^([\s\S]+)\n *{$this->opt($this->t('Important Reporting Information'))}\n/", $text);

        if (!empty($text) && strlen(preg_replace("/\s+/", '', $text)) < 600
            && preg_match("/\n *{$this->opt($this->t('MANAGE YOUR RESERVATION'))}\n\s*{$this->opt($this->t('Purchase Summary'))}\n/i", $text)
            && !preg_match("/\b{$this->opt($this->t('PASSENGERS'))}\b/i", $text)
        ) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                if ($this->isJunk($text) === true) {
                    $email->setIsJunk(true);
                } else {
                    $this->parseEmailPdf($email, $text);
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
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation Number'))} *: *([A-Z\d]{5,7})\n/", $textPdf))
        ;
        $travellerText = $this->re("/\n *{$this->opt($this->t("PASSENGERS"))} {2,}.+\n+([\s\S]+?)\n+ *(.+ {3,}Flight +[A-Z\d]{2} ?\d{1,5}|{$this->opt($this->t('Reservation'))}|{$this->opt($this->t('Purchase Summary'))})/", $textPdf);
        $traveller = preg_split("/\n{2,}/", $travellerText);
        $traveller = preg_replace("/^ {15,}.*/m", '', $traveller);
        $traveller = preg_replace("/^ {0,10}(\S.*?) {2,}.*/m", '$1', $traveller);
        $traveller = preg_replace("/\n\w+\s*$/", '', $traveller);
        $traveller = preg_replace("/\s+/", ' ', $traveller);
        $f->general()
            ->travellers(array_map('trim', $traveller));

        $re = "/ {4,}({$this->opt($this->t('Flight'))} .+(?:.*\n+){0,4}? *{$this->opt($this->t('DEPARTING'))} {2,}{$this->opt($this->t('ARRIVING'))}\n)/";
        $segments = $this->split($re, $textPdf);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            $tableText = $this->re("/\n *{$this->opt($this->t('DEPARTING'))} {2,}{$this->opt($this->t('ARRIVING'))}\n([\s\S]+?)\n\s*{$this->opt($this->t('Duration'))}/", $stext);
            $table = $this->createTable($tableText, $this->columnPositions($this->inOneRow($tableText)));

            if (preg_match("/^\s*{$this->opt($this->t('Flight'))}\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\n/", $stext, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            if (count($table) == 2) {
                $regexp = "/^\s*(?<name>.+?)\s*\((?<code>[A-Z]{3})\)\s*\n\s*(?<date>.+?)\s*$/s";

                if (preg_match($regexp, $table[0], $m)) {
                    $s->departure()
                        ->name($m['name'])
                        ->code($m['code'])
                        ->date($this->normalizeDate($m['date']));
                }

                if (preg_match($regexp, $table[1], $m)) {
                    $s->arrival()
                        ->name($m['name'])
                        ->code($m['code'])
                        ->date($this->normalizeDate($m['date']));
                }
            }
        }

        // Price
        $priceBlock = $this->re("/{$this->opt($this->t('PAYMENT DETAILS'))}\n+(.+{$this->opt($this->t('Airfare:'))}[\s\S]+{$this->opt($this->t('Total:'))}.+)\n/", $textPdf);

        $headers = $this->columnPositions($this->inOneRow($priceBlock));
        $pos = strlen($this->re("/^(.+){$this->opt($this->t('Airfare:'))}/m", $priceBlock));
        $headerNew = [];

        foreach ($headers as $h) {
            if ($pos > $h - 10 && $pos < $h + 10) {
                $headerNew = [0, $h];
            }
        }
        $table = $this->createTable($priceBlock, $headerNew);
        $pricetext = $table[1] ?? '';

        $total = $this->getTotal($this->re("/^ *{$this->opt($this->t('Total:'))} *(.+)/m", $pricetext));
        $f->price()
            ->total($total['amount'])
            ->currency($total['currency'])
        ;

        $cost = $this->getTotal($this->re("/^{$this->opt($this->t('Airfare:'))} *(.+)/m", $pricetext));
        $f->price()
            ->cost($cost['amount']);
        $feeRows = array_filter(explode("\n", $this->re("/{$this->opt($this->t('Airfare:'))}.+\n([\s\S]+)\n{$this->opt($this->t('Total:'))}/", $pricetext)));

        foreach ($feeRows as $row) {
            if (preg_match("/(.+): {2,}(.+)/", $row, $m)) {
                $f->price()
                    ->fee($m[1], $this->getTotal($m[2])['amount']);
            } else {
                $f->price()
                    ->fee(null, null);
            }
        }

        return $email;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
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

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }
}
