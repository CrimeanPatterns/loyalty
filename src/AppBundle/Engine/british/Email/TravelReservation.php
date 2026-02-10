<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelReservation extends \TAccountChecker
{
    public $mailFiles = "british/it-1586429.eml, british/it-1586434.eml, british/it-1680309.eml, british/it-1829586.eml, british/it-1843135.eml, british/it-1858690.eml, british/it-2015073.eml, british/it-2197065.eml";

    private $subjects = [
        'en' => ['travel Reservation'],
    ];

    private $langDetectors = [
        'en'  => ['Check-out:', 'Drop-off:'],
        'en2' => ['Pickup:', 'Dropoff:'],
    ];

    private $lang = '';

    private static $dict = [
        'en' => [
            'Booking reference' => ['Booking Number', 'Booking reference'],
            'Airport Pick-up:'  => ['Airport Pick-up:', 'Pickup:'],
            'Airport Drop-off:' => ['Airport Drop-off:', 'Dropoff:'],
            'Payments received' => ['Payments received', 'PAYMENTS RECEIVED'],
        ],
    ];

    private $patterns = [
        'dateTime'      => '(?<date>.{6,}?)(?:\s+(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?))?', // Wednesday 26/11/14 5:00PM    |    Sunday 21/09/14
        'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'phone'         => '[+)(\d][-.\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52    |    713.680.2992
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ba.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'British Airways') === false) {
            return false;
        }

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
            $this->http->XPath->query('//a[contains(@href,"//www.britishairways.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(.),"Yours sincerely,British Airways") or contains(normalize-space(.),"during your stay please contact the British Airways") or contains(.,"@ba.com")]')->length === 0
            && $this->http->XPath->query("//img[contains(@src, 'britishairways')]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang() || $this->assignLang($parser->getHTMLBody());
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang() && !$this->assignLang($this->http->Response['body'])) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('TravelReservation' . ucfirst($this->lang));

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
        $generalFields = [];

        $accountIds = $this->http->XPath->query("//tr[contains(., 'Loyalty Member Id') and not(.//tr)]/ancestor::*[1]/following-sibling::*/tr/td[normalize-space(.)][2]");

        foreach ($accountIds as $accountId) {
            $generalFields['awards'][] = $this->http->FindSingleNode('following-sibling::td[1]', $accountId);
            $generalFields['accounts'][] = trim($accountId->nodeValue);
        }

        $generalFields['clientName'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+({$this->patterns['travellerName']})\s*(?:[,.!]|$)/m");

        $bookingReference = $this->http->FindSingleNode("//td[{$this->eq($this->t('Booking reference'))}]/following-sibling::*[normalize-space(.)][1]", null, true, '/^[A-Z\d][A-Z\d\s]{3,}[A-Z\d]$/');
        $bookingReferenceTitle = $this->http->FindSingleNode("//td[{$this->eq($this->t('Booking reference'))}]");
        $email->ota()->confirmation(str_replace(' ', '', $bookingReference), preg_replace('/\s*:\s*$/', '', $bookingReferenceTitle));

        $bookingDate = $this->http->FindSingleNode("//td[{$this->eq($this->t('Booking date:'))}]/following-sibling::*[normalize-space(.)][1]");

        if ($bookingDate) {
            $generalFields['bookingDate'] = $this->normalizeDate($bookingDate);
        }

        $paymentsReceived = $this->http->FindSingleNode("//td[{$this->eq($this->t('Payments received'))}]/following-sibling::*[normalize-space(.)][1]");

        if (preg_match("/\b(\d[,.\'\d]*\s*Avios)/i", $paymentsReceived, $m)) {
            // 10,100 Avios
            $email->price()->spentAwards($m[1]);
        }

        if (preg_match("/\b(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)$/", $paymentsReceived, $matches)) {
            // USD 20.00
            $email->price()
                ->currency($matches['currency'])
                ->total($this->normalizeAmount($matches['amount']))
            ;
        }

        $cancelling1Items = $this->http->FindNodes("//*[{$this->eq($this->t('Cancelling your booking'))}]/following-sibling::*[normalize-space(.)][1]/li[normalize-space(.)]");
        $generalFields['cancellation'] = implode(' ', $cancelling1Items);
        $cancelling2Items = $this->http->FindNodes("//*[{$this->eq($this->t('Cancellation fees'))}]/following-sibling::*[normalize-space(.)][1]/li[normalize-space(.)]", null, '/.*cancel.*/is');
        $cancelling2Items = array_filter($cancelling2Items);

        if (count($cancelling2Items)) {
            $generalFields['cancellation'] .= ' ' . implode(' ', $cancelling2Items);
        }

        $hotels = $this->http->XPath->query("//text()[{$this->contains($this->t('Check-out:'))}]/ancestor::tr[ preceding-sibling::tr/descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Room'))}] ][1]");

        foreach ($hotels as $hotel) {
            $this->parseHotel($email, $hotel, $generalFields);
        }

        $cars = $this->http->XPath->query("//text()[{$this->contains($this->t('Pickup location:'))}]/ancestor::tr[ preceding-sibling::tr/descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Car'))}] ][1]");

        foreach ($cars as $car) {
            $this->parseCar($email, $car, $generalFields);
        }
    }

    private function parseHotel(Email $email, $root, $generalFields)
    {
        $h = $email->add()->hotel();

        if (!empty($generalFields['bookingDate'])) {
            $h->general()->date2($generalFields['bookingDate']);
        }

        $confirmation = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space(.)][1]", $root);

        if (preg_match("/({$this->opt($this->t('Confirmation:'))})\s*([A-Z\d][A-Z\d\s-]{3,}[A-Z\d])\b/", $confirmation, $m)) {
            $h->general()->confirmation(str_replace(' ', '', $m[2]), preg_replace('/\s*:\s*$/', '', $m[1]));
        }

        $xpathFragment1 = "descendant::tr[ not(.//tr) and descendant::img and (descendant::img[contains(@src,'star')] or string-length(normalize-space(.))<2) ]";

        $hotelName = $this->http->FindSingleNode($xpathFragment1 . "/preceding-sibling::tr[normalize-space(.)][1]", $root);
        $h->hotel()->name($hotelName);

        $addressTexts = $this->http->FindNodes($xpathFragment1 . "/following-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)]", $root);
        $addressText = implode(' ', $addressTexts);

        if (preg_match("/^(.{3,}?)\s+({$this->patterns['phone']})$/", $addressText, $m)) {
            $h->hotel()
                ->address($m[1])
                ->phone($m[2])
            ;
        } else {
            $h->hotel()->address($addressText);
        }

        $room = $h->addRoom();

        $roomDesc = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Room description:'))}]/ancestor::td[1]", $root, true, "/^{$this->opt($this->t('Room description:'))}\s*(.+)/");
        $room->setDescription($roomDesc, false, true);

        $roomType = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Room type:'))}]/following-sibling::*[normalize-space(.)][1]", $root);
        $room->setType($roomType);

        $dateCheckIn = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Check-in:'))}]/following-sibling::*[normalize-space(.)][1]", $root);

        if (preg_match("/^{$this->patterns['dateTime']}$/", $dateCheckIn, $m)) {
            $dateCheckInNormal = $this->normalizeDate($m['date']);

            if ($dateCheckInNormal) {
                $h->booked()->checkIn2($dateCheckInNormal . (empty($m['time']) ? '' : ' ' . $m['time']));
            }
        }

        $dateCheckOut = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Check-out:'))}]/following-sibling::*[normalize-space(.)][1]", $root);

        if (preg_match("/^{$this->patterns['dateTime']}$/", $dateCheckOut, $m)) {
            $dateCheckOutNormal = $this->normalizeDate($m['date']);

            if ($dateCheckOutNormal) {
                $h->booked()->checkOut2($dateCheckOutNormal . (empty($m['time']) ? '' : ' ' . $m['time']));
            }
        }

        $occupants = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Occupants:'))}]/following-sibling::*[normalize-space(.)][1]", $root);

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $occupants, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/i", $occupants, $m)) {
            $h->booked()->kids($m[1]);
        }

        $guestNames = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Occupants:'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]/td[2]", $root, "/^({$this->patterns['travellerName']})(?:\s*\({$this->opt($this->t('age'))}:\s*\d{1,3}\))?$/i");
        $guestNames = array_filter($guestNames);

        if (count($guestNames) === 0 && !empty($generalFields['clientName'])) {
            $guestNames = [$generalFields['clientName']];
        }
        $h->general()->travellers($guestNames);

        if (!empty($generalFields['cancellation'])) {
            $h->general()->cancellation($generalFields['cancellation']);

            if (
                preg_match("/Cancellations made outside of\s*(?<prior>\d{1,3})\s*hours?\s*to travel will be charged a cancellation fee/", $generalFields['cancellation'], $m) // en
            ) {
                $h->booked()->deadlineRelative($m['prior'] . ' hours');
            }
        }
    }

    private function parseCar(Email $email, $root, $generalFields)
    {
        $car = $email->add()->rental();

        if (!empty($generalFields['awards'])) {
            foreach ($generalFields['awards'] as $award) {
                $car->program()
                    ->earnedAwards($award);
            }
        }

        if (!empty($generalFields['accounts'])) {
            $car->program()
                ->accounts($generalFields['accounts'], false);
        }

        if (!empty($generalFields['clientName'])) {
            $car->general()->traveller($generalFields['clientName']);
        }

        if (!empty($generalFields['bookingDate'])) {
            $car->general()->date2($generalFields['bookingDate']);
        }

        $confirmation = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space(.)][1]", $root);

        if (preg_match("/({$this->opt($this->t('Confirmation:'))})\s*([A-Z\d][A-Z\d\s]{3,}[A-Z\d])\b/", $confirmation, $m)) {
            $car->general()->confirmation(str_replace(' ', '', $m[2]), preg_replace('/\s*:\s*$/', '', $m[1]));
        }

        $company = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Car type:'))}]/ancestor::tr[1]/preceding-sibling::*/descendant::img/@alt", $root);
        $car->extra()->company($company);

        $carType = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Car type:'))}]/following-sibling::*[normalize-space(.)][1]", $root);

        if (preg_match("/^(.{3,}) - (.{3,})$/", $carType, $m)) {
            // Compact Group B - Ford Focus or similar
            $car->car()
                ->type($m[1])
                ->model($m[2])
            ;
        } else {
            $car->car()->type($carType);
        }

        $datePickUp = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Airport Pick-up:'))}]/following-sibling::*[normalize-space(.)][1]", $root);

        if (preg_match("/^{$this->patterns['dateTime']}$/", $datePickUp, $m)) {
            $datePickUpNormal = $this->normalizeDate($m['date']);

            if ($datePickUpNormal) {
                $car->pickup()->date2($datePickUpNormal . ' ' . (empty($m['time']) ? '' : ' ' . $m['time']));
            }
        }

        $dateDropOff = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Airport Drop-off:'))}]/following-sibling::*[normalize-space(.)][1]", $root);

        if (preg_match("/^{$this->patterns['dateTime']}$/", $dateDropOff, $m)) {
            $dateDropOffNormal = $this->normalizeDate($m['date']);

            if ($dateDropOffNormal) {
                $car->dropoff()->date2($dateDropOffNormal . ' ' . (empty($m['time']) ? '' : ' ' . $m['time']));
            }
        }

        $locationPickUp = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Pickup location:'))}]/following-sibling::*[normalize-space(.)][1]", $root);
        $car->pickup()->location($locationPickUp);

        $locationDropOff = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Dropoff location:'))}]/following-sibling::*[normalize-space(.)][1]", $root);

        if ($locationDropOff) {
            $car->dropoff()->location($locationDropOff);
        } else {
            $car->dropoff()->noLocation();
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^(?:[^\d\W]{2,}\s+)?(\d{1,2})\/(\d{1,2})\/(\d{2})\s*$/u', $string, $matches)) {
            // 24/11/14    |    Saturday 23/02/19
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
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
                if (empty($text) && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }
}
