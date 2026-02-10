<?php

namespace AwardWallet\Engine\cinemark\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourTickets extends \TAccountChecker
{
    public $mailFiles = "cinemark/statements/it-76045395.eml, cinemark/statements/it-76537453.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@info.cinemark.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".cinemark.com/") or contains(@href,"info.cinemark.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Download the Cinemark App")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//*[contains(normalize-space(),'Thank you for your purchase')]")->length > 0
            && $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($this->isMembership()) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("//*[contains(normalize-space(),'Your tickets have been saved to your account')]")->length > 0;
    }
}
