<?php

namespace AwardWallet\Engine\blazepizza\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "blazepizza/statements/it-77949303.eml, blazepizza/statements/it-77947930.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@blazepizza.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".blazepizza.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Blaze Pizza, LLC. All rights reserved") or contains(.,"@blazepizza.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $login = null;

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Hey')]", null, true, "/^Hey\s+({$patterns['travellerName']})(?:[ ]*[!?]|$)/u");

        if ($name) {
            // it-77947930.eml
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("//text()[contains(normalize-space(),'This email was sent to')]", null, true, "/This email was sent to\s+(\S+@\S*\w)/i");

        if ($login) {
            $st->setLogin($login);
        }

        if ($name || $login) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
            // it-77949303.eml
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
        return $this->http->XPath->query("//*[contains(normalize-space(),'Your Favorite Location is:')]")->length > 0;
    }
}
