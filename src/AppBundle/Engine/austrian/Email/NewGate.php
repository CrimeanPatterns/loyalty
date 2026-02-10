<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Schema\Parser\Email\Email;

class NewGate extends \TAccountChecker
{
    public $mailFiles = "austrian/it-173915679.eml, austrian/it-324444967.eml, austrian/it-324457848.eml";

    private $detectFrom = "austrian@smile.austrian.com";
    private $detectSubject = [
        // en
        'New gate', //New Gate F12 for your flight OS373  Vienna - Amsterdam on 07.07.2022
        'Departure gate',
    ];
    private $detectBody = [
        'en' => [
            'The gate for your flight',
            'Your Austrian flight',
        ],
    ];

    private $subject;

    private $lang;
    private static $dictionary = [
        'en' => [
            "The gate for your flight " => ["The gate for your flight ", "Your Austrian flight "],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.austrian.com/'], '@href')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->subject = $parser->getSubject();
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Your Austrian flight is ')]", null, true, "/{$this->opt($this->t('Your Austrian flight is '))}(\w+)/");

        if (!empty($status)) {
            $f->setStatus($status);
        }

        $s = $f->addSegment();

        // New Gate
        $text = $this->http->FindSingleNode("//text()[{$this->contains($this->t('The gate for your flight '))}][not({$this->contains($this->t('flight is'))})]");

        if ($this->http->XPath->query("//text()[normalize-space()='Find alternative flights']")->length > 0) {
            $text = $this->http->FindSingleNode("//text()[normalize-space()='Find alternative flights']/preceding::text()[{$this->contains($this->t('The gate for your flight '))}][1][not({$this->contains($this->t('flight is'))})]");
        }

        if (
            preg_match($this->t("/flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) from (?<dep>.+?)(?:\s*\((?<depCode>[A-Z]{3})\))? to (?<arr>.+?)\s* on [\d\.]+\s*will depart\s*(?:\s*\((?<arrCode>[A-Z]{3})\))? on (?<date>[\d\.]+) (?:is|at) (?<depTime>[\d\:]+)?/"), $text, $m)
            || preg_match($this->t("/flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) from (?<dep>.+?)(?:\s*\((?<depCode>[A-Z]{3})\))? to (?<arr>.+?)(?:\s*\((?<arrCode>[A-Z]{3})\))? on (?<date>[\d\.]+) (?:is|at) (?<depTime>[\d\:]+)?/"), $text, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            if (!empty($m['depCode'])) {
                $s->departure()
                    ->code($m['depCode']);
            } else {
                $s->departure()
                    ->noCode();
            }

            $s->departure()
                ->name($m['dep']);

            if (isset($m['date']) && isset($m['depTime'])) {
                $s->departure()
                    ->date(strtotime($m['date'] . ' ' . $m['depTime']));
            } elseif (isset($m['date']) && !isset($m['depTime'])) {
                $s->departure()
                    ->day(strtotime($m['date']))
                    ->noDate();
            } elseif (!isset($m['date'])) {
                $s->departure()
                    ->noDate();
            }

            if (!empty($m['arrCode'])) {
                $s->arrival()
                    ->code($m['arrCode']);
            } else {
                $s->arrival()
                    ->noCode();
            }

            $s->arrival()
                ->name($m['arr'])
                ->noDate();

            return true;
        }

        //  Important information for your flight
        if (preg_match($this->t("/New Gate .* flight (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) (?<dep>.+?) - (?<arr>.+?) on (?<date>[\d\.]+)/"), $this->subject, $m) && !empty($m['al'])) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            $s->departure()
                ->name($m['dep'])
                ->noCode()
                ->day(strtotime($m['date']))
                ->noDate()
            ;

            $s->arrival()
                ->noCode()
                ->noDate();

            return true;
        }

        return true;
    }

    private function assignLang()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
