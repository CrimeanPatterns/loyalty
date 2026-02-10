<?php

namespace AwardWallet\Engine\airfrance\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "airfrance/statements/it-260975749.eml";
    public $subjects = [
        // en
        "We just need to know it’s you",
        // nl
        'We willen gewoon zeker weten dat u het bent',
        // es
        'Solo queremos confirmar que es usted',
        // pt
        'Só precisamos confirmar que é você',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'We’ve sent you this one-time PIN code to verify that it’s you!'
                        => 'We’ve sent you this one-time PIN code to verify that it’s you!',
            'PIN code:' => 'PIN code:',
        ],
        "nl" => [
            'We’ve sent you this one-time PIN code to verify that it’s you!'
                        => 'We willen zeker weten dat u het bent!',
            'PIN code:' => 'Pincode:',
        ],
        "es" => [
            'We’ve sent you this one-time PIN code to verify that it’s you!'
                        => 'Le hemos enviado este código PIN de un solo uso para verificar su identidad.',
            'PIN code:' => 'Código PIN:',
        ],
        "pt" => [
            'We’ve sent you this one-time PIN code to verify that it’s you!'
                        => 'Enviámos-lhe este código PIN de utilização única para verificarmos a sua identidade!',
            'PIN code:' => 'Código PIN:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'])
            && (stripos($headers['from'], '@infos-airfrance.com') !== false || stripos($headers['from'], '@infos-klm.com') !== false)
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
            if (!empty($dict['We’ve sent you this one-time PIN code to verify that it’s you!'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['We’ve sent you this one-time PIN code to verify that it’s you!'])}]")->length > 0
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
            if (!empty($dict['We’ve sent you this one-time PIN code to verify that it’s you!'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['We’ve sent you this one-time PIN code to verify that it’s you!'])}]")->length > 0
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
