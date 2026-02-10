<?php

namespace AwardWallet\Engine\fairmont\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelPDF extends \TAccountChecker
{
    public $mailFiles = "fairmont/it-156122733.eml, fairmont/it-158192747.eml";
    public $subjects = [
        'receipts@https://www.fairmont.com/',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'T' => ['T', 'Tel:', 'Tel'],
            'F' => ['F', 'Fax:', 'Fax'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (
                (strpos($text, 'Thank you for choosing to stay with Fairmont') !== false
                || strpos($text, 'Thank you for choosing to stay at The') !== false
                || strpos($text, 'Thank you for choosing to stay at Fairmont') !== false
                )

                && strpos($text, 'Folio') !== false && strpos($text, 'Room') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParseHotelPDF(Email $email, $text)
    {
        //$this->logger->debug($text);

        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/(?:[N°]*\s*Folio\s*|Confir)\s*[#]*\s*\:\s*(\d{4,8})/", $text));

        $traveller = $this->re("/\n\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+[ ]{10,}\w+\s*\/\s*Arrival/u", $text);

        if (empty($traveller)) {
            $traveller = $this->re("/\n\s+(M[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\n\s*Arrival/u", $text);
        }

        if (empty($traveller)) {
            $traveller = $this->re("/\n\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+[ ]{10,}\s*Departure/u", $text);
        }

        if (!empty($traveller) && stripos($traveller, 'US') === false) {
            $h->general()
                ->traveller($traveller);
        }

        $hotelTable = $this->splitCols($text, [0, 50]);

        $hotelInfo = $this->re("/(?:Room|Conf Num)\s*\:\s*\d*\n+\s*(.+\n{$this->opt($this->t('T'))}\s+[\+\(\)\-\d\s]+{$this->opt($this->t('F'))}[\d\s\(\)\-\+]+\n)/su", $hotelTable[0]);

        if (empty($hotelInfo)) {
            $hotelInfo = $this->re("/^((?:.+\n){2}\n*(?:T|Tel\:|Tel)\s+[\+\(\)\-\d\s]+(?:(?:F|Fax\:|Fax)[\d\s\(\)\-\+]+\n|\n))/su", $hotelTable[0]);
        }

        if (preg_match("/^(?<hotelName>\D+)\n(?<address>(?:.+|.+\n.+))\n\s*{$this->opt($this->t('T'))}\s*(?<phone>[+\s\d\-\(\)]+)\s+{$this->opt($this->t('F'))}\s*(?<fax>[+\s\d\-\(\)]+)/u", $hotelInfo, $m)
            || preg_match("/^(?<address>\d.+)\n\s+{$this->opt($this->t('T'))}\s*(?<phone>[\s\d\-\(\)]+)\s+{$this->opt($this->t('F'))}\s*(?<fax>[\s\d\-\(\)]+).+Reg/us", $hotelInfo, $m)
            || preg_match("/^(?<address>\d.+)\n\s*{$this->opt($this->t('T'))}\s*(?<phone>[\+\s\d\-\(\)]+)\s+{$this->opt($this->t('F'))}\s*(?<fax>[\+\s\d\-\(\)]+)/us", $hotelInfo, $m)
            || preg_match("/^(?<address>\d.+)\n\s*{$this->opt($this->t('T'))}\s*(?<phone>[\+\s\d\-\(\)]+)\n+$/us", $hotelInfo, $m)
        ) {
            if (!isset($m['hotelName'])) {
                $m['hotelName'] = $this->re("/Thank you for choosing to stay with (\D+)\n\s+Merci/u", $text);

                if (empty($m['hotelName'])) {
                    $m['hotelName'] = $this->re("/Thank you for choosing to stay (?:with|at) (\D+)$/u", $text);
                }
            }

            $h->hotel()
                ->name($m['hotelName'])
                ->address(preg_replace("/(?:\s+\n\s+|\n)/", " ", $m['address']))
                ->phone($m['phone']);

            if (isset($m['fax']) && !empty($m['fax'])) {
                $h->hotel()
                    ->fax($m['fax']);
            }
        }

        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/Arrival\s*\:\s*([\d\/\-]+)/", $text)))
            ->checkOut($this->normalizeDate($this->re("/Departure\s*\:\s*([\d\/\-]+)/", $text)));

        $roomNumer = $this->re("/Room\s*\:\s*(\d+)\n/", $text);

        if (!empty($roomNumer)) {
            $room = $h->addRoom();
            $room->setDescription('Room: ' . $roomNumer);
        }

        $h->price()
            ->total(str_replace(',', '', $this->re("/(?:Total|Total Charges)\s*([\d\.\,]+)/", $text)));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'Folio') !== false) {
                $this->ParseHotelPDF($email, $text);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = '.print_r( $date,true));

        $in = [
            // 03-23-22
            "/^(\d+)\-(\d+)\-(\d+)$/iu",
        ];
        $out = [
            "$2.$1.20$3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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
