<?php

namespace AwardWallet\Engine\british\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ResetPasswordText extends \TAccountChecker
{
    public $mailFiles = "british/statements/it-63099936.eml, british/statements/it-97156286.eml";
    public $subjects = [
        // en
        '/^Reset your password on ba\.com$/',
        // pt
        '/^Redefina a sua palavra-passe em ba\.com$/',
    ];
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            "Dear"                                       => ["Dear Mr", "Dear Ms", "Dear"],
            "You have been asked to reset your password" => "You have been asked to reset your password",
            "Membership number"                          => "Membership number",
            "My Avios"                                   => "My Avios",
            "My Tier Points"                             => "My Tier Points",
            "My Lifetime Tier Points"                    => "My Lifetime Tier Points",
        ],
        "pt" => [
            "Dear"                                       => ["Dear Mr", "Dear Ms", "Dear"],
            "You have been asked to reset your password" => "Foi-lhe pedido para redefinir a sua palavra-passe",
            "Membership number"                          => "Membership number",
            "My Avios"                                   => "My Avios",
            "My Tier Points"                             => "My Tier Points",
            "My Lifetime Tier Points"                    => "My Lifetime Tier Points",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $statementData = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Membership number')]");

        if (empty($statementData)) {
            $statementData = $parser->getPlainBody();
        }

        if (preg_match("/{$this->opt($this->t('Membership number'))}\s*\:\s*(\d+)\n?\s*{$this->opt($this->t('My Avios'))}\s*\:\s+(\d+)\n?\s*{$this->opt($this->t('My Tier Points'))}\s*\:\s+(\d+)\n?\s*{$this->opt($this->t('My Lifetime Tier Points'))}\s*\:\s+(\d+)\s*.+\s+{$this->opt($this->t('Dear'))}([[:alpha:] ]+[,.[:alpha:] ])\,/u", $statementData, $m)) {
            $st->setNumber($m[1])
                ->addProperty('TierPoints', $m[3])
                ->addProperty('LifetimeTierPoints', $m[4])
                ->addProperty('Name', $m[5]);

            $st->setBalance($m[2]);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:my|email)\.ba\.com>?/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && self::detectEmailFromProvider(trim($headers['from'])) == true) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, trim($headers['subject']))) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!isset($dict['You have been asked to reset your password'])) {
                return false;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($dict['You have been asked to reset your password'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains('British Airways')}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains('Your Executive Club Team')}]")->length > 0
            ) {
                return true;
            }

            $text = $parser->getPlainBody();

            if ($this->striposAll($text, $dict['You have been asked to reset your password']) == true
                && $this->striposAll($text, 'British Airways') == true
                && $this->striposAll($text, 'Your Executive Club Team') == true
            ) {
                return true;
            }
        }

        return false;
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
