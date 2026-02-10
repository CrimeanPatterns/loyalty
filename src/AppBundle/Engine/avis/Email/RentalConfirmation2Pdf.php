<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalConfirmation2Pdf extends \TAccountChecker
{
    public $mailFiles = "avis/it-188404901.eml";
    public $detectFrom = "avis@e.avis.com";
    public $detectSubject = [
        "en" => "Avis Rental Confirmation:",
    ];
    public $detectBodyRe = [
        "en" => ["RENTAL PERIOD", "ACKNOWLEDGEMENTS, AGREEMENTS AND PRIVACY CONSENTS"],
    ];
    public $pdfNamePattern = ".*\.pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public $lang = "en";
    public $subject;

    public function parsePdf(Email $email, $text)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Avis Rental Confirmation:'))}\s*(\d{5,})/u", $this->subject));

        $r->pickup()
            ->location($this->re("/{$this->opt($this->t('AT:'))}\s*(.+)\s+(?:PHONE:)/", $text))
            ->date($this->normalizeDate($this->re("/\s+{$this->opt($this->t("RENTED:"))}\s+(.+)\s+{$this->opt($this->t("AT:"))}/", $text)))
        ;
        $r->dropoff()
            ->location($this->re("/{$this->opt($this->t('AT:'))}\s*(.+)\s+(?:RATE CODE:)/", $text))
            ->date($this->normalizeDate($this->re("/\s+{$this->opt($this->t("DUE IN:"))}\s+(.+)\s+{$this->opt($this->t("AT:"))}/", $text)));

        $r->car()
            ->model($this->re("/{$this->opt($this->t('PLATE#'))}.+\n^(.+)[ ]{30,}/um", $text));

        if (preg_match("/{$this->opt($this->t('CHARGES AND AMOUNTS.'))}\s*(?<currency>\D)\s+(?<total>[\d\.\,]+)\s*\n/", $text, $m)) {
            $currency = $this->currency($m['currency']);
            $r->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers["subject"], 'Avis') === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if ($this->containsText($text, ['provided by Avis', 'RENTAL PERIOD']) !== true) {
                return false;
            }

            $subText = substr($text, 0, 500);

            foreach ($this->detectBodyRe as $detectBody) {
                foreach ($detectBody as $reDBody) {
                    if (preg_match("/" . $reDBody . "/us", $subText)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            $subText = substr($text, 0, 500);

            foreach ($this->detectBodyRe as $lang => $detectBody) {
                foreach ($detectBody as $reDBody) {
                    if (preg_match("/" . $reDBody . "/", $subText)) {
                        $this->lang = $lang;

                        $this->parsePdf($email, $text);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('$str = '.print_r( $date,true));

        $in = [
            // 16AUG22/1123
            "#^(\d+)(\D+)(\d{2})\/(\d{2})(\d{2})\s*$#iu",
        ];
        $out = [
            "$1 $2 20$3, $4:$5",
        ];
        $date = preg_replace($in, $out, $date);

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $date = str_replace($m[1], $en, $date);
//            }
//        }

        return strtotime($date);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> '$',
            '£'=> 'GBP',
            'R'=> 'ZAR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
