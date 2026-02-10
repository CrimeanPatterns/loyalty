<?php

namespace AwardWallet\Engine\anz\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AccessRegistration extends \TAccountChecker
{
    public $mailFiles = "anz/statements/it-66097692.eml";
    public $subjects = [
        '/ANZ\'s Investor Access registration$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@onepath.com.au') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'ANZ Wealth')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('ANZ Investor Access - website registrations'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your User ID is'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]onepath\.com\.au$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s*(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $st->setLogin($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your User ID is:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"));

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
