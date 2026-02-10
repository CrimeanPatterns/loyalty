<?php

namespace AwardWallet\Engine\booking\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationLink extends \TAccountChecker
{
    public $mailFiles = "booking/statements/it-96508736.eml, booking/statements/it-98991642.eml";

    private $detectSubjects = [
        // en
        'Your verification link',
        // pt
        'Seu link de verificação',
        // pl
        'Twój link weryfikacyjny',
        // it
        'Il tuo link di verifica',
        // fr
        'Votre lien de vérification',
        // es
        'Tu enlace de verificación',
        'Tu link de verificación',
        // ru
        'Ваша ссылка для входа',
        // ro
        'Link-ul dvs. de verificare',
        // hr
        'Vaš verifikacijski link',
        // pt
        'Seu link de verificação',
        // fl
        'Ang verification link mo',
        // bs
        'Vaš link za verifikaciju',
        // ko
        '인증 링크가 도착했습니다',
    ];
    private $lang = '';

    private static $dictionary = [
        'en' => [
            "sign in to your Booking.com account for" => "sign in to your Booking.com account for",
            "Verify me"                               => "Verify me",
        ],
        'pt' => [
            "sign in to your Booking.com account for" => "sua conta da Booking.com associada ao e-mail",
            "Verify me"                               => "Verificar",
        ],
        'pl' => [
            "sign in to your Booking.com account for" => "do konta na Booking.com przypisanego dla adresu",
            "Verify me"                               => "Zweryfikuj mnie",
        ],
        'it' => [
            "sign in to your Booking.com account for" => "accedere al tuo account Booking.com con l'indirizzo",
            "Verify me"                               => "Verifica la tua identità",
        ],
        'fr' => [
            "sign in to your Booking.com account for" => "connecter au compte Booking.com associé à l'adresse e-mail",
            "Verify me"                               => "Confirmer mon identité",
        ],
        'es' => [
            "sign in to your Booking.com account for" => ["iniciar sesión en tu cuenta de Booking.com asociada a", "sesión en la cuenta de Booking.com vinculada a la dirección"],
            "Verify me"                               => ["Verificar", "Verificar que soy yo"],
        ],
        'ru' => [
            "sign in to your Booking.com account for" => "чтобы войти в аккаунт Booking.com, привязанный к адресу",
            "Verify me"                               => "Подтвердить",
        ],
        'ro' => [
            "pentru a vă autentifica în contul Booking.com pentru",
            "Verify me" => "Verificare",
        ],
        'hr' => [
            "sign in to your Booking.com account for" => "se prijavili u svoj Booking.com račun povezan s e-adresom",
            "Verify me"                               => "Potvrdi identitet",
        ],
        'pt' => [
            "sign in to your Booking.com account for" => "login na sua conta da Booking.com associada ao e-mail",
            "Verify me"                               => "Verificar",
        ],
        'fl' => [
            "sign in to your Booking.com account for" => "para makapag-sign in sa iyong Booking.com account para sa",
            "Verify me"                               => "I-verify ako",
        ],
        'bs' => [
            "sign in to your Booking.com account for" => "nastavku kako biste se ulogovali na Booking.com nalog za adresu",
            "Verify me"                               => "I-verify ako",
        ],
        'ko' => [
            "sign in to your Booking.com account for" => "Booking.com 계정(",
            "Verify me"                               => "인증하기",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $login = null;
        $code = null;

        foreach (self::$dictionary as $lang => $dict) {
            if (empty($login) && isset($dict["sign in to your Booking.com account for"])) {
                $login = $this->http->FindSingleNode("//text()[" . $this->contains($dict["sign in to your Booking.com account for"]) . "]/following::text()[normalize-space()][1]",
                    null, true,
                    "/^\s*([\w.\-_]{2,}@[\w.\-_]{2,})\s*$/u");
            }

            if (empty($code) && isset($dict["Verify me"])) {
                $code = $this->http->FindSingleNode("//a[" . $this->eq($dict["Verify me"]) . " and contains(@href, 'account.booking.com')]/@href");

                if (stripos($code, 'urldefense.com') !== false) {
                    $code = preg_replace("/^https:\/\/urldefense\.com\/v3\/__(.+)__;.*/", '$1', $code);
                }

                if (stripos($code, '.protection.outlook.com') !== false) {
                    $code = preg_replace("/^.*\.protection\.outlook\.com\\/\?url=(.+?)&data=.*/", '$1', $code);
                    $code = urldecode($code);
                }

                if (stripos($code, 'urlsand.esvalabs.com') !== false) {
                    $code = preg_replace("/^.*\.urlsand\.esvalabs\.com\\/\?u=(.+?)&sdata=.*/", '$1', $code);
                    $code = urldecode($code);
                }
            }

            if (!empty($login) && !empty($code)) {
                $this->lang = $lang;

                break;
            }
        }

        if (!empty($code)) {
            $code = preg_replace("/(.+\?[^=]+=[a-z_\-\=\d]{10,})&[^=]+=.+/i", '$1', $code);
            $otc = $email->add()->oneTimeCode();
            $otc
                ->setCodeAttr("/https:\/\/account\.booking\.com\/(?:applink\/(?:web|booking)\?data|confirm-magic-link-token\?op_token)=[a-z_\-\=\d]{10,}$/i", 5000);
            $otc
                ->setCode($code)
            ;

            $st = $email->add()->statement();

            $st
                ->setMembership(true)
                ->setNoBalance(true)
            ;

            if (!empty($login)) {
                $st->setLogin($login);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@booking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === true) {
            foreach ($this->detectSubjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Verify me"]) && $this->http->XPath->query("//a[" . $this->eq($dict["Verify me"]) . " and contains(@href, 'account.booking.com')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
