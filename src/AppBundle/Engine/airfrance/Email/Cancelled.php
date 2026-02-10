<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Cancelled extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-64419837.eml, airfrance/it-64914599.eml";
    public static $dictionary = [
        "en" => [
            //            "Booking reference" => "",
            //            "Dear " => "",
            //            "Ticket number:" => "",
        ],
        "fr" => [
            "Booking reference" => "Référence de réservation",
            "Dear "             => "Cher ",
            //            "Ticket number:" => "",
        ],
    ];

    private $detectFrom = 'admin@service-airfrance.com';

    private $detectSubject = [
        'en' => 'Cancelation Confirmed',
        'fr' => 'Annulation Confirmée',
    ];

    private $detectBody = [
        'en' => ['Refund reference number:', 'Your booking has therefore been canceled'],
        'fr' => ['ANNULATION DE VOTRE RÉSERVATION'],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $body) {
            if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[' . $this->contains(['.service-airfrance.', '.airfrance.'], '@href') . ']')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($detectBody) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking reference")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//*[" . $this->eq($this->t("Booking reference")) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,7})\s*$/");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//td[" . $this->starts($this->t("Booking reference")) . "]", null, true, "/^\s*" . $this->opt($this->t("Booking reference")) . "\s*([A-Z\d]{5,7})\s*$/");
        }
        $f->general()
            ->confirmation($conf)
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true, "/" . $this->opt($this->t("Dear ")) . "(?:Mrs |Mr |Monsieur )?\s*(.+),\s*$/"))
            ->cancelled()
            ->status('Cancelled')
        ;
        $tickets = $this->http->FindNodes("//text()[" . $this->starts($this->t("Ticket number:")) . "]", null, "/:\s*(\d{10,})\s*$/");

        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode('|', array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug("Date: {$date}");
        $in = [
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s+(\d{1,2}:\d{2}(?: ?[ap]m)?)\s*$#ui", //25/06/2019 11:55AM
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $date = preg_replace($in, $out, $date);

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
//                $date = str_replace($m[1], $en, $date);
//            }
//        }
        return strtotime($date);
    }
}
