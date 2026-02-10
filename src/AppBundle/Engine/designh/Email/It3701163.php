<?php

namespace AwardWallet\Engine\designh\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3701163 extends \TAccountChecker
{
    public $mailFiles = "designh/it-26836627.eml, designh/it-44398376.eml, designh/it-47746948.eml";

    public static $dictionary = [
        'en' => [
            'Confirmation Number'               => ['Confirmation Number', 'CONFIRMATION NUMBER', 'Confirmation number', 'Booking Confirmation Number:'],
            'guests'                            => ['Travelers', 'TRAVELERS', 'Guest(s)', 'Guests'],
            'roomInfo'                          => ['Room Info', 'Room info', 'ROOM INFO'],
            'Hotel address and contact details' => ['Hotel address and contact details', 'Hotel Address and Contact Details', 'HOTEL ADDRESS AND CONTACT DETAILS'],
            'addressEnd'                        => ['Phone', 'Email'],
            'Phone'                             => ['Phone', 'PHONE'],
            'Email'                             => ['Email', 'EMAIL'],
            'Cancellation & Hotel Policies'     => ['Cancellation & Hotel Policies', 'Cancelation & Hotel Policies', 'Cancellation & hotel policies', 'Cancelation & hotel policies'],
        ],
    ];

    private $detectSubject = [
        'Booking confirmation',
        'Reservation Confirmation',
    ];

