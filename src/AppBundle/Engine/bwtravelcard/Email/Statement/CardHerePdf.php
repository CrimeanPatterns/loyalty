<?php

namespace AwardWallet\Engine\bwtravelcard\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class CardHerePdf extends \TAccountChecker
{
    public $mailFiles = "bwtravelcard/statements/it-76679036.eml";

    public $lang = '';

    public static $dict = [
        'en' => [],
    ];

    private $detectFrom = "@bwhhotelgroup.com";

    private $detectSubject = [
        "Your Best Western Travel Card is here!",
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();
        $st
            ->setMembership(true)
            ->setNoBalance(true)
        ;

        $pdfs = $parser->searchAttachmentByName(".*\.pdf");

        if (isset($pdfs[0])) {
            $pdf = $pdfs[0];
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!empty($text)) {
                $st = $email->add()->statement();

                if (preg_match("/\n\s*Travel Card Number +Amount +Currency +Order Date\s*\n *(\d{10,}) +(\d[\d,. ]*) +[A-Z]{3} +/",
                    $text, $m)) {
                    $st
                        ->setLogin($m[1])
                        ->setNumber($m[1])
                        ->setBalance(str_replace([' ', ',', '.'], '', $m[2]))
                    ;
                }
            }
        }

        if (empty($st->getBalance())) {
            $st->setNoBalance(true);
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
