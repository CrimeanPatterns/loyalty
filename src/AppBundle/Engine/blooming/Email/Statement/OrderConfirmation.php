<?php

namespace AwardWallet\Engine\blooming\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "blooming/statements/it-65915356.eml";
    public $subjects = [
        '/^Your \w+ Monthly Loyallist Rewards Statement$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@loyallist.bloomingdales.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Bloomingdale')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('YOUR ORDER CONFIRMATION'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('ORDER DETAILS'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]loyallist\.bloomingdales\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//td[{$this->starts($this->t('Loyallist Number:'))}]/ancestor::tr/preceding::tr[1]/descendant::text()[normalize-space()][1]");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $st->setBalance($this->http->FindSingleNode("//td[{$this->starts($this->t('Total point balance:'))}]/following::td[1]"));

        $st->setNumber($this->http->FindSingleNode("//td[{$this->starts($this->t('Loyallist Number:'))}]", null, true, "/{$this->opt($this->t('Loyallist Number:'))}\s*([*]+\d+)/"))->masked('left');

        $st->setLogin($this->http->FindSingleNode("//td[{$this->starts($this->t('Loyallist Number:'))}]/ancestor::tr/following::tr[1]/descendant::text()[contains(normalize-space(), '@')]"));

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
