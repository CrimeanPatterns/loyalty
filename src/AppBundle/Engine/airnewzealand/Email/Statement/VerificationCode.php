<?php

namespace AwardWallet\Engine\airnewzealand\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "airnewzealand/it-685768050.eml";
    public $subjects = [
        "Your Air New Zealand account verification code is here!",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
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
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Verification Code'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('to complete your log in process'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airnz\.co\.nz$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $c = $email->add()->oneTimeCode();
        $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Verification Code'))}]", null, true, "/^{$this->opt($this->t('Verification Code'))}\s*(\d{6})$/");
        $c->setCode($code);

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Kia ora'))}]", null, true, "/^{$this->opt($this->t('Kia ora'))}\s+(\D+)\,$/");

        if (!empty($name)) {
            $st = $email->add()->statement();

            $st->addProperty('Name', trim($name, ','));

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
