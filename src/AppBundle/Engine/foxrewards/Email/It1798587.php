<?php

namespace AwardWallet\Engine\foxrewards\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1798587 extends \TAccountChecker
{
    public $mailFiles = "foxrewards/it-1798587.eml, foxrewards/it-1801065.eml, foxrewards/it-1801067.eml, foxrewards/it-2191211.eml, foxrewards/it-3151837.eml, foxrewards/it-65656642.eml, foxrewards/it-75877600.eml";

    public $subjects = [
        '/From Fox Rent A Car$/',
        '/From Fox Rent-A-Car$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'notFee'              => ['ESTIMATED TOTAL'],
            'Confirmation Number' => ['Confirmation Number', 'Vehicle Provider Confirmation Number'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@foxrentacar.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Fox Rent A Car')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Summary'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Details'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]foxrentacar\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $r = $email->add()->rental();

        $foxRewardsNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Fox Rewards #'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($foxRewardsNo) {
            $r->program()->account($foxRewardsNo, false);
        }

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))}]/following::text()[normalize-space()][1]"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('First Name'))}]/following::text()[normalize-space()][1]") . ' ' .
                $this->http->FindSingleNode("//text()[{$this->starts($this->t('Last Name'))}]/following::text()[normalize-space()][1]"), true);

        $pickUpText = implode(' ', $this->http->FindNodes("//text()[{$this->starts($this->t('DIRECTIONS TO FOX DROPOFF LOCATION'))}]/ancestor::tr[1]/preceding-sibling::tr/descendant::td"));

        if (preg_match("/(?:View On Map)(?<location>.+)\s*{$this->opt($this->t('Toll Free:'))}\s*[\d\(\)\s\-]+\s*{$this->opt($this->t('Local Phone:'))}\s+(?<phone>[\d\(\)\s\-]+)\s*{$this->opt($this->t('Business Hours:'))}(?<hours>.+)\s*(?:\*)/", $pickUpText, $m)
            || preg_match("/^(?<location>.+)\s*{$this->opt($this->t('Toll Free:'))}\s*[\d\(\)\s\-]+\s*{$this->opt($this->t('Local Phone:'))}\s+(?<phone>[\d\(\)\s\-]+)\s*{$this->opt($this->t('Business Hours:'))}(?<hours>.+)$/", $pickUpText, $m)) {
            // it-3151837.eml, it-65656642.eml
            $r->pickup()
                ->location($m['location'])
                ->phone($m['phone'])
                ->openingHours($m['hours']);
        }

        $dropOffText = implode(' ', $this->http->FindNodes("//text()[{$this->starts($this->t('DIRECTIONS TO FOX DROPOFF LOCATION'))}]/following::table[1]/descendant::tr/descendant::td"));

        if (preg_match("/^(?<location>.+)\s+{$this->opt($this->t('Toll Free:'))}\s*[\d\(\)\s\-]+\s+{$this->opt($this->t('Local Phone:'))}\s+(?<phone>[\d\(\)\s\-]+)\s+{$this->opt($this->t('Business Hours:'))}(?<hours>.+)$/", $dropOffText, $m)) {
            $r->dropoff()
                ->location($m['location'])
                ->phone($m['phone'])
                ->openingHours($m['hours']);
        }

        $pickUpDropOffText = implode(' ', $this->http->FindNodes("//text()[{$this->starts($this->t('DIRECTIONS TO FOX LOCATION'))}]/following::table[1]/descendant::tr/descendant::td"));

        if (preg_match("/^(?<location>.+)\s+{$this->opt($this->t('Toll Free:'))}\s*[\d\(\)\s\-]+\s+{$this->opt($this->t('Local Phone:'))}\s+(?<phone>[\d\(\)\s\-]+)\s+{$this->opt($this->t('Hours:'))}(?<hours>.+)$/", $pickUpDropOffText, $m)) {
            // it-1798587.eml, it-1801065.eml, it-1801067.eml, it-2191211.eml
            $r->pickup()
                ->location($m['location'])
                ->phone($m['phone'])
                ->openingHours($m['hours']);

            $r->dropoff()->same();
        }

        // it-75877600.eml
        if (empty($r->getPickUpLocation())) {
            $pickUpLocation = $this->http->FindSingleNode("//tr[count(*)=2 and *[1][{$this->eq($this->t('Rental Pickup Location'))}]]/*[2]");
            $r->pickup()->location($pickUpLocation);
        }

        if (empty($r->getDropOffLocation())) {
            $dropOffLocation = $this->http->FindSingleNode("//tr[count(*)=2 and *[1][{$this->eq($this->t('Rental Drop Off Location'))}]]/*[2]");
            $r->dropoff()->location($dropOffLocation);
        }

        $pickUpTime = $this->http->FindSingleNode("//tr[count(*)=2 and *[1][{$this->eq($this->t('Pickup Time'))}]]/*[2]");
        $r->pickup()->date($this->normalizeDate($pickUpTime));
        $dropOffTime = $this->http->FindSingleNode("//tr[count(*)=2 and *[1][{$this->eq($this->t('Drop off Time'))}]]/*[2]");
        $r->dropoff()->date($this->normalizeDate($dropOffTime));

        $r->car()->model($this->http->FindSingleNode("//tr[count(*)=2 and *[1][{$this->eq($this->t('Vehicle'))}]]/*[2]"));

        $r->price()
            ->cost($this->http->FindSingleNode("//td[{$this->starts($this->t('RENTAL RATE CHARGES'))}]/following::td[1]", null, true, "/^\D?([\d\.]+)/"))
            ->total($this->http->FindSingleNode("//td[{$this->starts($this->t('ESTIMATED TOTAL'))}]/following::td[1]", null, true, "/^\D?([\d\.]+)/"))
            ->currency($this->normalizeCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('All rates in'))}]", null, true, "/{$this->opt($this->t('All rates in'))}\s+(\D+)\./")));

        $xpath = "//td[{$this->starts($this->t('RENTAL RATE CHARGES'))}]/ancestor::tr[1]/following-sibling::tr[not({$this->contains($this->t('notFee'))})]";
        $feesNode = $this->http->XPath->query($xpath);

        foreach ($feesNode as $root) {
            $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);
            $feeSum = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^\D?([\d\.]+)$/");

            if (!empty($feeName) && !empty($feeSum)) {
                $r->price()
                    ->fee($feeName, $feeSum);
            }
        }

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d{4})\-(\d+)\-(\d+)\s*([\d\:]+\s*A?P?M)$#", // 2020-12-11 07:00 PM
        ];
        $out = [
            "$3.$2.$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
