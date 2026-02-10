<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Schema\Parser\Email\Email;

class VoucherPdf extends \TAccountChecker
{
    public $mailFiles = "bcd/it-286951767.eml";

    public $detectSubject = [
        'en' => 'VENTURA S.P.A. - Passeggero:',
    ];
    public static $dict = [
        'en' => [],
    ];

    private $detectFrom = '@bcdtravel.';

    private $detectCompany = ['bcdtravel.', 'primetals.'];

    private $detectBody = [
        'en' => ['VOUCHER Customer'],
    ];

    private $lang = 'en';

    private $pdfNamePattern = ".+\.pdf";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $found = false;

            foreach ($this->detectCompany as $dCompany) {
                if (stripos($text, $dCompany) !== false || $this->http->XPath->query("//text()[contains(., '" . $dCompany . "')]")->length > 0) {
                    $found = true;

                    break;
                }
            }

            if ($found === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) !== false) {
                        $this->parsePdf($text, $email);
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $found = false;

            foreach ($this->detectCompany as $dCompany) {
                if (stripos($text, $dCompany) !== false || $this->http->XPath->query("//text()[contains(., '" . $dCompany . "')]")->length > 0) {
                    $found = true;

                    break;
                }
            }

            if ($found === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) !== false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

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

    private function parsePdf($textPDF, Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation(str_replace(' ', '', $this->re("#\s+VOUCHER:[ ]*(VO. [ \d]{5,})\s+#", $textPDF)), 'VOUCHER');

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("#\n\s*REF:[ ]*([A-Z\d]{5,})#", $textPDF))
            ->traveller($this->re("#\n\s*Mr/Mrs:\s*\n\s*(.+)#", $textPDF), true)
        ;

        // Hotel
        $hotel = $this->re("#^\s*Dear\s+((.*\n){1,7}?)\s+VOUCHER Customer#", $textPDF);

        if (preg_match("#(.+(?:\n.+)?)\n((?:.+\n){2})\s+(Tel.*|$)#", $hotel, $m)) {
            $h->hotel()
                ->name(preg_replace("#\s+#", ' ', $m[1]))
                ->address(preg_replace("#\s+#", ' ', $m[2]))
            ;
        }

        if (preg_match("#\s+Tel.[ ]*(\d[\d \-]{5,})#", $hotel, $m)) {
            $h->hotel()
                ->phone($m[1])
            ;
        }

        if (preg_match("#\s+Fax.[ ]*(\d[\d \-]{5,})#", $hotel, $m)) {
            $h->hotel()
                ->fax($m[1])
            ;
        }

        // Booked
        if (preg_match("#\s+IN[ ]+([\d\/\.]{6,})[ ]+OUT[ ]+([\d\/\.]{6,})\s+#", $textPDF, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]))
            ;
        }
        $h->booked()
            ->guests($this->re("#\s+NR\. PAX[ ]*(\d+)\s*\n#", $textPDF));

        $roomsText = $this->re("#Pls provide for:.*\n\s*([\s\S]+?)\n\n\n#", $textPDF);
        $rooms = $this->split("#(?:^|\n)(N\. \d+\s*.+?[ ]{3,}in .+)#", $roomsText);

        foreach ($rooms as $room) {
            if (preg_match("#N\. \d+\s*(.+?)[ ]{3,}in .+(\n[ ]{0,}.+)?([ ]{20,}|\n|$)#", $room, $m)) {
                $h->addRoom()
                    ->setType($m[1])
                    ->setDescription(preg_replace("#^\s*\W*#", "", $m[2]))
                ;
            }

            if (preg_match('#Pls provide for:[ ]+Amount#', $textPDF) && preg_match("#\n.*[ ]{20,}(\d[\d,]+)[ ]*([A-Z]{3})$#", $room, $m)) {
                if (!empty($h->getPrice()) && !empty($h->getPrice()->getTotal())) {
                    $h->price()
                        ->total($h->getPrice()->getTotal() + $this->amount($m[1]))
                        ->currency($m[2]);
                } else {
                    $h->price()
                        ->total($this->amount(str_replace(',', '.', $m[1])))
                        ->currency($m[2]);
                }
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*$#", //08/04/2019
        ];
        $out = [
            "$1.$2.$3",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function amount($price)
    {
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
}
