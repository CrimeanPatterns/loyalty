<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class DeltaConfirmation extends \TAccountChecker
{
    public $mailFiles = "delta/it-547630201.eml, delta/it-55719997.eml, delta/it-55874819.eml";

    private $emailDate;
    private $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->emailDate = strtotime($parser->getDate());

        $xpath = "//text()[starts-with(normalize-space(), 'DEPARTURE')]/ancestor::table[3]";
        $nodes = $this->http->XPath->query($xpath);

        if (count($nodes) > 0) {
            $this->collectionProp($email);

            $flight = $email->add()->flight();
            $accNumber = $this->http->FindSingleNode("//a[starts-with(normalize-space(), '#')]", null, true, '/[#](\d{5,})\s*$/');

            if (!empty($accNumber)) {
                $flight->program()
                    ->account($accNumber, false);
            }

            $confNumber = $this->http->FindSingleNode("//div[{$this->starts(['CONFIRMATION:', 'ONFIRMATION #:', 'FLIGHT CONFIRMATION #:'])}]", null, true, '/:\s*([A-Z\d]{5,7}\s*$)/');

            if (!empty($confNumber)) {
                $flight->general()->confirmation($confNumber);
            } else {
                $flight->general()->noConfirmation();
            }

            foreach ($nodes as $root) {
                $segment = $flight->addSegment();

                // DEPARTURE SAN 5:06 PM Sat, Mar 14 DL26    |    DEPARTURE BOM 3:00 AM Mon, Mar 02 Virgin Atlantic VS355*
                $depData = implode(' ', $this->http->FindNodes("//text()[starts-with(normalize-space(), 'DEPARTURE')]/ancestor::tr[2]/descendant::text()[normalize-space()]", $root));

                if (preg_match('/\s(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)[*]*\s*(?:$|Boarding closes)/', $depData, $m)) {
                    $segment->airline()
                        ->name($m['name'])
                        ->number($m['number']);
                }

                $tranferData = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'DEPARTURE')]/following::table[1]", $root);

                if (!empty($tranferData)) {
                    $timeDep = $this->re('/DEPARTURE\s+\D{3}\s(\d+[:]\d+\s\D{2})/', $depData);
                    $dateDep = $this->re('/DEPARTURE\s+\D{3}\s\d+[:]\d+\s\D{2}\s(\D+\s\d+)/', $depData);

                    $segment->departure()
                        ->code($this->re('/DEPARTURE\s+(\D{3})/', $depData))
                        ->date($this->normalizeDate($dateDep . ', ' . $timeDep));

                    $segment->arrival()
                        ->noDate()
                        ->code($this->re('/(\D{3})/', $tranferData));

                    $segment2 = $flight->addSegment();

                    $transferDate = $this->re('/\s\d+[:]\d+\s\D{2}\s(\D+\s\d+)/', $tranferData);
                    $transferTime = $this->re('/(\d{1,2}[:]\d{2}\s\D{2})/', $tranferData);

                    if (empty($transferDate)) {
                        $transferDate = $dateDep;
                    }

                    $segment2->departure()
                        ->code($this->re('/(\D{3})/', $tranferData))
                        ->date($this->normalizeDate($transferDate . ', ' . $transferTime));

                    if (preg_match('/\s(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)[*]*\s*(?:$|Boarding closes)/', $tranferData, $m)) {
                        // JFK 8:29 AM DL592    |    JFK 7:30 PM Virgin Atlantic VS4*
                        //  ATL 5:11 PM DL832 Boarding closes at 4:56 PM
                        $segment2->airline()
                            ->name($m['name'])
                            ->number($m['number']);
                    }

                    $arrData = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'DESTINATION')]/ancestor::tr[2]", $root);

                    $timeArr = $this->re('/DESTINATION\s*[A-Z]{3}\s*(\d+[:]\d+\s\D{2})/', $arrData);
                    $dateArr = $this->re('/DESTINATION\s*[A-Z]{3}\s*\d+[:]\d+\s\D{2}\s(\D+\s\d+)/', $arrData);

                    $segment2->arrival()
                        ->date($this->normalizeDate($dateArr . ', ' . $timeArr))
                        ->code($this->re('/DESTINATION\s*([A-Z]{3})\s*\d+[:]\d+/', $arrData));
                } else {
                    $timeDep = $this->re('/DEPARTURE\s+[A-Z]{3}\s(\d+[:]\d+\s\D{2})/', $depData);
                    $dateDep = $this->re('/DEPARTURE\s+[A-Z]{3}\s\d+[:]\d+\s\D{2}\s(\D+\s\d+)/', $depData);

                    $segment->departure()
                        ->code($this->re('/DEPARTURE\s+([A-Z]{3})/', $depData))
                        ->date($this->normalizeDate($dateDep . ', ' . $timeDep));

                    $arrData = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'DESTINATION')]/ancestor::tr[2]", $root);
                    $timeArr = $this->re('/DESTINATION\s*\D{3}\s*(\d+[:]\d+\s\D{2})/', $arrData);
                    $dateArr = $this->re('/DESTINATION\s*\D{3}\s*\d+[:]\d+\s\D{2}\s(\D+\s\d+)/', $arrData);

                    $segment->arrival()
                        ->date($this->normalizeDate($dateArr . ', ' . $timeArr))
                        ->code($this->re('/\s*(\D{3})\s*\d/', $arrData));
                }
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ((($this->http->XPath->query('//text()[contains(., "CONFIRMATION:")]')->length > 0)
            || ($this->http->XPath->query('//text()[contains(., "CONFIRMATION #:")]')->length > 0))
            && ($this->http->XPath->query('//text()[contains(., "Get Ready To Go")]')->length > 0)
            && ($this->http->XPath->query('//text()[contains(., "Get Connected In The Air") or contains(., "TSA Security Tips") or contains(., "We\'ve Got You Covered") or contains(., "Travel With Ease")]')->length > 0)
            && ($this->http->XPath->query('//text()[contains(., "Delta Air Lines, Inc. All rights reserved")]')->length > 0)
            && ($this->http->XPath->query('//img[contains(@src,"delta.com") and contains(@alt, "DELTA")]')->length > 0 || $this->http->XPath->query('//a[contains(@href,"delta.com")]')->length > 0)) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]t\.delta\.com/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['from']) && stripos($headers['from'], '@t.delta.com') !== false
               && !empty($headers['subject']) && preg_match('/Your\s+\D{3}\s[>]\s+\D{3}\s+Trip\s+Details/', $headers['subject']) > 0;
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

    private function normalizeDate($str)
    {
        $year = date('Y', $this->emailDate);

        $str .= ' ' . $year;

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function collectionProp(Email $email)
    {
        $props = [];

        $balance = $this->http->FindSingleNode("//a[({$this->ends('Miles')}) and ({$this->starts([0, 1, 2, 3, 4, 5, 6, 7, 8, 9])})]", null, false, "/(\d[,\d]*)/");

        if ($balance !== null) {
            $props['Balance'] = str_replace(',', '', $balance);
        }

        $props['Number'] = $props['Login'] = $this->http->FindSingleNode("//a[({$this->ends('Miles')}) and ({$this->starts([0, 1, 2, 3, 4, 5, 6, 7, 8, 9])})]/ancestor::td[1]/descendant::text()[normalize-space()!=''][1][starts-with(normalize-space(),'#')]", null, true, "#\#\s*([\d]{5,})#");

        $accountBarTexts = $this->http->FindNodes('//td[ not(.//td) and ./descendant::a[contains(.,"Miles")] and count(./descendant::text()[contains(.,"|")])=2 ]/descendant::text()[normalize-space(.)]');
        $accountBarText = implode(' ', $accountBarTexts);

        if (
            preg_match('/\|\s*(\w+\s+Medallion)\s*®?\s*\|/i', $accountBarText, $matches) // Platinum Medallion®
            || preg_match('/\|\s*SkyMiles\s*®?\s+(\w+)\s*\|/i', $accountBarText, $matches) // SkyMiles® Member
        ) {
            $props['Level'] = $matches[1]; // Status
        }

        if (!empty($props['Balance'])) {
            $s = $email->createStatement();

            foreach ($props as $key => $value) {
                if ($key === $s::EXPIRATION_KEY) {
                    $s->setExpirationDate($value);
                } elseif ($key === $s::BALANCE_KEY) {
                    $s->setBalance($value);
                } elseif ($key === $s::BALANCE_DATE_KEY) {
                    $s->setBalanceDate($value);
                } elseif ($key === 'SubAccounts') {
                    foreach ($value as $subacc) {
                        $s->addSubAccount($subacc);
                    }
                } elseif ($key === 'DetectedCards') {
                    foreach ($value as $card) {
                        $s->addDetectedCard($card);
                    }
                } else {
                    $s->addProperty($key, $value);
                }
            }
        }
    }
}
