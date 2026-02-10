<?php

namespace AwardWallet\Engine\aviancataca\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NewsStatement extends \TAccountChecker
{
    public $mailFiles = "aviancataca/statements/it-64366457.eml, aviancataca/statements/it-69145519.eml";
    private $lang = '';
    private $reFrom = ['@news.avianca.com'];
    private $reProvider = ['Avianca'];
    private $reSubject = [
        '✈ LifeMiles program news!',
        'Loyalty News tiene noticias para ti',
        'Loyalty News has an update for you',
    ];
    private $reBody = [
        'en' => [
            ['Your new Elite level will be valid until', 'LifeMiles Number:'],
        ],
        'en2' => [
            ['You still deserve to enjoy your benefits:', 'LifeMiles Number:'],
        ],
        'es' => [
            ['Mereces seguir disfrutando tus beneficios:', 'Número LifeMiles:'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Hello ' => ['Dear ', 'Hello '],
        ],
        'es' => [
            'Hello '            => ['Hola'],
            'LifeMiles Number:' => 'Número LifeMiles:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('LifeMiles Number:'))}]/following-sibling::*[1]", null,
            false, "/^\d+$/u");
        $name = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Hello '))}])[1]", null,
            false, "/^{$this->opt($this->t('Hello '))}\s*([[:alpha:]\s.\-]{1,}),/u");

        if (!empty($name)) {
            $st->setLogin($number);
            $st->setNumber($number);

            $st->addProperty('Name', $name);
            $st->setMembership(true);
            $st->setNoBalance(true);
        }

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
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
//        if ($this->http->XPath->query("//img[{$this->contains('Mileage Balance', '@alt')}]")->length == 0) {
//            return false;
//        }
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
