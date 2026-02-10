<?php

namespace AwardWallet\Engine\ebrite\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderCancelled2 extends \TAccountChecker
{
    public $mailFiles = "ebrite/it-508335507.eml, ebrite/it-514389035.eml, ebrite/it-515391013.eml";

    public $detectSubject = [
        // en
        'Order CANCELLED for',
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
            'Order no.'          => ['Order no.', 'Order #'],
            'has been cancelled' => 'has been cancelled',
        ],
    ];

    private $detectBody = [
        'en' => ['Tickets in this order have been voided and will no longer grant entry to'],
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

        $table = $this->http->FindNodes("//tr[{$this->starts($this->t('Order no.'))}][.//img][not({$this->contains($this->t('has been cancelled'))})]//td[not(.//td)][normalize-space()]");

        if (count($table) !== 3) {
            $table = [];
        }
        $event = $email->add()->event();

        $event->type()->event();

        $event->general()
            ->confirmation($this->re("/{$this->opt($this->t('Order no.'))}\s*(\d{5,})\s*$/", $table[0]))
            ->cancelled()
            ->status('Cancelled')
        ;

        $this->logger->debug('$table = ' . print_r($table, true));
        $event->place()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Tickets in this order have been voided and will no longer grant entry to")) . "]/following::text()[normalize-space()][1]"));

        // Thu., 28/09/2023 5:30 pm (AEST)
        // Mon, 2 Oct 2023 22:00 - 23:30 (UTC)
        // Tue., 26/09/2023 9:00 am - Thu., 28/09/2023 5:00 pm (AEST)
        // Thu., 28/09/2023 1:00 pm - 2:00 pm (AEST)
        $dateStr = preg_replace("/\s*\(\s*[A-Z]+\s*\)$/", '', $table[2]);

        if (
            preg_match("/^\s*(?<dateStart>.+\d{4}.*) (?<timeStart>\d{1,2}:\d{2}[ apm\.]*) - (?<dateEnd>.+\d{4}.*) (?<timeEnd>\d{1,2}:\d{2}[ apm\.]*)\s*$/i", $dateStr, $m)
            || preg_match("/^\s*(?<dateStart>.+\d{4}.*) (?<timeStart>\d{1,2}:\d{2}[ apm\.]*) - (?<timeEnd>\d{1,2}:\d{2}[ apm\.]*)\s*$/i", $dateStr, $m)
            || preg_match("/^\s*(?<dateStart>.+\d{4}.*) (?<timeStart>\d{1,2}:\d{2}[ apm\.]*)\s*$/i", $dateStr, $m)
        ) {
            $event->booked()
                ->start($this->normalizeDate($m['dateStart'] . ', ' . $m['timeStart']));

            if (!empty($m['timeEnd'])) {
                $event->booked()
                    ->end($this->normalizeDate((!empty($m['dateEnd']) ? $m['dateEnd'] : $m['dateStart']) . ', ' . $m['timeEnd']));
            } else {
                $event->booked()
                    ->noEnd();
            }
        }

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
            if (!is_string($lang) || empty($phrases['Order no.']) || empty($phrases['has been cancelled'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Order no.'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['has been cancelled'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            // Thu., 28/09/2023, 1:00 pm
            "#^\s*[[:alpha:]]+[.,\s]+(\d+)\\/(\d+)\\/(\d{4})\s*,\s+(\d+:\d+(\s*[ap]m)?)\s*$#ui",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        $this->logger->debug('$str = ' . print_r($str, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
