<?php

namespace AwardWallet\Engine\cityex\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
    public $mailFiles = "cityex/it-104617174.eml, cityex/it-144105922.eml, cityex/it-474204327.eml";
    public $subjects = [
        '/Cruises Order Confirmation/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'ORDER SUMMARY'                => ['ORDER SUMMARY', 'BOOKING SUMMARY'],
            'To share you experience with' => ['To share you experience with', 'Login using your email and confirmation number to manage your booking'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@cityexperiences.com') !== false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".cityexperiences.com/") or contains(@href,"www.cityexperiences.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Statue City Cruises") or contains(.,"www.statuecitycruises.com") or contains(normalize-space(), "City Experiences")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->contains(['Your bookings with Devour Tours and Walks', 'To share you experience with'])}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains(['Manage Booking', 'BOOKING SUMMARY'])}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]cityexperiences\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email): void
    {
        $e = $email->add()->event();

        $e->type()->event();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation No.:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-z\d\/ ]{5,}$/');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION NO.'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-z\d\/ ]{5,}$/');
        }
        $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation No.:'))}]", null, true, '/^(.+?)[\s:：]*$/u');

        if (empty($confirmationTitle)) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION NO.'))}]", null, true, '/^(.+?)[\s:：]*$/u');
        }
        $confirmationNumbers = preg_split("/\s*\/\s*/", $confirmation); // Confirmation No.: I40205005 / 40204969

        foreach ($confirmationNumbers as $number) {
            $e->general()->confirmation($number, $confirmationTitle);
        }

        $e->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\,/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'PURCHASE DATE.')]/following::text()[normalize-space()][1]")));

        $cost = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D*(\d[\d\,\.]*)/");

        if (!empty($cost)) {
            $e->price()
                ->cost($cost);
        }

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/following::text()[normalize-space() = 'TOTAL'][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D{1,5}(\d[\d\,\.]*)/");
        $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/following::text()[normalize-space() = 'TOTAL'][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/(?:^|\s|\d)([A-Z]{3})(?:\s|\d|$)/");

        if (empty($currency)) {
            $currency = $this->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/following::text()[normalize-space() = 'TOTAL'][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*(\D+?)\s*\d/"));
        }

        if (!empty($total) && !empty($currency)) {
            $e->price()
                ->total($total)
                ->currency($currency);
        }

        $discount = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'SUBTOTAL')]/following::text()[normalize-space() = 'DISCOUNT'][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D([\d\,\.]+)/");

        if (!empty($discount)) {
            $e->price()
                ->discount($discount);
        }

        $e->booked()
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='QTY']/ancestor::tr[1]/following::tr[1]/descendant::td[2]"));

        $address = $this->http->FindSingleNode("//text()[normalize-space()='ADDRESS']/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/ADDRESS\s*(.{3,}?)\s*(?i)QUESTIONS\? GET IN TOUCH\./");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='Meeting Point']/following::text()[normalize-space()][1]", null, true, "/This tour meets in\s*(.+)/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[normalize-space()='Meeting Point']/following::text()[normalize-space()][1]");

            //it-474204327.eml - save example
            /*if (stripos($address, "(") !== false) {
                $address = preg_replace("/\(.+/", "", $address);
            }*/

            if (preg_match("/^(?<address>.+)\.\s*(?<notes>Meet your guide under the portico.+)/", $address, $m)) {
                $address = $m['address'];
                $e->setNotes($m['notes']);
            }
        }

        if (empty($address)) {
            $address_temp = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Meeting Point:')]", null, true, "/{$this->opt($this->t('Meeting Point:'))}\s*(.+)/");

            if (strlen($address_temp) > 15) {
                $address = $address_temp;
            }
        }

        $e->place()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('ORDER SUMMARY'))}]/following::text()[normalize-space()][1]"))
            ->address($address);

        $phone = $this->http->FindSingleNode("//text()[normalize-space()='ADDRESS']/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/QUESTIONS\? GET IN TOUCH\.\s+([\(\d+\)\s\-]{10,})\s/s");

        if (!empty($phone)) {
            $e->place()
                ->phone($phone);
        }

        $startDateText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ORDER SUMMARY'))}]/following::text()[normalize-space()][1]/following::text()[normalize-space()][1]");

        if (preg_match("/(\d+\/\d+\/\d{4})\s*\|\s*([\d\:]+\s*A?P?M)/us", $startDateText, $m)) {
            $startDate = $m[1];
            $startTime = $m[2];

            $date = strtotime($startDate . ', ' . $startTime);
        } elseif (preg_match("/^(\w+)\-(\d+)\-(\d{4})[\s\|]+([\d\:]+\s*A?P?M?)$/", $startDateText, $m)) { //Jun-10-2022 | 8:30 AM
            $date = strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3] . ', ' . $m[4]);
        } elseif (preg_match("/^(\d+)\/(\d+)\/(\d{4})$/", $startDateText, $m)) {
            $startTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Tour Start Time:')]", null, true, "/{$this->opt($this->t('Tour Start Time:'))}\s*([\d\:]+\s*A?P?M?)$/");

            if (!empty($startTime)) {
                $date = strtotime($m[1] . '.' . $m[1] . '.' . $m[3] . ', ' . $startTime);
            }
        }

        $e->booked()
            ->start($date)
            ->noEnd();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'CA$' => 'CAD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
