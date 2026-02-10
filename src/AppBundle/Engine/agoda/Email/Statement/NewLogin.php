<?php

namespace AwardWallet\Engine\agoda\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NewLogin extends \TAccountChecker
{
    public $mailFiles = "agoda/statements/it-64958097.eml, agoda/statements/it-65220485.eml";
    public $subjects = [
        // en
        'New login on your Agoda account',
        // zh
        '你的Agoda帳戶有新的登入活動',
        '您的Agoda賬號近期登錄異常',
        // id
        'Login baru di akun Agoda Anda',
        // ja
        'お客様のアゴダアカウントに新規ログインがありました',
        // pt
        'Novo início de sessão na sua conta Agoda',
        // fr
        'Nouvel identifiant sur votre compte Agoda',
        // ru
        'Зафиксирован вход в ваш аккаунт на Agoda',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'We noticed a recent sign-in from your account' => [
                'We noticed a recent sign-in from your account',
                'We noticed a recent login from your account',
            ],

            'Agoda' => ['Agoda', 'Agoda Account Protection Team'],
        ],
        "zh" => [
            'We noticed a recent sign-in from your account' => [
                '我們留意到最近有人嘗試登入',
                '近期有人登入您的帳戶：',
            ],
            'Device'            => '裝置',
            'Hi'                => '，你好：',
            'from your account' => ['嘗試登入你的帳戶', '人登入您的帳戶'],
            'Agoda'             => ['Agoda', 'Agoda Account Protection Team'],
        ],
        "id" => [
            'We noticed a recent sign-in from your account' => [
                'Kami mendeteksi alamat email Anda',
            ],
            'Device'            => 'Perangkat',
            'Hi'                => 'Halo',
            'from your account' => 'alamat email Anda',
            'Agoda'             => ['Agoda', 'Agoda Account Protection Team'],
        ],
        "ja" => [
            'We noticed a recent sign-in from your account' => [
                '最近、お客様のアカウント',
            ],
            'Device'            => 'デバイス：',
            'Hi'                => 'さん、こんにちは',
            'from your account' => 'お客様のアカウント',
            'Agoda'             => ['Agoda', 'Agoda Account Protection Team'],
        ],
        "pt" => [
            'We noticed a recent sign-in from your account' => [
                'Detetámos um início de sessão recente na',
            ],
            'Device'            => 'Dispositivo',
            'Hi'                => 'Olá,',
            'from your account' => 'recente na sua conta',
            'Agoda'             => ['Agoda', 'Equipa de Proteção de Contas Agoda'],
        ],
        "fr" => [
            'We noticed a recent sign-in from your account' => [
                'Nous avons constaté une connexion récente sur votre compte',
            ],
            'Device'            => 'Appareil',
            'Hi'                => 'Bonjour',
            'from your account' => 'sur votre compte',
            'Agoda'             => ['Agoda', 'L\'Équipe de protection des comptes d\'Agoda'],
        ],
        "ru" => [
            'We noticed a recent sign-in from your account' => [
                'Мы заметили, что кто-то недавно пытался войти в ваш аккаунт',
            ],
            'Device'            => 'Устройство:',
            'Hi'                => 'Здравствуйте,',
            'from your account' => 'войти в ваш аккаунт',
            'Agoda'             => ['Agoda'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@security.agoda.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Agoda')]")->length > 0
                && !empty($dict['We noticed a recent sign-in from your account']) && $this->http->XPath->query("//text()[{$this->contains($dict['We noticed a recent sign-in from your account'])}]")->length > 0
                && !empty($dict['Device']) && $this->http->XPath->query("//text()[{$this->contains($dict['Device'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]security\.agoda\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['We noticed a recent sign-in from your account']) && $this->http->XPath->query("//text()[{$this->contains($dict['We noticed a recent sign-in from your account'])}]")->length > 0
                && !empty($dict['Device']) && $this->http->XPath->query("//text()[{$this->contains($dict['Device'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();
        $st->setNoBalance(true);

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+(\D+)$/");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^(\D+)\s+{$this->opt($this->t('Hi'))}$/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('from your account'))}]", null, true, "/{$this->opt($this->t('from your account'))}\s+([^\(\s]+@[^\(\s]+)\b/");

        if (empty($login)) {
            $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('from your account'))}]/following::text()[contains(normalize-space(), '@')][1]");
        }
        $st->setLogin($login)
            ->setNumber($login);

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
