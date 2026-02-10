<?php

namespace AwardWallet\Engine\azamara\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class LoyaltyNewsletter extends \TAccountChecker
{
    public $mailFiles = "azamara/statements/it-86902799.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]azamaraclubcruises\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'loyalty@email.azamaraclubcruises.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".azamaraclubcruises.com/") or contains(@href,"email.azamaraclubcruises.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Azamara. Ships registered in Malta. Intended for U.S. transmission only")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Dear')]", null, true, "/^Dear\s+({$patterns['travellerName']})[ ]*[,;!?]$/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($name) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
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
        return $this->http->XPath->query("//*[contains(normalize-space(),'As our valued loyalty members, we')]")->length > 0;
    }
}
