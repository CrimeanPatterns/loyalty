<?php

namespace AwardWallet\Engine\chase\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class DeserveStatement extends \TAccountChecker
{
    public $mailFiles = "chase/statements/it-63235905.eml, chase/statements/it-63266082.eml, chase/statements/it-63266799.eml, chase/statements/it-76953609.eml, chase/statements/it-77147280.eml";
    private $lang = '';
    private $reFrom = ['@e.chase.com', 'chase@email.chase.com'];
    private $reProvider = ['Chase & Co'];
    private $reSubject = [
        'You deserve it! Nicholas, announcing 5 total Rapid Rewards®',
        'Correction to World of Hyatt Bonus Points',
    ];
    private $reBody = [
        'en' => [
            'Email intended for',
        ],
    ];
    private static $dictionary = [
        'en' => [
            'For your account ending in:' => [
                'For your account ending in:',
                'For your account(s) ending in:',
                'credit card ending in',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (
            stripos($parser->getCleanFrom(), 'chasetravelbyexpedia') !== false || $this->http->XPath->query("//*[" . $this->contains(['chasetravelbyexpedia']) . "]")->length > 0
            || stripos($parser->getCleanFrom(), 'urtravel.chase.') !== false || $this->http->XPath->query("//*[" . $this->contains(['ultimaterewardstraveldv']) . "]")->length > 0
            || $this->http->XPath->query("//*[" . $this->contains(['Trip ID:']) . "]")->length > 0
        ) {
            // вероятно в письме есть резервация
            return false;
        }

        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('For your account ending in:'))}]/following::text()[normalize-space()][1]",
            null, false, "/^\s*(\d{4})\s*$/");

        if (!$number) {
            $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('For your account ending in:'))}]",
                null, false, "/" . $this->opt($this->t('For your account ending in:')) . "\s*(\d+)\s*$/");
        }

        if (empty($number) && empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Email intended for'))}]/following::text()[normalize-space()][position() < 5][contains(translate(., '0123456789', '##########'), '####')])[1]"))) {
            $st->setMembership(true);
        } /* else {
            $st->setLogin($number)->masked();
            $st->setNumber($number)->masked();
        }*/
        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Email intended for'))}]/following-sibling::*[1]",
            null, false, "/^\s*(.{3,30})$/");

        if (!$name) {
            $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Email intended for'))}]", null,
                false, "/" . $this->opt($this->t('Email intended for')) . "[\s:]*(.{3,30})$/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
            $st->setMembership(true);
            $st->setNoBalance(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

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
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0
            || $this->http->XPath->query("//a[contains(@href, '.chase.com')]")->length < 3
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'Flight overview')]")->length > 0) {
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
            if ($this->http->XPath->query("//text()[{$this->contains($values)}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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
