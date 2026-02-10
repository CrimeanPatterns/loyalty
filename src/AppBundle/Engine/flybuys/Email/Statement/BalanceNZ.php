<?php

namespace AwardWallet\Engine\flybuys\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BalanceNZ extends \TAccountChecker
{
    public $mailFiles = "flybuys/it-361728304.eml, flybuys/it-94113449.eml, flybuys/it-94115804.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from'])) {
            return false;
        }
        $emailfrom = ['FlyBuys@email.flybuys.co.nz', 'contactus@flybuys.co.nz'];

        foreach ($emailfrom as $ef) {
            if (stripos($headers['from'], $ef) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flybuys\.com\.nz$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//img[contains(@src, 'FlyBuys_person')]/ancestor::*[normalize-space()][1]");

        if (preg_match("/^\s*(?<name>[[:alpha:]][[:alpha:] \-]+)\s*(?<number>\d[\d ]{5,})$/", $name, $m)) {
            $st
                ->setNumber(str_replace(' ', '', $m['number']))
                ->addProperty("Name", $m['name'])
            ;
        }

        $balance = $this->http->FindSingleNode("(//text()[contains(., 'Flybuys Points as at')]/ancestor::*[starts-with(translate(normalize-space(),'0123456789','dddddddddd'),'d')][1])[1]");

        if (preg_match("/^\s*(?<balance>\d[\d,]*)\s*Flybuys Points as at\s+(?<date>.+?)$/", $balance, $m)) {
            // 308   Flybuys Points as at 25/05/2021

            $st
                ->setBalanceDate(strtotime(str_replace('/', '.', $m['date'])))
                ->setBalance(str_replace(',', '', $m['balance']))
            ;
        } elseif (!empty($st->getNumber())) {
            $st->setNoBalance(true);
        }

        if ($this->http->XPath->query("//node()[{$this->contains('Here\'s your sign in link')}]")->length > 0) {
            $otc = $email->add()->oneTimeCode();
            // https://www.flybuys.co.nz/magic_login?step=2&user%5Bemail_secret_code%5D=20029b72-af00-451e-b529-f0f34ad90895&user%5Busername%5D=6014355704909689
            $otc->setCodeAttr("#https\:\/\/www\.flybuys\.co\.nz\/magic_login\?.*email_secret_code%5D=[A-z\d\-]+&.*$#", 1000);
            $otc->setCode($this->http->FindSingleNode("//a[.//img[contains(@alt, 'Sign in with this link')] and contains(@href, 'flybuys') and contains(@href, 'magic_login')]/@href"));
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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
