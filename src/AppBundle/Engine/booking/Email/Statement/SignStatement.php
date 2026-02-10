<?php

namespace AwardWallet\Engine\booking\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SignStatement extends \TAccountChecker
{
    public $mailFiles = "booking/statements/it-63214752.eml, booking/statements/it-63653205.eml, booking/statements/it-78011780.eml";
    private $lang = '';
    private $reFrom = ['@booking.com'];
    private $reProvider = ['Booking.com'];
    private $reSubject = [
        // en
        'New sign-in to your account',
        'Booking.com account locked - please reset your password',
        // pt
        'Novo login na sua conta',
        // es
        'Nuevo inicio de sesión en tu cuenta',
        // zh
        '帐号登录异常',
        // ja
        'アカウントに新しいログインがありました',
        // pl
        'Nowa próba zalogowania na konto',
        // nl
        'Nieuwe inlogpoging op je account',
        // fr
        'Nouvelle connexion à votre compte',
        // de
        'Neue Anmeldung in Ihrem Konto',
        // ko
        '새로운 로그인 활동 감지',
    ];
    private $reBody = [
        'en' => [
            ['New sign-in to your account', 'Your account was used to sign in from a new device or browser.'],
            ['missing some important details', 'Update account details'],
        ],
        'it' => [
            [
                'Nuovo accesso al tuo account',
                'Il tuo account è stato utilizzato per accedere da un nuovo dispositivo o browser.',
            ],
        ],
        'pt' => [
            [
                'Novo login na sua conta',
                'Sua conta foi usada para fazer login em um novo dispositivo ou navegador.',
            ],
        ],
        'es' => [
            [
                'Nuevo inicio de sesión en tu cuenta',
                'Se ha iniciado sesión en tu cuenta desde un nuevo dispositivo o navegador.',
            ],
        ],
        'zh' => [
            [
                '帐号登录异常',
                '您的帐号已从新设备或浏览器登录。',
            ],
        ],
        'ja' => [
            [
                'アカウントに新規ログイン',
                'お使いのアカウントが、新しいデバイスまたはブラウザからのログインに使用されました。',
            ],
        ],
        'pl' => [
            [
                'Nowa próba zalogowania na konto',
                'Twoje konto zostało użyte do zalogowania się z nowego urządzenia lub innej przeglądarki.',
            ],
        ],
        'nl' => [
            [
                'Nieuwe inlogpoging op je account',
                'Je account is gebruikt om in te loggen vanaf een nieuw toestel of nieuwe browser.',
            ],
        ],
        'fr' => [
            [
                'Nouvelle connexion à votre compte',
                'Une connexion à votre compte a été détectée depuis un nouvel appareil ou navigateur.',
            ],
        ],
        'de' => [
            [
                'Neue Anmeldung in Ihrem Konto',
                'Jemand hat sich auf einem neuen Gerät oder Browser in Ihrem Konto angemeldet.',
            ],
        ],
        'ru' => [
            [
                'Вход в аккаунт с нового устройства/места',
                'В ваш аккаунт выполнен вход с нового устройства или браузера.',
            ],
        ],
        'ko' => [
            [
                '새로운 로그인 활동 감지',
                '고객님의 계정이 새로운 기기 또는 브라우저에서 로그인되었습니다.',
            ],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Hello' => ['Hello', 'Hi'],
        ],
        'it' => [
            'Hello' => ['Ciao'],
        ],
        'pt' => [
            'Hello' => ['Olá,'],
        ],
        'es' => [
            'Hello' => ['Hola,'],
        ],
        'zh' => [
            'Hello' => ['你好，'],
        ],
        'ja' => [
            'Hello' => ['さん'],
        ],
        'pl' => [
            'Hello' => ['Witaj'],
        ],
        'nl' => [
            'Hello' => ['Hallo'],
        ],
        'fr' => [
            'Hello' => ['Bonjour'],
        ],
        'de' => [
            'Hello' => ['Hallo'],
        ],
        'ru' => [
            'Hello' => ['Здравствуйте,'],
        ],
        'ko' => [
            //            'Hello' => ['']
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hello'))}]", null,
            false, "/^{$this->opt($this->t('Hello'))}\s*([[:alpha:]\s]{2,})[,:]$/u");

        $this->logger->warning("//text()[{$this->contains($this->t('Hello'))}]");
        $this->logger->error($name);

        if ($this->lang == 'ja' && empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hello'))}]", null,
                false, "/^([[:alpha:]\s]{2,})\s*{$this->opt($this->t('Hello'))}$/u");
        }

        if (!empty($name) && $nameFull = $this->http->FindSingleNode("//a[{$this->contains($name)}]")) {
            $name = $nameFull;
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }
        $st->setNoBalance(true);
        $st->setMembership(true);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
