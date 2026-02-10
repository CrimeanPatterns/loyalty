<?php

namespace AwardWallet\Engine\aegean\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OneTimeCode extends \TAccountChecker
{
    public $mailFiles = "aegean/it-164910233.eml";
    public $subjects = [
        // en
        'Complete your account login',
        // it
        'Completa l\'accesso al tuo account',
        // de
        'Anmeldung abschließen',
        // fr
        'Procédez à la connexion à votre compte',
        // ru
        'Завершите вход в свою учетную запись',
        // el
        'Ολοκληρώστε τη σύνδεση στον λογαριασμό σας',
        // es
        'Completar el inicio de sesión de su cuenta',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Complete your account login'   => 'Complete your account login',
            'Your one-time password is the' => 'Your one-time password is the',
        ],
        "it" => [
            'Complete your account login'   => 'Completa l\'accesso al tuo account',
            'Your one-time password is the' => 'La tua password monouso è',
        ],
        "de" => [
            'Complete your account login'   => 'Anmeldung abschließen',
            'Your one-time password is the' => 'Ihr Einmal-Passwort lautet',
        ],
        "fr" => [
            'Complete your account login'   => 'Procédez à la connexion à votre compte',
            'Your one-time password is the' => 'Votre mot de passe à usage unique est',
        ],
        "ru" => [
            'Complete your account login'   => 'Завершите вход в свою учетную запись',
            'Your one-time password is the' => 'Ваш одноразовый пароль',
        ],
        "el" => [
            'Complete your account login'   => 'Ολοκληρώστε τη σύνδεση στον λογαριασμό σας',
            'Your one-time password is the' => 'Ο κωδικός μίας χρήσης είναι ο',
        ],
        "es" => [
            'Complete your account login'   => 'Completar el inicio de sesión de su cuenta',
            'Your one-time password is the' => 'Su contraseña de un solo uso es',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aegeanair.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'AEGEAN AIRLINES') or contains(normalize-space(), 'Aegean Airlines. All rights reserved')]")->length > 0) {
            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['Complete your account login']) && !empty($dict['Your one-time password is the'])
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Complete your account login'])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Your one-time password is the'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aegeanair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) == true) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your one-time password is the'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your one-time password is the'))}\s*(\d+)/"));
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
}
