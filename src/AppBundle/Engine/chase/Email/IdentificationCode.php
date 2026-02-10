<?php

namespace AwardWallet\Engine\chase\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class IdentificationCode extends \TAccountChecker
{
    public function detectEmailFromProvider($from)
    {
        return preg_match('/\bchase\.com$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return strpos($parser->getPlainBody(), 'Here is the Identification Code you will need to help us recognize your computer') !== false
            && strpos($parser->getPlainBody(), 'Online Banking Team') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['subject']) && strpos($headers['subject'], 'Your Requested Online Banking Identification Code') !== false
            && !empty($headers['from']) && strpos($headers['from'], 'chase.com') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match('/Here is the Identification Code you will need to help us recognize your computer\.[\s\n]+Your Identification Code is: (\d{5,10})\b/', $parser->getPlainBody(), $m) > 0) {
            $email->add()->oneTimeCode()->setCode($m[1]);
            $email->add()->statement()->setNoBalance(true)->setMembership(true);
        }
    }
}
