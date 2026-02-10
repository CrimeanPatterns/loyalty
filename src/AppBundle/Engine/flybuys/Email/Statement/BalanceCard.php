<?php

namespace AwardWallet\Engine\flybuys\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BalanceCard extends \TAccountChecker
{
    public $mailFiles = "flybuys/it-535018444.eml";
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Loyalty Pacific Pty ')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Member Number'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Points Balance'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]edm\.flybuys\.com\.au$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[normalize-space()='Member Number']/preceding::text()[normalize-space()][1]");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[normalize-space()='Member Number']/following::text()[normalize-space()][1]");

        if (preg_match("/^(\d+)[\sx]+(\d+)$/", $number, $m)) {
            $st->setNumber($m[1] . '**' . $m[2])->masked('center');
        }

        $balance = $this->http->FindSingleNode("//text()[normalize-space()='Points Balance']/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)\s*pts/u");
        $st->setBalance(str_replace(['.', ','], '', $balance));

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
