<?php

namespace AwardWallet\Engine\classic\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "classic/it-36823445.eml, classic/it-41931540.eml";

    public static $dictionary = [
        "en" => [
            'Booking Number:' => ['Booking Number:', 'Confirmation Number:'],
        ],
    ];

    private $detectFrom = "classichotels.com";

    private $detectSubject = [
        "en" => " Confirmation:", //Hotel Carmel Confirmation: R3HL5TRDB
    ];
    private $detectCompany = ["classichotels.com", "Hotel Carmel"];
    private $detectBody = [
        "en" => "Please review your reservation details to the right",
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = html_entity_decode($this->http->Response["body"]);
        //		foreach($this->detectBody as $lang => $dBody){
        //			if (stripos($body, $dBody) !== false) {
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

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
        $body = $this->http->Response['body'];

        if ($this->striposAll($body, $this->detectCompany) === false) {
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

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $confirmation = $this->http->FindSingleNode("//td[not(.//td) and {$this->starts($this->t('Booking Number:'))}][1]");

        if (preg_match("/^(.+?)\s*:+\s*(.{5,})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $cancellationTexts = $this->http->FindNodes("//td[not(.//td) and {$this->starts($this->t('Cancellation Policy'))}][1]/descendant::text()[normalize-space() and not({$this->starts('*')})]");
        $cancellation = $this->re('/:\s*(.+)/', implode(' ', $cancellationTexts));

        $h->general()
            ->traveller($this->td($this->t("Guest Name:")), true)
            ->cancellation($cancellation);

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->starts('Thank you for choosing ') . "]", null, true, "#Thank you for choosing (.{3,}?)\.#"))
        ;

        if (!empty($h->getHotelName())) {
            $contacts = implode("\n",
                $this->http->FindNodes("(//text()[{$this->eq($h->getHotelName())} or {$this->contains($this->t('This email was sent by'))} and {$this->contains($h->getHotelName())}]/ancestor::td[1][ descendant::a[{$this->contains(['maps.google', 'classichotels.com'], '@href')} or {$this->contains('classichotels.com', '@title')}] ])[1]/descendant::text()[normalize-space()]")
            );

            if (empty($contacts)) {
                $contacts = implode("\n",
                    $this->http->FindNodes("(//text()[{$this->eq($h->getHotelName())} or {$this->contains($this->t('This email was sent by'))} and {$this->contains($h->getHotelName())}]/ancestor::td[1][ descendant::a ])[1]/descendant::text()[normalize-space()]")
                );
            }

            if (preg_match("#^\s*{$h->getHotelName()}\s+(?<address>[\S\s]{3,}?)\s+Reservations:\s*(?<phone>[\d\- \(\)A-Z]{5,})(?:\n|$)#", $contacts, $m)) {
                $h->hotel()
                    ->address(preg_replace('/\s+/', ' ', $m['address']))
                    ->phone($m['phone']);
            } elseif (preg_match("#{$h->getHotelName()}\s+(?<address>[\S\s]{3,}?)\s+We respect your right to privacy#", $contacts, $m)) {
                $h->hotel()->address(preg_replace('/\s+/', ' ', $m['address']));
            } elseif (preg_match("#{$h->getHotelName()}\s+(?<address>[\S\s]{3,}? \w+ \d{5,})#", $contacts, $m)) {
                //This email was sent by:Arizona Grand Resort & Spa
                //8000 S. Arizona Grand Parkway Phoenix, Arizona 85044
                $h->hotel()->address(preg_replace('/\s+/', ' ', $m['address']));
            }
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->td("Arrival Date:")))
            ->checkOut($this->normalizeDate($this->td("Departure Date:")))
        ;

        $checkInTime = $this->http->FindSingleNode("//text()[" . $this->contains("Check-in time is") . "]", null, true, "#Check-in time is\s*(.+?)(?:[.,]|$)#");

        if (empty($checkInTime)) {
            $checkInTime = $this->td(["Check-in time:"]);
        }

        if (!empty($checkInTime) && !empty($h->getCheckInDate())) {
            $h->booked()
                ->checkIn(strtotime($checkInTime, $h->getCheckInDate()));
        }
        $checkOutTime = $this->http->FindSingleNode("//text()[" . $this->contains("Check-out time is") . "]", null, true, "#Check-out time is\s*(.+?)(?:[.,]|$)#");

        if (empty($checkOutTime)) {
            $checkOutTime = $this->td(["Check-out time:"]);
        }

        if (!empty($checkOutTime) && !empty($h->getCheckOutDate())) {
            $h->booked()
                ->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()));
        }

        // Rooms
        $room = $h->addRoom()->setType($this->td(["Requested Room Category:", "Requested Room Type:"]));

        // Rate
        $rate = $this->td(["Room Tax:"]);

        if (!empty($rate)) {
            $room->setRate($rate);
        }

        $this->detectDeadLine($h);

        // Price
        $total = $this->td(["Total Room", "Room Subtotal:"]);

        if ($total !== null) {
            $h->price()
                ->total($this->amount($total))
                ->currency($this->currency($total), false, true);
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return false;
        }

        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        if (preg_match("#Reservations must be cancelled \((?<prior>\d{1,3})\) days? prior to arrival to avoid a penalty#i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('+' . $m['prior'] . ' days', '00:00');

            return true;
        } elseif (preg_match("#Cancel+ by (?<hour>{$patterns['time']}) local hotel time at least (?<prior>\d{1,3}) days? prior to arrival to avoid a \d+ nights? cancel+ penalty#", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('+' . $m['prior'] . ' days', $m['hour']);

            return true;
        }
        $h->booked()
            ->parseNonRefundable("This Advance Purchase reservation is non-refundable.#");

        return false;
    }

    private function striposAll($haystack, $needles)
    {
        if (is_string($needles)) {
            return stripos($haystack, $needles);
        }

        if (is_array($needles)) {
            foreach ($needles as $needle) {
                if (stripos($haystack, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
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
            //			"#^\s*(\d{1,2}\.\d{1,2}\.\d{4})\s*\(.* (\d+:\d+) Uhr\)\s*$#",//25.11.2018 (Check-in ab 14:00 Uhr)
        ];
        $out = [
            //			"$1 $2",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
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

    private function td($field)
    {
        $rule = $this->starts($field);

        return $this->http->FindSingleNode("(//td[not(.//td) and {$rule}])[1]", null, true, "#:\s*(.+)#");
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
