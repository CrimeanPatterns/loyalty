<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class OrderMiles extends \TAccountChecker
{
    public $mailFiles = "aa/statements/it-96049226.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            //            "Your Name:" => "",
            //            "AAdvantage Account:" => "",
        ],
    ];

    private $detectSubjects = [
        'en' => [
            'You\'ve Been Awarded Miles',
        ],
    ];

    private $detectBody = [
        'en' => ['have been credited to your AAdvantage account'],
    ];

    public function detectEmailFromProvider($from)
    {
        $emails = ['orderaamiles@aa.com'];

        foreach ($emails as $email) {
            if (stripos($from, $email) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $detectSubjects) {
            foreach ($detectSubjects as $dSubjects) {
                if (stripos($headers['subject'], $dSubjects) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//node()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("AAdvantage Account:")) . "]/following::text()[normalize-space()][1]", null, true,
            "#^\s*([A-Z\d]{5,12})\s*$#");

        $st->setNumber($number);
        $st->setLogin($number);

        if (!empty($number)) {
            $st->setNoBalance(true);
        }

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your Name:")) . "]/following::text()[normalize-space()][1]", null, true,
            "#^\s*([[:alpha:] ]+)\s*$#");

        if (preg_match("#^\s*([[:alpha:]]+) ([[:alpha:]]+)\s*$#u", $name, $m)) {
            $st->addProperty('Name', $name);
            $st->addProperty('LastName', $m[2]);
        } elseif (preg_match("#^\s*([[:alpha:]]+(?: [[:alpha:]]+)*)\s*$#u", $name, $m)) {
            $st->addProperty('Name', $name);
        }

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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
