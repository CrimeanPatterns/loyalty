<?php

namespace AwardWallet\Engine\bahn\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WithBalance extends \TAccountChecker
{
    public $mailFiles = "bahn/statements/it-66077932.eml, bahn/statements/it-66077937.eml";
    public $lang = 'de';

    public static $dictionary = [
        "de" => [
            'Herr'          => ['Herr', 'Frau', 'Liebe'],
            'Sehr geehrter' => ['Sehr geehrter', 'Sehr geehrte', 'Liebe'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'BahnBonus')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Punktestand'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Prämienpunkte'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Sehr geehrter'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Gut zu wissen'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.bahncard\.bahn\.de$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Sehr geehrter'))}]", null, true, "/{$this->opt($this->t('Herr'))}\s+(\D+)\,$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $balance = str_replace('.', '', $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Punktestand:')]/following::text()[contains(normalize-space(), 'Prämienpunkte')][1]/preceding::text()[normalize-space()][1]", null, true, "/^([\d\.]+)$/"));
        $st->setBalance($balance);

        $balanceDate = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Punktestand:')]", null, true, "/Punktestand\:\s*(\d+\.\d+\.\d{4})/");

        if (!empty($balanceDate)) {
            $st->setBalanceDate(strtotime($balanceDate));
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return 0;
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
