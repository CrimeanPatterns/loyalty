<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ChangeReservationJunk extends \TAccountCheckerAa
{
    public $mailFiles = "aa/it-59331677.eml";

    public $detectFrom = ["American.Airlines@aa.com"];
    public $detectSubject = [
        'AA.com Change Reservation Confirmation',
    ];
    public $detectBody = [
        'en' => ['Thank you for modifying your travel arrangements on AA.com.'],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear ')]", null, true, "#Dear (.+?)[\s\,\.\!]$#");
        // if $traveller is traveller name, go to aa/API type 'ChangeReservation'
        if (!empty($traveller) && preg_match("#Customer#i", $traveller)) {
            $email->setIsJunk(true);

            return $email;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!empty($headers["subject"])) {
            foreach ($this->detectSubject as $dSubject) {
                if (strpos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Check flight status'], $words['Flight'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Check flight status'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Flight'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
