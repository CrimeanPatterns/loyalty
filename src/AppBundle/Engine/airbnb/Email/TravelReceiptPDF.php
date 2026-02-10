<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-74374749.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $detectFormat = [
        'en' => ['Booked by', 'Security Deposit'],
    ];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                foreach ($this->detectFormat as $lang => $words) {
                    if (strpos($text, 'Airbnb Travel Receipt') && strpos($text, $words[0]) && strpos($text, $words[1])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airbnb\.com$/', $from) > 0;
    }

    public function parsePDF(Email $email, $text)
    {
        $h = $email->add()->hotel();
        $h->general()
            ->confirmation($this->re("/Conﬁrmation\s*Code\s*([A-Z\d]+)/", $text), 'Confirmation Code');

        $infoBlock = $this->re("/(\s{3}Check In.+)Cost per traveler/ms", $text);
        $colPosition = strpos($this->re("/(\s{3}Check In.+)/", $text), 'Charges') - 5;
        $infoTable = $this->splitCols($infoBlock, [0, $colPosition]);

        $travellers = explode("\n", $this->re("/\d+ Travellers on this trip\s*(.+)/ums", $infoTable[0]));
        $h->general()
            ->travellers(array_filter($travellers), true);

        if (preg_match("/Check In\s+Check Out\n(\d+\s*\w+\s\d{4})\s*[]\s*(\d+\s*\w+\s\d{4})/u", $infoTable[0], $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime($m[2]));
        }

        if (preg_match("/Entire home\/apt\s+(.+)\n(\d.+)\s+Hosted\sby\D+Phone\:.([+][\d\s]+)\n/msu", $infoTable[0], $m)) {
            $h->hotel()
                ->name(str_replace("\n", ' ', $m[1]))
                ->address(str_replace("\n", ' ', $m[2]))
                ->phone($m[3]);
        }

        $h->booked()
            ->guests($this->re("/(\d+)\s*Travellers on this trip/", $text));

        $total = $this->re("/Total\s*\D([\d\.]+)\s*/", $text);
        $currency = $this->re("/Total\s*\D[\d\.]+\s*([A-Z]{3})/", $text);

        if ($total && $currency) {
            $h->price()
                ->total($total)
                ->currency($currency);
        }

        $cost = $this->re("/[×]\s*\d+\s*night\s*\D([\d\.]+)\s*[A-Z]{3}/u", $text);

        if (!empty($cost)) {
            $h->price()
                ->cost($cost);
        }

        $feeBlock = $this->re("/[×]\s*\d\s*night\s*\D[\d\.]+\s*[A-Z]{3}\n\s+(.+)\s+Total\s+.+Total Paid/ms", $infoTable[1]);
        $feeTable = array_filter(explode("\n", $feeBlock));

        foreach ($feeTable as $feeRow) {
            $feeName = trim($this->re("/\s*(\D+)\s+/", $feeRow));
            $feeSum = $this->re("/\s+\D([\d\.]+)/", $feeRow);

            if ($feeName && $feeSum) {
                $h->price()
                    ->fee($feeName, $feeSum);
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->parsePDF($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = strstr(strstr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
