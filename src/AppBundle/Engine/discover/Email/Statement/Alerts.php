<?php

namespace AwardWallet\Engine\discover\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Alerts extends \TAccountChecker
{
    public $mailFiles = "discover/statements/it-110289981.eml, discover/statements/it-111220027.eml";

    private $subjects = [
        'en' => ['No New SSN, Inquiry or New Account Alerts to Report This Month', 'You have a new statement online'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@services.discover.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".discover.com/") or contains(@href,"www.discover.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@services.discover.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if ($this->isMembership() === true) {
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
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        return $this->http->XPath->query("//text()[normalize-space()='You had no alerts this month' or normalize-space()='Your paperless statement is ready']/ancestor::*[{$xpathBold}]")->length > 0;
    }
}
