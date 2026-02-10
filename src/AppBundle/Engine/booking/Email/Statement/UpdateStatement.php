<?php

namespace AwardWallet\Engine\booking\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpdateStatement extends \TAccountChecker
{
    public $mailFiles = "booking/statements/it-63502034.eml, booking/statements/it-63561932.eml";
    private $lang = '';
    private $reFrom = ['@booking.com'];
    private $reProvider = ['Booking.com'];
    private $reSubject = [
        ', your account was updated',
        'il tuo account è stato aggiornato',
    ];
    private $reBody = [
        'en' => [
            ["s been an update to your account.", 'you can securely reset your password here'],
        ],
        'it' => [
            ["il tuo account è stato aggiornato.", 'puoi reimpostare la password in modo sicuro da qui:'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
        'it' => [
            'Your Booking.com password for' => "La tua password di Booking.com per l",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $name = $this->http->FindSingleNode("(//a[{$this->contains($this->t('https://www.booking.com/'), '@href')}]/ancestor::td[1]/following-sibling::td[1])[1]",
            null,
            false, "/^\s*([[:alpha:]\-\s'\\\]{2,})$/u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }
        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your Booking.com password for'))}]",
            null,
            false, "/\s+([\w.\-_]{2,}@[\w.\-_]{2,})\s+/u");

        if (!empty($login)) {
            $st->setLogin($login);
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
        return [];
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
                    $this->lang = $lang;

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
