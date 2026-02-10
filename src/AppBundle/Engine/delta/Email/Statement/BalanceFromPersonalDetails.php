<?php

namespace AwardWallet\Engine\delta\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BalanceFromPersonalDetails extends \TAccountChecker
{
    public $mailFiles = "delta/statements/it-70002735.eml";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//text()[normalize-space()='MILES AVAILABLE']/following::text()[normalize-space()!=''][1]",
            null, false, "/^(\d[,\d]*)$/");

        if ($balance === null) {
            $balance = $this->http->FindSingleNode("//span[contains(@class,'pax-miles')]", null, false,
                "/(\d[,\d]*)\s+miles/");
        }
        $st->setBalance(str_replace(',', '', $balance));
        $name = $this->http->FindSingleNode("//text()[normalize-space()='Basic Info']/following::text()[normalize-space()!=''][1][normalize-space()='Name']/following::text()[normalize-space()!=''][1]");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//text()[normalize-space()='SKYMILES #']/following::text()[normalize-space()!=''][1]",
            null, false, "#^([\d]{5,})$#");
        $st
            ->setNumber($number)
            ->setLogin($number);
        $accountBarText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'SKYMILES MEMBER SINCE')]/preceding::text()[normalize-space()!=''][1]");
        $level = $this->http->FindPreg("/^(\w+\s+Medallion)\s*®?$/i", false, $accountBarText);

        if (empty($level)) {
            $level = $this->http->FindPreg("/^SkyMiles\s*®?\s+(\w+)$/i", false, $accountBarText);
        }

        $st->addProperty('Level', $level); // Status

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $subj = $parser->getSubject();

        return $this->http->XPath->query("//text()[normalize-space()='Personal Details']/following::text()[normalize-space()!=''][1][normalize-space()='Basic Info & Passport Details']")->length > 0
            && strpos($subj, 'email parse') !== false
            && $this->http->XPath->query("//text()[normalize-space()='SKYMILES #']")->length > 0
            && $this->http->XPath->query("//a[contains(.,'VIEW YOUR BENEFITS')]")->length > 0;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
