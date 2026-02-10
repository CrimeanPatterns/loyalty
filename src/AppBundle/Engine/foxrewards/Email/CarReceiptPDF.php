<?php

namespace AwardWallet\Engine\foxrewards\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "foxrewards/it-126998324.eml";
    public $subjects = [
        'FOX RENT A CAR INC',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $bodyText;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tsdnotify.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'RECEIPT') !== false
                && strpos($text, 'foxrentacarblog.com') !== false
                && strpos($text, 'Closed rental subject to final audit') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tsdnotify\.com$/', $from) > 0;
    }

    public function ParseCarPDF(Email $email, $text)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/RA\s*#\s*:\s*([A-Z\-\d]+)\n/", $text))
            ->traveller($this->re("/Renter\s*\:*\s*([A-Z\s]+)\n/", $text));

        $r->pickup()
            ->location(str_replace("\n", ", ", $this->re("/RECEIPT\n+(.+)\n\nRA\s*[#]/us", $text)))
            ->phone($this->re("/Rental Extensions [#]\s*([\(\)\s\d\-]+)/", $text));

        $r->dropoff()
            ->same();

        $r->pickup()
            ->date($this->normalizeDate($this->re("/Date\/Time Pickup :\s*(.+)\n/", $text)));

        $r->dropoff()
            ->date($this->normalizeDate($this->re("/Date\/Time Return :\s*(.+)\n/", $text)));

        $r->price()
            ->total($this->re("/TOTAL CHARGES:\s*\D+(\d[\d\.\,]+)/u", $text))
            ->currency($this->re("/TOTAL CHARGES:\s*(\D+)\d[\d\.\,]+/u", $text));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'RECEIPT')]")->length > 0) {
            $this->bodyText = strip_tags($parser->getHTMLBody());
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseCarPDF($email, $text);
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
        $in = [
            '#^(\d+)\/(\d+)\/(\d{4})\s*([\d\:]+\s*A?P?M)$#', //12/21/2021 11:41 PM
        ];
        $out = [
            '$2.$1.$3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }
}
