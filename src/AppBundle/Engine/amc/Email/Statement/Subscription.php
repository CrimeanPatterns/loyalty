<?php

namespace AwardWallet\Engine\amc\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "amc/statements/it-70546198.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.amctheatres.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".amctheatres.com/") or contains(@href,"email.amctheatres.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"www.AMCStubs.com") or contains(.,"www.amctheatres.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $email->setType('Subscription');
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode('.', $root, true, '/^#\s*([-A-Z\d]{5,})$/');
        $st->setNumber($number);

        $name = $this->http->FindSingleNode('preceding-sibling::tr[normalize-space()][1]', $root, true, '/^Hello[ ]*,[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[ ]*[:;!?]|$)/u');
        $st->addProperty('Name', $name);

        if ($number || $name) {
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
        return $this->http->XPath->query("//tr[not(.//tr) and starts-with(normalize-space(),'Hello')]/following-sibling::tr[normalize-space()][1][starts-with(normalize-space(),'#')]");
    }
}
