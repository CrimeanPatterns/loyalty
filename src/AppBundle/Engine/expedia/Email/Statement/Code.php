<?php

namespace AwardWallet\Engine\expedia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "expedia/statements/it-274389341.eml, expedia/statements/it-424864865-hotels.eml";

    public static $dictionary = [
        "en" => [
            'Your secure code expires in 15 minutes.' => 'Your secure code expires in 15 minutes.',
            'Hi'                                      => 'Hi',
        ],
        "pt" => [
            'Your secure code expires in 15 minutes.' => 'O código de segurança expira em 15 minutos.',
            'Hi'                                      => 'Olá',
        ],
        "no" => [
            'Your secure code expires in 15 minutes.' => 'Sikkerhetskoden din utløper om 15 minutter.',
            'Hi'                                      => 'Hei',
        ],
        "de" => [
            'Your secure code expires in 15 minutes.' => 'Ihr Sicherheitscode läuft in 15 Minuten ab.',
            'Hi'                                      => 'Hallo',
        ],
        "ko" => [
            'Your secure code expires in 15 minutes.' => '보안 코드는 15분 후 만료됩니다.',
            'Hi'                                      => '안녕하세요',
        ],
        "ja" => [
            'Your secure code expires in 15 minutes.' => 'このセキュリティコードは 15 分で期限切れになります。',
            'Hi'                                      => '様',
        ],
        "it" => [
            'Your secure code expires in 15 minutes.' => 'Il tuo codice di sicurezza scade tra 15 minuti.',
            'Hi'                                      => 'Ciao',
        ],
        "es" => [
            'Your secure code expires in 15 minutes.' => 'Tu código de seguridad caduca en 15 minutos.',
            'Hi'                                      => 'Hola',
        ],
        "da" => [
            'Your secure code expires in 15 minutes.' => 'Din kode udløber om 15 minutter.',
            'Hi'                                      => 'Hej',
        ],
        "fr" => [
            'Your secure code expires in 15 minutes.' => 'Votre code de sécurité expire dans 15 minutes.',
            'Hi'                                      => 'Bonjour',
        ],
        "zh" => [
            'Your secure code expires in 15 minutes.' => '你的安全碼將於 15 分鐘後逾時。',
            'Hi'                                      => '您好',
        ],
        "fi" => [
            'Your secure code expires in 15 minutes.' => 'Turvallinen koodisi vanhenee 15 minuutin kuluttua.',
            'Hi'                                      => 'Hei',
        ],
        "sv" => [
            'Your secure code expires in 15 minutes.' => 'Din säkerhetskod går ut om 15 minuter.',
            'Hi'                                      => 'Hej',
        ],
        "tr" => [
            'Your secure code expires in 15 minutes.' => 'Güvenli kodunuzun süresi 15 dakika içinde sona erer.',
            'Hi'                                      => 'Merhaba',
        ],
        "nl" => [
            'Your secure code expires in 15 minutes.' => 'De veiligheidscode vervalt over 15 minuten.',
            'Hi'                                      => 'Hallo',
        ],
        "th" => [
            'Your secure code expires in 15 minutes.' => 'รหัสรักษาความปลอดภัยจะหมดอายุใน 15 นาที',
            'Hi'                                      => 'สวัสดี',
        ],
        "hu" => [
            'Your secure code expires in 15 minutes.' => 'Biztonságos kódod 15 perc múlva lejár.',
            'Hi'                                      => 'Üdv',
        ],
    ];

    private $providerCode = '';
    private $lang;

    private $detectSubject = [
        // en
        'is your secure sign in code',
        // pt
        'é o seu código de login seguro',
        // no
        'er din sikre påloggingskode',
        // de
        'ist Ihr sicherer Anmeldecode',
        // ko
        '보안 로그인 코드입니다.',
        // ja
        '安全なサインインコードです',
        // it
        'è il tuo codice di accesso sicuro',
        // es
        'es su código de inicio de sesión seguro',
        // da
        'er din sikre log-in kode',
        // fr
        'est votre code de connexion sécurisé',
        // zh
        '是您的安全登錄代碼',
        // fi
        'on suojattu kirjautumiskoodisi',
        // sv
        'är din säkra inloggningskod',
        // tr
        'güvenli oturum açma kodunuz',
        // nl
        'is uw veilige inlogcode',
        // th
        'เป็นรหัสลงชื่อเข้าใช้ที่ปลอดภัยของคุณ',
        // hu
        'az Ön biztonságos bejelentkezési kódja',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['from'], '@accounts.hotels.com') !== false
            || strpos($headers['from'], '@expediagroup.com') !== false
            || strpos($headers['from'], 'do-not-reply@accounts.expedia.com') !== false
        ) {
            foreach ($this->detectSubject as $dSubject) {
                if (strpos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (!$this->assignProvider($parser->getHeaders())) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:expediagroup|accounts\.expedia)\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            return $email;
        }

        $this->assignProvider($parser->getHeaders());
        $email->setProviderCode($this->providerCode);
        $email->setType('Code');

        $secureCode = $this->http->FindSingleNode("//node()[{$this->eq($this->t('Your secure code expires in 15 minutes.'))}]/following::text()[normalize-space()][1]", null, true, '/^\d{5,}$/');

        if ($secureCode) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($secureCode);

            $traveller = $this->http->FindSingleNode("//node()[{$this->eq($this->t('Your secure code expires in 15 minutes.'))}]/preceding::text()[{$this->starts($this->t('Hi'))}][1]", null, true, "/^\s*{$this->opt($this->t('Hi'))}\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*[,;:!?]$/u");

            if ($traveller) {
                $st = $email->add()->statement();
                $st->addProperty('Name', $traveller)->setNoBalance(true);
            }
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

    public static function getEmailProviders()
    {
        return ['hotels', 'expedia'];
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Your secure code expires in 15 minutes."])
                && $this->http->XPath->query("//*[{$this->eq($dict['Your secure code expires in 15 minutes.'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function assignProvider($headers): bool
    {
        if (strpos($headers['from'], '@accounts.hotels.com') !== false
            || $this->http->XPath->query('//img[normalize-space(@alt)="hotels logo" or contains(@src,".hotels.com/") and contains(@src,"/logo.")]')->length === 1
            || $this->http->XPath->query('//img[normalize-space(@alt)="hoteis logo" or contains(@src,".hoteis.com/") and contains(@src,"/logo.")]')->length === 1
            || $this->http->XPath->query('//img[normalize-space(@alt)="hoteles logo" or contains(@src,".hoteles.com/") and contains(@src,"/logo.")]')->length === 1
        ) {
            $this->providerCode = 'hotels';

            return true;
        }

        if (strpos($headers['from'], '@expediagroup.com') !== false
            || $this->http->XPath->query('//img[normalize-space(@alt)="expedia logo" or contains(@src,".expedia.com/") and contains(@src,"/logo.")]')->length === 1
        ) {
            $this->providerCode = 'expedia';

            return true;
        }

        return false;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
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
