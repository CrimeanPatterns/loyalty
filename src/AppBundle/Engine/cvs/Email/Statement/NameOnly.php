<?php

namespace AwardWallet\Engine\cvs\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NameOnly extends \TAccountChecker
{
    public $mailFiles = "cvs/statements/it-71088741.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'CVS Pharmacy')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('My Account'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('ExtraCare'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Year to Date Savings'))}]")->count() == 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]pharmacy\.cvs\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Member'))}]/ancestor::div[1]", null, true, "/^ExtraCare\S\s*{$this->opt($this->t('Member'))}\:?\s*(\w+)$/u");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
            $st->setNoBalance(true);
        } elseif (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Member'))}]/ancestor::*[1]", null, true, "/^ExtraCare\S\s*{$this->opt($this->t('Member'))}\s*$/u"))) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
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
}
