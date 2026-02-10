<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AccountUpdate extends \TAccountChecker
{
    public $mailFiles = "aa/statements/it-62025724.eml, aa/statements/it-96737857.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            "AAdvantage account update" => [
                'AAdvantage account update',
                'AAdvantage email address updated',
                'Your password was changed',
                'We reset your password',
                'Your account number request',
            ],
        ],
        'pt' => [
            "AAdvantage account update" => [
                'Atualização da conta AAdvantage',
                'Endereço de email AAdvantage atualizado',
                'Sua senha foi alterada',
                'Sua solicitação de número de conta',
            ],
        ],
        'es' => [
            "AAdvantage account update" => [
                'Actualización de la cuenta AAdvantage',
                'Su solicitud de número de cuenta',
                'La dirección de e-mail de AAdvantage ha sido actualizada'
            ],
        ],
    ];

    private $detectSubjects = [
        'en' => [
            'Your AAdvantage account was updated',
            'Your AAdvantage email address was updated',
            'Your password was changed',
            'Your password request',
            'Your AAdvantage number request',
        ],
        'pt' => [
            'Sua conta AAdvantage foi atualizada',
            'Seu endereço de email do AAdvantage foi atualizado',
            'Sua solicitação de número AAdvantage'
        ],
        'es' => [
            'Su cuenta AAdvantage ha sido actualizada',
            'Su solicitud de número AAdvantage',
            'Su dirección de e-mail de AAdvantage ha sido actualizada',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'American.Airlines@aa.com') !== false || stripos($from, 'american.airlines@info.email.aa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $detectSubjects) {
            foreach ($detectSubjects as $dSubjects) {
                if (stripos($headers['subject'], $dSubjects) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['AAdvantage account update']) && $this->http->XPath->query("//node()[" . $this->contains($dict['AAdvantage account update']) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['AAdvantage account update']) && $this->http->XPath->query("//node()[" . $this->contains($dict['AAdvantage account update']) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("AAdvantage account update")) . "]/preceding::text()[" . $this->starts($this->t("AAdvantage #:")) . "]", null, true,
            "#AAdvantage \#:\s*([A-Z\d]{5,12})\s*$#");

        if (!empty($number)) {
            $st->setNumber($number);
            $st->setLogin($number);
        }

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("AAdvantage account update")) . "]/preceding::text()[" . $this->starts($this->t("AAdvantage #:")) . "]/preceding::text()[normalize-space()][1]");

        if (preg_match("#^\s*([[:alpha:]]+) ([[:alpha:]]+)\s*$#u", $name, $m)) {
            $st->addProperty('Name', $name);
            $st->addProperty('LastName', $m[2]);
        } elseif (preg_match("#^\s*([[:alpha:]]+(?: [[:alpha:]]+)*)\s*$#u", $name, $m)) {
            $st->addProperty('Name', $name);
        }

        if (empty($number) && empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("AAdvantage account update")) . "]/preceding::text()[normalize-space()][1]");

            if (preg_match("#^\s*([[:alpha:]]+) ([[:alpha:]]+)\s*$#u", $name, $m)) {
                $st->addProperty('Name', $name);
                $st->addProperty('LastName', $m[2]);
            } elseif (preg_match("#^\s*([[:alpha:]]+(?: [[:alpha:]]+)*)\s*$#u", $name, $m)) {
                $st->addProperty('Name', $name);
            }
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("(//node()[" . $this->eq($this->t("AAdvantage® number:")) . "])[1]/following::text()[normalize-space()][1]", null, true,
                "#^\s*([A-Z\d]{5,12})\s*$#");

            if (!empty($number)) {
                $st->setNumber($number);
                $st->setLogin($number);
            }
        }

        if (!empty($number) || !empty($name)) {
            $st->setNoBalance(true);
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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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
}
