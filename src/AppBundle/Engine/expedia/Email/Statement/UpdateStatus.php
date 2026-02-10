<?php

namespace AwardWallet\Engine\expedia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class UpdateStatus extends \TAccountChecker
{
    public $mailFiles = "expedia/it-62778534.eml, expedia/statements/it-72936464.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = "expediamail.";
    private $detectSubjects = [
        "You’re so close to Silver status",
        "You've gone Gold",
        "congratulations (start enjoying your membership)",
        " you qualify for Silver status!",
        "Your points are departing on",
        "A change to your VIP Access benefits",
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getCleanFrom()) === false
            || (stripos($parser->getHeader('from'), 'Expedia Rewards') === false)) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($parser->getSubject(), $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/Dear\s*(\D+)\,/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $status = null;

        if (!empty($this->http->FindSingleNode("//img[@alt='Gold_Badge' or contains(@src, '/g_tier_badge_')]/@src"))) {
            $status = 'Gold';
        }

        if (!empty($this->http->FindSingleNode("//img[@alt='Silver_Badge' or contains(@src, '/s_tier_badge_')]/@src"))) {
            $status = 'Silver';
        }

        if (!empty($this->http->FindSingleNode("//img[@alt='Blue_Badge' or contains(@src, '/b_tier_badge_')]/@src"))) {
            $status = 'Blue';
        }

        if ($this->striposAll($parser->getSubject(), ['You\'ve gone Gold.']) === true) {
            $status = 'Gold';
        }

        if ($this->striposAll($parser->getSubject(), ['you qualify for Silver status!']) === true) {
            $status = 'Silver';
        }

        if ($this->striposAll($parser->getSubject(), ['close to Silver status', 'start enjoying your membership']) === true) {
            $status = 'Blue';
        }

        if (!empty($status)) {
            $st->addProperty('Status', $status);
            $st->setNoBalance(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class) . ucfirst($this->lang));

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
