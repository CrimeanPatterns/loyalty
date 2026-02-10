<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourOnlineHotelReservation extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-33593103.eml";

    private $subjects = [
        'en' => ['Your Online Hotel Reservation'],
    ];

    private $langDetectors = [
        'en' => ['Check Out Date:', 'Check-Out Date:'],
    ];

    private $lang = '';

    private static $dict = [
        'en' => [
            'Confirmation Number:' => ['Confirmation Number:', 'Confirmation Number :'],
            'Hotel:'               => ['Hotel:', 'Hotel :'],
            'Guest Name:'          => ['Guest Name:', 'Guest Name :'],
            'Room Type:'           => ['Room Type:', 'Room Type :'],
            'contactFieldNames'    => ['Reservation Centre', 'Hotel Phone', 'Email', 'Get Directions'],
        ],
    ];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        // Detect Provider
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Disney Explorers Lodge') === false) {
            return false;
        }

        // Detect Format
        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query('//a[contains(@href,"//www.hongkongdisneyland.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Greetings from Hong Kong Disneyland Resort") or contains(normalize-space(),"Thank you for making a reservation at Disney Explorers Lodge") or contains(.,"@hongkongdisneyland.com") or contains(.,"www.hongkongdisneyland.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() || $this->assignLang($parser->getHTMLBody());
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang() && !$this->assignLang($this->http->Response['body'])) {
            $this->logger->notice("Can't determine a language!");
        }

        $this->parseEmail($email);
        $email->setType('YourOnlineHotelReservation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $xpathFragmentCell = '/ancestor::td[1]/following-sibling::*[normalize-space()]';

        $h = $email->add()->hotel();

        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]" . $xpathFragmentCell, null, true, '/^([A-Z\d]{5,})$/');
        $h->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));

        $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel:'))}]" . $xpathFragmentCell);
        $h->hotel()->name($hotelName);

        $guestName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name:'))}]" . $xpathFragmentCell, null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $h->general()->traveller($guestName);

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]" . $xpathFragmentCell, null, true, "/^\b[-,.;!\d\s[:alpha:]]+$/u");
        $room->setType($roomType);

        $numberRooms = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Rooms'))}]" . $xpathFragmentCell, null, true, "/^\d{1,3}$/");
        $h->booked()->rooms($numberRooms);

        $numberAdults = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Adults'))}]" . $xpathFragmentCell, null, true, "/^\d{1,3}$/");
        $h->booked()->guests($numberAdults);

        $numberChildren = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Children'))}]" . $xpathFragmentCell, null, true, "/^\d{1,3}$/");
        $h->booked()->kids($numberChildren);

        $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check In Date'))}]" . $xpathFragmentCell);
        $h->booked()->checkIn2($dateCheckIn);
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check In Time'))}]" . $xpathFragmentCell);

        if (!empty($h->getCheckInDate()) && !empty($timeCheckIn)) {
            $h->booked()->checkIn(strtotime(preg_replace('/^After\s*/i', '', $timeCheckIn), $h->getCheckInDate()));
        }

        $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check Out Date'))}]" . $xpathFragmentCell);
        $h->booked()->checkOut2($dateCheckOut);
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check Out Time'))}]" . $xpathFragmentCell);

        if (!empty($h->getCheckOutDate()) && !empty($timeCheckOut)) {
            $h->booked()->checkOut(strtotime(preg_replace('/^Before\s*/i', '', $timeCheckOut), $h->getCheckOutDate()));
        }

        $xpathFragmentPrice = "//text()[{$this->eq($this->t('Price and Payment Summary'))}]";

        $payment = $this->http->FindSingleNode($xpathFragmentPrice . "/following::text()[{$this->starts($this->t('Overall Total'))}]" . $xpathFragmentCell);

        if (preg_match('/^(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)$/', $payment, $matches)) {
            // HKD 3,688.75
            $h->price()
                ->currency($matches['currency'])
                ->total($this->normalizeAmount($matches['amount']))
            ;
        }

        $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation Policy'))}]/following-sibling::tr[normalize-space()][1][{$this->contains(['Cancel', 'cancel'])}]");

        if ($cancellation) {
            $h->general()->cancellation($cancellation);
            $h->booked()->parseNonRefundable('No cancellation or amendment is allowed.');
        }

        if (!empty($h->getHotelName())) {
            $contactUsTexts = $this->http->FindNodes("//text()[{$this->eq($this->t('Contact Us'))}]/following::table[ descendant::text()[{$this->eq($h->getHotelName())}] ][1]/descendant::text()[normalize-space()]");
            $contactUsText = implode("\n", $contactUsTexts);

            // address
            if (preg_match("/{$this->opt($h->getHotelName())}\s+(.+?)\s+{$this->opt($this->t('contactFieldNames'))}/s", $contactUsText, $m)) {
                $h->hotel()->address(preg_replace('/\s+/', ' ', $m[1]));
            }

            // phone
            if (preg_match("/{$this->opt($this->t('Hotel Phone'))}\s*:?\s*([+)(\d][-.\s\d)(]{5,}[\d)(])/s", $contactUsText, $m)) {
                $h->hotel()->phone($m[1]);
            }
        }
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
