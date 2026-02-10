<?php

namespace AwardWallet\Engine\ebrite\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class JunkStillInterested extends \TAccountChecker
{
    public $mailFiles = "ebrite/it-522711992.eml";

    public $detectSubject = [
        // en, de
        'are waiting',
        // it
        'ti aspettano',
    ];

    public $lang = '';
    public static $dictionary = [
        "en" => [
            'Still interested in' => 'Still interested in',
            'Get tickets'         => ['Get tickets', 'Get my tickets', 'Conseguir entradas', 'Complete Your Order'],
        ],
        "de" => [
            'Still interested in' => 'Noch interessiert an',
            'Get tickets'         => ['Tickets kaufen'],
        ],
        "it" => [
            'Still interested in' => 'Ti interessa ancora',
            'Get tickets'         => ['Ordina biglietti'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query('//a[contains(@href,".eventbrite.com")]')->length === 0
        ) {
            return false;
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setIsJunk(true);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.eventbrite.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
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

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (empty($phrases['Still interested in']) || empty($phrases['Get tickets'])) {
                continue;
            }

            if (
                ($this->http->XPath->query("/descendant::text()[normalize-space()][not(ancestor::style)][position() < 4][{$this->starts($phrases['Still interested in'])}][contains(., '?')]")->length > 0
                    || $this->http->XPath->query("/descendant::text()[normalize-space()][not(ancestor::style)][position() < 5][{$this->eq($phrases['Still interested in'])}]/following::text()[normalize-space()][2][normalize-space() = '?']")->length > 0
                )
                && $this->http->XPath->query("/descendant::text()[normalize-space()][not(ancestor::style)]")->length < 20
                && $this->http->XPath->query("//a[{$this->eq($phrases['Get tickets'])}][contains(@href, 'eventbrite')]")->length > 0
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
