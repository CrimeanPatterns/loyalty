<?php

namespace AwardWallet\Engine\blooming\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MonthlyStatement extends \TAccountChecker
{
    public $mailFiles = "blooming/statements/it-65204196.eml";
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
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Loyallist Number'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Available Points'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]loyallist\.bloomingdales\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Loyallist Number'))}]/preceding::tr[3]", null, true, "/^([A-Z\s]+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $st->setLogin($this->http->FindSingleNode("//text()[{$this->starts($this->t('Loyallist Number'))}]/preceding::tr[2]", null, true, "/^(\S+\@\S+)$/"));

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Loyallist Number'))}]/following::text()[normalize-space()][1]");
        $st->setNumber(preg_replace("/[x]+/", "**", $number))->masked('center');

        $htmlText = $parser->getBodyStr();
        $st->setBalance($this->re("/Available Points\D+(\d+)/", $htmlText));

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
