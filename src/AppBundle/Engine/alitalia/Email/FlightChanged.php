<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChanged extends \TAccountCheckerExtended
{
    public $mailFiles = "alitalia/it-61890183.eml";
    public $detectSubject = [
        "en" => "Information about your booking reference:",
        "it" => "Informazioni inerenti la tua prenotazione:",
    ];

    public $detectProvider = 'alitalia.com';
    public $detectBody = [
        "en" => ["schedule of your flight has been changed", "itinerary has undergone the following changes"],
        "it" => ["tuo itinerario di viaggio ha subito la seguente variazione"],
    ];

    public static $dictionary = [
        "en" => [
            //            "booking reference:" => "",
            //            "Dear " => "",
            //            "flight has been changed" => "",
            "flight has been changed" => ["flight has been changed", "undergone the following changes"],
        ],
        "it" => [
            "booking reference:"      => "la tua prenotazione:",
            "Dear "                   => "Gentile ",
            "flight has been changed" => "itinerario di viaggio ha subito la seguente variazione",
        ],
    ];

    private $detectFrom = '@alitalia.it';

    private $lang = "";
    private $date;
    private $subject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $this->subject = $parser->getSubject();

        foreach ($this->detectSubject as $lang => $detectSubject) {
            if (stripos($this->subject, $detectSubject) !== false && $this->http->XPath->query("//*[" . $this->contains($this->detectBody[$lang]) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            foreach ($this->detectBody as $lang => $detectBody) {
                if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '" . $this->detectProvider . "')]")->length == 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();

        $r->general()
            ->confirmation($this->re("#" . $this->preg_implode($this->t("booking reference:")) . "\s*([A-Z\d]{5,7})\b#u", $this->subject))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
                "#" . $this->preg_implode($this->t("Dear ")) . "(.+?),#"))
        ;

        $xpath = "//text()[" . $this->contains($this->t('flight has been changed')) . "]/following::text()[string-length(normalize-space())>2][1]/ancestor::tr[1]/ancestor::*[1]/*";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (preg_match("#^[\s\-0]+$#", $root->nodeValue)) {
                continue;
            }

            $s = $r->addSegment();

            $date = null;

            // Airline
            $node = $this->http->FindSingleNode("./*[1]", $root);

            if (preg_match("#^\s*(.+) - ([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
                $date = $this->normalizeDate($m[1]);
                $s->airline()
                    ->name($m[2])
                    ->number($m[3]);
            }

            // Departure
            if (preg_match("#(.+) - ([A-Z]{3})\s*(\d+:\d+)#",
                $this->http->FindSingleNode("./*[2]", $root), $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                    ->date((!empty($date)) ? strtotime($m[3], $date) : null)
                ;
            }

            // Arrival
            if (preg_match("#(.+) - ([A-Z]{3})\s*(\d+:\d+)#",
                $this->http->FindSingleNode("./*[3]", $root), $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                    ->date((!empty($date)) ? strtotime($m[3], $date) : null)
                ;
            }
        }

        return true;
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^\s*([^\d\s]+)\s+(\d{1,2})\s*$#", //Aug 21
            "#^\s*(\d{1,2}+)/(\d{1,2})\s*$#", //31/08
        ];
        $out = [
            "$2 $1 " . $year,
            "$1.$2." . $year,
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return EmailDateHelper::parseDateRelative($str, $this->date);
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

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
