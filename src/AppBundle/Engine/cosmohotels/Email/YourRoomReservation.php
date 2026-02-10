<?php

namespace AwardWallet\Engine\cosmohotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourRoomReservation extends \TAccountCheckerExtended
{
    public $mailFiles = "cosmohotels/it-40580358.eml, cosmohotels/it-696858431.eml"; // +1 bcdtravel(html)[en]

    public $reFrom = 'online@cosmopolitanhotel.com.hk';
    public $reSubject = [
        'en' => [
            'Your room reservation has been confirmed for',
            'Your Autograph Collection Reservation Confirmation',
            'Your Stay Reservation Confirmation for',
        ],
    ];

    public $lang = '';

    public $langDetectors = [
        'en' => ['Departure Date', 'DepartureDate', 'Cancellation Date', 'Guest'],
    ];

    public static $dictionary = [
        'en' => [
            'Booking #:'         => ['Booking #:', 'Booking#:', 'Booking Number'],
            'Web Reservation #'  => ['Web Reservation #', 'Web Reservation#', 'WebReservation #', 'WebReservation#'],
            'Arrival Date'       => ['Arrival Date', 'ArrivalDate'],
            'Departure Date'     => ['Departure Date', 'DepartureDate'],
            'Check-In Begins:'   => ['Check-In Begins:', 'Check-InBegins:', 'CHECK-IN BEGINS:'],
            'Check-Out:'         => ['Check-Out:', 'CHECK-OUT:'],
            'Name:'              => ['Name:', 'Guest'],
            'Email intended for' => ['Email intended for', 'Email intendedfor', 'Emailintended for', 'Emailintendedfor'],
            'Number of Guests'   => ['Number of Guests', 'NumberofGuests'],
            'Room Type'          => ['Room Type', 'RoomType'],
        ],
    ];

    private $supportedHotels = [
        'The Cosmopolitan of Las Vegas' => [
            '3708 Las Vegas Boulevard South | Las Vegas, NV 89109',
            '3708 Las Vegas BoulevardSouth | Las Vegas, NV 89109',
            '3708 Las VegasBoulevard South | Las Vegas, NV 89109',
        ],
    ];

