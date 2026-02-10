<?php

namespace AwardWallet\Engine\aviancataca\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Account extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-578870226.eml, aviancataca/it-601277507.eml, aviancataca/statements/it-64366457.eml, aviancataca/statements/it-69145519.eml";
    private $lang = '';
    private $reFrom = ['lifemiles@confirmation.lifemiles.com'];
    private $reProvider = ['LifeMiles LTD'];
    private $reSubject = [
        // es
        'Bienvenido a LifeMiles!',
        'No has activado la autenticación en dos pasos',
        // en
        'You have configured 2-Step Verification',
    ];
    private $reBody = [
        'es' => [
            ['¡Bienvenido a LifeMiles! Tu número LifeMiles', 'Tu número LifeMiles es:'],
            ['Tu cuenta LifeMiles no está protegida por la autenticación en dos pasos', 'Tu número LifeMiles es:'],
        ],
        'en' => [
            ['¡Bienvenido a LifeMiles! Tu número LifeMiles', 'Tu número LifeMiles es:'],
            ['You have enabled 2-step verification on your LifeMiles', 'Your LifeMiles number is:'],
        ],
    ];
    private static $dictionary = [
        'es' => [
            // 'Hola '  => '',
            // 'Tu número LifeMiles es:' => '',
        ],
        'en' => [
            'Hola '                   => 'Hi ',
            'Tu número LifeMiles es:' => 'Your LifeMiles number is:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();
        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Tu número LifeMiles es:'))}]/ancestor::td[1]", null,
            false, "/^\s*{$this->opt($this->t('Tu número LifeMiles es:'))}\s*(\d{5,})\s*$/u");
        $name = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Hola '))}])[1]", null,
            false, "/^{$this->opt($this->t('Hola '))}\s*([[:alpha:]\s.\-]{1,}),/u");

        if (!empty($name)) {
            $st->setLogin($number);
            $st->setNumber($number);

            $st->addProperty('Name', $name);
            $st->setMembership(true);
            $st->setNoBalance(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@confirmation.lifemiles.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->arrikey($headers['from'], $this->reFrom) === false) {
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
        return array_keys(self::$dictionary);
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
                    $this->lang = substr($lang, 0, 2);

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
