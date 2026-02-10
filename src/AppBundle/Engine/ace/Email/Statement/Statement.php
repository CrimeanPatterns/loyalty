<?php

namespace AwardWallet\Engine\ace\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statement extends \TAccountChecker
{
    public $mailFiles = "ace/statements/it-87371018.eml, ace/statements/it-87666230.eml";
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Ace Hardware Corporation')]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('This email was sent to'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('acehardware@email.acehardware.com'))}]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ne\.acehardware\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $login = $this->http->FindSingleNode("//text()[{$this->eq($this->t('This email was sent to'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s*(\S+[@]\S+\.\S+)/");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s*(\S+[@]\S+\.\S+)/");
        }

        if (!empty($login)) {
            $st->setLogin(trim($login, '.'));
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Member ID:'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->eq($this->t('Current Points:'))}]")->length > 0) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Name:'))}]/following::text()[normalize-space()][1]");

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }

            $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Member ID:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

            if (!empty($number)) {
                $st->setNumber($number);
            }

            $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Current Points:'))}]/following::text()[normalize-space()][1]", null, true, "/^([\d\.,]+)$/");

            if ($balance !== null) {
                $st->setBalance($balance);
            } else {
                $st->setNoBalance(true);
            }
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('Member ID:'))}]")->length == 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Current Points:'))}]")->length == 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('points'))}]")->length > 0) {
            $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('points'))}]/ancestor::tr[1]", null, true, "/^\s*(\d+)\s*{$this->opt($this->t('points'))}/");

            if ($balance !== null) {
                $st->setBalance($balance);
            }
        } else {
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
