<?php

namespace AwardWallet\Engine\british\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourAccount extends \TAccountChecker
{
    public $mailFiles = "british/statements/it-61851166.eml";
    public $headers = [
        '/^Your latest Executive Club statement$/',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@my.ba.com') !== false) {
            foreach ($this->headers as $header) {
                if (preg_match($header, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'British Airways')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'YOUR ACCOUNT')]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'YOUR BA MILES')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]my\.ba\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/^Dear\s*Mrs?\s+(\D+)\,?$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Membership No')]/following::text()[normalize-space()][1]", null, true, "/^\:\s*(\d{7,})$/");

        if (!empty($number)) {
            $st->addProperty('Number', $number);
        }

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'YOUR BA MILES')]/following::text()[normalize-space()][1]");
        $balance = str_replace([',', '.'], '', $balance);

        if (!empty($balance)) {
            $st->setBalance($balance);
        } elseif ($balance == '0') {
            $st->setBalance(0);
        }

        $tierPoints = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'TIER POINTS')]/following::text()[normalize-space()][1]");

        if (!empty($tierPoints)) {
            $st->addProperty('TierPoints', $tierPoints);
        } elseif ($tierPoints == 0) {
            $st->addProperty('TierPoints', $tierPoints);
        }

        $lifitimeTierPoints = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'You are a')]/following::text()[normalize-space()][1]", null, true, "/^\:\s*(\D+)$/");

        if (!empty($lifitimeTierPoints)) {
            $st->addProperty('Level', $lifitimeTierPoints);
            //$this->logger->warning($lifitimeTierPoints);
        }
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
