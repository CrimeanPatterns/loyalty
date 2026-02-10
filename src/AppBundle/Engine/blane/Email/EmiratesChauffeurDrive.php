<?php

namespace AwardWallet\Engine\blane\Email;

use AwardWallet\Schema\Parser\Email\Email;

class EmiratesChauffeurDrive extends \TAccountChecker
{
    public $mailFiles = "blane/it-662125050.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Your ride details' => 'Your ride details',
        ],
    ];

    private $detectFrom = "emirates.cds@info.blacklane.com";
    private $detectSubject = [
        // en
        'Your Emirates Chauffeur-Drive ride in',
    ];
    private $detectBody = [
        'en' => [
            'We look forward to chauffeuring you in',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]blacklane\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // TODO choose case

        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Emirates Chauffeur-Drive') === false
        ) {
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
        if (
            $this->http->XPath->query("//a[{$this->contains(['blacklane.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Blacklane Booking Number'])}]")->length === 0
        ) {
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
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Your ride details"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Your ride details'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $t = $email->add()->transfer();

        $t->general()
            ->confirmation(
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Blacklane Booking Number:'))}]/following::text()[normalize-space()][1]"),
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Blacklane Booking Number:'))}]"), true
            )
            ->confirmation(
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Emirates Booking Reference:'))}]/following::text()[normalize-space()][1]"),
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Emirates Booking Reference:'))}]")
            )
        ;

        // Segment
        $s = $t->addSegment();

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('From:'))}]/following::text()[normalize-space()][1]");
        $s->departure()
            ->date(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Date:'))}]/following::text()[normalize-space()][1]")))
            ->name($name)
        ;

        if (preg_match("/^\s*([A-Z]{3}) Airport\s*$/", $name, $m)) {
            $s->departure()
                ->code($m[1]);
        }

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('To:'))}]/following::text()[normalize-space()][1]");
        $s->arrival()
            ->noDate()
            ->name($name)
        ;

        if (preg_match("/^\s*([A-Z]{3}) Airport\s*$/", $name, $m)) {
            $s->arrival()
                ->code($m[1]);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

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
