<?php

namespace AwardWallet\Engine\dresorts\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReceipt extends \TAccountChecker
{
    public $mailFiles = "dresorts/it-111528798.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    private $patterns = [
        'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Diamond Resorts Hotel Receipt') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Diamond Resorts International')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Reservation Confirmation')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel policies'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Price Summary'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@diamondresorts.com') !== false;
    }

    public function ParseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        $reference = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reference # is'))}]");

        if (preg_match("/({$this->opt($this->t('Your reference # is'))})\s*([-A-Z\d]{5,})(?:\s*[,.!;?]|$)/", $reference, $m)) {
            $m[1] = preg_replace("/^Your\s+/i", '', $m[1]);
            $m[1] = preg_replace("/\s+is$/i", '', $m[1]);
            $h->general()->confirmation($m[2], $m[1]);
        }

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)\,/"), true)
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Cancellations and changes']/following::text()[normalize-space()][1]"));

        $totalText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Total Paid')]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(\D)\s*([\d\.\,]+)/", $totalText, $m)) {
            $h->price()
                ->total($m[2])
                ->currency($m[1]);
        }

        $spentAwards = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Points Value Applied:')]", null, true, "/{$this->opt($this->t('Points Value Applied:'))}\s*(\d+)/");

        if (!empty($spentAwards)) {
            $h->price()
                ->spentAwards($spentAwards);
        }

        $tax = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Taxes & Fees')]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][last()]", null, true, "/^\D([\d\.\,]+)$/");

        if (!empty($tax)) {
            $h->price()
                ->tax($tax);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Your reference # is')]/following::span[normalize-space()][1]"));

        $hotelInfo = implode(" ", $this->http->FindNodes("//text()[contains(normalize-space(), 'Check-in Time:')]/ancestor::table[1]/preceding::table[normalize-space()][1]/descendant::text()[normalize-space()]"));

        if (!empty($h->getHotelName())
            && preg_match("/^{$h->getHotelName()}\s*(?<address>.{3,}?)(?:\s+(?<phone>{$this->patterns['phone']}))?$/", $hotelInfo, $m)
        ) {
            $h->hotel()->address($m['address']);

            if (!empty($m['phone'])) {
                $h->hotel()->phone($m['phone']);
            }
        }

        $checkInOutText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Your reference # is')]/following::span[normalize-space()][2]");

        if (preg_match("/(\d+\/\d+\/\d{4})\s*\-\s*(\d+\/\d+\/\d{4})/u", $checkInOutText, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]));
        }

        $checkInTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Check-in Time:')]", null, true, "/{$this->opt($this->t('Check-in Time:'))}\s*({$this->patterns['time']})/u");

        if (!empty($checkInTime)) {
            $h->booked()->checkIn(strtotime($checkInTime, $h->getCheckInDate()));
        }

        $checkOutTime = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Check-out Time:')]", null, true, "/{$this->opt($this->t('Check-out Time:'))}\s*({$this->patterns['time']})/u");

        if (!empty($checkOutTime)) {
            $h->booked()->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()));
        }

        $guests = $this->http->FindSingleNode("//text()[normalize-space()='Hotel policies']/following::text()[normalize-space()='Room']/following::text()[contains(normalize-space(), 'Adult')]", null, true, "/^\s*(\d+)\s*{$this->opt($this->t('Adult'))}/");

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
        }

        $kids = $this->http->FindSingleNode("//text()[normalize-space()='Hotel policies']/following::text()[normalize-space()='Room']/following::text()[contains(normalize-space(), 'Children')]", null, true, "/^\s*(\d+)\s*{$this->opt($this->t('Children'))}/");

        if ($kids !== null) {
            $h->booked()
                ->kids($kids);
        }

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Hotel policies']/following::text()[normalize-space()='Room'][2]/following::text()[normalize-space()][1]");
        $room->setType($roomType);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number'))}]");

        if (preg_match("/({$this->opt($this->t('Confirmation Number'))})\s*:\s*([-A-Z\d]{5,})(?:\s*[,.!;?]|$)/", $confirmation, $m)) {
            $room->setConfirmation($m[2]);
            $room->setConfirmationDescription($m[1]);
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\/(\d+)\/(\d{4})$#u', // 10/25/2021
        ];
        $out = [
            '$2.$1.$3',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancell?ations (?i)or changes made after\s*(?<time>{$this->patterns['time']}).+on\s*(?<month>[[:alpha:]]+)\s*(?<day>\d{1,2}),\s*(?<year>\d{4})\s*are/us", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
        }
    }
}
