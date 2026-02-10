<?php

namespace AwardWallet\Engine\ebrite\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderCancelled extends \TAccountChecker
{
    public $mailFiles = "ebrite/it-115290720.eml";

    public $detectSubject = [
        // en
        'Free Order Cancelled for',
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
            'Event Name:'   => ['Event Name:', 'Event Name :'],
            'Order Number:' => ['Order Number:', 'Order Number :'],
        ],
    ];

    private $detectBody = [
        'en' => ['was successfully cancelled through Eventbrite.'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $event = $email->add()->event();

        $event->setEventType(Event::TYPE_EVENT);

        $event->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Order Number:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*(\d{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello '))}]", null, true, "/{$this->opt($this->t('Hello '))}\s*([[:alpha:] \-]+),?\s*$/"), false)
            ->cancelled()
        ;

        $name = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Event Name:")) . "]/following::text()[normalize-space()][1]");

        $event->place()
            ->name($name);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.eventbrite.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".eventbrite.com/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Eventbrite. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
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
        return count(self::$dictionary);
    }

    private function detectBody(): bool
    {
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Event Name:']) || empty($phrases['Order Number:'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Event Name:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Order Number:'])}]")->length > 0
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
