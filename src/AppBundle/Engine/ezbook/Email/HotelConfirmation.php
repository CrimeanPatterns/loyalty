<?php

namespace AwardWallet\Engine\ezbook\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "ezbook/it-33168280.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "ezbookbyabc.com";
    private $detectSubject = [
        "en" => "Hotel Reservation Confirmation",
    ];

    private $detectCompany = 'ezBOOK';
    private $detectBody = [
        "en" => "Hotel details",
    ];
    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        $body = $this->http->Response['body'];
//        foreach ($this->detectBody as $lang => $detectBody){
//            if (strpos($body, $detectBody) !== false) {
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
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (strpos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
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
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('ezBOOK Confirmation #')) . "]/following::text()[normalize-space(.)][1]"), "ezBOOK Confirmation #");

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes("//text()[" . $this->starts($this->t('GuestName:')) . "]", null, "#:\s*(?:Mr\s+|Mrs\s+)?(.+)#"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Booking date')) . "]/following::text()[normalize-space(.)][1]")))
            ->status($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Booking status')) . "]/following::text()[normalize-space(.)][1]"))
            ->cancellation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Cancellation policy')) . "]/following::div[normalize-space(.)][1]"))
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Hotel details')) . "]/following::text()[" . $this->eq($this->t('Name')) . "][1]/following::text()[normalize-space(.)][1]"))
            ->address($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Hotel details')) . "]/following::text()[" . $this->eq($this->t('Address')) . "][1]/following::text()[normalize-space(.)][1]"))
            ->phone($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Hotel details')) . "]/following::text()[" . $this->eq($this->t('Phone')) . "][1]/following::text()[normalize-space(.)][1][not(contains(., 'Check-in'))]"), false, true)
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Hotel details')) . "]/following::text()[" . $this->eq($this->t('Check-in')) . "][1]/following::text()[normalize-space(.)][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Hotel details')) . "]/following::text()[" . $this->eq($this->t('Check-out')) . "][1]/following::text()[normalize-space(.)][1]")))
        ;

        // Rooms
        $h->addRoom()
            ->setType(implode("|", $this->http->FindNodes("//text()[" . $this->eq($this->t('Room type')) . "]/following::text()[normalize-space(.)][1]")))
        ;

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total charges for room")) . "]/following::text()[normalize-space(.)][1]");

        if (preg_match("#\b(\d[\d.,]*) ([A-Z]{3})#", $total, $m)) {
            $h->price()
                ->total(str_replace(',', '', $m[1]))
                ->currency($m[2])
            ;
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[\w|\D]+\s+(\d+)\s+(\D+)\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
