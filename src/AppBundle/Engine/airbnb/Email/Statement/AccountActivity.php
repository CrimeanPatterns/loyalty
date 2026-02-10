<?php

namespace AwardWallet\Engine\airbnb\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AccountActivity extends \TAccountChecker
{
    public $mailFiles = "airbnb/statements/it-66043280.eml, airbnb/statements/it-66040768.eml";

    private $subjects = [
        'en' => ['Account activity:', 'Account alert:'],
    ];

    private $detectors = [
        'nl' => [
            'gezien dat er een nieuwe betaalmethode aan je Airbnb-account is toegevoegd',
        ],
        'en' => [
            'tap the button below to confirm your account',
            'your Airbnb account was logged into from a new device',
            'new payment method was added to your Airbnb account',
            'password for your Airbnb account was recently changed',
            'following phone number was recently added to your account',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airbnb.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Confirm your Airbnb account') !== false) {
            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Airbnb') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".airbnb.com/") or contains(@href,"www.airbnb.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Sent with ♥ from Airbnb")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($this->detectBody()) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
