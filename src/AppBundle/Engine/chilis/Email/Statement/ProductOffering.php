<?php

namespace AwardWallet\Engine\chilis\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class ProductOffering extends \TAccountChecker
{
    public $mailFiles = "chilis/statements/it-66154903.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.chilis.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".chilis.com/") or contains(@href,"email.chilis.com")]')->length === 0
            && $this->http->XPath->query("//*[contains(normalize-space(),\"MY CHILI'S REWARDS\")]")->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
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
        return $this->http->XPath->query("//tr/*[not(.//tr) and contains(normalize-space(),'THIS EMAIL WAS SENT TO:') and contains(normalize-space(),\"You are receiving this message because you have opted in to My Chili's Rewards.\")]")->length > 0;
    }
}
