<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AwardRedemption extends \TAccountChecker
{
    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//text()[contains(., "We\'ve successfully completed your request and are pleased to present you with the following AAdvantage")]')
            ->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && stripos($headers['from'], 'americanairlines@info.ms.aa.com') !== false
            && !empty($headers['subject']) && stripos($headers['subject'], 'Your recent AAdvantage Award Redemption') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@info.ms.aa.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $number = $this->http->FindSingleNode('//td[text()[contains(., "AAdvantage")] and text()[contains(., "member:")]]/text()[last()]', null, true, '/^[A-Z\d*]+$/');

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'AAdvantage') and contains(normalize-space(), 'member:')]/descendant::text()[last()]", null, true, '/^[A-Z\d*]+$/');
        }
        $rows = $this->http->XPath->query('//tr[.//td[contains(., "Date issued")] and .//td[contains(., "Miles redeemed")] and not(.//tr)]/following-sibling::tr');

        foreach ($rows as $row) {
            $cells = $this->http->FindNodes('./td', $row);

            if (count($cells) == 4
            && preg_match('/^\d{2}-\d{2}-\d{4}$/', $cells[0]) > 0
            && preg_match('/^[\d,]+$/', $cells[1]) > 0
            && preg_match('/^[A-z\s]+$/', $cells[2]) > 0) {
                $email->add()->awardRedemption()
                    ->setDateIssued(strtotime(str_replace('-', '/', $cells[0])))
                    ->setMilesRedeemed(str_replace(',', '', $cells[1]))
                    ->setRecipient($cells[2])
                    ->setDescription($cells[3], true, true)
                    ->setAccountNumber($number);
            }
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
