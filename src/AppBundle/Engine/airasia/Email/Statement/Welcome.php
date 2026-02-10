<?php

namespace AwardWallet\Engine\airasia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "airasia/it-62951794.eml";

    public static $dictionary = [
        'en' => [
            "BIG Member ID:" => "BIG Member ID:",
            //            "Dear " => "",
            "subjectRE" => [
                "Welcome (.+), you are a BIG member!",
            ],
        ],
    ];

    private $detectFrom = "no-reply@confirmation.airasia.com";
    private $detectSubjects = [
        // en
        ", you are a BIG member!",
        "Please reset your password",
        "Your password is updated",
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $t) {
            if (!empty($t['BIG Member ID:']) && !empty($this->http->FindSingleNode("//text()[" . $this->contains($t["BIG Member ID:"]) . "]"))) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        // BIGShotID
        $number = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("BIG Member ID:")) . "]", null, true,
            "#\s*" . $this->preg_implode($this->t("BIG Member ID:")) . "\s*(\d{5,})\s*$#u");
        $st->addProperty('BIGShotID', $number);

        // Balance
        if (!empty($number)) {
            $st->setNoBalance(true);
        }

        // Name
        $nameRes = (array) $this->t("subjectRE");

        foreach ($nameRes as $nameRe) {
            if (preg_match("#" . $nameRe . "#", $parser->getSubject(), $m) && !empty($m[1])) {
                $name = $m[1];

                break;
            }
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
                "#^\s*" . $this->preg_implode($this->t("Dear ")) . "\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*,\s*$#u");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class));

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
