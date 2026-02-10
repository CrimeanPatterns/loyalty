<?php

namespace AwardWallet\Engine\fairmont\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HotelReservations extends \TAccountChecker
{
    public $mailFiles = "fairmont/it-28633156.eml";
    private $subjects = [
        'en' => ['The Savoy'],
    ];
    private $langDetectors = [
        'en' => ['Guest Name'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Thank you for choosing' => ['Thank you for choosing', 'Thank you for your reservation at'],
            'Nightly Room Rate'      => ['Nightly Room Rate', 'Room Rate'],
            'Confirmation Number'    => ['Confirmation Number', 'Confirmation No.'],
            'Address & Directions'   => ['Address & Directions', 'Address:'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@fairmont.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        $condition1 = $this->http->XPath->query('//node()[contains(.,"@fairmont.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.fairmont.com/")]')->length === 0;
        $con3 = $this->http->XPath->query("//node()[contains(., 'hardrockhotel')]")->length === 0;
        $con4 = $this->http->XPath->query("//a[contains(@href, 'dangleterre.com')]")->length === 0;

        if ($condition1 && $condition2 && $con3 && $con4) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        if (0 < $this->http->XPath->query("//node()[contains(., 'hardrockhotel')]")->length) {
            $email->setProviderCode('hardrock');
        }
        $this->parseEmail($email);
        $email->setType('HotelReservations' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailProviders()
    {
        return ['hardrock', 'fairmont'];
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
        $h = $email->add()->hotel();

        // hotelName
        $intro = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you for choosing'))}]", null,
            true, "/{$this->opt($this->t('Thank you for choosing'))}\s*(.{3,}?)[,\.]/");

        if ($intro && $this->http->XPath->query('//node()[contains(normalize-space(.),"' . $intro . '")]')->length > 1) {
            $h->hotel()->name($intro);
        } elseif ($name = $this->http->FindSingleNode("//text()[{$this->contains('all set and the details of your stay with us at')}][1]",
            null, true, "/You're all set and the details of your stay with us at\s+(.+) are below/i")) {
            $h->hotel()->name($name);
        }

        // travellers
        $guest = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Name'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]",
            null, true, "/^([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])$/");
        $h->general()->traveller($guest);

        $patterns['time'] = '\d{1,2}(?:[.:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?'; // 4:19PM    |    2:00 p.m.    |    3pm    |    3.00pm

        // checkInDate
        $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival Date'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");
        $h->booked()->checkIn2($dateCheckIn);
        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in is from'))}]", null,
            true, "/{$this->opt($this->t('Check-in is from'))}\s*({$patterns['time']})/");

        if ($timeCheckIn && !empty($h->getCheckInDate())) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
        }

        // checkOutDate
        $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure Date'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");
        $h->booked()->checkOut2($dateCheckOut);
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('check-out is at'))}]", null,
            true, "/{$this->opt($this->t('check-out is at'))}\s*({$patterns['time']})/");

        if ($timeCheckOut && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
        }

        $r = $h->addRoom();

        // r.type
        $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");
        $r->setType($roomType);

        $rate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Nightly Room Rate'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]");

        if ($rate) {
            $r->setRate($rate);
        }

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation Number'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]",
            null, true, '/^([A-Z\d]{5,})$/');
        $h->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));

        // cancellation
        $ratePolicies = $this->http->FindSingleNode("//text()[{$this->contains($this->t('cancel your stay'))}]");

        if ($ratePolicies) {
            $ratePolicies = preg_replace('/(\d)\.(\d{2})/', '$1:$2', $ratePolicies); // 6.00pm  ->  6:00pm
            $ratePoliciesParts = preg_split('/[.]+\s*\b/', $ratePolicies);
            $ratePoliciesParts = array_filter($ratePoliciesParts, function ($item) {
                return stripos($item, 'cancel') !== false;
            });
            $cancellationText = implode('. ', $ratePoliciesParts);
            $h->general()->cancellation($cancellationText);
        } elseif ($cancel = $this->http->FindSingleNode("//p[normalize-space(.)='Cancellation and Early Departure Policies']/following-sibling::p[1]")) {
            $h->general()->cancellation($cancel);
        } elseif ($cancel = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Please note that cancellations or modifications are applicable free of charge until')][1]")) {
            $h->general()->cancellation($cancel);
        }

        // deadline
        if (
        preg_match("/Should you need to cancel your stay, please contact .{3,}? before\s*(?<hour>{$patterns['time']})\s*on the day prior to arrival to avoid any cancellation or no show charges/",
            $h->getCancellation(), $m) // en
        ) {
            $h->booked()->deadlineRelative('1 day', $m['hour']);
        } elseif (preg_match('/Our cancellation policy is (\d{1,2} [ap]m) (\d{1,2})\-days prior to your arrival/',
            $h->getCancellation(), $m)) {
            $h->booked()->deadlineRelative("{$m[2]} days", $m[1]);
        } elseif (preg_match('/Please note that cancellations or modifications are applicable free of charge until (\d{1,2} days) prior to arrival/',
            $h->getCancellation(), $m)) {
            $h->booked()->deadlineRelative($m[1]);
        }

        $patterns['phone'] = '[+)(\d][-.\s\d)(]{5,}[\d)(]'; // +377 (93) 15 48 52    |    713.680.2992

        $adults = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Persons'))}]/ancestor::td[1]/following-sibling::*[normalize-space(.)][last()]",
            null, true, '/\d{1,2}/');

        if (!empty($adults)) {
            $h->booked()->guests($adults);
        }
        // phone
        // fax
        // address
        $xpathFragmentContacts = "//text()[contains(.,'fairmont.com')]/ancestor::tr[1]";
        $phone = $this->http->FindSingleNode($xpathFragmentContacts . "/descendant::a[{$this->eq($this->t('Telephone'), '@title')}]",
            null, true, "/({$patterns['phone']})$/");

        if (!$phone) {
            $phone = $this->http->FindSingleNode("//text()[{$this->starts('Your Dedicated Hard Rock Concierge')}]/following-sibling::text()[normalize-space(.)][1]",
                null, true, '/([\d\-]+)/');
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[contains(.,'Phone')]/following::text()[normalize-space(.)][1]",
                null, true, '/[\d\-\+ ]+/');
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("(" . $xpathFragmentContacts . "/descendant::a[1])[1]",
                null, true, "/({$patterns['phone']})$/");
        }

        $fax = $this->http->FindSingleNode($xpathFragmentContacts . "/descendant::text()[{$this->starts($this->t('F.'))}]",
            null, true, "/^{$this->opt($this->t('F.'))}\s*({$patterns['phone']})$/");

        if (empty($fax)) {
            $fax = $this->http->FindSingleNode("//text()[contains(.,'Fax')]/following::text()[normalize-space(.)][1]",
                null, true, '/[\d\-\+ ]+/');
        }
        $address = $this->http->FindSingleNode($xpathFragmentContacts . "/descendant::a[{$this->eq($this->t('Address & Directions'), '@title')}]");

        if (empty($address) && !empty($h->getHotelName())) {
            $address = implode(', ',
                $this->http->FindNodes("//text()[starts-with(normalize-space(.), 'Hotel {$h->getHotelName()}')][1]/following-sibling::node()[string-length(normalize-space(.)) > 2][position() < 3]"));
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Address & Directions')) . "]",
                null, true, '/' . $this->opt($this->t('Address & Directions')) . '(.+)/');
        }

        if ($address) {
            $h->hotel()
                ->address(preg_replace("/^{$this->opt($this->t('A.'))}\s*/", '', $address));
        } elseif (!empty($h->getHotelName()) && !empty($h->getCheckInDate())) {
            $h->hotel()
                ->noAddress();
        }
        $h->hotel()
            ->phone(preg_replace("/^{$this->opt($this->t('T.'))}\s*/", '', $phone))
            ->fax($fax, true, true);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
