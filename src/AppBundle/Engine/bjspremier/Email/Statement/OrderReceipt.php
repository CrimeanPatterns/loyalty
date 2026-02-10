<?php

namespace AwardWallet\Engine\bjspremier\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderReceipt extends \TAccountChecker
{
    public $mailFiles = "bjspremier/statements/it-76204425.eml";
    public $subjects = [
        "/Order receipt from BJ \'s Fresno/u",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bjsrestaurants.com') !== false) {
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
        return $this->http->XPath->query('//text()[contains(normalize-space(), "ordering from BJ")]')->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Member Id:'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Guest Email:'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bjsrestaurants\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/^{$this->opt($this->t('Hello'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Member Id:'))}]/ancestor::tr[1]/descendant::td[last()]");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Email:'))}]/ancestor::tr[1]/descendant::td[last()]");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $st->setNoBalance(true);

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
