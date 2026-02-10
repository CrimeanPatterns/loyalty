<?php

namespace AwardWallet\Engine\boots\Email\Statement;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "boots/statements/it-493171192.eml, boots/statements/it-494496368.eml, boots/statements/it-494495057.eml, boots/statements/it-500575266.eml";

    private $format = null;

    private $xpath = [
        'noDisplay' => 'ancestor-or-self::*[contains(translate(@style," ",""),"display:none")]',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]boots\.com$/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".boots.com/") or contains(@href,"mail.boots.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"This email was sent by Boots") or contains(normalize-space(),"You can find your current balance on Boots") or contains(normalize-space(),"This message was sent by Boots")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length > 0
            || $this->http->XPath->query('//*[not(.//tr) and starts-with(normalize-space(),"Advantage Card number")]')->length > 0; // it-494495057.eml
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]{0,25}[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $st = $email->add()->statement();

        if (preg_match("/^({$patterns['travellerName']})\s*,\s*.{10,}/u", $parser->getSubject(), $m)
            || preg_match("/.{10,}\s*,\s*({$patterns['travellerName']})\s*!$/u", $parser->getSubject(), $m)
            || preg_match("/\bIs it top-up time \s*({$patterns['travellerName']})\s*\?$/u", $parser->getSubject(), $m)
        ) {
            /*
                Phillip B, buy 1 get 1 free? You won't want to miss this.
                Your £7.21 worth of points are saying 'spend me', Yitzchok!
                Is it top-up time Sandra?
            */
            $st->addProperty('Name', $m[1]);
        }

        $cardNumber = $this->http->FindSingleNode("//tr[not(.//tr) and starts-with(normalize-space(),'Advantage Card number')]", null, true, "/^Advantage Card number[:\s]+([-A-Z\d]{4,})$/i");

        if (preg_match("/^[Xx]{4,}([-A-Z\d]+)$/", $cardNumber, $m)) {
            // XXXXXXXX5678
            $st->setNumber($m[1])->masked();
        } elseif (preg_match("/^([-A-Z\d]+?)[Xx]{4,}$/", $cardNumber, $m)) {
            // 5678XXXXXXXX
            $st->setNumber($m[1])->masked('right');
        } else {
            // 567812345678
            $st->setNumber($cardNumber);
        }

        $roots = $this->findRoot();

        if ($roots->length === 0 && $this->hasNoBalance() && $cardNumber !== null) {
            $st->setNoBalance(true); // it-494495057.eml

            return $email;
        } elseif ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        if ($this->format === 1 || $this->format === 3) {
            $pointsVal = $this->http->FindSingleNode(".", $root, true, "/^(.*?\d.*?)\s*(?:worth of points?)?$/i");
        } elseif ($this->format === 2) {
            $pointsVal = $this->http->FindSingleNode(".", $root);
        } else {
            $pointsVal = '';
        }

        if (preg_match("/^(?:[^\-\d)(]+)?[ ]*(\d[,.‘\'\d ]*)$/u", $pointsVal, $matches)) {
            // £5.31    |    €5.31
            $st->setBalance(PriceHelper::parse($matches[1]));
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->format = 1; // it-493171192.eml
        $nodes = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][starts-with(normalize-space(),'Your Advantage Card balance')] ]/*[normalize-space()][2][contains(normalize-space(),'worth of points')]");

        if ($nodes->length === 0) {
            $this->format = 2; // it-494496368.eml
            $nodes = $this->http->XPath->query("//node()[not(.//tr) and normalize-space()='Your points balance is worth']/following::node()[not(.//tr) and normalize-space()][1][contains(translate(.,'0123456789','∆∆∆∆∆∆∆∆∆∆'),'∆') and string-length(normalize-space())<15]");
        }

        if ($nodes->length === 0) {
            $this->format = 3; // it-500575266.eml
            $nodes = $this->http->XPath->query("//*[normalize-space()='Your Balance:' or normalize-space()='Your Balance :']/following::*[not(.//tr) and normalize-space()][1][contains(normalize-space(),'worth of points')]");
        }

        return $nodes;
    }

    private function hasNoBalance(): bool
    {
        return $this->http->XPath->query("//tr[ count(*)=2 and *[1][descendant::img[not({$this->xpath['noDisplay']})] and normalize-space()=''] and *[2][normalize-space()='View in Browser'] ]")->length === 1
            && $this->http->XPath->query("//*[contains(.,'Balance') or contains(.,'balance') or contains(.,'BALANCE')]")->length === 0
        ;
    }
}
