<?php

namespace AwardWallet\Engine\airasia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class LoginOTP extends \TAccountChecker
{
    public $mailFiles = "airasia/statements/it-344944353.eml";

    public $detectFrom = "login@service.airasia.co.in";
    public $detectSubject = [
        'AirAsia Login OTP',
        'AirAsia Email Change OTP',
    ];
    public static $dictionary = [
        'en' => [
            "detectText" => [
                'as your One Time Password for changing your email on AirAsia India.',
                'is your OTP for logging on to AirAsia India.',
            ],
        ],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['detectText']) && $this->http->XPath->query("//text()[" . $this->contains($dict["detectText"]) . "]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class));

        $detectSubject = false;

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($parser->getSubject(), $dSubject) !== false) {
                $detectSubject = true;

                break;
            }
        }

        if ($detectSubject == false) {
            return $email;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['detectText']) && $this->http->XPath->query("//text()[" . $this->contains($dict["detectText"]) . "]")->length > 0) {
                $this->lang = $lang;

                $st = $email->add()->statement();

                $st->setMembership(true);

                return $email;
            }
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
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
