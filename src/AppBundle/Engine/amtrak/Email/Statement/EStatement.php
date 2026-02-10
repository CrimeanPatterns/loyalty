<?php

namespace AwardWallet\Engine\amtrak\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers amtrak/CompanionStatement (in favor of amtrak/CompanionStatement)

class EStatement extends \TAccountChecker
{
    public $mailFiles = "amtrak/statements/it-63676511.eml";
    private $lang = '';
    private $reFrom = ['@e-mail.amtrak.com'];
    private $reProvider = ['Amtrak'];
    private $reSubject = [
        'Your August eStatement: Earnings, savings, and more inside',
    ];
    private $reBody = [
        'en' => [
            ['Amtrak and Amtrak Guest Rewards are', 'eStatement'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->info("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $nodes = $this->http->FindNodes("//text()[{$this->contains('eStatement')}]/ancestor::td[1]//text()");
        $text = join("\n", $nodes);
        $this->logger->debug($text);

        /*
        August eStatement
        John | Member
        #
        8470953756
         */
        if (preg_match("/{$this->opt('eStatement')}\s*([[:alpha:]\s]{3,})\s+\|\s+([[:alpha:]\s]{3,})\s*#\s*(\d{5,})/",
            $text, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->setLogin($m[3]);
            $st->setNumber($m[3]);
            $st->setMembership(true);

            $st->parseBalanceDate($this->http->FindSingleNode("//text()[{$this->contains('Points as of')}]", null, false, "/{$this->opt('Points as of')}\s+(.+)/"));
            $balance = $this->http->FindSingleNode("//text()[{$this->contains('Points as of')}]/ancestor::tr[1]/following-sibling::tr[1]", null, false, self::BALANCE_REGEXP);
            $st->setBalance(str_replace(',', '', $balance));
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
        if ($this->http->XPath->query("//text()[{$this->contains('eStatement')}]")->length == 0) {
            return false;
        }

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
