<?php

namespace AwardWallet\Engine\birchbox\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NewOrder extends \TAccountChecker
{
    public $mailFiles = "birchbox/statements/it-79974063.eml";
    public $subjects = [
        '/Birchbox\:\s*New Order \#\s*\d+$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@birchbox.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Birchbox')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Birchbox Points:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('We’ve received your order'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]birchbox\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('We’ve received your order,'))}]", null, true, "/^{$this->opt($this->t('We’ve received your order,'))}\s+(\D+)\.$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Order #:'))}]", null, true, "/{$this->opt($this->t('Order #:'))}\s*(\d+)\s*\|/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Birchbox Points:'))}]/ancestor::*[1]", null, true, "/{$this->opt($this->t('Your Birchbox Points:'))}\s*(\d+)/");
        $st->setBalance($balance);

        $dateOfBalance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Order #:'))}]", null, true, "/\|\s*(.+)\sEST/");
        $st->setBalanceDate($this->normalizeDate($dateOfBalance));

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

    private function normalizeDate($str)
    {
        $in = [
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\s*(\d+\:\d+)\:\d+\s*(A?P?M)$#u", //February 16, 2021 1:32:55 PM
        ];
        $out = [
            "$2 $1 $3, $4 $5",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
