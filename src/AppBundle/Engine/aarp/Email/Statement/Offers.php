<?php

namespace AwardWallet\Engine\aarp\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Offers extends \TAccountChecker
{
    public $mailFiles = "aarp/statements/it-76854693.eml, aarp/statements/it-77183984.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.aarp.org') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'member@email.aarp.org') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".aarp.org/") or contains(@href,"email.aarp.org")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            if (stripos($parser->getCleanFrom(), 'member@email.aarp.org') === 0) {
                $st->setMembership(true);
            }

            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $memberID = $membershipExpires = $name = $memberSince = null;

        if ($this->format === 1) {
            $memberID = $this->http->FindSingleNode("*[starts-with(normalize-space(),'Mem #')]", $root, true, "/^Mem #[:\s]+([-A-Z\d]{5,})$/i");
            $st->addProperty('MemberID', $memberID);

            $membershipExpires = $this->http->FindSingleNode("*[starts-with(normalize-space(),'Exp')]", $root, true, "/^Exp[:\s]+(.*\d.*)$/i");
            $st->addProperty('MembershipExpires', $membershipExpires);

            $name = $this->http->FindSingleNode("//tr[not(.//tr) and contains(normalize-space(),'we remain committed to providing our members')]", null, true, "/^({$patterns['travellerName']})[ ]*,[ ]*we remain committed to providing our members/iu");
            $st->addProperty('Name', $name);
        } elseif ($this->format === 2) {
            /*
                Welcome, William!
                Valued Member Since: 2016
            */
            $name = $this->http->FindSingleNode("tr[normalize-space()][1]", $root, true, "/^Welcome[ ]*,[ ]*({$patterns['travellerName']})(?:\s*[,:;!?]|$)/iu");
            $st->addProperty('Name', $name);
            $memberSince = $this->http->FindSingleNode("tr[normalize-space()][2]", $root, true, "/^Valued Member Since[: ]+(\d{4})$/i");
            $st->addProperty('MemberSince', $memberSince);
        }

        if ($memberID || $membershipExpires || $name || $memberSince) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->format = 1; // it-76854693.eml
        $nodes = $this->http->XPath->query("//tr[ *[starts-with(normalize-space(),'Mem #')]/following-sibling::*[normalize-space()][1][starts-with(normalize-space(),'Exp')] ]");

        if ($nodes->length === 0) {
            $this->format = 2; // it-77183984.eml
            $nodes = $this->http->XPath->query("//*[ tr[normalize-space()][2][starts-with(normalize-space(),'Valued Member Since')] ][not(ancestor-or-self::*[contains(@class,'webkit-hide') or contains(@style,'display:none')])]");
        }

        return $nodes;
    }
}
