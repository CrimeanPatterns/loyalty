<?php

namespace AwardWallet\Engine\disneyvacation\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class WelcomeTo extends \TAccountChecker
{
    public $mailFiles = "disneyvacation/statements/it-90849145.eml, disneyvacation/statements/it-89233352.eml, disneyvacation/statements/it-125240088.eml, disneyvacation/statements/it-125785389.eml, disneyvacation/statements/it-125743950.eml";

    private $providerCode = '';

    private $subjects = ['New Sign-in To Your Account'];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@disneyaccount.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Welcome to Disney') !== false
            || stripos($headers['subject'], 'Your Disney Account passcode') !== false
        ) {
            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) !== true) {
            return false;
        }

        // Detecting Format
        return $this->http->XPath->query("//*[contains(normalize-space(),'Welcome to your Disney Account') or contains(normalize-space(),'Your account was just used to sign in') or contains(normalize-space(),'Here is your one-time Disney Account Passcode')]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Hello')]", null, true, "/^Hello\s+({$patterns['travellerName']})(?:\s*[,:;!?]|$)/u");
        $st->addProperty('Name', $name);

        if ($name) {
            $st->setNoBalance(true);
        }

        $passCode = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Here is your one-time Disney Account Passcode')]/following::text()[normalize-space()][1]", null, true, "/^\d{3,}$/");

        if ($passCode !== null) {
            // it-125240088.eml
            $code = $email->add()->oneTimeCode();
            $code->setCode($passCode);
        }

        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public static function getEmailProviders()
    {
        return ['disney', 'disneycruise', 'disneyresort', 'disneyvacation'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignProvider($headers): bool
    {
        if ($this->http->XPath->query('//a[contains(@href,".disneymovieinsiders.com/") or contains(@href,"www.disneymovieinsiders.com") or contains(@href,"support.disneymovieinsiders.com")]')->length > 0
            || $this->http->XPath->query('//*[normalize-space()="This request for a Disney Account passcode originated at Disney Movie Insiders"]')->length > 0
        ) {
            // it-125785389.eml
            $this->providerCode = 'disney';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,"disneycruise.disney.go.com")]')->length > 0
            || $this->http->XPath->query('//*[normalize-space()="This request for a Disney Account passcode originated at Disney Cruise"]')->length > 0
        ) {
            // it-125240088.eml
            $this->providerCode = 'disneycruise';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,"disneyland.disney.go.com")]')->length > 0
            || $this->http->XPath->query('//*[normalize-space()="This request for a Disney Account passcode originated at Disneyland"]')->length > 0
        ) {
            // it-125743950.eml
            $this->providerCode = 'disneyresort';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,"disneyvacationclub.disney.go.com") or contains(@href,"support.disney.com") or contains(@href,".disney.com/")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Explore Disney Vacation Club")]/following::*[contains(normalize-space(),"Thank you for creating a Disney")]')->length > 0
        ) {
            // it-90849145.eml
            $this->providerCode = 'disneyvacation';

            return true;
        }

        return false;
    }
}
