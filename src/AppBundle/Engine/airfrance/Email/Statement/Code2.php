<?php

namespace AwardWallet\Engine\airfrance\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code2 extends \TAccountChecker
{
    public $mailFiles = "airfrance/statements/it-722208696.eml";
    public $subjects = [
        // en
        "We just need to know it’s you",
        // pt
        'Só precisamos confirmar que é você',
        // es
        'Solo queremos confirmar que es usted',
        // fr
        'Nous devons nous assurer de votre identité',
        // zh
        '我们只是需要知道是您本人在操作',
        // ja
        'ご本人様確認が必要です',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'To verify your identity, we have sent you this one-time PIN code.'
                        => 'To verify your identity, we have sent you this one-time PIN code.',
            'PIN code:' => 'PIN code:',
        ],
        "pt" => [
            'To verify your identity, we have sent you this one-time PIN code.'
                        => 'Para verificar a sua identidade, enviámos-lhe um código PIN único.',
            'PIN code:' => 'Código PIN:',
        ],
        "es" => [
            'To verify your identity, we have sent you this one-time PIN code.'
                        => 'Para verificar su identidad, le hemos enviado un código PIN de un solo uso.',
            'PIN code:' => 'Código PIN:',
        ],
        "fr" => [
            'To verify your identity, we have sent you this one-time PIN code.'
                        => 'Pour vérifier votre identité, nous vous avons envoyé un code PIN à usage unique.',
            'PIN code:' => 'Code PIN :',
        ],
        "zh" => [
            'To verify your identity, we have sent you this one-time PIN code.'
                        => '为验证您的身份，我们向您发送了临时密码。',
            'PIN code:' => '临时密码：',
        ],
        "ja" => [
            'To verify your identity, we have sent you this one-time PIN code.'
                        => 'お客様の身元確認のために、PINコード（ワンタイムパスワード）を送信させていただきました。',
            'PIN code:' => 'PINコード：',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'])
            && (stripos($headers['from'], 'account@service-airfrance.com') !== false || stripos($headers['from'], 'account@service-klm.com') !== false)
        ) {
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
        if ($this->http->XPath->query("//text()[{$this->contains(['Air France', 'KLM Royal Dutch Airlines'])}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['To verify your identity, we have sent you this one-time PIN code.'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['To verify your identity, we have sent you this one-time PIN code.'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['airfrance', 'klm'];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]infos-airfrance.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (stripos($parser->getCleanFrom(), '@infos-klm.com') !== false) {
            $email->setProviderCode('klm');
        } elseif (stripos($parser->getCleanFrom(), '@infos-airfrance.com') !== false
        ) {
        } elseif (
            $this->http->XPath->query("//text()[{$this->contains('KLM Royal Dutch Airlines')}]")->length > 0
        ) {
            $email->setProviderCode('klm');
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['To verify your identity, we have sent you this one-time PIN code.'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['To verify your identity, we have sent you this one-time PIN code.'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $code = $email->add()->oneTimeCode();
        $code->setCode($this->http->FindSingleNode("//text()[{$this->starts($this->t('PIN code:'))}]", null, true, "/^{$this->opt($this->t('PIN code:'))}\s*(\d+)$/"));

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
