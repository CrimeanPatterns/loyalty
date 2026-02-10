<?php

namespace AwardWallet\Engine\ctmoney\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notification extends \TAccountChecker
{
    public $mailFiles = "ctmoney/statements/it-84984452.eml, ctmoney/statements/it-85613180.eml, ctmoney/statements/it-85761320.eml";
    public $subjects = [
        // en
        'You\'re one step away from joining Triangle!',
        'Canadian Tire Customer Panel',
        'TESTED for Life in Canada: Testers Needed for Tools and Hardware',
        'TESTED for Life in Canada: Testers Needed for Tools and Hardware',
        'Reset Your Triangle Password',
        'Password change notification',
        // fr
        'Veuillez terminer votre inscription Triangle',
    ];

    public $lang = 'en';

    public $detectLang = [
        "fr" => ["Veuillez"],
        "en" => ["password", "verification", "successfully", "Your", "account", "access", "having registered with"],
    ];

    public static $dictionary = [
        "en" => [
            'detectBody' => [
                'This study will help Canadian Tire',
                'The Tested for Life in Canada team is currently',
                'Forgot Your Triangle Password',
                'Your password was successfully changed',
                'The final step is to verify your Triangle ID email address',
                'You\'ve got yourself a brand new password',
                'You are receiving this periodic newsletter after having registered with Triangle Community',
                'Forgot Your Triangle Password?',
                'your Triangle ID password has been successfully changed',
                'request to reset your Triangle ID password',
            ],
            'Hey' => ['Hey', 'Hello'],
        ],
        "fr" => [
            'detectBody' => [
                'Veuillez terminer votre inscription Triangle',
            ],
            'Hey' => ['Bonjour'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '.triangle.com') !== false) {
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
        if ($this->assignLang() == true) {
            return $this->http->XPath->query("//text()[{$this->contains(['Canadian Tire', 'Triangle ID Password', 'Triangle ID password'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('detectBody'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:email|signin)\.triangle\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Hey'))}\s+(\S+[@]\S+\.\S+)\,/");
        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey'))}]", null, true, "/^{$this->opt($this->t('Hey'))}\s+(\D+)\,/");

        if ((!empty($login) && empty($name)) || (!empty($login) && !empty($name))) {
            $st->setLogin($login);
            $st->setNoBalance(true);
        } elseif (!empty($name) && empty($login)) {
            $st->addProperty('Name', trim($name, ','))
                ->setNoBalance(true);
        } elseif ($this->detectEmailByBody($parser) == true) {
            $st->setMembership(true);
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

    private function assignLang()
    {
        if (isset($this->detectLang)) {
            foreach ($this->detectLang as $lang => $reBody) {
                foreach ($reBody as $word) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$word}')]")->length > 0
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }
}
