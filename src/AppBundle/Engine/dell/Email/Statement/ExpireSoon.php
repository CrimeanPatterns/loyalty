<?php

namespace AwardWallet\Engine\dell\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ExpireSoon extends \TAccountChecker
{
    public $mailFiles = "dell/statements/it-77205439.eml, dell/statements/it-77451558.eml";

    public $lang = 'en';

    public static $dict = [
        'en' => [],
    ];

    private $detectSubject = [
        "your Dell Rewards expire soon!",
        " in new Dell Rewards.",
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//text()[" . $this->eq(['Total Active Rewards:']) . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*\\$(\d[\d\., ]*)\s*$/");

        if (!empty($balance)) {
            $balance = str_replace([',', ' '], '', $balance);
        }

        if (!is_null($balance)) {
            $st
                ->setBalance($balance)
                ->setMembership(true)
            ;
        }

        $date = $this->http->FindSingleNode("//text()[" . $this->eq(['Total Active Rewards:']) . "]/following::text()[normalize-space()][position() < 5][" . $this->eq(['Expiration date:']) . "]/following::text()[normalize-space()][1]");
        $st
            ->setExpirationDate($this->normalizeDate($date))
        ;

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]dell\.com$/', $from);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate(?string $text)
    {
//        $this->logger->debug($text);
        $in = [
            // 05-28-2020
            '/^\s*(\d{2})-(\d{1,2})-(\d{4})\s*$/u',
        ];
        $out = [
            '$2.$1.$3',
        ];
        $text = preg_replace($in, $out, $text);

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $text, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $text = str_replace($m[1], $en, $text);
//        }

        return strtotime($text);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
