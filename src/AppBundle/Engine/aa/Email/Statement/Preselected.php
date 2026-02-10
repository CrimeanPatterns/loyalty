<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Preselected extends \TAccountChecker
{
    public $mailFiles = "aa/statements/it-707403919.eml, aa/statements/it-708617695.eml";
    public $subjects = [
        'you’ve been preselected to apply for a',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@loyalty.ms.aa.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Current AAdvantage')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('you’ve been pre-selected.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('miles'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]loyalty\.ms\.aa\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t(', you’ve been pre-selected.'))}]", null, true, "/^(.+){$this->opt($this->t(', you’ve been pre-selected.'))}$/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('you’ve been pre-selected.'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(.+)\,$/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t(', you’ve been pre-selected.'))}]/preceding::text()[{$this->starts($this->t('Member'))}][1]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Member'))}\s*([A-Z\d\*]+)/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('you’ve been pre-selected'))}]/preceding::img[contains(@alt, 'member')]/following::text()[normalize-space()][1]");
        }

        if (!empty($number)) {
            if (preg_match("/^([A-Z\d]+)[*]+$/", $number, $match)) {
                $st->setNumber($match[1] . '**')->masked('right');
            } else {
                $st->setNumber($number);
            }
        }

        $balanceText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Current AAdvantage')]/ancestor::tr[1]");

        if (preg_match("/miles as of\s+(?<dateBalance>\d+\/\d+\/\d{4})\s+(?<balance>[\d\.\,\']+)$/", $balanceText, $m)) {
            $st->setBalance(str_replace(',', "", $m['balance']));
            $st->setBalanceDate(strtotime($m['dateBalance']));
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
