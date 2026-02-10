<?php

namespace AwardWallet\Engine\delta\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BalanceFromTravelDeals extends \TAccountChecker
{
    public $mailFiles = "delta/statements/it-14849723.eml, delta/statements/it-18027078.eml, delta/statements/it-48114017.eml, delta/statements/it-541814687.eml, delta/statements/it-69904241.eml, delta/statements/it-74737989.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'DetectFormat' => [
                'THANK YOU FOR UPDATING YOUR SKYMILES ACCOUNT INFORMATION.',
                'CONGRATULATIONS AND THANKS FOR YOUR LOYALTY.',
                'You are now enrolled in the SkyMiles Medallion Status Match Challenge.',
                'We’re excited to announce that Robert Dressman has given you the gift of',
                'You Are Now Enrolled In The SkyMiles Medallion Status Match Challenge',
                'ENJOY TRAVELING THE MEDALLION WAY',
                'An Award Travel transaction has posted to your account',
                'YOUR SKYMILES ACCOUNT ACCESS.',
                'HOW CAN I EARN MILES FASTER?',
                'YOUR SKYMILES LOGIN INFORMATION.',
                'BPI SKYMILES',
                'REGISTER TODAY AND GET ONE MONTH FREE',
                'Check out more SkyMiles updates',
                'With more earning and redemption options on the way',
                'Your request to update your SkyMiles account information has been completed.',
                'This Price Discount Offer is valid for SkyMiles Members who do not have an active',
                'Simply earn the following Medallion Qualification Dollars below',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        $xpath = "//text()[starts-with(normalize-space(), 'DEPARTURE')]/ancestor::table[3]";
        $nodes = $this->http->XPath->query($xpath);

