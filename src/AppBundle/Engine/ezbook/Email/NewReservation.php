<?php

namespace AwardWallet\Engine\ezbook\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NewReservation extends \TAccountCheckerExtended
{
    public $mailFiles = "ezbook/it-50084060.eml, ezbook/it-50350812.eml";
    private $detectFrom = "ezbookpro.com";
    private $detectSubject = ["New Reservation", "Hotel"];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $subject) {
            if (stripos($headers["subject"], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (($this->http->XPath->query("//img[contains(@alt,'ezBookPro')]")->length > 0)
            && ($this->http->XPath->query("//text()[contains(normalize-space(), 'Hotel Details') or contains(normalize-space(), 'HOTEL DETAILS')]")->length > 0)
            && ($this->http->XPath->query("//text()[contains(normalize-space(), 'Adult')]")->length > 0)
            && ($this->http->XPath->query("//text()[contains(normalize-space(), 'This booking must be paid according to our terms and conditions')]")->length > 0)
        ) {
            return true;
        }

        return false;
    }

    private function parseHtml(Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//*[contains(text(), 'Confirmation:')]", null, true, '/[#](.+)/'), "ezbookpro Confirmation #");

        $h = $email->add()->hotel();

        $nodes = $this->http->FindNodes("//*[{$this->starts('This booking can be cancelled')}]/preceding-sibling::p");

        foreach ($nodes as $node) {
            $traveller = $this->re("/\d\s[-]\s(\D+)\s[(](:?Adult|Kid|Child)/", $node);

            if (!empty($traveller)) {
                $travellers[] = $traveller;
            }
        }

        $h->general()
            ->noConfirmation()
            ->travellers($travellers)
            ->status($this->http->FindSingleNode("//*[" . $this->starts('Status:') . "]", null, true, "/Status:\s(.+)/"))
            ->cancellation($this->http->FindSingleNode("//*[" . $this->starts('This booking can be cancelled') . "]", null, true, '/^.*?excluded./'));

        $this->detectDeadLine($h);

        if (!empty($this->http->FindSingleNode("//*[contains(text(), 'Hotel Details') or contains(text(), 'HOTEL DETAILS')]/following::text()[normalize-space()][3]", null, true, '/((:?Confirmation:|Phone:))/'))) {
            $hotelName = $this->http->FindSingleNode("//*[contains(text(), 'Hotel Details') or contains(text(), 'HOTEL DETAILS')]/following::text()[normalize-space()][1]");
            $hotelAdress = $this->http->FindSingleNode("//*[contains(text(), 'Hotel Details') or contains(text(), 'HOTEL DETAILS')]/following::text()[normalize-space()][2]");
        }
        $h->hotel()
            ->name($hotelName)
            ->address($hotelAdress);

        $phone = $this->http->FindSingleNode("//*[contains(text(), 'Phone:')]", null, true, "/Phone:\s(\d+)/");

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $checkIn = $this->normalizeDate($this->http->FindSingleNode("//*[contains(text(), 'Check-in')]", null, true, "/Check-in\s(.+)/"));

        if (empty($checkIn)) {
            $checkIn = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel Details') or starts-with(normalize-space(), 'HOTEL DETAILS')]/following::text()[starts-with(normalize-space(), 'Check-in')][1]", null, true, "/Check-in\s(.+)/"));
        }
        $checkOut = $this->normalizeDate($this->http->FindSingleNode("//*[contains(text(), 'Check-out')]", null, true, "/Check-out\s(.+)/"));

        if (empty($checkOut)) {
            $checkOut = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel Details') or starts-with(normalize-space(), 'HOTEL DETAILS')]/following::text()[starts-with(normalize-space(), 'Check-out')][1]", null, true, "/Check-out\s(.+)/"));
        }

        $h->booked()
            ->checkIn($checkIn)
            ->checkOut($checkOut);

        if (!empty($this->http->FindSingleNode("//*[contains(text(), 'Check-out')]/following::text()[normalize-space()][3]", null, true, '/\d\s[-]\s(\D+)\s[(].+[)]/'))) {
            $roomType = $this->http->FindSingleNode("//*[contains(text(), 'Check-out')]/following::text()[normalize-space()][1]");
            $roomDescription = $this->http->FindSingleNode("//*[contains(text(), 'Check-out')]/following::text()[normalize-space()][2]", null, true, '/[-]\s+(.+)/');
        }

        if (!empty($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel Details') or starts-with(normalize-space(), 'HOTEL DETAILS')]/following::text()[starts-with(normalize-space(), 'Check-out')][1]", null, true, '/Check-out\s(.+)/'))) {
            $roomType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel Details') or starts-with(normalize-space(), 'HOTEL DETAILS')]/following::text()[starts-with(normalize-space(), 'Check-out')][1]/following::text()[normalize-space()][position()<5]"
                . "[ following::text()[normalize-space()][1][starts-with(normalize-space(), '-')] and  following::text()[normalize-space()][2][contains(normalize-space(), 'Adult')]]");
            $roomDescription = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel Details') or starts-with(normalize-space(), 'HOTEL DETAILS')]/following::text()[starts-with(normalize-space(), 'Check-out')][1]/following::text()[normalize-space()][position()<5]"
                . "[ following::text()[normalize-space()][1][starts-with(normalize-space(), '-')] and  following::text()[normalize-space()][2][contains(normalize-space(), 'Adult')]]/following::text()[normalize-space()][1]", null, true, '/[-]\s+(.+)/');
        }

        $h->addRoom()
            ->setType($roomType)
            ->setDescription($roomDescription);

        $nodes = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'This booking must')]/following::text()[normalize-space()]");
        $node = implode("\n", $nodes);

        if (preg_match("#Total:\s+(\D{3})\s+(\d+[.]\d+)#", $node, $m)) {
            $h->price()
                ->total(PriceHelper::cost($m[2])) //str_replace(',', '', $m[2])
                ->currency($this->normalizeCurrency($m[1]));
        }

        return $email;
    }

    private function normalizeDate($str)
    {
        if (preg_match('/(\d+).(\d+).(\d+)/', $str, $m)) {
            $date = $m[2] . '.' . $m[1] . '.' . $m[3];
        }

        return strtotime($date);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/This booking can be cancelled free of charge until\s(.+)\s+This booking/i", $cancellationText, $m)) {
            $h->booked()->deadline($this->normalizeDate($m[1]));
        }
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
