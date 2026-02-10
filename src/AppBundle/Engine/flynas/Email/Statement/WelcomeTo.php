<?php

namespace AwardWallet\Engine\flynas\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WelcomeTo extends \TAccountChecker
{
    public $mailFiles = "flynas/statements/it-64154588.eml, flynas/statements/it-296694242.eml";
    public $subjects = [
        '/Welcome to Family Points$/i',
        '/Password Change/i',
    ];

    public $lang = 'ar';

    public static $dictionary = [
        "ar" => [
            'Dear'    => 'عزيزي',
            'Welcome' => 'مرحبًا',
            'noNames' => ['بك في نقاط العائلة'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flynas.com') !== false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".flynas.com/") or contains(@href,"www.flynas.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"password on flynas.com") or contains(.,"www.flynas.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flynas\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->detectBody()) {
            $this->logger->debug('Unknown format!');

            return $email;
        }

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $xpathNoName = "not({$this->contains($this->t('noNames'))})";

        $st = $email->add()->statement();

        $name = null;
        $names = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Welcome'))}][{$xpathNoName}]", null, "/^{$this->opt($this->t('Welcome'))}[,\s]+({$patterns['travellerName']})(?:\s*[،,;:!?]|$)/u"));

        if (count(array_unique($names)) === 1) {
            $name = array_shift($names);
        }

        if ($name === null) {
            $names = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]/ancestor::p[1][{$xpathNoName}]", null, "/^({$patterns['travellerName']})\s+{$this->opt($this->t('Dear'))}$/u"));

            if (count(array_unique($names)) === 1) {
                $name = array_shift($names);
            }
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
            $st->setNoBalance(true);
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

    private function detectBody(): bool
    {
        $phrases = [
            'لقد قمت بإنشاء حساب نقاط العائلة معنا !',
            'بناءً على طلبك، تم تجديد كلمة المرور الخاصة بك',
        ];

        return $this->http->XPath->query("//*[{$this->contains($phrases)}]")->length > 0;
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
