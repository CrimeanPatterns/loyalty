<?php

namespace AwardWallet\Engine\aerlingus\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = ""; //bcd

    public $reFrom = ["aerlingus."];
    public $reBodyPdf = [
        'en' => ['BOARDING PASS', 'Travel Information'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Departing'     => 'Departing',
            'Flight Number' => 'Flight Number',
        ],
    ];
    private $keywordProv = 'Aer Lingus';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectPdf($text) && $this->assignLang($text)) {
                        if (!$this->parseEmailPdf($text, $email)) {
                            return $email;
                        }
                    }
                }
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)
                && $this->detectPdf($text) && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $r = $email->add()->flight();

        $r->general()
            ->noConfirmation()
            ->traveller($this->re("#{$this->opt($this->t('Passenger'))}[ ]{3,}.+\n(.+?)[ ]{3,}#", $textPDF));

        $r->issued()
            ->ticket($this->re("#{$this->opt($this->t('Passenger'))}[ ]{3,}ELECTRONIC.+\n.+?[ ]{3,}(\d{5,})#",
                $textPDF), false);

        $s = $r->addSegment();

        if (preg_match("#\n[ ]*{$this->opt($this->t('From'))}\s+(?<name>.+?)[ ]{3,}(?<code>[A-Z]{3})[ ]*(?:TML (?<terminal>.+?)(?:[ ]{3,}|.*))?\n#",
            $textPDF, $m)) {
            $s->departure()
                ->name($m['name'])
                ->code($m['code']);

            if (isset($m['terminal']) && !empty($m['terminal'])) {
                $s->departure()->terminal($m['terminal']);
            }
        }

        if (preg_match("#\n[ ]*{$this->opt($this->t('to'))}\s+(?<name>.+?)[ ]{3,}(?<code>[A-Z]{3})[ ]*(?:TML (?<terminal>.+?)(?:[ ]{3,}|.*))?\n#",
            $textPDF, $m)) {
            $s->arrival()
                ->noDate()
                ->name($m['name'])
                ->code($m['code']);

            if (isset($m['terminal']) && !empty($m['terminal'])) {
                $s->arrival()->terminal($m['terminal']);
            }
        }

        $text = strstr($textPDF, $this->t('Travel Information'), true);

        if (preg_match("#\n([ ]*{$this->opt($this->t('Flight Number'))}.+)#s", $text, $m)) {
            $table = $this->splitCols($m[1]);

            if (count($table) !== 5) {
                $this->logger->debug('other format');

                return false;
            }

            if (preg_match("#{$this->opt($this->t('Flight Number'))}\s+(?<name>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<number>\d+)\s*$#",
                $table[0], $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }
            $year = date('Y', $this->date);

            if (preg_match("#{$this->opt($this->t('Date'))}\s+(?<day>\d+)\s*(?<mnth>\w+)\s*$#", $table[1], $m)) {
                $date = strtotime($m['day'] . ' ' . $m['mnth'] . ' ' . $year);

                if ($date < $this->date) {
                    $date = strtotime("+1 year", $date);
                }

                if (preg_match("#{$this->opt($this->t('Departing'))}\s+(?<time>\d+:\d+)\s*$#", $table[2], $m)) {
                    $s->departure()->date(strtotime($m['time'], $date));
                }
            }

            if (preg_match("#{$this->opt($this->t('Seat'))}\s+(\d+[A-z])\s*\s*$#", $table[3], $m)) {
                $s->extra()->seat($m[1]);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectPdf($body)
    {
        if (isset($this->reBodyPdf)) {
            foreach ($this->reBodyPdf as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Departing"], $words["Flight Number"])) {// здесь выбирать пару полей которые всегда присутствуют в данном формате
                if (stripos($body, $words["Departing"]) !== false && stripos($body,
                        $words["Flight Number"]) !== false
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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
}
