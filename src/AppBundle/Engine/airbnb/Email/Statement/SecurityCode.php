<?php

namespace AwardWallet\Engine\airbnb\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class SecurityCode extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-146142590.eml, airbnb/statements/it-95687957.eml";


    public $detectSubject = [
        // en
        'Your security code is',
        // pt
        'Seu código de segurança é',
        // it
        'Il tuo codice di sicurezza è',
        // es
        'Tu código de seguridad es',
        // de
        'Dein Sicherheitscode lautet ',
        // fr
        'Votre code de sécurité est',
        // no
        'Sikkerhetskoden din er ',
        // nl
        'Je beveilingscode is',
        // ko
        '보안 코드:',
        // zh
        '你的安全碼是',
        // ru
        'Ваш код безопасности —',
        // ar
        'كود الأمن هو',
    ];
    public static $dictionary = [
        "en" => [
            'Your Airbnb security code' => 'Your Airbnb security code',
            'Never share your code' => 'Never share your code',
        ],
        "pt" => [
            'Your Airbnb security code' => 'Seu código de segurança do Airbnb',
            'Never share your code' => 'Nunca compartilhe seu código com ninguém',
        ],
        "it" => [
            'Your Airbnb security code' => 'Il tuo codice di sicurezza Airbnb',
            'Never share your code' => 'Non condividere mai il tuo codice con nessuno',
        ],
        "es" => [
            'Your Airbnb security code' => 'Tu código de seguridad de Airbnb',
            'Never share your code' => ['Nunca compartas tu código con nadie', 'No compartas tu código con nadie'],
        ],
        "de" => [
            'Your Airbnb security code' => 'Dein Airbnb-Sicherheitscode',
            'Never share your code' => 'Teile deinen Code niemals',
        ],
        "fr" => [
            'Your Airbnb security code' => 'Votre code de sécurité Airbnb',
            'Never share your code' => 'Ne communiquez jamais votre code à quiconque',
        ],
        "no" => [
            'Your Airbnb security code' => 'Din Airbnb-sikkerhetskode',
            'Never share your code' => 'Del aldri koden din med noen',
        ],
        "nl" => [
            'Your Airbnb security code' => 'Je Airbnb-beveiligingscode',
            'Never share your code' => 'Deel je code nooit met anderen',
        ],
        "ko" => [
            'Your Airbnb security code' => '에어비앤비 보안 코드',
            'Never share your code' => '아무에게도 코드를 알려주지 마세요.',
        ],
        "zh" => [
            'Your Airbnb security code' => '你的 Airbnb 安全碼',
            'Never share your code' => '絕不要與任何人分享你的驗證碼',
        ],
        "ru" => [
            'Your Airbnb security code' => 'Ваш код безопасности Airbnb',
            'Never share your code' => 'Никому не сообщайте этот код.',
        ],
        "ar" => [
            'Your Airbnb security code' => 'كود الأمن على Airbnb',
            'Never share your code' => 'لا تشارك الرمز الخاص بك مع أي شخص',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airbnb.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || stripos($headers['from'], 'automated@airbnb.com') === false) {
            return false;
        }
        foreach ($this->detectSubject as $dSubject) {
            if (!empty($headers['subject']) && stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your Airbnb security code']) && !empty($dict['Never share your code'])
                && $this->http->XPath->query('//*[self::p or self::td]['.$this->contains($dict['Your Airbnb security code']).']')->length > 0
                && $this->http->XPath->query('//*[self::p or self::td]['.$this->contains($dict['Never share your code']).']')->length > 0
            ) {
                return true;
            }
        }
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your Airbnb security code']) && !empty($dict['Never share your code'])
            ) {
                $codeText = implode(" ", $this->http->FindNodes('//*['.$this->contains($dict['Your Airbnb security code']).']
                    /following-sibling::*['.$this->contains($dict['Never share your code']).']
                    /following-sibling::*[1]//text()[normalize-space()]'));
                if (preg_match("/^\s*(\d{4,6})(\s+\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})?\s*$/", $codeText, $m)) {
                    $code = $m[1];
                }

                if (!empty($code)) {
                    $email->add()->oneTimeCode()->setCode($code);
                    $email->add()->statement()->setNoBalance(true)->setMembership(true);
                }
            }
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }
}
