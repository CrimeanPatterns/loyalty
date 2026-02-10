<?php

namespace AwardWallet\Engine\delnorth\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "delnorth/it-93492874.eml, delnorth/it-93685844.eml, delnorth/it-93846202.eml, delnorth/it-94132573.eml, delnorth/it-94221956.eml";
    public $lang = "en";

    public static $dictionary = [
        "en" => [
            //            '' => '',
            'confirmed for arrival on'         => ['confirmed for arrival on', 'scheduled to arrive at'],
            'Cancellations and Modifications:' => ['Cancellations and Modifications:', 'Cancellation Policy:', 'Cancellations:'],
        ],
    ];

    private $detectFrom = "@delawarenorth.com";

    private $detectSubject = [
        // en
        "Reservation Confirmation", // Wuksachi Lodge Reservation Confirmation
    ];

    private $detectCompany = [
        'Hospitality by Delaware North Companies',
        'http://www.delawarenorth.com',
    ];

    private $detectBody = [
        "en" => ["Your reservation is confirmed for arrival on", "Congratulations! You're scheduled to arrive at the"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        foreach ($this->detectBody as $lang => $detectBody){
//            if ($this->http->XPath->query("//text()[".$this->contains($detectBody)."]")->length > 0) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains($this->detectCompany, '@href') . "] | //*[" . $this->contains($this->detectCompany) . "]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('your reservation number is')) . "]/ancestor::*[self::p or self::td or self::div][1]", null, true,
            "/" . $this->preg_implode($this->t('your reservation number is')) . "\s*([A-Z\d]{5,})\s*\./");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//*[self::b or self::strong][" . $this->starts($this->t('Reservation Number:')) . "]/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong])]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/");
        }
        $h->general()
            ->confirmation($confirmation);

        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Dear ')) . "]", null, true,
            "/^\s*" . $this->preg_implode($this->t('Dear')) . "\s*([[:alpha:] \-]{5,})\s*,/");

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller);
        }

        $cancellation = $this->http->FindSingleNode("//strong[" . $this->starts($this->t('Cancellations and Modifications:')) . "]/following-sibling::*[1]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Cancellations and Modifications:')) . "]/following::text()[normalize-space()][1]/ancestor::*[1][not(" . $this->contains($this->t('Cancellations and Modifications:')) . ")]");
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }
        // Hotel

        $h->hotel()
            ->name(implode(", ", array_unique(array_filter([
                $this->http->FindSingleNode("//text()[" . $this->contains($this->t('forward to welcoming you to')) . "]", null, true,
                "/" . $this->preg_implode($this->t('forward to welcoming you to')) . "\s*(.+?)\s*\./"),
                $this->http->FindSingleNode("//text()[" . $this->contains($this->t('to welcome you to a new era at')) . "]", null, true,
                "/" . $this->preg_implode($this->t('to welcome you to a new era at')) . "\s*([^,]+?)\s*, complete /"),
                $this->http->FindSingleNode("//text()[" . $this->contains($this->t('scheduled to arrive at the')) . "]", null, true,
                "/" . $this->preg_implode($this->t('scheduled to arrive at the')) . "\s*([^,]+?) on /"),
                $this->http->FindSingleNode("//text()[" . $this->starts($this->t('WELCOME TO')) . "]", null, true,
                "/^\s*" . $this->preg_implode($this->t('WELCOME TO')) . "\s+(.+)/"),
                $this->http->FindSingleNode("//text()[" . $this->starts('We are thrilled to welcome you to ') . "]", null, true,
                "/^\s*We are thrilled to welcome you to (.+), where/"),
            ]))))
        ;
        $address = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Hospitality by Delaware North')) . "][following::text()[normalize-space()][1][starts-with(normalize-space(),'©')]]/ancestor::td[1]//text()[normalize-space()][last()]", null, true,
            "/(.+)\d{5}\s*$/");

        if (!empty($address)) {
            $h->hotel()
                ->address($address);
        } elseif (!empty($h->getHotelName())) {
            $h->hotel()
                ->noAddress();
        }

        // Booked
        $dates = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('confirmed for arrival on')) . "]/ancestor::*[self::p or self::td or self::div][1]");

        if (preg_match("/arrival on (.+?) and departure on (.+?), your reservation/", $dates, $m)
            || preg_match("/to arrive at (?:.+?) on (.+?) and departure on (.+?)\s*(?:, your reservation|\.)/", $dates, $m)
        ) {
            $ci = strtotime($m[1]);
            $co = strtotime($m[2]);
            $times = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Check-in')) . " and " . $this->contains($this->t('Check-out')) . "]");

            if (preg_match("/Check-in: (\d{1,2}:\d{2}(?:[ap]m)?)\s*\| Check-out\s*(\d{1,2}:\d{2}(?:[ap]m)?)\b/i", $times, $mat)) {
                $ci = strtotime($mat[1], $ci);
                $co = strtotime($mat[2], $co);
            }
            $h->booked()
                ->checkIn($ci)
                ->checkOut($co)
            ;
        }

        // Rooms
        $h->addRoom()
            ->setType(
                $this->http->FindSingleNode("//*[self::b or self::strong][" . $this->eq($this->t('Room Reserved:')) . "]/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong])]")
                ?? $this->http->FindSingleNode("//*[self::b or self::strong][" . $this->eq(preg_replace('/[\s:]+$/', '', $this->t('Room Reserved:'))) . "]/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong])]", null, true, "/^:\s*(.+)/")
                ?? $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Room Reserved:')) . "]", null, true, "/Room Reserved:\s*(.+)/")
            )
            ->setRate(
                $this->http->FindSingleNode("//*[self::b or self::strong][" . $this->eq($this->t('Average Room Rate:')) . "]/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong])]")
                ?? $this->http->FindSingleNode("//*[self::b or self::strong][" . $this->eq(preg_replace('/[\s:]+$/', '', $this->t('Average Room Rate:'))) . "]/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong])]", null, true, "/^:\s*(.+)/")
                ?? $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Average Room Rate:')) . "]", null, true, "/Average Room Rate:\s*(.+)/"), true, true)
            ->setRateType(
                $this->http->FindSingleNode("//*[self::b or self::strong][" . $this->eq($this->t('Rate Reserved:')) . "]/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong])]")
                ?? $this->http->FindSingleNode("//*[self::b or self::strong][" . $this->eq(preg_replace('/[\s:]+$/', '', $this->t('Rate Reserved:'))) . "]/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong])]", null, true, "/^:\s*(.+)/")
                ?? $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Rate Reserved:')) . "]", null, true, "/Rate Reserved:\s*(.+)/")
            )
        ;

        // Price
        $total = $this->http->FindSingleNode("//*[self::b or self::strong][" . $this->starts($this->t('Total:')) . "]/following::text()[normalize-space()][1][not(ancestor::*[self::b or self::strong])]", null, true, "/^.*\d.*$/");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Total:')) . "]",
                null, true, "/Total:\s*(.+)/");
        }

        if (preg_match("#^\s*([^\s\d]{1,5})\s*(\d[\d.,]*)\s*(\(.+\))?$#", $total, $m)) {
            $h->price()
                ->total(PriceHelper::cost($m[2]))
                ->currency($m[1]);
        }

        $this->detectDeadLine($h);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellations made (?<priorD>\d+ hours?) or more prior to check-in will receive a full refund\./i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['priorD']);
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
