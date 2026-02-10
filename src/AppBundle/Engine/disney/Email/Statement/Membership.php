<?php

namespace AwardWallet\Engine\disney\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Membership extends \TAccountChecker
{
    public $mailFiles = "disney/statements/it-79803142.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thedisneymovieclub.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return $this->detectEmailFromProvider($headers['from']) === true;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"//thedisneymovieclub.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@thedisneymovieclub.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[starts-with(normalize-space(),'MEMBERSHIP #')]")->length > 0
            && $this->http->XPath->query("//*[contains(normalize-space(),'using your Disney Movie Club membership number at the top of this email')]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $number = $login = null;

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'MEMBERSHIP #')]", null, true, "/MEMBERSHIP #[: ]+([-A-Z\d]{5,})$/i");

        if ($number) {
            $st->setNumber($number);
        }

        $login = $this->http->FindSingleNode("//text()[normalize-space()='This email was sent to']/following::text()[normalize-space()][1]", null, true, "/^\S+@\S+$/");

        if ($login) {
            $st->setLogin($login);
        }

        if ($number || $login) {
            $st->setNoBalance(true);
        } elseif (stripos($parser->getCleanFrom(), '@thedisneymovieclub.com') !== false) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
