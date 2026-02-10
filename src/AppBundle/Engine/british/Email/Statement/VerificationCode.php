<?php

namespace AwardWallet\Engine\british\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "british/statements/it-626566464.eml, british/statements/it-96688715.eml";
    private $detectSubjects = [
        // en
        'Your verification code',
        'Account security code',
        'Verify your identity',
        // de
        'Ihr Verifizierungscode',
        'Kontosicherheitscode',
        //fr
        'Code de sécurité du compte',
        //pt
        'Código de segurança da conta',
        //zh
        '帐户安全码',
        //ru
        'Код безопасности аккаунта',
        //it
        'Codice di sicurezza del conto',
    ];
    private $lang = '';

    private static $dictionary = [
        'en' => [
            "Your verification code is:" => ["Your verification code is:", "Security code:", "Your code is:"],
            "detect"                     => ["Use this to unlock your ba.com account", "Please use the following security code to access your Executive Club account",
                "Please use the following code to help verify your identity:", "If it expires you must generate a new one in order to authenticate.", ],
        ],
        'de' => [
            "Your verification code is:" => ["Ihr Verifizierungscode lautet:", "Sicherheitscode:"],
            "detect"                     => ["Verwenden Sie ihn, um Ihr Konto auf ba.com freizuschalten", "Bitte verwenden Sie den folgenden Sicherheitscode, um auf Ihr Executive Club-Konto zuzugreifen"],
        ],
        'fr' => [
            "Your verification code is:" => ["Code de sécurité :"],
            "detect"                     => ["Veuillez utiliser le code de sécurité suivant pour accéder à votre compte Executive Club"],
        ],
        'pt' => [
            "Your verification code is:" => ["Código de segurança:"],
            "detect"                     => ["Utilize o seguinte código de segurança para aceder à sua conta do Executive Club"],
        ],
        'zh' => [
            "Your verification code is:" => ["安全码："],
            "detect"                     => ["请使用以下安全码访问您的 Executive Club 帐户。"],
        ],
        'ru' => [
            "Your verification code is:" => ["Код безопасности:"],
            "detect"                     => ["Используйте следующий код безопасности для доступа к аккаунту Executive Club"],
        ],
        'it' => [
            "Your verification code is:" => ["Codice di sicurezza:"],
            "detect"                     => ["Per accedere al conto Executive Club, utilizza il seguente codice di sicurezza"],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["detect"]) && $this->http->XPath->query("//text()[" . $this->contains($dict["detect"]) . "]")->length > 0) {
                $this->lang = $lang;
                $st = $email->add()->statement();

                $st
                    ->setMembership(true)
                    ->setNoBalance(true)
                ;

                break;
            }
        }

        $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your verification code is:'))}]", null, true,
            "/{$this->opt($this->t('Your verification code is:'))}\s*(\d+)\s*$/u");

        if (empty($code)) {
            $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your verification code is:'))}]/ancestor::tr[1]", null, true,
                "/{$this->opt($this->t('Your verification code is:'))}\s*(\d+)\s*$/u");
        }

        if (!empty($code)) {
            $email->add()->oneTimeCode()->setCode($code);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'ba.verifyidentity@ba.com') !== false;
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
            if (isset($dict["detect"]) && $this->http->XPath->query("//text()[" . $this->contains($dict["detect"]) . "]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
