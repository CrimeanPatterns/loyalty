<?php

namespace AwardWallet\Engine\flysaa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "flysaa/statements/it-557758449.eml";
    public $subjects = [
        'Voyager login OTP',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your OTP for login is:'     => ['Your OTP for login is:', 'A change of ID/Passport number on your SAA Voyager account'],
            'SAA Voyager Contact Centre' => ['SAA Voyager Contact Centre', 'SAA Voyager Service Centre'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flysaa.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your OTP for login is:'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('SAA Voyager Contact Centre'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flysaa\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true, "/^{$this->opt($this->t('Dear '))}(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim(preg_replace("/^(?:MRS|MR|MS)/", "", $name), ','));
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your OTP for login is:'))}]")->length > 0) {
            $st->setNoBalance(true);
        }

        $c = $email->add()->oneTimeCode();
        $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your OTP for login is:'))}]", null, true, "#{$this->opt($this->t('Your OTP for login is:'))}\s*(\d+)#");
        $c->setCode($code);

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
}
