<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingAtHotel2023 extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-631158217.eml, ctrip/it-629891777.eml, ctrip/it-628624495-cancelled.eml, ctrip/it-702492991-pt.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'otaConfNumber'        => ['N.º da reserva'],
            'Hi'                   => ['Olá'],
            'confNumber'           => ['Número de confirmação do hotel:'],
            'statusPhrases'        => ['está'],
            'statusVariants'       => ['confirmada'],
            // 'cancelledPhrases' => [''],
            // 'cancelledStatus' => [''],
            // 'Refund Details' => '',
            'Booking Details'      => 'Detalhes da reserva',
            'checkIn'              => ['Check-in'],
            'checkOut'             => ['Check-out'],
            'address'              => ['Endereço'],
            'Hotel Contact Number' => 'Número de contato do hotel',
            'After'                => 'Depois de',
            'Before'               => 'Antes de',
            'Your Booking'         => 'Sua reserva',
            'Room'                 => 'quarto',
            'Booking for'          => 'Reserva para',
            'adult'                => 'adulto',
            'children'             => 'criança',
            'Free Cancellation'    => 'Cancelamento gratuito',
            'nonRefundablePhrases' => [
                'Essa reserva não pode ser modificada, e não haverá reembolso caso você a cancelle.',
                'Essa reserva não pode ser modificada, e não haverá reembolso caso você a cancele.',
            ],
            'Guest Names'   => 'Nomes dos hóspedes',
            'Price Details' => 'Detalhamento do preço',
            // 'Total' => '',
            'Prepay Online' => 'Pagar on-line',
            'costStart'     => 'quarto×noite',
        ],
        'en' => [
            'otaConfNumber'        => ['Booking No.'],
            'Hi'                   => ['Hi', 'Dear'],
            'confNumber'           => ['Updated hotel confirmation number:', 'Hotel confirmation number:'],
            'statusPhrases'        => ['has been'],
            'statusVariants'       => ['confirmed', 'cancelled', 'canceled', 'awaiting confirmation', 'updated'],
            'cancelledPhrases'     => ['your booking has been cancelled', 'your booking has been canceled'],
            'cancelledStatus'      => ['Cancelled', 'Canceled'],
            // 'Refund Details' => '',
            // 'Booking Details' => '',
            'checkIn'   => ['Check-in'],
            'checkOut'  => ['Check-out'],
            'address'   => ['Address'],
            // 'Hotel Contact Number' => '',
            // 'After' => '',
            // 'Before' => '',
            // 'Your Booking' => '',
            // 'Room' => '',
            // 'Booking for' => '',
            // 'adult' => '',
            // 'children' => '',
            // 'Free Cancellation' => '',
            'nonRefundablePhrases' => [
                'This booking cannot be modified, and no refund will be given if you cancell it.',
                'This booking cannot be modified, and no refund will be given if you cancel it.',
            ],
            // 'Guest Names' => '',
            // 'Price Details' => '',
            // 'Total' => '',
            // 'Prepay Online' => '',
            'costStart' => ['NightxRoom', 'NightsxRoom'],
        ],
    ];

    private $subjects = [
        'pt' => ['Sua reserva no '],
        'en' => ['Your booking at '],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]trip\.com$/i', $from) > 0;
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
        if ($this->http->XPath->query('//a[contains(@href,".trip.com/") or contains(@href,"www.trip.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"thanks for booking with Trip.com") or contains(normalize-space(),"Trip.com all rights reserved")]')->length === 0
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
        $email->setType('BookingAtHotel2023' . ucfirst($this->lang));

        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold") or contains(translate(@style," ",""),"font-weight:600"))';

        $patterns = [
            'date'          => '.{4,}\b\d{4}\b', // Apr 5, 2024    |    8 jul 2024
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{4,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992    |    400033
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $otaConfirmations = array_values(array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('otaConfNumber'))}]/following::text()[normalize-space()][1]", null, '/^[A-Z\d]{5,}$/'))));

        if (count($otaConfirmations) === 1) {
            $otaConfirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('otaConfNumber'))}][last()]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmations[0], $otaConfirmationTitle);
        }

        $confirmationVal = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]");

        if (preg_match('/^[#\s]*([A-Z\d]{5,})$/', $confirmationVal, $m)) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($m[1], $confirmationTitle);
        } elseif (count($otaConfirmations) === 1) {
            $h->general()->noConfirmation();
        }

        // it-631158217.eml
        $roomConfirmations = strpos($confirmationVal, ';') !== false && preg_match('/^[A-Z\d;\s]{5,}$/', $confirmationVal) ? preg_split('/(\s*[;]+\s*)+/', $confirmationVal) : [];

        if (count($roomConfirmations) === 0) {
            // it-702492991-pt.eml
            $roomConfirmations = strpos($confirmationVal, ',') !== false && preg_match('/^[A-Z\d,\s]{5,}$/', $confirmationVal) ? preg_split('/(\s*[,]+\s*)+/', $confirmationVal) : [];
        }

        $hotelName = null;
        $hotelNameTexts = array_reverse($this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/preceding::tr[not(.//tr) and normalize-space() and not({$this->eq($this->t('Booking Details'))} or {$this->eq($this->t('Refund Details'))})][position()<4]/*[normalize-space()][1]"));

        foreach ($hotelNameTexts as $hotelName_temp) {
            if ($this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
                $hotelName = $hotelName_temp;

                break;
            }
        }

        $xpathCheckIn = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/*[normalize-space()][2]/descendant::tr[count(*[normalize-space()])>1][1]";
        $dateCheckIn = strtotime($this->normalizeDate($this->http->FindSingleNode($xpathCheckIn . "/*[normalize-space()][1]", null, true, "/^{$patterns['date']}$/")));
        $timeCheckIn = $this->http->FindSingleNode($xpathCheckIn . "/*[normalize-space()][2]", null, true, "/^(?:{$this->opt($this->t('After'))}\s+)?({$patterns['time']})(?:\s*[-–]|$)/");

        if ($dateCheckIn && $timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        }

        $xpathCheckOut = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]/*[normalize-space()][2]/descendant::tr[count(*[normalize-space()])>1][1]";
        $dateCheckOut = strtotime($this->normalizeDate($this->http->FindSingleNode($xpathCheckOut . "/*[normalize-space()][1]", null, true, "/^{$patterns['date']}$/")));
        $timeCheckOut = $this->http->FindSingleNode($xpathCheckOut . "/*[normalize-space()][2]", null, true, "/{$patterns['time']}$/");

        if ($dateCheckOut && $timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        }

        $roomsCount = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Your Booking'))}] ]/*[normalize-space()][2]", null, true, "/\b(\d{1,3})\s*{$this->opt($this->t('Room'))}/i");
        $h->booked()->rooms($roomsCount);

        $bookingForVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Booking for'))}] ]/*[normalize-space()][2]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/i", $bookingForVal, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('children'))}/i", $bookingForVal, $m)) {
            $h->booked()->kids($m[1]);
        }

        $address = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('address'))}] ]/*[normalize-space()][2]/descendant::*[ tr[normalize-space() and not(.//tr[normalize-space()])][2] ][1]/tr[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('address'))}] ]/*[normalize-space()][2]");
        $phone = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Hotel Contact Number'))}] ]/*[normalize-space()][2]/descendant::*[ tr[normalize-space() and not(.//tr[normalize-space()])][2] ][1]/tr[normalize-space()][1]", null, true, "/^{$patterns['phone']}$/")
            ?? $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Hotel Contact Number'))}] ]/*[normalize-space()][2]", null, true, "/^{$patterns['phone']}$/");
        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

        $freeCancellation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->eq($this->t('Free Cancellation'))}] ]/*[normalize-space()][1]");

        if (preg_match("/^{$this->opt($this->t('Before'))}\s+(?<time>{$patterns['time']})[,\s]+(?<date>{$patterns['date']})$/i", $freeCancellation, $m)
            || preg_match("/^{$this->opt($this->t('Before'))}\s+(?<date>{$patterns['date']})[,\s]+(?<time>{$patterns['time']})$/i", $freeCancellation, $m)
        ) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))));
        }

        $nonRefundableVal = $this->http->FindSingleNode("descendant::*[{$this->eq($this->t('nonRefundablePhrases'))}][last()]");

        if (!$nonRefundableVal) {
            $nonRefundableTexts = $this->http->FindNodes("descendant::text()[{$this->starts($this->t('nonRefundablePhrases'))}]");

            if (count(array_unique($nonRefundableTexts)) === 1) {
                $nonRefundableVal = array_shift($nonRefundableTexts);
            }
        }

        if ($nonRefundableVal) {
            $h->booked()->nonRefundable();
            $h->general()->cancellation($nonRefundableVal);
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $h->general()->status($status);
        } elseif ($hotelName && preg_match("/^{$this->opt($this->t('statusVariants'))}$/i", $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq([$hotelName, mb_strtolower($hotelName), mb_strtoupper($hotelName)])}] ]/*[normalize-space()][2]"), $m)) {
            $h->general()->status($m[0]);
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0
            || !empty($h->getStatus()) && preg_match("/^{$this->opt($this->t('cancelledStatus'))}$/i", $h->getStatus())
        ) {
            $h->general()->cancelled();
        }

        $roomType = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guest Names'))}] ]/preceding::tr[not(.//tr) and normalize-space()][1][ descendant::text()[normalize-space()][2][{$this->contains($this->t('Room'))}] ]/descendant::text()[normalize-space()][1][ ancestor::*[{$xpathBold}] ]")
            ?? $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guest Names'))}] ]/preceding::tr[not(.//tr) and normalize-space()][1][descendant::text()[normalize-space() and ancestor::*[{$xpathBold}]] and count(descendant::text()[normalize-space() and not(ancestor::*[{$xpathBold}])])=0]");

        if (count($roomConfirmations) > 0 || $roomType) {
            if (count($roomConfirmations) > 0 && ($roomsCount === null || $roomsCount !== null && count($roomConfirmations) === (int) $roomsCount)) {
                // it-631158217.eml
                foreach ($roomConfirmations as $roomConf) {
                    $room = $h->addRoom();
                    $room->setConfirmation($roomConf);

                    if ($roomType) {
                        $room->setType($roomType);
                    }
                }
            } elseif ($roomsCount !== null && $roomType) {
                // it-629891777.eml
                for ($i = 0; $i < (int) $roomsCount; $i++) {
                    $room = $h->addRoom();
                    $room->setType($roomType);
                }
            } elseif ($roomType) {
                $room = $h->addRoom();
                $room->setType($roomType);
            }
        }

        $travellers = [];
        $guestNamesVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guest Names'))}] ]/*[normalize-space()][2]");
        $guestNames = preg_split('/(\s*[,]+\s*)+/', $guestNamesVal);

        foreach ($guestNames as $gName) {
            if (preg_match("/^{$patterns['travellerName']}$/u", $gName) > 0) {
                $travellers[] = $gName;
            } else {
                $travellers = [];

                break;
            }
        }

        if (!$guestNamesVal) {
            // it-628624495-cancelled.eml
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $travellers = [$traveller];
            }
        }

        if (count($travellers) > 0) {
            $h->general()->travellers($travellers, true);
        }

        // price
        $xpathPriceHeader = $this->eq($this->t('Price Details'));
        $xpathTotalPrice = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}]";
        $totalPrice = $this->http->FindSingleNode("//tr[{$xpathPriceHeader}]/following::tr[{$xpathTotalPrice}]/*[normalize-space()][2]", null, true, "/^(?:{$this->opt($this->t('Prepay Online'))}\s*)?(.*\d.*)$/");

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)
        ) {
            // HK$ 1,113.00    |    £ 140.56    |    145,56 €
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[{$xpathPriceHeader}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->contains($this->t('costStart'), 'translate(.,"0123456789 ","")')}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $baseFare, $m)
            ) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $discountAmounts = [];
            $discountRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][starts-with(normalize-space(),'-')] and preceding::tr[{$xpathPriceHeader}] and following::tr[{$xpathTotalPrice}] ]");

            foreach ($discountRows as $dRow) {
                $dCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $dRow, true, '/^[-–]+\s*(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $dCharge, $m)
                    || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $dCharge, $m)
                ) {
                    $discountAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                }
            }

            if (count($discountAmounts) > 0) {
                $h->price()->discount(array_sum($discountAmounts));
            }
        }

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
            if (!is_string($lang) || empty($phrases['checkIn']) || empty($phrases['address'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr[ *[not(.//tr[normalize-space()]) and normalize-space()][1][{$this->eq($phrases['checkIn'])}] ]")->length > 0 && $this->http->XPath->query("//tr[ *[not(.//tr[normalize-space()]) and normalize-space()][1][{$this->eq($phrases['address'])}] ]")->length > 0) {
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'HKD' => ['HK$'],
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]+)\s+(\d{1,2})[,\s]+(\d{4})$/u', $text, $m)) {
            // Jun 30, 2024
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})$/u', $text, $m)) {
            // 23 ago 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
