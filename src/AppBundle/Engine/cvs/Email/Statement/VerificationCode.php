<?php

namespace AwardWallet\Engine\cvs\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "cvs/statements/it-364209203.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your requested verification code from CVS') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".cvs.com/") or contains(@href,"www.cvs.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"please contact us by email at customercare@cvs.com") or contains(normalize-space(),"As always, thank you for using CVS")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alerts.cvs.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Verification code not found!');

            return $email;
        }
        $root = $roots->item(0);

        $verificationCode = $this->http->FindSingleNode('.', $root, true, "/^(\d{3,})(?:\s*[,.;:!?]|$)/");

        if ($verificationCode) {
            $otp = $email->add()->oneTimeCode();
            $otp->setCode($verificationCode);

            $st = $email->add()->statement();
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $xpathBold = '(self::h2 or self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))';

        return $this->http->XPath->query("//text()[contains(normalize-space(),'verification code below')]/following::text()[normalize-space()][1]/ancestor::*[{$xpathBold}][1]");
    }
}
