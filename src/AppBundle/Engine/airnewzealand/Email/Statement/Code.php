<?php

namespace AwardWallet\Engine\airnewzealand\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "airnewzealand/statements/it-383794106.eml";
    public $subjects = [
        "Confirm it's you trying to sign in",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Yes this was me' => ['Yes this was me', 'Confirm my sign in'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airnz.co.nz') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Air New Zealand')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Yes this was me'))}]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('copy and paste the following into the address bar of your browser:'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Confirm my sign in'))}]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airnz\.co\.nz$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $c = $email->add()->oneTimeCode();
        $link = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'copy and paste the following into the address bar of your browser:')]/ancestor::p[1]/descendant::a[1]");

        if (empty($link)) {
            $link = $this->http->FindSingleNode("//a[contains(normalize-space(), 'Confirm my sign in')]/@href");
        }
        $c->setCodeAttr("#https\:\/\/auth\.identity\.airnewzealand\.com\/api\/verify\-magic\-link\?token[=][A-z\d\.\_\-]+$#", 1000);
        $c->setCode($link);

        if (!empty($link)) {
            $st = $email->add()->statement();
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Kia ora '))}]", null, true, "/^{$this->opt($this->t('Kia ora'))}\s+(\D+)\,$/");

            if (!empty($name)) {
                $st->addProperty('Name', trim($name, ','));
            }

            $st->setMembership(true);
            $st->setNoBalance(true);
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
