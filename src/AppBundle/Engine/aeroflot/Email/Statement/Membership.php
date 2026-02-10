<?php

namespace AwardWallet\Engine\aeroflot\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Membership extends \TAccountChecker
{
    public $mailFiles = "aeroflot/statements/it-77039439.eml, aeroflot/statements/it-77636968.eml, aeroflot/statements/it-82903133.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            "words" => [
                "word at www.aeroflot.com was successfully modified",
                "Aeroflot Bonus program was successfully changed",
            ],
        ],

        'ru' => [
            "words" => [
                "Ваш пароль для доступа в личный кабинет на www.aeroflot.ru был успешно изменён",
                "Пожалуйста, не отвечайте на данное сообщение",
                "Спасибо за то, что выбираете Аэрофлот",
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->detectLang();

        if (stripos($parser->getCleanFrom(), '@aeroflot.ru') === false) {
            return false;
        }

        if (stripos($parser->getCleanFrom(), 'bonus@aeroflot.ru') !== false) {
            return true;
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('words'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]aeroflot\.ru$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();

        $st = $email->add()->statement();

        $st
            ->setMembership(true)
            ->setNoBalance(true)
        ;

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
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

    private function detectLang()
    {
        $body = $this->http->Response['body'];

        foreach (self::$dictionary as $lang => $detects) {
            foreach ($detects as $detect) {
                if ($this->http->XPath->query("//text()[{$this->contains($detect)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
