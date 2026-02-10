<?php

namespace AwardWallet\Engine\bing\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Membership extends \TAccountChecker
{
    public $mailFiles = "bing/statements/it-100893164.eml, bing/statements/it-109033318.eml, bing/statements/it-109043072.eml, bing/statements/it-109048888.eml, bing/statements/it-109683210.eml, bing/statements/it-110231846.eml";
    public $subjects = [
        '/Confirmation\: Your Microsoft account is waiting/',
        '/Microsoft account security code/',
        '/Microsoft account security confirmation/',
        '/Notice to suspend Email/',
        '/Microsoft account password change/',
        '/Microsoft account password reset/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Microsoft Corporation' => [
                'Microsoft Corporation',
                'Microsoft account team',
                'Microsoft Security Team',
            ],

            'Your account of' => [
                'Your account of',
                'Please use the following security code for the Microsoft account',
                'Please use this code to reset the password for the Microsoft account',
                'Your password for the Microsoft account',
            ],

            'Security code:' => ['Security code:', 'Here is your code:'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (
                stripos($headers['from'], '@engage.windows.com') !== false
            || stripos($headers['from'], '@accountprotection.microsoft.com') !== false)) {
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
        $body = $parser->getBodyStr();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Microsoft Corporation'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('You have changed your primary alias from'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Your Microsoft account is active'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Please use the following security code for the Microsoft account'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Please use this code to reset the password for the Microsoft account'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Your password for the Microsoft account'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Your password changed'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('will be disconnected from sending or receiving mails from other users'))}]")->length > 0;
        } elseif (preg_match("/{$this->opt($this->t('Microsoft Corporation'))}/", $body)) {
            if (stripos($body, 'You have changed your primary alias from') !== false
                || stripos($body, 'Your Microsoft account is active') !== false
                || stripos($body, 'Please use the following security code for the Microsoft account') !== false
                || stripos($body, 'Your password for the Microsoft account') !== false
                || stripos($body, 'will be disconnected from sending or receiving mails from other users') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]engage\.windows\.com$/', $from) > 0
            || stripos($from, '.microsoft.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser) == true) {
            $st = $email->add()->statement();

            $code = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Security code:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Security code:'))}\s*(\d{5,})/");

            if (!empty($code)) {
                $otc = $email->add()->oneTimeCode();
                $otc->setCode($code);
            }

            $login = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'You have changed your primary alias from')]", null, true, "/{$this->opt($this->t('to'))}\s*(\S+[@]\S+\.\S+)/");

            if (empty($login)) {
                $login = $this->re("/\s*\S+[@]\S+\.\S+\s*to\s*(\S+[@]\S+\.\S+)/s", $parser->getBodyStr());
            }

            if (empty($login)) {
                $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your account of'))}]", null, true, "/{$this->opt($this->t('Your account of'))}\s*(\S+[@]\S+\.\S+)/");
            }

            if (empty($login)) {
                $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your account of'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your account of'))}\s*(\S+[@]\S+\.\S+)/");
            }

            if (!empty($login) && preg_match("/[*]/u", $login)) {
                $this->logger->error('YES');

                if (preg_match("/[A-z0-9]+[*]+[@]/u", $login)) {
                    $login = preg_replace("/[*]+/u", "**", $login);
                }
                $st->setLogin($login)->masked('centr');
                $st->setNoBalance(true);
            } elseif (!empty($login)) {
                $st->setLogin($login);
                $st->setNoBalance(true);
            } else {
                $st->setMembership(true);
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
        return count(self::$dictionary);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
