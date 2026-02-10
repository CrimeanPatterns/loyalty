<?php

namespace AwardWallet\Engine\airmilesca\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CashMiles extends \TAccountChecker
{
    public $mailFiles = "airmilesca/statements/it-70850617.eml, airmilesca/statements/it-70863683.eml, airmilesca/statements/it-70875511.eml";
    public $lang = '';

    public $detectLang = [
        'en' => 'Miles',
        'fr' => 'Milles',
    ];

    public static $dictionary = [
        "en" => [
        ],
        "fr" => [
            'Hey'         => 'Bonjour',
            'Cash'        => 'Argent',
            'Dream'       => 'Rêves',
            'Bonus Miles' => 'Le Programme de récompense',
            'As of'       => 'Au',
            ', you have'  => ', vous avez :',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->AssignLang() === true) {
            return $this->http->XPath->query("//text()[contains(normalize-space(),'AIR MILES')]")->count() > 0
                && $this->http->XPath->query("//*[({$this->contains($this->t('Cash'))} and {$this->contains($this->t('Dream'))}) or {$this->contains($this->t('Bonus Miles'))}]")->count() > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.airmiles\.ca$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();
        $email->setType('CashMiles' . ucfirst($this->lang));

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey'))}]", null, true, "/^{$this->opt($this->t('Hey'))}\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:[ ]*[,;:!?]|$)/u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey'))}]/following::text()[{$this->starts($this->t('Cash'))}][1]/ancestor::*[1]/preceding::text()[normalize-space()][1]", null, true, "/^([\d\,]+)$/");
        $st->setBalance(str_replace(',', '', $balance));

        $date = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey'))}]/following::text()[{$this->starts($this->t('As of'))}][1]", null, true, "/{$this->opt($this->t('As of'))}\s+(.+\d{4})(?:{$this->opt($this->t(', you have'))}|$)/");

        if (!empty($date)) {
            $st->setBalanceDate($this->normalizeDate($date));
        }

        return $email;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function AssignLang(): bool
    {
        foreach ($this->detectLang as $lang => $word) {
            if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$word}')]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $this->logger->warning('IN' . $str);
        $in = [
            // As of November 13, 2020, you have:
            "/^(\w+)\s*(\d+)\,?\s*(\d{4})$/u",
            // Au 13 novembre 2020, vous avez :
            "/^(\d+)\s*(\w+)\s*(\d{4})$/u",
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        $this->logger->warning('OUT' . $str);

        return strtotime($str);
    }
}
