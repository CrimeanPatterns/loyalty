<?php

namespace AwardWallet\Engine\agoda\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourOTP extends \TAccountChecker
{
    public $mailFiles = "agoda/statements/it-340104515.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return $this->detectEmailFromProvider($headers['from']) && strpos($headers['subject'], 'Email OTP') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".agoda.com/") or contains(@href,"www.agoda.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"This email was sent by: Agoda Company") or contains(normalize-space(),"This email was sent by:: Agoda Company")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@security.agoda.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('OTP not found!');

            return $email;
        }
        $root = $roots->item(0);

        $otpValue = $this->http->FindSingleNode('.', $root, true, "/your Agoda OTP is\s+(\d+)(?:\s*[,.;:!?]|$)/");

        if ($otpValue) {
            $otp = $email->add()->oneTimeCode();
            $otp->setCode($otpValue);

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
        return $this->http->XPath->query("//tr[not(.//tr) and contains(normalize-space(),'your Agoda OTP is')]");
    }
}