    public function parseHtml(Email $email)
    {
        // TripNumber
        $webNumber = $this->http->FindSingleNode("//tr[ *[2][{$this->starts($this->t('Web Reservation #'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]", null, true, '/^\s*([A-Z\d]{5,})\s*$/');

        if (!$webNumber) {
            $webNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Web Reservation #'))}]/following::text()[normalize-space()][1]", null, true, '/^\s*([A-Z\d]{5,})\s*$/');
        }

        $email->ota()
            ->confirmation($webNumber);

        $h = $email->add()->hotel();

        // ConfirmationNumber
        $bookingNumber = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Booking #:'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, '/^\s*([A-Z\d]{5,})\s*$/');

        if (!$bookingNumber) {
            $bookingNumber = $this->nextText($this->t('Booking #:'), null, '/^\s*([A-Z\d]{5,})\s*$/');
        }
        $h->general()
            ->confirmation($bookingNumber);

        $cancellationNumber = $this->http->FindSingleNode("//text()[(contains(normalize-space(.),'Cancellation Number'))]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{5})$/");

        if (!empty($cancellationNumber)) {
            $h->general()
                ->cancellationNumber($cancellationNumber)
                ->cancelled();
        }

        // HotelName
        // Address
        $hotelName = $this->http->FindSingleNode("//text()[" . $this->eq("Follow Us") . "]/ancestor::td[./preceding::td][1]/preceding::td[1]/descendant::text()[normalize-space(.)][1]");
        $hotelAddress = $this->http->FindSingleNode("//text()[" . $this->eq("Follow Us") . "]/ancestor::td[./preceding::td][1]/preceding::td[1]/descendant::text()[normalize-space(.)][2]");

        if (!$hotelName || !$hotelAddress) {
            $logoTexts = array_map(function ($item) {
                return trim(str_ireplace('Image removed by sender.', '', $item), '. ');
            }, $this->http->FindNodes('//tr[not(.//tr) and count(descendant::a/descendant::img)=1 and count(descendant::text()[normalize-space()])=0]/descendant::a/descendant::img/@alt'));

            foreach ($this->supportedHotels as $name => $addresses) {
                $name = $this->normalizeWords($name);

                if (empty($hotelName)
                    && (in_array($name, $logoTexts) || in_array(strtoupper($name), $logoTexts) || $this->http->XPath->query("//*[{$this->contains(['your room at ' . $name . ' is booked', 'your room at ' . strtoupper($name) . ' is booked'])}]")->length > 0)
                ) {
                    $hotelName = $name;
                }

                if (empty($hotelAddress)) {
                    foreach ($addresses as $address) {
                        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $address . '")]')->length > 0) {
                            //							$it['Address'] = preg_replace('/\s*\|\s*/', ', ', $address);
                            $hotelAddress = $address;

                            break 2;
                        }
                    }
                }
            }
        }

        $phone = '';

        // Phone
        if (!empty($hotelAddress)) {
            $phoneTexts = $this->http->FindNodes('//text()[' . $this->eq($hotelAddress) . ']/following::text()[normalize-space(.)][position()<4]', null, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
            $phoneValues = array_values(array_filter($phoneTexts));

            if (count($phoneValues) === 1) {
                $phone = preg_replace('/(\d)\.(\d)/', '$1-$2', $phoneValues[0]);
            }
        }

        $h->hotel()
            ->name($hotelName)
            ->address($hotelAddress);

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        // CheckInDate
        $dateCheckIn = $this->http->FindSingleNode("//tr[ *[1][{$this->starts($this->t('Arrival Date'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, '/^.{6,}$/');
        $dateCheckIn = strtotime($this->normalizeDate($dateCheckIn));

        if (!$dateCheckIn) {
            $dateCheckIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival Date'))}]/following::text()[normalize-space()][1]", null, true, '/^.{6,}$/');
            $dateCheckIn = strtotime($this->normalizeDate($dateCheckIn));
        }
        $inDate = $dateCheckIn;

        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-In Begins:'))}]", null, true, "/^[^:]+:+\s*({$patterns['time']})(?:\s*•|$)/");

        if ($timeCheckIn && $inDate) {
            $inDateTime = strtotime($timeCheckIn, $inDate);
        }

        // CheckOutDate
        $dateCheckOut = $this->http->FindSingleNode("//tr[ *[2][{$this->starts($this->t('Departure Date'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]", null, true, '/^.{6,}$/');
        $dateCheckOut = strtotime($this->normalizeDate($dateCheckOut));

        if (!$dateCheckOut) {
            $dateCheckOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure Date'))}]/following::text()[normalize-space()][1]", null, true, '/^.{6,}$/');
            $dateCheckOut = strtotime($this->normalizeDate($dateCheckOut));
        }
        $outDate = $dateCheckOut;

        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-Out:'))}]", null, true, "/^[^:]+:+\s*({$patterns['time']})$/");

        if (!$timeCheckOut) {
            $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-Out:'))}]", null, true, "/[^:]+:+\s*({$patterns['time']})$/");
        }

        if ($timeCheckOut && $outDate) {
            $outDateTime = strtotime($timeCheckOut, $outDate);
        }

        if (!empty($inDateTime) && !empty($outDateTime)) {
            $h->booked()
                ->checkIn($inDateTime)
                ->checkOut($outDateTime);
        }

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        // GuestNames
        $guest = $this->http->FindSingleNode("//tr[ *[1][{$this->starts($this->t('Name:'))}] and not(descendant::a) and not(.//tr) ]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, "/^{$patterns['travellerName']}$/u");

        if (!$guest) {
            $guest = $this->nextText($this->t('Name:'), null, "/^{$patterns['travellerName']}$/u");
        }

        if (!$guest) {
            $guest = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Email intended for'))}]", null, true, "/{$this->opt($this->t('Email intended for'))}\s*({$patterns['travellerName']})/iu");
        }

        if ($guest) {
            $h->general()
                ->travellers([$guest]);
        }

        // Guests
        $guestCount = $this->http->FindSingleNode("//tr[ *[1][{$this->starts($this->t('Number of Guests'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, '/^\d{1,3}$/');

        if (!$guestCount) {
            $guestCount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Guests'))}]/following::text()[normalize-space()][1]", null, true, '/^\d{1,3}$/');
        }

        if (!empty($guestCount)) {
            $h->booked()
                ->guests($guestCount);
        }

        // RoomType
        $roomType = $this->http->FindSingleNode("//tr[ *[1][{$this->starts($this->t('Room Type'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, '/^\/*(.*?)\/*$/');

        if (!$roomType) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type'))}]/following::text()[normalize-space()][1]", null, true, '/^\/*(.*?)\/*$/');
        }

        if ($roomType) {
            $h->addRoom()->setType($roomType);
        }

        // Total
        $currency = $this->currency($this->nextText("Total"));
        $total = $this->amount($this->nextText("Total"));

        if (!empty($currency) && $total !== null) {
            $h->price()
                ->total($total)
                ->currency($currency);
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cosmopolitanhotel.com.hk') !== false
            || stripos($from, '@cosmopolitanlasvegas.com') !== false
            || stripos($from, 'The Cosmopolitan of Las Vegas') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (strpos($parser->getHTMLBody(), 'Cosmopolitan') === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true;

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    protected function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

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

    private function normalizeWords($words = ''): string
    {
        if (empty($words)) {
            return $words;
        }
        $words = preg_replace('/(\w)([A-Z][a-z])/u', '$1 $2', $words); // EconomyClass           ->    Economy Class
        $words = preg_replace('/(\d)([A-Z][A-z])/', '$1 $2', $words); // Airport 2TERMINAL 1    ->    Airport 2 TERMINAL 1

        return $words;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[^\s\d]+\s+([^\s\d]+)\s+(\d+),\s+(\d{4})$#", //Saturday August 26, 2017
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
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

    private function nextText($field, $root = null, $regexp = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regexp);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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
