<?php

namespace AwardWallet\Engine\flysaa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class ProgrammeUpdate extends \TAccountChecker
{
    public $mailFiles = "flysaa/statements/it-64250783.eml, flysaa/statements/it-64259528.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectSubjects = [
        'SAA Voyager Programme Update – ',
        'An important announcement about the Voyager programme',
    ];

    private $detectBody = [
        'en' => [
            'we announced a few temporary changes to the Voyager programme ',
            'An important announcement about the Voyager programme',
        ],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'saanoreply@flysaa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.flysaa.com/za/en/voyager')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        // Number
        $number = $this->http->FindSingleNode("//text()[" . $this->eq('Membership Number:') . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*(\d{5,12})\s*$/");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[" . $this->starts('Membership Number:') . "]", null, true,
                "/:\s*(\d{5,12})\s*$/");
        }
        $st->setNumber($number);

        // Tier
        $status = $this->http->FindSingleNode("//text()[" . $this->eq('Tier Status:') . "]/following::text()[normalize-space()][1]");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[" . $this->starts('Tier Status:') . "]", null, true,
                "/:\s*(.+)\s*$/");
        }
        $st->addProperty('Tier', $status);

        // Balance
        $balance = $this->http->FindSingleNode("//text()[" . $this->eq('Available Miles*:') . "]/following::text()[normalize-space()][1]");

        if (preg_match("/^\s*(\d[\d,]*)\s*$/", $balance)) {
            $st->setBalance(str_replace(',', '', $balance));
            $st->setBalanceDate(strtotime($this->http->FindSingleNode("//text()[" . $this->starts('*The above figures are accurate as of') . "]", null, true,
                "/The above figures are accurate as of\s+(.+)\.\s*$/")));
        } else {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
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
}
