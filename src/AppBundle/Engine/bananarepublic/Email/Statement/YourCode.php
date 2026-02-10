<?php

namespace AwardWallet\Engine\bananarepublic\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourCode extends \TAccountChecker
{
    public $mailFiles = "bananarepublic/statements/it-148668656.eml, bananarepublic/statements/it-181010465.eml, bananarepublic/statements/it-183495428-junk.eml";

    private $subjects = [
        'en' => ['Your SecurPass code'],
    ];

    private $providerCode = '';

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@services.barclaysus.com') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".barclaysus.com/") or contains(@href,"emails.barclaysus.com")] | //*[contains(normalize-space(),"is issued by Barclays")]')->length === 0) {
            return false;
        }

        return !empty($this->getEntryPoint()) || $this->findOneTimeCode()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $entryPoint = $this->getEntryPoint();

        if (empty($entryPoint)) {
            $this->logger->debug('Entry point is empty!');

            return $email;
        }
        $this->logger->debug('Entry point: ' . $entryPoint);

        if ($this->isJunk($entryPoint)) {
            $email->setIsJunk(true);

            return $email;
        }

        // Detecting Provider
        $this->assignProvider($entryPoint);
        $email->setProviderCode($this->providerCode);

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][starts-with(normalize-space(),'Account ending in')] ]/*[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");

        if (empty($name)) {
            $names = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Hi')]", null, "/^Hi[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($names)) === 1) {
                $name = array_shift($names);
            }
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Account ending in')]/following::text()[normalize-space()][1]", null, true, '/^[:\s]*(\d{4,})$/');
        $st->setNumber($number)->masked();

        $passcode = null;
        $otcRoots = $this->findOneTimeCode();

        if ($otcRoots->length === 1) {
            $otcRoot = $otcRoots->item(0);
            $passcode = $this->http->FindSingleNode('.', $otcRoot, true, '/^\d{3,}$/');

            if ($passcode !== null) {
                $code = $email->add()->oneTimeCode();
                $code->setCode($passcode);
            }
        }

        if ($name || $number || $passcode) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public static function getEmailProviders()
    {
        return ['bananarepublic', 'barclaycard'];
    }

    private function assignProvider(?string $entryPoint): bool
    {
        /*
        if ($this->http->FindSingleNode("descendant::*[starts-with(normalize-space(),'The AAdvantage') and contains(normalize-space(),'is issued by')][last()]", null, true, "/^The AAdvantage.{1,70} is issued by/i") !== null
            || $this->http->XPath->query('//*[contains(normalize-space(),"To send us an email, simply log in to AviatorMastercard.com")]')->length > 0
        ) {
            $this->providerCode = 'aa';

            return true;
        }

        if ($this->http->FindSingleNode("descendant::*[starts-with(normalize-space(),'The JetBlue') and contains(normalize-space(),'is issued by')][last()]", null, true, "/^The JetBlue.{1,70} is issued by/i") !== null
            || $this->http->XPath->query('//*[contains(normalize-space(),"To send us an email, simply log in to jetbluemastercard.com")]')->length > 0
        ) {
            $this->providerCode = 'jetblue';

            return true;
        }

        if ($this->http->FindSingleNode("descendant::*[starts-with(normalize-space(),'The Holland America') and contains(normalize-space(),'is issued by')][last()]", null, true, "/^The Holland America.{1,70} is issued by/i") !== null) {
            $this->providerCode = 'hollandamerica';

            return true;
        }

        if ($this->http->FindSingleNode("descendant::*[starts-with(normalize-space(),'The FRONTIER Airlines') and contains(normalize-space(),'is issued by')][last()]", null, true, "/^The FRONTIER Airlines.{1,70} is issued by/i") !== null) {
            $this->providerCode = 'frontierairlines';

            return true;
        }

        if ($this->http->FindSingleNode("descendant::*[starts-with(normalize-space(),'The Wyndham') and contains(normalize-space(),'is issued by')][last()]", null, true, "/^The Wyndham.{1,70} is issued by/i") !== null) {
            $this->providerCode = 'triprewards'; // Wyndham

            return true;
        }

        if ($this->http->FindSingleNode("descendant::*[starts-with(normalize-space(),'The Hawaiian Airlines') and contains(normalize-space(),'is issued by')][last()]", null, true, "/^The Hawaiian Airlines.{1,70} is issued by/i") !== null
            || $this->http->XPath->query('//*[contains(normalize-space(),"To send us an email, simply log in to HawaiianBOHcard.com")]')->length > 0
        ) {
            $this->providerCode = 'hawaiian';

            return true;
        }

        if ($this->http->FindSingleNode("descendant::*[starts-with(normalize-space(),'The Priceline') and contains(normalize-space(),'is issued by')][last()]", null, true, "/^The Priceline.{1,70} is issued by/i") !== null
            || $this->http->XPath->query('//*[contains(normalize-space(),"To send us an email, simply log in to pricelinerewardsvisa.com")]')->length > 0
        ) {
            $this->providerCode = 'priceline';

            return true;
        }

        if ($this->http->FindSingleNode("descendant::*[starts-with(normalize-space(),'The Emirates') and contains(normalize-space(),'is issued by')][last()]", null, true, "/^The Emirates.{1,70} is issued by/i") !== null
            || $this->http->XPath->query('//*[contains(normalize-space(),"To send us an email, simply log in to EmiratesSkywardsCards.com")]')->length > 0
        ) {
            $this->providerCode = 'skywards'; // Emirates

            return true;
        }
        */

        // for GAP Brands (Banana Republic, Old Navy, Gap etc.)
        if (stripos($entryPoint, 'gap.barclaysus.com') !== false
            || stripos($entryPoint, 'oldnavy.barclaysus.com') !== false
            || stripos($entryPoint, 'athleta.barclaysus.com') !== false
            || stripos($entryPoint, 'bananarepublic.barclaysus.com') !== false
        ) {
            $this->providerCode = 'bananarepublic'; // it-181010465.eml

            return true;
        }

        // always last!
        if (stripos($entryPoint, 'BarclaysUS.com') !== false
            || stripos($entryPoint, 'jetbluemastercard.com') !== false
        ) {
            $this->providerCode = 'barclaycard'; // it-148668656.eml

            return true;
        }

        return false;
    }

    private function getEntryPoint(): ?string
    {
        return $this->http->FindSingleNode("descendant::*[contains(normalize-space(),'To send us an email, simply log in to') and contains(normalize-space(),'and click contact us to send your message securely')][last()]", null, true, "/To send us an email, simply log in to\s*(\S{5,})\s*and click contact us to send your message securely/i");
    }

    private function isJunk(?string $entryPoint): bool
    {
        if (empty($entryPoint)) {
            return false;
        }

        if (stripos($entryPoint, 'BarclaysUS.com') === false
            && stripos($entryPoint, 'jetbluemastercard.com') === false
            && stripos($entryPoint, 'gap.barclaysus.com') === false
            && stripos($entryPoint, 'oldnavy.barclaysus.com') === false
            && stripos($entryPoint, 'athleta.barclaysus.com') === false
            && stripos($entryPoint, 'bananarepublic.barclaysus.com') === false
        ) {
            return true;
        }

        return false;
    }

    private function findOneTimeCode(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[starts-with(normalize-space(),'Your code is') or starts-with(normalize-space(),'Code:')]/following::text()[normalize-space()][1][starts-with(translate(normalize-space(),'0123456789','dddddddddd'),'ddd')]");
    }
}
