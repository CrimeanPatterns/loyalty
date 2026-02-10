<?php

namespace AwardWallet\Engine\bankofamerica\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AccountAlert extends \TAccountChecker
{
    public $mailFiles = "bankofamerica/statements/it-68943110.eml, bankofamerica/statements/it-69114802.eml, bankofamerica/statements/it-69388248.eml, bankofamerica/statements/it-69497886.eml, bankofamerica/statements/it-69641036.eml, bankofamerica/statements/it-69857914.eml";
    public $subjects = [
        '/your On Business statement is ready to view$/',
        '/Welcome to your new account\,\s*\w+/',
        '/^Account Alert: Your Account Has Been Closed Per Your Request$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'FormatDetect' => [
                'Online bill payment over your requested alert limit',
                'Account: Bank of America Advantage Plus Banking ending',
                'This Pay To account has been added to your list of Pay To accounts in Online Banking',
                'Advantage Banking account',
                'Account: Regular Savings ending in',
                'American Express has changed your account number',
                'your balance is below your chosen alert limit',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ealerts.bankofamerica.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Bank of America')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('FormatDetect'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ealerts\.bankofamerica\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $st->setNoBalance(true);

        $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), \"We're letting you know\")]/preceding::text()[normalize-space()][1]");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]", null, true, "/{$this->opt($this->t('Hi'))}\,\s*(\w+)\,/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\:/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'you’ve completed')]", null, true, "/^([A-Z]+)\,\s*{$this->opt($this->t('you’ve completed'))}/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Online bill payment over your requested alert limit')]", null, true, "/{$this->opt($this->t('Online bill payment over your requested alert limit'))}\s*([A-Z\s]+)\,\s*/");
        }

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $login = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'This email was sent to:')]", null, true, "/{$this->opt($this->t('This email was sent to:'))}\s*(.+)/");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return 0;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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
}
