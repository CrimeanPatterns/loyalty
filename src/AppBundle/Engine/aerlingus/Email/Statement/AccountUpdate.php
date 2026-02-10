<?php

namespace AwardWallet\Engine\aerlingus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AccountUpdate extends \TAccountChecker
{
    public $mailFiles = "aerlingus/statements/it-106429459.eml, aerlingus/statements/it-77093414.eml, aerlingus/statements/it-77102009.eml, aerlingus/statements/it-77191694.eml";

    public $lang = '';

    public static $dict = [
        'en' => [],
    ];

    private $detectFrom = "@aerlingus.com";

    private $detectSubject = [
        "Verify Your Account - ",
        "Reset Your Password - ",
        "Verify new email",
        "Your password has been successfully changed",
        "Reset your password at Aer Lingus",
        "Verify your identity",
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if (preg_match("/^" . $this->preg_implode(['Verify Your Account', 'Reset Your Password']) . " - (\S+@\S+\.\w+)\s*$/",
            $parser->getSubject(), $m)) {
            $st->setLogin($m[1]);
        }

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear ')][not(contains(normalize-space(), 'Member'))]",
            null, true, "/^\s*Dear ([[:alpha:]\- ]+),\s*$/");

        if (!empty($name) && !preg_match("/^\s*guest\s*$/i", $name)) {
            $st->addProperty('Name', $name);
        }
        $st
            ->setMembership(true)
            ->setNoBalance(true)
        ;

        $code = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your code is:')]", null, true, "/Your code is\:\s*(\d{5,})/");

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($code);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->detectFrom) !== false) {
            foreach ($this->detectSubject as $detectSubject) {
                if (strpos($headers['subject'], $detectSubject) !== false) {
                    return true;
                }
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
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
