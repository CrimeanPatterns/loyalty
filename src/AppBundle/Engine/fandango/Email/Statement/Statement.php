<?php

namespace AwardWallet\Engine\fandango\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statement extends \TAccountChecker
{
    public $mailFiles = "fandango/statements/it-83875398.eml, fandango/statements/it-85248979.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'buttons' => [
                'BUY TICKETS',
                'CLAIM MY $5 REWARD',
                'SEE WHAT’S PLAYING',
                'GET TICKETS',
                'GET EARLY ACCESS TICKETS',
                'GET MY TICKETS',
                'CHECK SHOWTIMES',
                'SEE IT AGAIN',
                'VIEW MY TICKET',
                'SEE WHAT\'S PLAYING',
                'FANDANGONOW',
                'RESET MY PASSWORD',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Fandango Media')]")->length > 0
            && ($this->http->XPath->query("//a[{$this->contains($this->t('buttons'))}]")->length > 0
                || $this->http->XPath->query("//img[contains(@alt, 'Reset Password')]")->length > 0)
            && ($this->http->XPath->query("//text()[contains(normalize-space(), 'MY ACCOUNT')]")->length > 0
                || $this->http->XPath->query("//img[contains(@src, 'Forgot_Password') or contains(@src, 'forgotpassword')]")->length > 0);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $dateBalance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Points balance as of'))}]", null, true, "/{$this->opt($this->t('Points balance as of'))}\s*(\d+\/\d+\/\d+)/");

        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Points balance as of'))}]/preceding::text()[{$this->contains($this->t('POINTS'))}][last()]", null, true, "/^\s*(\d+)\s*{$this->opt($this->t('POINTS'))}/");

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('MY ACCOUNT'))}]/following::text()[{$this->contains($this->t('POINTS'))}][1]", null, true, "/^\s*(\d+)\s*{$this->opt($this->t('POINTS'))}/");
        }

        if ($balance !== null) {
            $st->setBalance($balance);

            if (!empty($dateBalance)) {
                $st->setBalanceDate(strtotime($dateBalance));
            }
        } elseif ($this->detectEmailByBody($parser) == true) {
            $st->setMembership(true);
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
