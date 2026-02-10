<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "avis/it-131958932.eml, avis/it-185982422.eml";

    public $detectFrom = "avis@e.avis.com";
    public $detectSubject = [
        "en" => "Avis Rental Confirmation:",
        "fr" => "Avis Rental Confirmation:",
    ];
    public $detectBodyRe = [
        "en" => ["RENTAL AGREEMENT NUMBER .*"],
        "fr" => ["NUMÉRO DE CONTRAT .*"],
    ];
    public $pdfNamePattern = ".*\.pdf";

    public static $dictionary = [
        "en" => [
            //            'RENTAL AGREEMENT NUMBER' => '',
            //            'RESERVATION NUMBER' => '',
            //            'Avis Car Number' => '',
        ],

        "fr" => [
            'RENTAL AGREEMENT NUMBER' => 'NUMÉRO DE CONTRAT',
            'RESERVATION NUMBER'      => 'NUMÉRO DE RÉSERVATION',
            'Customer Name'           => 'Nom du client',
            'Pickup Date/Time'        => 'Date/Heure Départ',
            'Return Date/Time'        => 'Date/Heure Retour',
            'Pickup Location'         => 'Agence Départ',
            'Return Location'         => 'Agence Retour',
            'Veh Description'         => 'Information Véh.',
        ],
    ];

    public $lang = "en";

    public function parsePdf(Email $email, $text)
    {
        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('RENTAL AGREEMENT NUMBER'))} *(\d{5,})(?:\s|\n)/u", $text), ((array) $this->t('RENTAL AGREEMENT NUMBER'))[0])
            ->traveller(preg_replace("/^\s*(.+?)\s*,\s*(.+?)\s*$/", '$2 $1',
                $this->re("/\n\s*{$this->opt($this->t('Customer Name'))} *: *([[:alpha:]\-, ]+?)\.? {2,}/", $text)), true)
        ;

        $conf = $this->re("/{$this->opt($this->t('RESERVATION NUMBER'))} *([\w\-]{5,})\s+/", $text);

        if (!empty($conf)) {
            $r->general()
                ->confirmation($conf, ((array) $this->t('RESERVATION NUMBER'))[0]);
        }

        // Pick Up, Drop Off
        $r->pickup()
            ->date($this->normalizeDate($this->re("/\s+{$this->opt($this->t("Pickup Date/Time"))} *: *(.+?) {2,}/", $text)))
        ;
        $r->dropoff()
            ->date($this->normalizeDate($this->re("/\s+{$this->opt($this->t("Return Date/Time"))} *: *(.+?)\n/", $text)))
        ;

        if (preg_match("/\n(( *{$this->opt($this->t('Pickup Location'))} *: *.*){$this->opt($this->t('Return Location'))}(.*\n)+?)\n/", $text, $m)) {
            $table = $this->SplitCols($m[1], [0, strlen($m[2]) - 3]);
            $r->pickup()
                ->location(preg_replace("/\s*\n\s*/", ', ',
                    trim($this->re("/^\s*{$this->opt($this->t("Pickup Location"))} *: *(.+)/s", $table[0]))));
            $r->dropoff()
                ->location(preg_replace("/\s*\n\s*/", ', ',
                    trim($this->re("/^\s*{$this->opt($this->t("Return Location"))} *: *(.+)/s", $table[1]))))
            ;
        }

        // Car
        $r->car()
            ->model($this->re("/\s+{$this->opt($this->t('Veh Description'))} *: *(.+)/", $text));

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

            if ($this->containsText($text, ['WWW.AVIS.COM', 'Avis Car Number']) !== true) {
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

                        continue 3;
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
//         $this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            // JAN 15,2022@11:15 AM
            "#^\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*@\s*(\d{1,2}:\d{1,2}(?:\s*[ap]m))\s*$#iu",
        ];
        $out = [
            "$2 $1 $3, $4",
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
            '$'=> 'USD',
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
