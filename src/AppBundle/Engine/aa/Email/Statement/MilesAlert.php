<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MilesAlert extends \TAccountChecker
{
    public $mailFiles = "aa/statements/it-69118965.eml";
    public $subjects = [
        '/^Extra miles alert at/',
        '/^Alert: Extra miles at your favorite stores$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aadvantageeshopping.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'American Airlines, AAdvantage')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Total miles earned'))}]")->count() > 0
            ;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aadvantageeshopping\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi, '))}]", null, true, "/^{$this->opt($this->t('Hi, '))}(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi, '))}]/following::text()[normalize-space()][1]", null, true, "/^[#]\*+([A-Z\d]+)$/");

        if (!empty($number)) {
            $st->setNumber($number)->masked('left');
            $st->setNoBalance(true);
        }

        /* apparently this is not aa balance
        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total miles earned'))}]/following::text()[normalize-space()][1]", null, true, "/^([\d\,]+)[*]/");
        $st->setBalance(str_replace(",", "", $balance));
        */

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
