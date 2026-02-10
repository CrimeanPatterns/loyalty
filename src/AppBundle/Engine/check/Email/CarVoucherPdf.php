<?php

namespace AwardWallet\Engine\check\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarVoucherPdf extends \TAccountChecker
{
    public $mailFiles = "check/it-38248107.eml , check/it-53632109.eml"; // +2 bcdtravel(html,pdf)[no]

    public $lang = "de";

    public static $dictionary = [
        "de" => [
            'Buchungsübersicht' => ['Buchungsübersicht', 'Voucher', 'Voucher (Reservierungsbestätigung) zur Abgabe beim Vermieter vor Ort'],
            'Station'           => ['Abholort', 'Station', 'Rückgabeort'],
        ],
    ];

    protected $pdfPattern = ".*\.pdf";

    private $detectFrom = "check24.de";

    private $detectSubject = [
        "de" => "Voucher für Ihre Mietwagenbuchung Nr.",
    ];
    private $detectCompany = "CHECK24";

    private $detectBody = [
        "de" => ["Ihre Buchungsdokumente"],
    ];

    private $patterns = [
        'phone' => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($textPdf, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($textPdf, $dBody) !== false) {
                        $this->parsePdf($email, $textPdf);
                    }
                    //                    return true;
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

    private function parsePdf(Email $email, string $text)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()
            ->confirmation($this->re("#Ihre Buchungsdokumente\s+zur Mietwagenbuchung Nr\.[ ]*(\w+)\n#", $text));

        $itinText = strstr($text, $this->t("Buchungsübersicht\n"));

        if (empty($itinText)) {
            $itinText = strstr($text, 'Voucher');
        }
        $itinText = strstr($itinText, $this->t("Ihre Versicherungsleistungen\n"), true);

        $r = $email->add()->rental();

        $info = $this->re("#\n\s*" . $this->t('Ihr Mietwagen') . "\n(.+)#s", $itinText);
        // General
        $r->general()
            ->noConfirmation()
            ->traveller($this->re("#\n\s*Hauptfahrer[ ]+(.+)#", $info));

        $table1Text = $this->re("#\b" . $this->opt($this->t('Buchungsübersicht')) . "\s*\n([ ]*Abholung[\s\S]+?)\n\s*" . $this->t('Ihr Mietwagen') . "\n#", $itinText);

        if (preg_match("/^\s*{$this->t('Adresse')}.*[A-Z]{2,}\s{$this->t('Adresse')}/m", $table1Text, $m)) {
            $table1Text = preg_replace("/([A-Z]{2,})\s(Adresse)/u", "$1          $2", $table1Text);
        }
        $table = $this->splitCols($table1Text);

        if (count($table) == 2) {
            // Pick Up
            if (preg_match("#Datum[ ]+(.+)#", $table[0], $m) && preg_match("#Uhrzeit[ ]+(.+)#", $table[0], $m1)) {
                $r->pickup()
                    ->date($this->normalizeDate(trim($m[1]) . ', ' . trim($m1[1])));
            }

            if (preg_match("#{$this->opt($this->t('Station'))}[ ]+(.+(?:\n[ ]{20,}.*){0,5})#", $table[0], $m) && preg_match("#Adresse[ ]+(.+(?:\n[ ]{20,}.*){0,5})#", $table[0], $m1)) {
                $m1[1] = preg_replace('#\*{3}\s*MUST HAVE FLIGHT INFO\s*\*{3}#', '', $m1[1]);
                $r->pickup()
                    ->location(trim(preg_replace("#\s+#", ' ', trim($m[1]) . ', ' . trim($m1[1])), ', '));
            }

            if (preg_match("#Telefon[ ]+(.+)#", $table[0], $m)) {
                $r->pickup()
                    ->phone(trim($m[1]));
            }

            if (preg_match("#Öffnungszeiten \([^)]+\)[ ]{2,}(.+)#", $table[0], $m)) {
                $r->pickup()
                    ->openingHours(trim($m[1]));
            }

            // Drop Off
            if (preg_match("#Datum[ ]+(.+)#", $table[1], $m) && preg_match("#Uhrzeit[ ]+(.+)#", $table[1], $m1)) {
                $r->dropoff()
                    ->date($this->normalizeDate(trim($m[1]) . ', ' . trim($m1[1])));
            }

            if (preg_match("#{$this->opt($this->t('Station'))}[ ]+(.+(?:\n[ ]{20,}.*){0,5})#", $table[1], $m) && preg_match("#Adresse[ ]+(.+(?:\n[ ]{20,}.*){0,5})#", $table[1], $m1)) {
                $m1[1] = preg_replace('#\*{3}\s*MUST HAVE FLIGHT INFO\s*\*{3}#', '', $m1[1]);
                $r->dropoff()
                    ->location(trim(preg_replace("#\s+#", ' ', trim($m[1]) . ', ' . trim($m1[1])), ', '));
            } else {
                //This is not a good idea, this is a crutch. it-53632109.eml
                if (preg_match("#((?: Sta)(?:t|)(?:i|)(?:o|))#", $table[0], $ma)) {
                    if (preg_match("#^((?:t|)(?:i|)(?:on \s{2,}))#m", $table[1], $mb)) {
                        if (strpos($ma[1] . $mb[1], "Station") !== false) {
                            $table[1] = preg_replace("#" . $mb[1] . "#", "Station         ", $table[1]);
                        }
                    }

                    if (preg_match("#((?: Adr)(?:e|)(?:s|)(?:s|)(?:e|))#", $table[0], $ma)) {
                        if (preg_match("#^((?:e|)(?:s|)(?:se \s{2,}))#m", $table[1], $mb)) {
                            if (strpos($ma[1] . $mb[1], "Adresse") !== false) {
                                $table[1] = preg_replace("#" . $mb[1] . "#", "Adresse         ", $table[1]);
                            }
                        }

                        if (preg_match("#Station[ ]+(.+(?:\n[ ]{20,}.*){0,5})#", $table[1], $m) && preg_match("#Adresse[ ]+(.+(?:\n[ ]{20,}.*){0,5})#", $table[1], $m1)) {
                            $m1[1] = preg_replace('#\*{3}\s*MUST HAVE FLIGHT INFO\s*\*{3}#', '', $m1[1]);
                            $r->dropoff()
                                ->location(trim(preg_replace("#\s+#", ' ', trim($m[1]) . ', ' . trim($m1[1])), ', '));
                        }
                    }
                }
            }

            if (preg_match("#Telefon[ ]+(.+)#", $table[1], $m)) {
                $r->dropoff()
                    ->phone(trim($m[1]));
            }

            if (preg_match("#Öffnungszeiten \([^)]+\)[ ]{2,}(.+)#", $table[1], $m)) {
                $r->dropoff()
                    ->openingHours(trim($m[1]));
            }
        }

        // Car
        $r->car()
            ->type($this->re("#\n\s*Fahrzeugeigenschaften[ ]+(.+)#", $info))
            ->model($this->re("#\n\s*Fahrzeugkategorie[ ]+(.+)#", $info))
        ;

        // Extra
        $r->extra()
            ->company($this->re("#\n\s*Vermieter[ ]+(.+)#", $info));

        // Price
        $total = $this->re("#\n\s*Gesamtpreis[ ]{5,}(.+)#", $text);

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $r->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->http->log('$str = '.print_r( $str,true));
        $in = [
            "#^\s*(\d+)\.(\d+)\.(\d{4})[\s,]+(\d+:\d+(?:\s*[ap]m)?)\s*(Uhr)?\s*$#i", //30.08.2019, 13:30 Uhr
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }
        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {
            return preg_quote($v, '#');
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
                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
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

    private function ColsPos($table, $correct = 5)
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

    private function amount($price)
    {
        $price = str_replace(',', '.', $price);
        $price = str_replace(' ', '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
