<?php

namespace AwardWallet\Engine\exxonmobil\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Receipt extends \TAccountChecker
{
    public $mailFiles = "exxonmobil/statements/it-75266019.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [],
    ];

    private $detectSubject = [
        'Exxon Mobil Receipt',
        'Exxon Mobil Rewards+ Receipt',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody();

        if (empty($text) || stripos($text, 'EM Rewards card:') === false) {
            $text = $parser->getHTMLBody();
        }

        if (stripos($text, 'Sent from the Exxon Mobil Rewards+ app') !== false
            && stripos($text, 'EM Rewards card:') !== false) {
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@exxonmobil.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $text = $parser->getPlainBody();

        if (empty($text) || stripos($text, 'EM Rewards card:') === false) {
            $text = preg_replace('/<[^>]*>/', "\n", $parser->getHTMLBody());
        }

        if (preg_match("/\bEM Rewards card:\s*\*{4,}(\d{4})\b(?:\s*.*\n){0,5}.*\bBalance (\d+) pts\b/", $text, $m)) {
            $st->setNumber($m[1])->masked();
            $st->setBalance($m[2]);
        }

        if (
               preg_match("/from your purchase on (\d{2}\/\d{2}\/\d{2})\s+/", $text, $m)
               || preg_match("/\s+(\d{2}\/\d{2}\/20\d{2})\s+\d{5,}\s+\d{2}:\d{2}:\d{2}\s*[AP]M/", $text, $m)
        ) {
            $st->setBalanceDate($this->normalizeDate($m[1]));
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

    private function normalizeDate(?string $text)
    {
//        $this->logger->debug($text);
        $in = [
            // 05-28-2020
            '/^\s*(\d{2})\/(\d{2})\/(\d{2})\s*$/u',
        ];
        $out = [
            '$2.$1.20$3',
        ];
        $text = preg_replace($in, $out, $text);

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $text, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $text = str_replace($m[1], $en, $text);
//        }

        return strtotime($text);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
