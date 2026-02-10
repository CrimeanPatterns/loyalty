<?php

namespace AwardWallet\Engine\boots\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Order extends \TAccountChecker
{
    public $mailFiles = "boots/statements/it-80305093.eml, boots/statements/it-80261755.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]boots.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Order confirmation') !== false
            || stripos($headers['subject'], 'Your order has left our warehouse') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".boots.com/") or contains(@href,"www.boots.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Boots") or contains(.,"Boots.com") or contains(.,"@care.boots.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//*[contains(normalize-space(),'Order Number') or contains(normalize-space(),'Your order')]")->length > 0
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
        return $this->http->FindSingleNode("descendant::*[contains(normalize-space(),'Boots Advantage Card points collected') or contains(normalize-space(),'Total Advantage Card points earned')][1]", null, true, "/(?:Boots Advantage Card points collected|Total Advantage Card points earned)\s*(\d[,.\'\d ]*)\s*pts/i") !== null;
    }
}
