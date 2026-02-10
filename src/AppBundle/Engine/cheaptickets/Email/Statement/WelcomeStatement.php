<?php

namespace AwardWallet\Engine\cheaptickets\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WelcomeStatement extends \TAccountChecker
{
    public $mailFiles = "cheaptickets/statements/it-63334737.eml, cheaptickets/statements/it-63349350.eml";
    private $lang = '';
    private $reFrom = ['.cheaptickets.com'];
    private $reProvider = ['CheapTickets'];
    private $reSubject = [
        'Welcome to CheapTickets,',
        'Reset your CheapTickets password',
    ];
    private $reBody = [
        'en' => [
            ['Welcome to CheapTickets!', 'Thanks for creating a CheapTickets account!'],
            ['We heard you need to reset your CheapTickets account password.', 'This email expires in'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            return $email;
        }
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $subject = $parser->getHeader('subject');
        // Welcome to CheapTickets, Mateusz!
        $name = $this->http->FindPreg("/^{$this->opt($this->t('Welcome to CheapTickets,'))}\s+([[:alpha:]\s.\-]{2,})!$/u",
            false, $subject);
        // Hey Daniel,
        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hey'))}]", null,
                false, "/^{$this->opt($this->t('Hey'))}\s*([[:alpha:]\s.\-]{2,}),$/u");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }
        $st->setNoBalance(true);
        $st->setMembership(true);

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
