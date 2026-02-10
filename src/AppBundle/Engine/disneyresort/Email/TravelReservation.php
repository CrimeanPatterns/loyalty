<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TravelReservation extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-114364196.eml, disneyresort/it-30255808.eml, disneyresort/it-46508277.eml";
    public $lang = '';
    public static $dict = [
        'en' => [
            'namePrefixes'                         => ['Mr.', 'Mr ', 'Ms.', 'Ms ', 'Mstr.', 'Mstr ', 'Mrs.', 'Mrs ', 'Dr.', 'Dr ', 'Miss ', 'Mstr.'],
            'Grand Total'                          => ['Grand Total', 'Total Package Price'],
            'Reservation Number:'                  => ['Reservation Number:', 'Your confirmation number is:'],
            'Guest Information'                    => ['Booked for Guest(s):', 'Guest Information'],
            'Check-in time is generally between'   => ['Check-in time is generally between', 'Check-in time is usually between'],
        ],
    ];
    private $subjects = [
        'Disneyland(R) Travel Reservation Confirmed',
        'Disneyland® Travel Reservation Confirmed',
        'Disneyland Travel Reservation Confirmed',
        'Your Reservation Confirmation',
    ];
    private $langDetectors = [
        'en' => ['Reservation Number:', 'Your confirmation number is:'],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]disneyonline\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $phrase) {
            if (stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Disneyland® Hotel") or contains(normalize-space(),"Disneyland(R) Hotel") or contains(.,"@disneyonline.com") or contains(., "disneyworld.com.")]')->length === 0
            && $this->http->XPath->query("//node()[{$this->contains($this->subjects)}]")->length === 0
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

        $reservationNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Number:'))}]");

        if (preg_match("/\:\s*$/", $reservationNumber)) {
            $reservationNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation Number:'))}]/ancestor::tr[1]");
        }

        if (preg_match("/({$this->opt($this->t('Reservation Number:'))})\s*([A-Z\d]{5,})/", $reservationNumber, $m)) {
            $email->ota()->confirmation($m[2], preg_replace('/(?:\s*is\:\s*$|\s*:\s*$)/', '', $m[1]));
        }

        $this->parseEmail($email);

        // p.currencyCode
        // p.total
        $xpathFragmentPayment = "//text()[{$this->eq($this->t('Payment Information'))}]";
        $grandTotal = $this->http->FindSingleNode($xpathFragmentPayment . "/following::text()[{$this->starts($this->t('Grand Total'))}]/ancestor::div[1]");

        if (empty($grandTotal)) {
            $grandTotal = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Grand Total')]/ancestor::tr[1]");
        }

        if (preg_match("/{$this->opt($this->t('Grand Total'))}.*?\(\s*(?<currency>[A-Z]{3})\s*\)\s*:[^\d)(]*(?<amount>\d[,.\'\d]*)$/", $grandTotal, $matches)
            || preg_match("/{$this->opt($this->t('Grand Total'))}.*?\(?\s*(?<currency>\D)\s*\)?\s*\:?[^\d)(]*(?<amount>\d[,.\'\d]*)$/", $grandTotal, $matches)) {
            // Grand Total** (USD):    $1,836.32
            $email->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);

            // p.tax
            $tax = $this->http->FindSingleNode($xpathFragmentPayment . "/following::text()[{$this->starts($this->t('Tax'))}]/ancestor::div[1]");

            if (preg_match("/{$this->opt($this->t('Tax'))}.*?\(\s*" . preg_quote($matches['currency'], '/') . "\s*\)\s*:[^\d)(]*(?<amount>\d[,.\'\d]*)$/", $tax, $m)) {
                $email->price()->tax($this->normalizeAmount($m['amount']));
            }
        }

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

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail(Email $email): void
    {
        $guestNames = $this->http->FindNodes("//text()[{$this->eq($this->t('Guest Information'))}]/following::text()[{$this->starts($this->t('namePrefixes'))}]", null, '/^([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]\.?)\s*(?:\(|$)/u');
        $guestNames = array_filter($guestNames);

        ////////////////
        //// HOTELS ////
        ////////////////

        $h = $email->add()->hotel();
        $h->general()->noConfirmation();
        $h->general()->travellers($guestNames);

        // hotelName
        // address
        $addressHtml = $this->http->FindHTMLByXpath("//*[preceding-sibling::*[{$this->eq($this->t('Itinerary Details'))}] and following-sibling::*[{$this->starts($this->t('Arriving on'))}] and normalize-space()]");
        $addressText = $this->htmlToText($addressHtml);
        // Disneyland® Hotel   1150 Magic Way Anaheim, CA 92802
        if (preg_match("/^\s*(.{10,})[ ]*\n+[ ]*([\w\s,.]{3,})\s*/i", $addressText, $m)) {
            $h->hotel()
                ->name($m[1])
                ->address(preg_replace('/[, ]*\n+[, ]*/', ', ', $m[2]));
        } else {
            $h->hotel()
                ->name($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Resort Name:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Resort Name:'))}\s*(.+)/"))
                ->noAddress();
        }

        $arrivingDetails = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arriving on'))}]");

        if (empty($arrivingDetails)) {
            $arrivingDetails = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Arriving :'))}]/ancestor::tr[1]");
        }

        // checkInDate
        // checkOutDate
        if (preg_match("/{$this->opt($this->t('Arriving on'))}\s*(.{6,}?)\s*{$this->opt($this->t('for'))}\s*(\d{1,3})\s*{$this->opt($this->t('night'))}/", $arrivingDetails, $m)) {
            $h->booked()->checkIn2($m[1]);

            if (!empty($h->getCheckInDate())) {
                $h->booked()->checkOut(strtotime('+' . $m[2] . ' days', $h->getCheckInDate()));
            }
        } else {
            $checkInDate = strtotime($this->re("/:\s*[-[:alpha:]]*\s*(\d{1,2}.\d{1,2}.\d{2,4})$/u", $arrivingDetails));
            $checkInTimeText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in time is generally between'))}]");

            if (preg_match("/{$this->opt($this->t('Check-in time is generally between'))}\s*([\d\:]+)\s*and\s*[\d\:]+\s*(a?p?)\.?(m)/", $checkInTimeText, $m)) {
                $h->booked()->checkIn(strtotime($m[1] . ' ' . $m[2] . $m[3], $checkInDate));
            } elseif ($checkInDate) {
                $h->booked()->checkIn($checkInDate);
            }

            $checkOutText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Departing :')]/ancestor::tr[1]");

            if (preg_match("/\w*\s*(?<date>\d+.\d+.\d+)\s*{$this->opt($this->t('for'))}\s*\d{1,3}\s*{$this->opt($this->t('night'))}/iu", $checkOutText, $m)) {
                $h->booked()->checkOut(strtotime($m['date']));
            }

            if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $checkOutText, $m)) {
                $h->booked()->guests($m[1]);
            }

            if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/i", $checkOutText, $m)) {
                $h->booked()->kids($m[1]);
            }
        }

        // guestCount
        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/", $arrivingDetails, $m)) {
            $h->booked()->guests($m[1]);
        }

        // kidsCount
        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Child'))}/", $arrivingDetails, $m)) {
            $h->booked()->kids($m[1]);
        }

        $r = $h->addRoom();

        // r.type
        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type:'))}]/following::text()[normalize-space(.)][1]");
        $r->setType($roomType);

        $rates = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->eq($this->t('Rate Per Night'))}] ]/following-sibling::tr[count(*[normalize-space()])=2]/*[normalize-space()][2]", null, '/^[^\-\d)(]+[ ]*\d[,.\'\d ]*$/');

        if (count($rates) && !in_array(null, $rates, true)) {
            $r->setRates($rates);
        }

        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation and Refunds']/following::text()[normalize-space()][1]/ancestor::li[1]");

        if (!empty($cancellation)) {
            $h->setCancellation($cancellation);
        }

        //////////////
        //// CARS ////
        //////////////

        $cars = $this->http->XPath->query("//text()[{$this->eq($this->t('Ground Transportation'))}]/ancestor::*[{$this->contains($this->t('Pick-up:'))}][1]");

        foreach ($cars as $root) {
            $car = $email->add()->rental();
            $car->general()->noConfirmation();
            $car->general()->travellers($guestNames);

            // Alamo Full Size car (3 days)
            $rentalInfo = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Car Rental:'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<company>Alamo)\s+/i", $rentalInfo, $m)) {
                $car->extra()->company($m['company']);
            }

            if (preg_match("/^.+?\s+(?<carType>Full Size car)[\s(]/i", $rentalInfo, $m)) {
                $car->car()->type($m['carType']);
            }

            $carModel = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Car:'))}]/following::text()[normalize-space()][1]", $root);
            $car->car()->model($carModel);

            // Ontario, CA (ONT) November 08, 2019 12:00:00 PM
            $patterns['locDate'] = '(?<loc>.{3,}?)\s+(?<dateTime>[[:alpha:]]{3,}\s+\d{1,2}[ ]*,[ ]*\d{2,4}.*)';

            $pickUp = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Pick-up:'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^{$patterns['locDate']}$/u", $pickUp, $m)) {
                $car->pickup()
                    ->location($m['loc'])
                    ->date2($m['dateTime']);
            }

            $return = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Return:'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^{$patterns['locDate']}$/u", $return, $m)) {
                $car->dropoff()
                    ->location($m['loc'])
                    ->date2($m['dateTime']);
            }
        }
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
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
