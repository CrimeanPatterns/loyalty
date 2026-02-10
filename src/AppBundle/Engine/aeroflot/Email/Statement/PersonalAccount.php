<?php

namespace AwardWallet\Engine\aeroflot\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PersonalAccount extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-62050865.eml, aeroflot/it-62055110.eml, aeroflot/it-62276838.eml, aeroflot/it-62343009.eml, aeroflot/statements/it-83850438.eml";
    public $headers = [
        '/Benefits in the Palm of Your Hand$/',
        '/^New sign-in to your Aeroflot Bonus personal account$/',
        '/Brighten Up Your Summer with Our Partners\’ Offers$/',
        '/You Asked\, We Listened\: No Expiry of Miles and Special Conditions/',
        '/On Your Marks\, Get Set\, Go to the Rewards Catalogue/',
        '/^Message from the CEO$/',
        '/Fly for Less in Business and Comfort Class/',
        '/Мы подготовили предложения для Вас/',
        '/Фиксируем выгодный курс на/',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            "Miles"            => ["Miles", "miles"],
            "Qualifying Miles" => ["Qualifying Miles", "Qualifying miles", "qualifying miles"],
        ],

        "ru" => [
            'Qualifying Miles'   => ['кв. миль', 'Кв. миль'],
            'Miles'              => ['миль', 'Миль'],
            'Aeroflot Bonus'     => 'Аэрофлот Бонус',
            'Special conditions' => 'Специальные условия',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getCleanFrom(), 'info@aeroflot.ru') === false) {
            return false;
        }

        if ($this->detectLang() === true) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Miles'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Qualifying Miles'))}]")->length > 0;
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

        $dataText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Qualifying Miles'))}]/ancestor::td[1]");

        if (empty($dataText)) {
            $dataText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Special conditions'))}]/preceding::text()[{$this->contains($this->t('Qualifying Miles'))}]/ancestor::td[1]");
        }

        if (preg_match("/^(?<name>\D+)?\s?(?<miles>[\d\s]+)\s+\/\s+(?<qMiles>[\d\s]+)\s+\D+$/", $dataText, $m)
            || preg_match("/^(?<name>\D+)?\s?(?<miles>[\d\s]+)\s+{$this->opt($this->t('Miles'))}\s+(?<qMiles>[\d\s]+)\D+$/", $dataText, $m)) {
            $st->setBalance(str_replace(" ", "", $m['miles']));

            if (isset($m['qMiles']) && !empty(trim($m['qMiles']))) {
                $st->addProperty('QualMiles', str_replace(" ", "", $m['qMiles']));
            } elseif (isset($m['qMiles']) && trim($m['qMiles']) == '0') {
                $st->addProperty('QualMiles', 0);
            }

            if (isset($m['name']) && !empty($m['name'])) {
                $st->addProperty('Name', $m['name']);
            }
        }

        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Qualifying Miles'))}]/ancestor::td[1]/preceding::a[2]", null, true, "/^(\d+)$/");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Special conditions'))}]/preceding::text()[{$this->contains($this->t('Qualifying Miles'))}]/ancestor::td[1]/preceding::a[2]", null, true, "/^(\d+)$/");
        }

        if (!empty($login)) {
            $st->addProperty('Number', $login);

            $st->addProperty('Login', $login);
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
