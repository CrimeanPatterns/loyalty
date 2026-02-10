<?php

namespace AwardWallet\Engine\amex\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "amex/statements/it-654102587.eml, amex/statements/it-654391910.eml";
    public $subjects = [
        'Your American Express One-Time Verification Code',
        'American Express - Online Services - Your Reauthentication Key',
        'Your American Express® temporary security code',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ['temporary security code'],
        "fr" => ['Votre code de sécurité'],
    ];

    public static $dictionary = [
        "en" => [
            'One-Time Verification Code:' => ['One-Time Verification Code:', 'Here is your Re-authentication key for American Express Online Services.', 'You requested for a One-Time Password to be sent to your email id. Your One-Time Password is'],
            'online account'              => ['online account', 'temporary security code'],
        ],
        "fr" => [
            'One-Time Verification Code:' => ['Voici votre code d’authentification pour les Services en ligne American Express :'],
            'online account'              => ['Votre code de sécurité'],
            'Dear'                        => 'Madame,',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@welcome.americanexpress.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'American Express')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('One-Time Verification Code:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('online account'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]welcome\.americanexpress\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $code = $this->http->FindSingleNode("//text()[{$this->eq($this->t('One-Time Verification Code:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d{6})\.*$/");

        if (empty($code)) {
            $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('One-Time Verification Code:'))}]", null, true, "/{$this->opt($this->t('One-Time Verification Code:'))}\s*(\d{6})\.*/");
        }

        if (!empty($code)) {
            $oc = $email->add()->oneTimeCode();
            $oc->setCode($code);

            $st = $email->add()->statement();
            $st->setMembership(true);
            $st->setNoBalance(true);

            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s*(.+)$/");

            if (!empty($traveller)) {
                $st->addProperty('Name', trim($traveller, ','));
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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang=> $detects) {
            foreach ($detects as $detect) {
                if ($this->http->XPath->query("//text()[{$this->contains($detect)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
