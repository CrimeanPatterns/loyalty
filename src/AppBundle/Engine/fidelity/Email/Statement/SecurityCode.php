<?php

namespace AwardWallet\Engine\fidelity\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class SecurityCode extends \TAccountChecker
{
    public $mailFiles = "fidelity/it-490449250.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'fidelityealerts@alert.fidelityrewards.com') !== false
            && isset($headers['subject']) && stripos($headers['subject'], ' Here’s the security code you requested.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fidelityrewards\.com\b/i', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'fidelityrewards.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'is the security code you requested for your Fidelity ')]")->length > 0;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $code = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'is the security code you requested for your Fidelity')]/preceding::text()[normalize-space()][1]", null, true, "/^\s*(\d{6})\s*$/");

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($code);

            $st = $email->add()->statement();

            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hello')]", null, true, "/^Hello\s+(\D+)\,$/");

            if (!empty($name)) {
                $st->addProperty('Name', trim($name, ','));
            }

            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
