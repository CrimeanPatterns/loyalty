<?php

namespace AwardWallet\Engine\alamo\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsed emails from @goalamo.com

class Goalamo extends \TAccountChecker
{
    public $mailFiles = "alamo/statements/it-72388748.eml, alamo/statements/it-72397332.eml, alamo/statements/it-72460363.eml, alamo/statements/it-83411341.eml";

    public $detectSubject = [
        'pt' => 'Confirmação de inscrição do Alamo Insiders',
        'en' => 'Important Information about your Alamo Insiders Membership',
        'Alamo Insiders Enrollment Confirmation',
        'Your Alamo Insiders Profile Has Been Updated',
    ];
    public $detectBody = [
        'pt' => [ // it-83411341.eml
            'Parabéns por se tornar um Alamo Insider',
        ],
        'en' => [
            'Congratulations on becoming an Alamo Insider',
            'This is confirmation that your Alamo Insiders profile has been updated.',
            'Your username and/or password information has been changed',
            'Click on the link below to reset your password',
            'Here is the information you requested:',
        ],
    ];

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'welcomePhrases'                    => 'Parabéns por se tornar um Alamo Insider!',
            'Your Alamo Insiders ID number is:' => 'Seu número de ID do Alamo Insiders é:',
        ],
        'en' => [
            'welcomePhrases' => 'Congratulations on becoming an Alamo Insider!',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@goalamo.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getCleanFrom(), '@goalamo.com') === false
            && $this->http->XPath->query("//a[contains(@href,'.alamo.com/') or contains(@href,'www.alamo.com')]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@goalamo.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $this->assignLang();

        if (!empty($this->lang)) {
            $st->setMembership(true);
        }

        $number = $login = $name = null;

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Your Alamo Insiders ID number is:"))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{5,})\s*$/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $login = $this->http->FindSingleNode("//text()[" . $this->starts("Your Username is:") . "]",
            null, true, "/Your Username is:\s*(\S*@\S*)\s*$/");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        $name = $this->http->FindSingleNode("//text()[" . $this->starts(['Dear ', 'Hi ']) . "]",
            null, true, "/(?:Dear|Hi)\s+([A-Z][A-Za-z \-]*)[,]\s*$/");

        if (empty($name)) {
            // it-72397332.eml
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('welcomePhrases'))}]/ancestor::td[normalize-space()][1]",
                null, true, "/^\s*([A-Z][A-Z \-]*),\s*{$this->opt($this->t('welcomePhrases'))}/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        if ($number || $login || $name) {
            $st->setNoBalance(true);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        if (!isset($this->detectBody, $this->lang)) {
            return false;
        }

        foreach ($this->detectBody as $lang => $phrases) {
            if (!is_string($lang) || !is_array($phrases)) {
                continue;
            }

            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
