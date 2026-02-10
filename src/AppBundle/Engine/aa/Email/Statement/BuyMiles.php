<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BuyMiles extends \TAccountChecker
{
    public $mailFiles = "aa/statements/it-68724899.eml, aa/statements/it-68870816.eml";
    public $subjects = [
        '/^AAdvantage Buy Miles Confirmation$/',
        '/^AAdvantage Buy Miles Processing$/',
        '/^Confirmación de la compra de millas AAdvantage/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Thank you for buying miles' => ['Thank you for buying miles', 'Your transaction is being processed', 'Congratulations! You received miles from'],
            "Hello"                      => "Hello",
        ],
        "es" => [
            'Thank you for buying miles' => ['Gracias por comprar millas'],
            "Hello"                      => "Hola,",
            "This email was sent to"     => "Este e-mail fue enviado a",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@notify.email.aa.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'AAdvantage')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for buying miles'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Confirmation number'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]notify\.email\.aa\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Hello']) && $this->http->XPath->query("//text()[{$this->starts($dict['Hello'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/ancestor::td[1]", null, true, "/^{$this->opt($this->t('Hello'))}(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/following::td[{$this->starts($this->t('AAdvantage'))}][1]/descendant::text()[last()]");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This email was sent to'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('This email was sent to'))}\s*(.+)\.$/s");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/following::text()[{$this->starts($this->t('AAdvantage'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('AAdvantage®'))}\s+(.+)/");

        if (!empty($status)) {
            $st->addProperty('Status', $status);
        }

        $st->setNoBalance(true);

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
