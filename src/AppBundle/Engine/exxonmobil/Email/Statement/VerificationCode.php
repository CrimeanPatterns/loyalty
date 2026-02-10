<?php

namespace AwardWallet\Engine\exxonmobil\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "exxonmobil/statements/it-315899836.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'We received an access request for your Exxon Mobil Rewards+™ account' => 'We received an access request for your Exxon Mobil Rewards+™ account',
            'Your verification code:'                                              => 'Your verification code:',
        ],
    ];

    private $detectFrom = "info@exxonandmobilrewardsplus.com";
    private $detectSubject = [
        // en
        'verification code',
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
        if ($this->detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (
                !empty($dict['Your verification code:']) && !empty($dict['We received an access request for your Exxon Mobil Rewards+™ account'])
                && $this->http->XPath->query("//text()[" . $this->contains($dict['We received an access request for your Exxon Mobil Rewards+™ account']) . "]")->length > 0
                && $this->http->XPath->query("//text()[" . $this->eq($dict['Your verification code:']) . "]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict['Your verification code:']) && !empty($dict['We received an access request for your Exxon Mobil Rewards+™ account'])
                && $this->http->XPath->query("//text()[" . $this->contains($dict['We received an access request for your Exxon Mobil Rewards+™ account']) . "]")->length > 0
                && $this->http->XPath->query("//text()[" . $this->eq($dict['Your verification code:']) . "]")->length > 0
            ) {
                $this->lang = $lang;
                $code = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Your verification code:')) . "]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*(\d{6})\s*$/");

                if (!empty($code)) {
                    $otc = $email->add()->oneTimeCode();
                    $otc->setCode(str_replace(' ', '', $code));
                }

                break;
            }
        }

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
        return 0;
    }

    private function t($phrase)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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
