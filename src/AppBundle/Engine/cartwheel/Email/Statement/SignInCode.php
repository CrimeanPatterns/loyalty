<?php

namespace AwardWallet\Engine\cartwheel\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SignInCode extends \TAccountChecker
{
    public $mailFiles = "cartwheel/it-232282124.eml";
    public $subjects = [
        'to sign in to your Target.com account',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) || strpos($headers['subject'], 'Target.com') !== false) {
            if (stripos($headers['subject'], 'RedCard') !== false || stripos($headers['from'], 'redcard') !== false) {
                return false;
            }
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
        if (
            $this->http->XPath->query("//a[contains(@href, '.target.com')]")->length > 0
            && $this->http->XPath->query("//*[contains(normalize-space(), 'RedCard') or contains(@href, 'redcard')]")->length === 0
        ) {
            return $this->http->XPath->query("//node()[{$this->contains('Enter this code on the Two-step verification')}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]target\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($this->http->XPath->query("//*[contains(normalize-space(), 'RedCard') or contains(@href, 'redcard')]")->length > 0
        ) {
            return $email;
        }

        $otc = $email->add()->oneTimeCode();

        $code = $this->http->FindSingleNode("//text()[normalize-space() = 'Sign-in code']/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{6})\s*$/");

        $otc->setCode($code);

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
}
