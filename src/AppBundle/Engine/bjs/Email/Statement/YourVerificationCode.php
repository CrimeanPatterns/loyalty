<?php

namespace AwardWallet\Engine\bjs\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourVerificationCode extends \TAccountChecker
{
    public $mailFiles = "bjs/statements/it-656640601.eml";

    private $subjects = [
        'en' => ['Verification Code'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//*[contains(normalize-space()," at bjs.com/")]')->length === 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(),'©') and contains(normalize-space(),\"BJ's Wholesale Club, Inc\")]")->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/\bdonotreply@emails\.bjs\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('OTP not found!');

            return $email;
        }
        $root = $roots->item(0);

        $verificationCode = $this->http->FindSingleNode('following::text()[normalize-space()][1]', $root, true, '/^\d{3,}$/');

        if ($verificationCode !== null) {
            $st = $email->add()->statement();
            $st->setMembership(true); // see QuestionAnalyzer::isOtcQuestion()

            $otp = $email->add()->oneTimeCode();
            $otp->setCode($verificationCode);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(),'Here is your verification code')]");
    }
}
