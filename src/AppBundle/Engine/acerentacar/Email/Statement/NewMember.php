<?php

namespace AwardWallet\Engine\acerentacar\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NewMember extends \TAccountChecker
{
    public $mailFiles = "acerentacar/statements/it-70773205.eml";
    public $subjects = [
        '/^ACE Rent A Car \- New Member$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'rent@acerentacar.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'ACE Rent A Car')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for registering as a member'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('NEW ACCOUNT DETAILS'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We appreciate the opportunity to serve you'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/rent[@.]acerentacar\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Registered To:'))}]/following::text()[normalize-space()][1]");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Email:'))}]/following::text()[normalize-space()][1]");
        $st->setLogin($login);

        $st->setNoBalance(true);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return 0;
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
