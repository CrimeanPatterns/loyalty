<?php

namespace AwardWallet\Engine\changs\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "changs/statements/it-78191294.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@pfchangs.com') !== false || stripos($from, '@e.pfchangs.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".elal-mail.com/") or contains(@href,"ma.elal-mail.com")]')->length === 0
            && $this->http->XPath->query("//node()[contains(normalize-space(),\"P.F. Chang's China Bistro, Inc. All Rights Reserved\") or contains(normalize-space(),\"This email was sent by: P.F. Chang's China Bistro, Inc\") or contains(.,\"order.pfchangs.com\") or contains(.,\"Order.PFChangs.com\")]")->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//*[contains(normalize-space(),'You are receiving this email to')]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $login = null;

        $number = $this->http->FindSingleNode("//tr[not(.//tr) and not(preceding-sibling::tr[normalize-space()]) and not(following-sibling::tr[normalize-space()]) and starts-with(normalize-space(),'ID:')]", null, true, "/^ID:\s*([-A-Z\d]{5,})$/");
        $st->setNumber($number);

        $login = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'You are receiving this email to')]/ancestor::*[1]", null, true, "/^You are receiving this email to\s*(\S+@\S+\w)/i");
        $st->setLogin($login);

        if ($number !== null || $login) {
            $st->setNoBalance(true);
        } elseif (stripos($parser->getCleanFrom(), 'rewards@e.pfchangs.com') !== false) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
