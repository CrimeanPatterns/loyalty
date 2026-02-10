<?php

namespace AwardWallet\Engine\aireuropa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SumaMilesBalance extends \TAccountChecker
{
    public $mailFiles = "aireuropa/statements/it-64613366.eml";
    public $subjects = [
        '/Your SUMA Miles balance - /',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@omp.aireuropanews.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Air Europa SUMA')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('CLIENT:'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('SUMA CARD Nº'))}]")->count() > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('CLIENT:'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('CLIENT:'))}\s+(\D+)\s*{$this->opt($this->t('SUMA CARD Nº:'))}/s");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('SUMA CARD Nº:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('SUMA CARD Nº:'))}\s*([\d\*]+)/s");

        if (!empty($number)) {
            $st->setNumber($number)->masked('right');
        }

        $st->setNoBalance(true);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]omp\.aireuropanews\.com$/', $from) > 0;
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