        if (count($nodes) == 0) {
            $st = $email->add()->statement();

            $balance = $this->http->FindSingleNode("(//a[starts-with(normalize-space(.),'Your Mileage Balance') or @title='Use Miles'])[1]", null, false, "/:\s+(-?\d[,\d]*)/");

            if ($balance === null) {
                $balance = $this->http->FindSingleNode("//a[starts-with(normalize-space(.), 'Miles')]/preceding-sibling::node()[normalize-space(.)][1]", null, false, "/(-?\d[,\d]*)/");
            }

            if ($balance === null) {
                $balance = $this->http->FindSingleNode("//a[ ./descendant::text()[starts-with(normalize-space(.), 'Miles')] ]", null, false, "/^(-?\d[,\d]*)/");
            }

            if ($balance === null) {
                $balance = $this->http->FindSingleNode("//a[({$this->ends('Miles')}) and ({$this->starts([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, '-'])})]", null, false, "/(-?\d[,\d]*)/");
            }

            if ($balance !== null) {
                $st->setBalance(str_replace(',', '', $balance));
            }

            if (empty($balance) && (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('DetectFormat'))}]")))) {
                $st->setNoBalance(true);
            }

            $number = $this->http->FindSingleNode("//a[starts-with(normalize-space(.),'SkyMiles')]", null, false, "#\#\s*([\d]{5,})#");

            if (empty($number)) {
                $number = $this->http->FindSingleNode("//a[starts-with(normalize-space(.),'SkyMiles')]/ancestor::tr[1]", null, false, "#\#\s*([\d]{5,})#");
            }

            if (empty($number)) {
                $number = $this->http->FindSingleNode("a[starts-with(normalize-space(.),'SkyMiles')]/preceding-sibling::node()[normalize-space(.)!=''][contains(normalize-space(.), '#')][1]", null, true, "#\#\s*([\d]{5,})#");
            }

            if (empty($number)) {
                $number = $this->http->FindSingleNode("(//a[@title='Your Account'])[1]", null, true, "#\#\s*([\d]{5,})#");
            }

            if (empty($number)) {
                $number = $this->http->FindSingleNode("//a[({$this->ends('Miles')}) and ({$this->starts([0, 1, 2, 3, 4, 5, 6, 7, 8, 9])})]/ancestor::td[1]/descendant::text()[normalize-space()!=''][1][starts-with(normalize-space(),'#')]", null, true, "#\#\s*([\d]{5,})#");
            }

            $st->setNumber($number)
                ->setLogin($number);

            $accountBarTexts = $this->http->FindNodes('//td[ not(.//td) and ./descendant::a[contains(.,"Miles")] and count(./descendant::text()[contains(.,"|")])=2 ]/descendant::text()[normalize-space(.)]');

            if (empty($accountBarTexts)) {
                $accountBarTexts = $this->http->FindNodes('//td[ not(.//td) and ./descendant::a[contains(.,"Miles")] and not (contains(normalize-space(), "Terms and Conditions"))]');
            }

            if (empty($accountBarTexts)) {
                $accountBarTexts = $this->http->FindNodes('//td[ not(.//td) and ./descendant::a[contains(.,"Medallion®")]]');
            }
            $accountBarText = implode(' ', $accountBarTexts);

            if (
                preg_match('/\|?\s*(\w+\s+Medallion)\s*®?\s*\|?/i', $accountBarText, $matches) // Platinum Medallion®
                || preg_match('/\|?\s*SkyMiles\s*®?\s+(\w+)\s*\|?/i', $accountBarText, $matches) // SkyMiles® Member
            ) {
                $level = $this->re("/^(\D+)\d*/u", $matches[1]);
                $st->addProperty('Level', $level); // Status
            }
            $name = $this->http->FindSingleNode("//*[starts-with(normalize-space(text()), 'Hello,')]/text()[1]", null, true, "/Hello, (.+)$/");

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//*[starts-with(normalize-space(text()), 'Hello')]/text()[1]", null, true, "/Hello (.+)\,$/");
            }

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }

            $mqmLink = $this->http->FindSingleNode("//text()[contains(normalize-space(), '(MQM')]/ancestor::table[1]/descendant::img[1]/@src");

            if (preg_match("/mqm_balance\=(\d+)/", $mqmLink, $m)) {
                $st->addProperty('MedallionMilesYTD', $m[1]);
            }

            $mqsLink = $this->http->FindSingleNode("//text()[contains(normalize-space(), '(MQS')]/ancestor::table[1]/descendant::img[1]/@src");

            if (preg_match("/mqs_balance\=(\d+)/", $mqsLink, $m)) {
                $st->addProperty('MedallionSegmentsYTD', $m[1]);
            }

            $mqdLink = $this->http->FindSingleNode("//text()[contains(normalize-space(), '(MQD')]/ancestor::table[1]/descendant::img[1]/@src");

            if (empty($mqdLink)) {
                $mqdLink = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'MQD')]/ancestor::table[1]/descendant::img[1]/@src");
            }

            if (preg_match("/mqd_balance\=(\d+)/", $mqdLink, $m)) {
                $st->addProperty('MedallionDollarsYTD', $m[1]);
            }

            $balanceDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Activity As Of')]", null, true, "/Activity as Of\s+([\d\/]+)$/i");

            if (!empty($balanceDate)) {
                $st->setBalanceDate(strtotime($balanceDate));
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $subj = $parser->getSubject();
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $condition1 =
            $this->http->XPath->query('//img[contains(@alt,"Travel Deals") or contains(@title, "DELTA") or contains(@alt,"WELCOME TO SILVER")] | //a[contains(@title, "SkyMilesLife Deals") or normalize-space(@title)="DELTA" or normalize-space(@title)="DELTA AIR LINES"]')->length > 0
            || strpos($body, 'Delta Air Lines, Inc. All rights reserved')
            || stripos($this->http->Response['body'], 'Summer Days Of Deals') !== false;

        $condition2 =
            $this->http->XPath->query('//td[ not(.//td) and ./descendant::a[contains(.,"Miles")] and count(./descendant::text()[contains(.,"|")])=2 and not(following::text()[normalize-space()][position() < 5][contains(normalize-space(), "Flight #:")])]')->length > 0 // it-18027078.eml
            // with "Flight #:" may intersect with delta/MakingYourSelection
            || stripos($subj, 'Delta Travel Deals') !== false
            || strpos($subj, 'Flight Deals') !== false
            || stripos($subj, 'Summer Days Of Deals') !== false
            || stripos($subj, 'The Deals Continue') !== false
            || stripos($subj, 'On Sale! Fly') !== false
            || stripos($subj, 'Premium Summer Getaways Now On Sale') !== false
            || stripos($body, 'Please refer Delta to customer service') !== false
            || stripos($body, 'THANK YOU FOR UPDATING YOUR SKYMILES ACCOUNT INFORMATION.') !== false
            || stripos($body, 'CONGRATULATIONS AND THANKS FOR YOUR LOYALTY.') !== false
            || stripos($body, 'We’re excited to announce that Robert Dressman has given you the gift of') !== false
            || $this->http->XPath->query("//text()[starts-with(normalize-space(), 'ENJOY TRAVELING THE MEDALLION WAY')]")->length > 0
            || stripos($body, 'You are now enrolled in the SkyMiles Medallion Status Match Challenge.') !== false
            || stripos($body, 'An Award Travel transaction has posted to your account') !== false
            || stripos($body, 'YOUR SKYMILES ACCOUNT ACCESS.') !== false
            || stripos($body, 'HOW CAN I EARN MILES FASTER?') !== false
            || stripos($body, 'YOUR SKYMILES LOGIN INFORMATION.') !== false
            || stripos($body, 'BPI SKYMILES') !== false
            || stripos($body, 'REGISTER TODAY AND GET ONE MONTH FREE') !== false
            || stripos($body, 'Check out more SkyMiles updates') !== false
            || stripos($body, 'Please log in to your SkyMiles account to verify all updates') !== false
            || stripos($body, 'With more earning and redemption options on the way') !== false
            || stripos($body, 'Complete one of the flight and spend qualifiers below') !== false
            || stripos($body, 'helping you pave your path to more adventure') !== false
            || stripos($body, 'you\'ll only need MQDs to earn toward Medallion') !== false
            || stripos($body, 'and agree to the SkyMiles') !== false
            || stripos($body, 'SkyMiles Members can receive') !== false
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'All benefits described below are strictly conditioned upon your compliance with the Delta Membership Guide and Program Rules.')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'FLIGHT QUALIFIER COMPLETE')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'premium experiences just for SkyMiles Members')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'Simply earn the following Medallion Qualification Dollars below')]")->length > 0;

        $condition3 = $this->http->XPath->query('//text()[starts-with(normalize-space(), "DEPARTURE")]/ancestor::table[3]')->length == 0;

        if ($condition1 && $condition2 && $condition3) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function ends($field, $source = 'normalize-space()')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $rules = [];

        foreach ($field as $f) {
            $len = mb_strlen($f);

            if ($len > 0) {
                $rule = "substring({$source},string-length({$source})+1-{$len},{$len})='{$f}'";
                $rules[] = $rule;
            }
        }

        if (count($rules) == 0) {
            return 'false()';
        }

        return implode(' or ', $rules);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }
}
