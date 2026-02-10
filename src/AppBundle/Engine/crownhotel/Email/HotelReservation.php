<?php

namespace AwardWallet\Engine\crownhotel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "crownhotel/it-536291642.eml, crownhotel/it-542634337.eml, crownhotel/it-539545186-cancellation.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'       => ['Confirmation number:', 'Confirmation Number:'],
            'checkIn'          => ['Check-in date:', 'Check-In date:'],
            'checkOut'         => ['Check-out date:', 'Check-Out date:'],
            'sincerely'        => ['Sincerely,', 'Until then,', 'See you soon,', 'Regards,'],
            'roomDetails'      => ['Room details:', 'Room Details:'],
            'roomRate'         => ['Room rate (per night):', 'Room Rate (per night):', 'Room rate (Per Night):', 'Room Rate (Per Night):'],
            'cancelledPhrases' => ['see the details of your cancellation below'],
        ],
    ];

    private $subjects = [
        'en' => ['Reservation Confirmation', 'Reservation cancellation', 'Reservation cancelation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@crownhotels.com.au') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".crownhotels.com.au/") or contains(@href,"www.crownhotels.com.au") or contains(@href,"email.crownhotels.com.au")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Sincerely, Crown")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('HotelReservation' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.;\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang    |    Aancha; Sehgal
        ];

        $h = $email->add()->hotel();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0
            || $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cancellation Date:'))}] ]")->length > 0
        ) {
            $h->general()->cancelled();
        }

        $hotelName = null;
        $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->eq($this->t('sincerely'))}]/following::text()[normalize-space()][1]");

        if ($hotelName_temp && (
            $this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1
            || stripos($hotelName_temp, 'Crown') !== false)
        ) {
            $hotelName = $hotelName_temp;
        }

        $xpathContacts = "//*[ *[2] and *[normalize-space()][last()][{$this->starts($this->t('PHONE:'))}] ]";
        $address = implode(' ', $this->http->FindNodes($xpathContacts . "/*[normalize-space()][ following-sibling::*[{$this->starts($this->t('PHONE:'))}] ]"));
        $phone = $this->http->FindSingleNode($xpathContacts . "/*[{$this->starts($this->t('PHONE:'))}]", null, true, "/^{$this->opt($this->t('PHONE:'))}[:\s]*({$patterns['phone']})$/");

        $h->hotel()->name($hotelName)->address($address)->phone($phone);

        $traveller = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Name:'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['travellerName']}$/u");
        $h->general()->traveller($traveller, true);

        $confirmation = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//*[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $dateCheckInVal = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/*[normalize-space()][2]", null, true, "/^.*\b\d{4}\b.*$/");
        $dateCheckOutVal = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]/*[normalize-space()][2]", null, true, "/^.*\b\d{4}\b.*$/");

        $dateCheckIn = strtotime($dateCheckInVal);
        $dateCheckOut = strtotime($dateCheckOutVal);

        $timeCheckIn = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Check-in time:'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['time']}/") ?? '00:00';
        $timeCheckOut = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Check-out time:'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['time']}/") ?? '00:00';

        if ($dateCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        }

        if ($dateCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        }

        $room = $h->addRoom();

        $roomType = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('roomDetails'))}] ]/*[normalize-space()][2]");
        $room->setType($roomType);

        $roomRates = [];
        $roomRateRows = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('roomRate'))}] ]/*[normalize-space()][2]/descendant::*/tr[count(*[normalize-space()])=2]");

        foreach ($roomRateRows as $rRow) {
            // it-542634337.eml
            $rVal = $this->http->FindSingleNode("*[normalize-space()][2]", $rRow);

            if (preg_match('/^[^\-\d)(]+[ ]*\d[,.‘\'\d ]*$/u', $rVal)
                || preg_match('/^\d[,.‘\'\d ]*[ ]*[^\-\d)(]+?$/u', $rVal)
            ) {
                // $1,269.00    |    AUD 639.00    |    639.00 AUD
                $roomRates[] = $rVal;
            } else {
                $roomRates = [];

                break;
            }
        }

        if ($roomRateRows->length === 0) {
            // it-536291642.eml
            $roomRateTexts = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('roomRate'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]");

            foreach ($roomRateTexts as $rText) {
                $rVal = $this->http->FindSingleNode(".", $rText, true, "/^.+\b\d{4}[-\s]+(.*\d.*)$/")
                    ?? $this->http->FindSingleNode(".", $rText, true, "/^[^\-\d)(]{1,3}[ ]*\d[,.‘\'\d ]*$/u")
                    ?? $this->http->FindSingleNode(".", $rText, true, "/^\d[,.‘\'\d ]*[ ]*[^\-\d)(]{1,3}$/u")
                ;

                if (preg_match('/^[^\-\d)(]+[ ]*\d[,.‘\'\d ]*$/u', $rVal)
                    || preg_match('/^\d[,.‘\'\d ]*[ ]*[^\-\d)(]+?$/u', $rVal)
                ) {
                    // $1,269.00    |    AUD 639.00    |    639.00 AUD
                    $roomRates[] = $rVal;
                } else {
                    $roomRates = [];

                    break;
                }
            }
        }

        $room->setRate(implode(', ', $roomRates));

        $cancellation = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Cancellation policy:'))}] ]/*[normalize-space()][2]");
        $h->general()->cancellation($cancellation, false, true);

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
