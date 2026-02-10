<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelConfirmation extends \TAccountChecker
{
    public $mailFiles = "british/it-661004115.eml, british/it-663700856.eml";
    public $subjects = [
        'British Airways Travel Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@britishairways.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'British Airways')]")->length > 0) {
            if ($this->http->XPath->query("//text()[{$this->contains($this->t('Hotel'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking details'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Room description:'))}]")->length > 0) {
                return true;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('Car Hire'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Car hire supplied by'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Car hire details:'))}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]britishairways\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[normalize-space()='Hotel']/following::text()[normalize-space()='Booking summary']")->length > 0) {
            $this->ParseHotel($email);
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Car Hire']/following::text()[normalize-space()='Booking summary']")->length > 0) {
            $this->ParseCar($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Occupants:']/ancestor::tr[2]/descendant::table[2]/descendant::text()[normalize-space()]");
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking reference:')]", null, true, "/^{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]+)/"))
            ->travellers($travellers)
            ->date(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Booking date:']/following::text()[normalize-space()][1]")));

        $accounts = $this->http->FindNodes("//text()[normalize-space()='Loyalty Member ID']/ancestor::tr[2]/descendant::table[2]", null, "/^(\d{5,})$/");

        if (count($accounts) > 0) {
            $h->setAccountNumbers($accounts, false);
        }

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Booking details']/following::text()[starts-with(normalize-space(), 'Arriving')]/ancestor::tbody[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/Arriving.*\n(?<hName>.+)\n(?<hAddress>(?:.+\n){1,5})Call\:\s*(?<phone>.+)\nInstructions/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['hName'])
                ->address(str_replace("\n", " ", $m['hAddress']))
                ->phone($m['phone']);
        }

        $in = $this->http->FindSingleNode("//text()[normalize-space()='Check-in:']/ancestor::tr[2]/descendant::table[2]", null, true, "/\w+\s*([\d\/]+\s*[\d\:]+\s*A?P?M)[\s\-]*/u");
        $out = $this->http->FindSingleNode("//text()[normalize-space()='Check-out:']/ancestor::tr[2]/descendant::table[2]", null, true, "/\w+\s*([\d\/]+\s*[\d\:]+\s*A?P?M)/");

        $h->booked()
            ->checkIn($this->normalizeDate($in))
            ->checkOut($this->normalizeDate($out))
            ->guests(count($travellers));

        $roomType = $this->http->FindNodes("//text()[normalize-space()='Room type:']/ancestor::tr[2]/descendant::table[2]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'guaranteed'))]");

        if (count($roomType) === 1) {
            $h->addRoom()->setType($roomType[0]);
        }

        $spentAwards = $this->http->FindSingleNode("//text()[normalize-space()='Avios paid']/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)$/");

        if (!empty($spentAwards)) {
            $h->price()
                ->spentAwards($spentAwards);
        }

        $earned = $this->http->FindSingleNode("//text()[normalize-space()='Avios earned']/following::text()[normalize-space()][1]", null, true, "/^\s*([\d\,]+\s*Avios)/");

        if (!empty($earned)) {
            $h->setEarnedAwards($earned);
        }

        $taxInfo = $this->http->FindSingleNode("//text()[normalize-space()='Property Fees' or normalize-space()='Taxes']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<tax>[\d\.\,]+)/", $taxInfo, $m)) {
            $currency = $this->currency($m['currency']);
            $h->price()
                ->tax(PriceHelper::parse($m['tax'], $currency))
                ->currency($currency);
        }

        $totalInfo = $this->http->FindSingleNode("//text()[normalize-space()='Cash paid']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)/", $totalInfo, $m)) {
            $currency = $this->currency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Hotel stay']/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*([\d\.\,]+)/");

            if (!empty($cost)) {
                $h->price()
                    ->cost($cost);
            }
        }
    }

    public function ParseCar(Email $email)
    {
        $r = $email->add()->rental();

        $travellers = $this->http->FindSingleNode('//text()[normalize-space()="Traveller\'s name"][1]/following::text()[normalize-space()][1]');
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking reference:')]", null, true, "/^{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]+)/"))
            ->traveller($travellers)
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Car hire details:']/preceding::text()[normalize-space()='Booking date:'][1]/following::text()[normalize-space()][1]")));

        $accounts = $this->http->FindNodes("//text()[normalize-space()='Loyalty Member ID']/ancestor::tr[2]/descendant::table[2]", null, "/^(\d{5,})$/");

        if (count($accounts) > 0) {
            $r->setAccountNumbers($accounts, false);
        }

        $spentAwards = $this->http->FindSingleNode("//text()[normalize-space()='Avios paid']/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)$/");

        if (!empty($spentAwards)) {
            $r->price()
                ->spentAwards($spentAwards);
        }

        $earned = $this->http->FindSingleNode("//text()[normalize-space()='Avios earned']/following::text()[normalize-space()][1]", null, true, "/^\s*([\d\,]+\s*Avios)/");

        if (!empty($earned)) {
            $r->setEarnedAwards($earned);
        }

        $taxInfo = $this->http->FindSingleNode("//text()[normalize-space()='Property Fees' or normalize-space()='Taxes']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<tax>[\d\.\,]+)/", $taxInfo, $m)) {
            $currency = $this->currency($m['currency']);
            $r->price()
                ->tax(PriceHelper::parse($m['tax'], $currency))
                ->currency($currency);
        }

        $totalInfo = $this->http->FindSingleNode("//text()[normalize-space()='Cash paid']/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)/", $totalInfo, $m)) {
            $currency = $this->currency($m['currency']);
            $r->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Hotel stay']/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*([\d\.\,]+)/");

            if (!empty($cost)) {
                $r->price()
                    ->cost($cost);
            }
        }

        $company = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Car hire supplied by')]", null, true, "/{$this->opt($this->t('Car hire supplied by'))}\s*(\D+)/");

        if (!empty($company)) {
            $r->setCompany($company);
        }

        $r->car()
            ->type($this->http->FindSingleNode("//text()[normalize-space()='Class:']/following::text()[normalize-space()][1]"))
            ->model($this->http->FindSingleNode("//text()[normalize-space()='Car:']/following::text()[normalize-space()][1]"));

        $pickUpInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Pick up:']/ancestor::tr[2]/descendant::table[2]/descendant::text()[normalize-space()]"));

        if (preg_match("/^\w+\s*(?<depDate>\d+\/\d+\/\d+)\n(?<depTime>[\d\:]+\s*A?P?M?)\n(?<location>(?:.+\n){1,6})Open\s*(?<hours>.+)$/", $pickUpInfo, $m)) {
            $r->pickup()
                ->location(str_replace("\n", " ", $m['location']))
                ->date($this->normalizeDate($m['depDate'] . ' ' . $m['depTime']))
                ->openingHours($m['hours']);

            $phone = $this->http->FindSingleNode("//text()[normalize-space()='Pick up:']/ancestor::tr[2]/following::tr[1][contains(normalize-space(), 'Contact:')]/descendant::table[2]");

            if (!empty($phone)) {
                $r->pickup()
                    ->phone($phone);
            }
        }

        $dropOffInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Drop off:']/ancestor::tr[2]/descendant::table[2]/descendant::text()[normalize-space()]"));

        if (preg_match("/^\w+\s*(?<arrDate>\d+\/\d+\/\d+)\n(?<arrTime>[\d\:]+\s*A?P?M?)\n(?<location>(?:.+\n){1,6})Open\s*(?<hours>.+)$/", $dropOffInfo, $m)) {
            $r->dropoff()
                ->location(str_replace("\n", " ", $m['location']))
                ->date($this->normalizeDate($m['arrDate'] . ' ' . $m['arrTime']))
                ->openingHours($m['hours']);

            $phone = $this->http->FindSingleNode("//text()[normalize-space()='Drop off:']/ancestor::tr[2]/following::tr[1][contains(normalize-space(), 'Contact:')]/descendant::table[2]");

            if (!empty($phone)) {
                $r->dropoff()
                    ->phone($phone);
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\/(\d+)\/(\d+)\s+([\d\:]+\s*A?P?M)$#u", //14/04/24 2:00 PM
            "#^(\d+)\/(\d+)\/(\d+)$#u", //14/04/24
        ];
        $out = [
            "$1.$2.20$3, $4",
            "$1.$2.20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
