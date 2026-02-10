<?php

namespace AwardWallet\Engine\airbaltic\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class JunkNotFinish extends \TAccountChecker
{
    private $lang = 'en';

    private $detectFrom = 'flights@info.airbaltic.com';
    private $detectSubject = [
        // en
        'Can we help you with your booking?',
        // lv
        'Vai varam kā palīdzēt ar biļešu iegādi?',
        ' Tevi gaida!',
        // et
        'Kas saame Sulle broneerimisel abiks olla?',
        // lt
        'Ar galime padėti tau su užsakymu?',
        // fi
        'Voimmeko auttaa sinua varauksen teossa?',
        ' ootab Sind!',
        // ru
        'Kак мы можем вам помочь с вашим бронированием?',
        // de
        'Können wir Ihnen bei Ihrer Buchung behilflich sein?',
    ];

    private static $dictionary = [
        'en' => [
            'If you would like to finish the booking please follow the link below' => [
                'If you would like to finish the booking please follow the link below',
            ],
            "Complete booking" => ['Complete booking'],
        ],
        'lv' => [
            'If you would like to finish the booking please follow the link below' => [
                'Ja vēlies turpināt rezervāciju, spied uz saites zemāk.',
            ],
            "Complete booking" => ['Pabeigt rezervāciju', 'Apskati kalendāru'],
        ],
        'et' => [
            'If you would like to finish the booking please follow the link below' => 'Näib, et oled broneerimise pooleli jätnud.',
            "Complete booking"                                                     => 'Lõpeta broneering',
        ],
        'lt' => [
            'If you would like to finish the booking please follow the link below' => [
                'Panašu, kad tavo skrydžio užsakymas nebuvo baigtas.',
                'negavote užsakymo patvirtinimo bei norite užbaigti užsakymą, spustelėkite žemiau esančią nuorodą.',
            ],
            "Complete booking" => ['Užbaigti užsakymą', 'Žiūrėti kalendorių'],
        ],
        'fi' => [
            'If you would like to finish the booking please follow the link below' => [
                'Lennon varaaminen jäi kesken.',
                'Kui soovid oma broneeringut kinnitada, kuid Sa ei ole saanud broneeringu kinnitusmeili, siis klõpsa alloleval lingil.',
            ],
            "Complete booking" => ['Tee varaus loppuun', 'Vaata kalendrit'],
        ],
        'ru' => [
            'If you would like to finish the booking please follow the link below'
                               => 'Похоже, вы остановились на середине бронирования полёта.',
            "Complete booking" => 'Завершить бронирование',
        ],
        'de' => [
            'If you would like to finish the booking please follow the link below' => [
                '‌Scheinbar waren Sie gerade dabei, einen Flug zu buchen.',
            ],
            "Complete booking" => 'Buchung abschließen',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['If you would like to finish the booking please follow the link below'])
                && !empty($dict['Complete booking'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['If you would like to finish the booking please follow the link below'])}]")->length > 0
                && $this->http->XPath->query("//a[{$this->eq($dict['Complete booking'])} and contains(@href, '.airbaltic.com')]")->length > 0
            ) {
                $email->setIsJunk(true);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) != true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.airbaltic.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['If you would like to finish the booking please follow the link below'])
                && !empty($dict['Complete booking'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['If you would like to finish the booking please follow the link below'])}]")->length > 0
                && $this->http->XPath->query("//a[{$this->eq($dict['Complete booking'])} and contains(@href, '.airbaltic.com')]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
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
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
