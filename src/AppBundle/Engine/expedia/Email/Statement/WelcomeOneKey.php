<?php

namespace AwardWallet\Engine\expedia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WelcomeOneKey extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang = 'en';
    public $providerCode;

    public $detectSubject = [
        'Your One Key monthly check-in',
    ];

    public static $detectProviders = [
        'hotels' => [
            'from'    => 'mail@eg.hotels.com',
            'bodyUrl' => '.hotels.com',
        ],
        'expedia' => [
            'from'    => 'mail@eg.expedia.com',
            'bodyUrl' => '.hotels.com',
        ],
        'homeaway' => [
            'from'    => 'mail@eg.vrbo.com',
            'bodyUrl' => '.vrbo.com',
        ],
    ];
    public static $dictionary = [
        "en" => [
            'membershipTrue'               => ['Your latest One Key™ update', 'Welcome to One Key™', 'As a Blue member, you get great benefits'],
            'membershipFalse'              => ['Just become a member (it\'s free!),', 'Join One Key to unlock rewards',
                'Plus, when you sign up, you can unlock Member Prices', ],
            'Save more with Member Prices' => [
                // after status
                'Save more with Member Prices', 'Congrats! As a Blue member you now qualify for great benefits',
                'As a Blue member, you get great benefits',
            ],
            'Explore your new rewards' => [
                // before status
                'Explore your new rewards', 'Explore your new rewards',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:expediagroup|accounts\.expedia)\.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProviders as $code => $detects) {
            if (strpos($headers['from'], 'mail@eg.expedia.com') !== false
            || strpos($headers['from'], 'mail@eg.hotels.com') !== false
            || strpos($headers['from'], 'do-not-reply@accounts.expedia.com') !== false
        ) {
                // foreach ($this->detectSubject as $dSubject) {
                //     if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
                // }
            // }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return false;
        // if (!$this->assignProvider($parser->getHeaders())) {
        //     return false;
        // }
        //
        // return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        // $this->assignLang();

        // if (empty($this->lang)) {
        //     return $email;
        // }

        $this->assignProvider($parser->getHeaders());
        $email->setProviderCode($this->providerCode);

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('membershipFalse'))}]")->length > 0) {
            $email->setIsJunk(true);

            return $email;
        }

        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//p[{$this->starts($this->t('You have'))}][{$this->contains($this->t('in OneKeyCash'))}]");

        if (preg_match("/{$this->opt($this->t('You have'))}\s*\\$\s*(\d[\d\., ]*)\s*{$this->opt($this->t('in OneKeyCash'))}/u", $balance, $m)) {
            $st->setBalance($m[1]);
        } elseif (empty($balance)) {
            $st->setNoBalance(true);

            if ($this->http->XPath->query("//node()[{$this->contains($this->t('membershipTrue'))}]")->length > 0) {
                $st->setMembership(true);
            }
        }

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Save more with Member Prices'))}]/preceding::text()[normalize-space()][1]/ancestor::*[1][contains(@style, 'background-color:')]");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Explore your new rewards'))}]/following::text()[normalize-space()][1]/ancestor::*[1][contains(@style, 'background-color:')]");
        }

        if (!empty($status)) {
            $st->addProperty('Status', $status);
        }

        $tripToNextStatus = $this->http->FindSingleNode("//text()[{$this->contains($this->t('trip elements collected to reach'))}]",
            null, true, "/^\s*(\d+)\s+of\s+\d+\s+{$this->opt($this->t('trip elements collected to reach'))}/");

        if ($this->providerCode === 'hotels' && (!empty($tripToNextStatus)) || $tripToNextStatus === '0') {
            $st->addProperty('TripToNextStatus', $tripToNextStatus);
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProviders);
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Your latest One Key™ update"])
                && $this->http->XPath->query("//*[{$this->eq($dict['Your latest One Key™ update'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    private function assignProvider($headers): bool
    {
        foreach (self::$detectProviders as $providerCode => $detects) {
            if (!empty($detects['from']) && strpos($headers['from'], $detects['from']) !== false
            ) {
                $this->providerCode = $providerCode;

                return true;
            }
        }

        return false;
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
