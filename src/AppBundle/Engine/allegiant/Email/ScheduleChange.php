<?php

namespace AwardWallet\Engine\allegiant\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScheduleChange extends \TAccountChecker
{
    public $mailFiles = "allegiant/it-294592904.eml"; // + bcd
    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = 'no-reply@t.allegiant.com';

    private $detectSubject = [
        "Schedule Change Notice",
    ];
    private $detectCompany = '.allegiant.com/';
    private $detectBody = [
        'en' => ['Here is your updated flight information'],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    $this->lang = $lang;
                }
            }
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

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
        $body = $this->http->Response['body'];

        if ($this->http->XPath->query("//a[contains(@href, '" . $this->detectCompany . "')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
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
        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        $s = $f->addSegment();

        $s->airline()
            ->name('G4')
            ->number($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Updated Allegiant Flight")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*(\d{1,5})\s*$#"));

        $s->departure()
            ->name($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Updated Departure City:")) . "]", null, true, "#:\s*(.+?)\s*\([A-Z]{3}\)\s*$#"))
            ->code($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Updated Departure City:")) . "]", null, true, "#:\s*.+?\s*\(([A-Z]{3})\)\s*$#"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Updated Departure Date / Time:")) . "]", null, true, "#:\s*(.+)\s*$#")))
        ;

        $s->arrival()
            ->name($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Updated Arrival City:")) . "]", null, true, "#:\s*(.+?)\s*\([A-Z]{3}\)\s*$#"))
            ->code($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Updated Arrival City:")) . "]", null, true, "#:\s*.+?\s*\(([A-Z]{3})\)\s*$#"))
            ->noDate()
        ;

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
        $in = [
            //Sunday, July 5, 2020 at 9:45 PM
            "#^\s*[^\s\d]+\s*,\s*([^\s\d]+)\s*(\d{1,2})\s*,\s*(\d{4})\s+at\s+(\d+:\d+(\s*[ap]m)?)\s*$#iu",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