    private $lang = '';
    private $emailSubject;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@designhotels.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for booking with Design Hotels") or contains(.,"@designhotels.com")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->emailSubject = $parser->getHeader("subject");
        $this->parseHtml($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(static::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(static::$dictionary);
    }

    private function parseHtml(Email $email): void
    {
        $xpathNoEmpty3 = 'string-length(normalize-space())>3';

        $h = $email->add()->hotel();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))}]");

        if (preg_match("/({$this->opt($this->t('Confirmation Number'))})[\s:]+([A-Z\d]{5,})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        } elseif ($confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/')) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $h->general()->traveller($this->getField(["Guest Info", "Guest info", "GUEST INFO"]));
        $account = $this->getField(["Member number", "MEMBER NUMBER"], "#^\s*([A-Z\d]{5,})\s*$#");

        if (!empty($account)) {
            $h->program()->account($account, false);
        }

        $hotelContacts = implode(' ', $this->http->FindNodes("//text()[{$this->starts($this->t('Hotel address and contact details'))}]/ancestor::*[count(descendant::text()[$xpathNoEmpty3])>1][ descendant::text()[normalize-space()][1][{$this->starts($this->t('Hotel address and contact details'))}] ][1]/descendant::text()[normalize-space()][not({$this->starts($this->t('Hotel address and contact details'))})]"));

        if (empty($hotelContacts)) {
            // it-26836627.eml
            $hotelContacts = implode(' ', $this->http->FindNodes("//text()[{$this->eq($this->t('Hotel address and contact details'))}]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1][ count(descendant::text()[normalize-space()])>2 ]/descendant::text()[normalize-space()]"));
        }

        // Hotel
        $hotelName = null;

        if ((preg_match("/Booking confirmation\s+-\s+(.{3,}?)(?:,|$)/i", $this->emailSubject, $m)
                || preg_match("/Reservation Confirmation\s*\(.+?\)\s-\s(.+)\s-/i", $this->emailSubject, $m))
            && $this->http->XPath->query("//node()[{$this->contains($m[1])}]")->length > 0
        ) {
            $hotelName = $m[1];
        }

        if (!$hotelName) {
            $hotelName = $this->http->FindSingleNode("//table/descendant::tr[normalize-space()][1]/descendant-or-self::*[contains(@class,'hotel-name')]");
        }

        $address = preg_match("#^(.*?)\s+{$this->opt($this->t('addressEnd'))}#i", $hotelContacts, $m) ? $m[1] : null;

        if (empty($hotelName) && (!empty($address))) {
            $hotelName = $this->http->FindSingleNode("//text()[normalize-space()='Dates']/preceding::text()[normalize-space()][2]/ancestor::tr[1]");
        }

        $phone = preg_match("#{$this->opt($this->t('Phone'))}\s+(?:{$this->opt($this->t('Email'))}[ ]+[-A-z@. ]+)?(\+\d[-. \d)(]{5,}[\d)]) \\1#", $hotelContacts, $m) || preg_match("/{$this->opt($this->t('Phone'))}\s+([+(\d][-. \d)(]{5,}[\d)])/", $hotelContacts, $m) ? $m[1] : null;
        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone, false, true);

        // Booked
        $dates = $this->getField(["Dates", "DATES"]);

        if (!empty($dates) && preg_match("/(?<in>[[:alpha:]]{3,}\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]{3,})[ ]*[–-][ ]*(?<out>[[:alpha:]]{3,}\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]{3,}),?\s+(?<year>\d{4})/u", $dates, $m)) {
            // Jul 10 – Jul 13, 2020    |    Oct 4-Oct 6, 2018 (3 Days / 2 Nights)    |    23 Sep - 24 Sep 2020
            $h->booked()
                ->checkIn(strtotime($m['in'] . ' ' . $m['year']))
                ->checkOut(strtotime($m['out'] . ' ' . $m['year']))
            ;
        } elseif (!empty($dates) && preg_match("#from\s[A-z]+,\s([A-z]+)\s(\d{1,2}),\s(\d{4})\s-\sto\s[A-z]+,\s([A-z]+)\s(\d{1,2}),\s(\d{4})\s#u", $dates, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[2] . " " . $m[1] . " " . $m[3]))
                ->checkOut(strtotime($m[5] . " " . $m[4] . " " . $m[6]))
            ;
        }
        $guests = $this->getField($this->t('guests'), "#\b(\d{1,3})\s+Adult#i");
        $kids = $this->getField($this->t('guests'), "#\b(\d{1,3})\s+Child#i");
        $rooms = $this->getField($this->t('roomInfo'), "#\b(\d{1,3})\s+Room#i");

        if (empty($guests)) {
            $guests = $this->getField($this->t('guests'), "#\d+\s\((\d+)\s&\s\d+\)#i");
        }

        if (empty($kids)) {
            $kids = $this->getField($this->t('guests'), "#\d+\s\(\d+\s&\s(\d+)\)#i");
        }

        if (empty($rooms)) {
            $rooms = $this->getField($this->t('roomInfo'), "#(\d+),#i");
        }

        if (!empty($guests)) {
            $h->booked()
                ->guests($guests);
        }

        if (!empty($kids)) {
            $h->booked()
                ->kids($kids, false, true);
        }

        if (!empty($rooms)) {
            $h->booked()
                ->rooms($rooms);
        }

        $roomType = null;
        $roomInfo = $this->getField($this->t('roomInfo'));

        if (preg_match("/\b\d{1,3}\s+Room(?:s|\(s\))?,\s+(.+)/i", $roomInfo, $m)
            || preg_match("/^([^,]{2,})$/i", $roomInfo, $m)
        ) {
            // 1 room, Deluxe Room    |    Premier King
            $roomType = $m[1];
        }
        $roomRateType = $this->getField(["Rate Name", "Rate name", "RATE NAME"]);

        if ($roomType || $roomRateType) {
            $room = $h->addRoom();

            if ($roomType) {
                $room->setType($roomType);
            }

            if ($roomRateType) {
                $room->setRateType($roomRateType);
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space(.)='Total Price']/following::text()[normalize-space(.)][2]");

        if (preg_match("#:\s*(?<total>\d[,.\'\d]*)\s*(?<cur>[A-Z]{3})\b#", $total, $m)
            || preg_match("#\:?\s*(?<cur>[A-Z]{3})\s*(?<total>\d[,.\'\d]*)\b#", $total, $m)
        ) {
            $h->price()
                ->total($this->normalizeAmount($m['total']))
                ->currency($m['cur'])
            ;
        }

        $cancellation = implode(' ', $this->http->FindNodes("//text()[{$this->starts($this->t('Cancellation & Hotel Policies'))}]/ancestor::*[count(descendant::text()[$xpathNoEmpty3])>1][ descendant::text()[normalize-space()][1][{$this->starts($this->t('Cancellation & Hotel Policies'))}] ][1]/descendant::text()[normalize-space()][not({$this->starts($this->t('Cancellation & Hotel Policies'))})]"));

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);

            if (preg_match("/If (?i)cancell?ed up to (?<prior>\d+hrs?) before date of arrival, no fee will be charged\./", $cancellation, $m)
                || preg_match("/Reservations (?i)must be cancell?ed or modified (?<prior>\d{1,3} hours?) prior to arrival day\./", $cancellation, $m)
            ) {
                $m['prior'] = preg_replace('/^(\d+)\s*hrs?$/', '$1 hours', $m['prior']); // 48hrs
                $h->parseDeadlineRelative($m['prior'], null);
            } elseif (preg_match("#Cancellation up to (\d+ days) before the arrival date, considering midday local time – No penalty#", $cancellation, $m)) {
                $h->parseDeadlineRelative($m[1], '12:00');
            } elseif (preg_match("#Cancellation: free of charge until (\d{1,2})([ap]m) on day of arrival.#", $cancellation, $m)) {
                $h->parseDeadlineRelative("0 day", $m[1] . ':00 ' . $m[2]);
            }
            $h->parseNonRefundable('No change or cancellation is possible.');
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Confirmation Number']) || empty($phrases['Hotel address and contact details'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Confirmation Number'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Hotel address and contact details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function getField($str, $regex = null)
    {
        return $this->http->FindSingleNode("//text()[" . $this->eq($str) . "]/following::text()[normalize-space(.)][1]", null, true, $regex);
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
