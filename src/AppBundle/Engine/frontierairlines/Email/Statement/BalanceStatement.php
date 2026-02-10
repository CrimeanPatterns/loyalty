<?php

namespace AwardWallet\Engine\frontierairlines\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BalanceStatement extends \TAccountChecker
{
    public $mailFiles = "frontierairlines/statements/it-63508424.eml";
    private $lang = '';
    private $reFrom = ['myfrontier@emails.flyfrontier.com'];
    private $reProvider = ['Frontier Airlines'];
    private $reSubject = [
        ', your miles balance could be ',
        'LIMITED TIME: Join Discount Den and earn a',
    ];
    private $reBody = [
        'en' => [
            ['This one-time offer is valid for eligible cardmembers.', 'This message was sent to'],
            ['Unlock a TON of savings!', 'This message was sent to'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (self::detectEmailFromProvider(implode(' ', $parser->getFrom())) !== true) {
            // without this check, the detections may intersect with YourTrip
            return false;
        }
        $this->assignLang();
        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//text()[{$this->contains($this->t('With your current balance of'))}]", null,
            false, "/{$this->opt($this->t('With your current balance of'))}\s+([\d,.\-\s]+)M/u");

        if (!empty($balance)) {
            $st->setBalance(str_replace(',', '', $balance));
        }

        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('This message was sent to'))}]/following-sibling::*[normalize-space()]/text()",
            null,
            false, "/^.+?@.+?$/u");

        if (!empty($login)) {
            $st->setLogin($login);
            $st->setMembership(true);

            if (empty($balance)) {
                $st->setNoBalance(true);
            }
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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
