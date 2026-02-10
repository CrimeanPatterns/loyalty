<?php

namespace AwardWallet\Engine\easemytrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelVoucher extends \TAccountChecker
{
    public $mailFiles = "easemytrip/it-206650766.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'   => ['Confirmation No'],
            'itineraryId'  => ['Itinerary id'],
            'checkIn'      => ['Check-in', 'Check-IN', 'CHECK-IN'],
            'checkOut'     => ['Check-out', 'Check-OUT', 'CHECK-OUT'],
            'hotelDetails' => ['HOTEL DETAILS', 'Hotel Details'],
            'priceSummary' => ['PRICE SUMMARY', 'Price Summary'],
            'primaryGuest' => ['PRIMARY GUEST', 'PRIMARY Guest'],
        ],
    ];

    private $subjects = [
        'en' => ['Hotel Confirmation'],
    ];

    private $detectors = [
        'en' => ['Hotel Voucher'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@easemytrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".easemytrip.com/") or contains(@href,"www.easemytrip.com") or contains(@href,"delivery.easemytrip.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"please write to us at hotels@easemytrip.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('HotelVoucher' . ucfirst($this->lang));

        $this->parseHotel($email);

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

    private function parseHotel(Email $email): void
    {
        $xpathNoDisplay = 'not(ancestor-or-self::*[contains(translate(@style," ",""),"display:none")])';
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*noon)?', // 4:19PM    |    2:00 p. m.    |    3pm    |    12 noon
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $bookingDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Date -'))}]", null, true, "/{$this->opt($this->t('Booking Date -'))}\s*(.*\d.*)$/");
        $h->general()->date2($bookingDate);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})\s+{$this->opt($this->t('is'))}\s+([-A-z\d]{5,})$/", $confirmation, $m)) {
            // Confirmation No is 2021111014a8ce4dab-8a36-4ce9-9980-233d0bfd6db9
            $h->general()->confirmation($m[2], $m[1]);
        }

        $itineraryId = $this->http->FindSingleNode("//text()[{$this->contains($this->t('itineraryId'))}]");

        if (preg_match("/({$this->opt($this->t('itineraryId'))})\s+{$this->opt($this->t('is'))}\s+([-A-z\d]{5,})$/", $itineraryId, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $xpathHotelName = "//tr[{$this->eq($this->t('hotelDetails'))}]/following::text()[normalize-space()][1][ ancestor::*[{$xpathBold}] ]";

        $hotelName = $this->http->FindSingleNode($xpathHotelName);
        $address = $this->http->FindSingleNode($xpathHotelName . "/following::text()[normalize-space()][1][not({$this->contains($this->t('Contact Details:'))})]");
        $phone = $this->http->FindSingleNode($xpathHotelName . "/following::text()[normalize-space()][2][{$this->contains($this->t('Contact Details:'))}]", null, true, "/{$this->opt($this->t('Contact Details:'))}\s*({$patterns['phone']})(?:\s*\||$)/");

        if ($phone) {
            $phone = preg_replace('/([+])(?:[ ]*\1)+/u', '$1', $phone); // remove double symbols
        }

        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

        $dateCheckIn = strtotime($this->http->FindSingleNode($xpathHotelName . "/following::text()[{$this->contains($this->t('checkIn'))}]/following::text()[normalize-space()][1]"));
        $timeCheckIn = $this->http->FindSingleNode($xpathHotelName . "/following::text()[{$this->contains($this->t('checkIn'))}]/following::text()[normalize-space()][2][{$xpathNoDisplay}]", null, true, "/^{$patterns['time']}$/");

        if ($dateCheckIn && $timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        } elseif ($dateCheckIn) {
            $h->booked()->checkIn($dateCheckIn);
        }

        $dateCheckOut = strtotime($this->http->FindSingleNode($xpathHotelName . "/following::text()[{$this->contains($this->t('checkOut'))}]/following::text()[normalize-space()][1]"));
        $timeCheckOut = $this->http->FindSingleNode($xpathHotelName . "/following::text()[{$this->contains($this->t('checkOut'))}]/following::text()[normalize-space()][2][{$xpathNoDisplay}]", null, true, "/^{$patterns['time']}$/");

        if ($dateCheckOut && $timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        } elseif ($dateCheckOut) {
            $h->booked()->checkOut($dateCheckOut);
        }

        $roomType = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room Type'))}] ]/*[normalize-space()][2]");

        $roomsCount = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Number of Rooms'))}] ]/*[normalize-space()][2]", null, true, '/^\d{1,3}$/');

        if ($roomsCount !== null) {
            $h->booked()->rooms($roomsCount);
        }

        $travellersCount = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Travellers'))}] ]/*[normalize-space()][2]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $travellersCount, $m)) {
            $h->booked()->guests($m[1]);
        }

        $meal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Meal'))}] ]/*[normalize-space()][2]");

        if ($roomType || $meal) {
            $room = $h->addRoom();

            if ($roomType) {
                $room->setType($roomType);
            }

            if ($meal) {
                $room->setDescription('Meal: ' . $meal);
            }
        }

        $status = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Status'))}] ]/*[normalize-space()][2]");
        $h->general()->status($status);

        $xpathPrice = "//tr/*[{$this->eq($this->t('priceSummary'))}]";

        $totalPrice = $this->http->FindSingleNode($xpathPrice . "/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Grand Total in'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // Rs. 4072
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $cost = $this->http->FindSingleNode($xpathPrice . "/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->contains($this->t('Rooms x'))} and {$this->contains($this->t('Night(s)'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $cost, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $otherCharges = $this->http->FindSingleNode($xpathPrice . "/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Other charges & service fees'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $otherCharges, $m)) {
                $feeName = $this->http->FindSingleNode($xpathPrice . "/following::tr[ count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('Other charges & service fees'))}]");
                $feeCharge = PriceHelper::parse($m['amount'], $currencyCode);
                $h->price()->fee($feeName, $feeCharge);
            }

            $discount = $this->http->FindSingleNode($xpathPrice . "/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Discount'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $discount, $m)) {
                $h->price()->discount(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $primaryGuest = $this->http->FindSingleNode("//tr[{$this->eq($this->t('primaryGuest'))}]/following-sibling::tr[normalize-space()][1]/descendant::text()[{$this->eq($this->t('Name:'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
        $h->general()->traveller($primaryGuest, true);

        $cancellation = implode('; ', $this->http->FindNodes("//tr[{$this->eq($this->t('Booking & Cancellation Policy'))}]/following-sibling::tr[normalize-space()][1]/descendant::ul/li[normalize-space()]"));

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Booking & Cancellation Policy'))}]/following-sibling::tr[normalize-space()][1]/descendant::ul[normalize-space()]");
        }

        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/^Booking is non refundable(?:\s*[.;!]|$)/i", $cancellation)) {
            $h->booked()->nonRefundable();
        }
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'INR' => ['Rs.'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
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
