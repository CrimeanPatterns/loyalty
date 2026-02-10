<?php

namespace AwardWallet\Engine\amoma\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Reservations extends \TAccountChecker
{
    public $mailFiles = "amoma/it-1703896.eml, amoma/it-1780544.eml, amoma/it-2397172.eml, amoma/it-2632119.eml, amoma/it-2739390.eml, amoma/it-36617267.eml, amoma/it-39233445.eml,amoma/it-1.eml, amoma/it-1747233.eml";
    public $lang = '';
    public static $dict = [
        'en' => [
            'Booking n'           => 'Booking n',
            'Hotel details'       => 'Hotel details',
            'Name:'               => 'Name:',
            'Cancellation policy' => 'Cancellation policy',
            'Client name:'        => 'Client name:',
            'Client number:'      => 'Client number:',
            'Hotel:'              => 'Hotel:',
            'Check-in date'       => 'Check-in date',
            'Check-out date'      => 'Check-out date',
            'Address:'            => 'Address:',
            'Country:'            => 'Country:',
            'Phone:'              => 'Phone:',
            'Your reservation:'   => 'Your reservation:',
            'guest'               => 'guest',
            'room'                => 'room',
            'Room(s):'            => 'Room(s):',
            'adult'               => ['adult', 'adults'],
            'children'            => 'children',
            'Total price'         => ['Total price', 'Total to be paid'],
            'Total without taxes' => 'Total without taxes',
        ],
        'no' => [
            'Booking n'           => 'Bestillingsnummer',
            'Hotel details'       => 'Hotellinformasjon',
            'Name:'               => 'Navn:',
            'Cancellation policy' => 'Kanselleringspolitikk',
            'Client name:'        => 'Kundenavn:',
            //            'Client number:' => '',
            //            'Hotel:'=>'',
            'Check-in date:'  => 'Innsjekksdato:',
            'Check-out date:' => 'Utsjekksdato:',
            'Address:'        => 'Adresse:',
            //            'Country:' => '',
            'Phone:'            => 'Telefon:',
            'Your reservation:' => 'Din reservasjon:',
            'guest'             => 'gjest',
            'room'              => 'rom',
            'Room(s):'          => 'Rom:',
            'adults'            => 'voksne',
            //            'children'=>'',
            'Total price' => 'Totalt',
            //            'Total without taxes' => '',
            //            'Taxes and fees' => '',
        ],
        'sv' => [
            'Booking n'           => 'Bokningsnummer',
            'Hotel details'       => 'Information om hotellet',
            'Name:'               => 'Namn:',
            'Cancellation policy' => 'Avbokningspolicy',
            'Client name:'        => 'Kundens namn:',
            'Client number:'      => 'Kundnummer:',
            'Hotel:'              => 'Hotell:',
            'Check-in date'       => 'Incheckningsdatum',
            'Check-out date'      => 'Utcheckningsdatum',
            'Address:'            => 'Adress:',
            'Country:'            => 'Land',
            'Phone:'              => 'Telefon:',
            'Your reservation:'   => 'Din bokning:',
            'guest'               => 'personer',
            'room'                => 'rum',
            'Room(s):'            => 'Rum:',
            //            'adult' => '',
            //            'children'=>'',
            'Total price' => ['Totalbelopp att betala'],
            //            'Total without taxes' => '',
            //            'Taxes and fees' => '',
        ],
        'de' => [
            'Booking n'           => 'Buchungs-Nr.',
            'Hotel details'       => 'Hoteldetails',
            'Name:'               => 'Name:',
            'Cancellation policy' => 'Stornierungsrichtlinien',
            'Client name:'        => 'Kundenname:',
            'Client number:'      => 'Kundennummer:',
            'Hotel:'              => 'Hotel:',
            'Check-in date'       => 'Check-In-Datum',
            'Check-out date'      => 'Check-Out-Datum',
            'Address:'            => 'Adresse:',
            'Country:'            => 'Land',
            'Phone:'              => 'Telefonnummer:',
            'Your reservation:'   => 'Ihre Reservierung:',
            'guest'               => 'Personen',
            'room'                => 'Zimmer',
            'Room(s):'            => 'Zimmer:',
            //            'adult' => '',
            //            'children'=>'',
            'Total price' => ['Zu zahlender Gesamtbetrag'],
            //            'Total without taxes' => '',
            //            'Taxes and fees' => '',
        ],
        'ro' => [
            'Booking n' => 'Rezervare număr n',
            //            'Hotel details' => '',
            //            'Name:' => '',
            'Cancellation policy' => 'Politica de anulare',
            'Client name:'        => 'Nume Client:',
            'Client number:'      => 'Număr client:',
            'Hotel:'              => 'Hotel:',
            'Check-in date'       => 'Data check-in',
            'Check-out date'      => 'Data check-out',
            //            'Address:' => '',
            //            'Country:' => '',
            //            'Phone:' => '',
            //            'Your reservation:' => '',
            //            'guest' => '',
            //            'room' => '',
            'Room(s):'    => 'Cameră (e):',
            'adult'       => 'adulți',
            'children'    => 'copii',
            'Total price' => ['Preț final'],
            //            'Total without taxes' => '',
            //            'Taxes and fees' => '',
        ],
    ];

    private static $providers = [
        'amoma'   => [
            'from'    => ["mywellsfargorewards.com", "@amoma.com"],
            'body'    => [
                'en' => ['ALL AMENDMENTS MUST BE SENT IN WRITING TO AMOMA.com', 'modification@amoma.com'],
                'no' => ['TOATE MODIFICĂRILE TREBUIE TRIMISE ÎN SCRIS LA AMOMA.com'],
                'sv' => ['ALLA ÄNDRINGAR MÅSTE SKICKAS IN SKRIFTLIGT TILL AMOMA.com'],
                'de' => ['ALLE VERÄNDERUNGEN MÜSSEN SCHRIFTLICH AN AMOMA.com'],
                'ro' => ['TOATE MODIFICĂRILE TREBUIE TRIMISE ÎN SCRIS LA AMOMA.com'],
            ],
            'subject' => [
                'Your booking confirmation with AMOMA.com',
                'Din bokningsbekraftelse med AMOMA.com',
                'Test Hotel only Aoma.com',
                'Test Hotel only Amoma.com',
                'Confirmarea rezervării dumneavoastră cu AMOMA.com',
            ],
        ],
        'olotels' => [
            'from'    => ["bookings@olotels.com"],
            'body'    => [
                'en' => ['ALL AMENDMENTS MUST BE SENT IN WRITING TO Olotels'],
            ],
            'subject' => [
                'Your booking confirmation with Olotels',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        if (!empty($code = $this->getProviderCode())) {
            $email->setProviderCode($code);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['body'] as $lang => $body) {
                foreach ($body as $phrase) {
                    if (strpos($this->http->Response["body"], $phrase) !== false) {
                        return $this->assignLang();
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $key => $option) {
            foreach ($option['from'] as $reFrom) {
                return strpos($from, $reFrom) !== false;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['subject'] as $lang => $subject) {
                if (strpos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $cancellation = implode(' / ',
            $this->http->FindNodes("//text()[{$this->contains($this->t('Cancellation policy'))}]/ancestor::tr[1]/following-sibling::tr[1]//li"));

        if (empty($cancellation)) {
            $cancellation = implode(' / ',
                $this->http->FindNodes("//text()[{$this->contains($this->t('Cancellation policy'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('Cancellation policy'))})]"));
        }

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking n'))}]/ancestor::td[1]/following-sibling::td[1]"))
            ->cancellation($cancellation);

        $travellers = [];
        $roomInfoNodes = $this->http->FindNodes("//td[" . $this->contains($this->t('Room(s)')) . "]/following-sibling::td[1]//text()");

        foreach ($roomInfoNodes as $n) {
            if (!empty($n)) {
                if (!preg_match('/(\d+)\s*(?:(?:x)|' . $this->opt($this->t('adult')) . '|' . $this->opt($this->t('children')) . ')\s*(.*)/', $n, $m)) {
                    $travellers[] = $n;
                }
            }
        }
        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Client name:'))}]/ancestor::td[1]/following-sibling::td[1]",
            null, true, "#^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$#u");

        if ($this->stripos($traveller, $travellers) === false) {
            $travellers[] = $traveller;
        }

        if (!empty($travellers)) {
            $r->general()
                ->travellers(array_unique($travellers), true);
        }

        $acc = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Client number:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        if (!empty($acc)) {
            $r->program()->account($acc, false);
        }

        $hotel = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel:'))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()!=''][1]");

        if (empty($hotel)) {
            $hotel = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel details'))}]/ancestor::tr[1]/following-sibling::tr//*[contains(text(),'" . $this->t("Name:") . "')]/ancestor::tr[1]/td[2]");
        }
        $address = trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Hotel'))}]/ancestor::td[1]/following-sibling::td/descendant::text()[normalize-space()!=''][2]",
            null, true, "#-\s*(.*?)$#"));

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Address:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!='']");
            $country = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Country:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!='']");
            $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Phone:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!='']");

            if (!empty($country)) {
                $address .= ',' . $country;
            }
        }

        $r->hotel()
            ->name(trim($hotel, ' *'))
            ->address($address);

        if (isset($phone)) {
            $r->hotel()->phone($phone);
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Room(s):'))}]/ancestor::td[1]/following-sibling::td/descendant::text()[normalize-space()!=''][2]",
            null, true, "#(\d+)\s+{$this->opt($this->t('adult'))}#");

        if (empty($guests)) {
            $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!='']",
                null, false, "#(\d+)\s+{$this->opt($this->t('guest'))}#");
        }
        $kids = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Room(s):'))}]/ancestor::td[1]/following-sibling::td/descendant::text()[normalize-space()!=''][2]",
            null, true, "#(\d+)\s+{$this->opt($this->t('children'))}#");

        $roomCnt = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Room(s):'))}]/ancestor::td[1]/following-sibling::td/descendant::text()[normalize-space()!=''][1]",
            null, true, "#(\d+)\s*x#");

        if (!$roomCnt) {
            $roomCnt = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation:'))}]/ancestor::tr[1]/td[2]",
                null, true, "#(\d+)\s+{$this->opt($this->t('room'))}#");
        }
        $r->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in date'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-out date'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]")))
            ->guests($guests)
            ->rooms($roomCnt);

        if ($kids !== null) {
            $r->booked()
                ->kids($kids);
        }

        $room = $r->addRoom();
        $room->setType(trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Room(s):'))}]/ancestor::td[1]/following-sibling::td/descendant::text()[normalize-space()!=''][1]",
            null, true, "#\d+\s*x(.+)#ms")));

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total price'))}]/ancestor-or-self::td[1]/following-sibling::td[1]");
        $sum = $this->getTotalCurrency($totalPrice);

        if (!empty($sum['Total'])) {
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        } elseif (preg_match("#^(?<Total>\d[,.'\d]*)[ ]*(?<Currency>[^\d)(])$#u", $totalPrice, $m)) {
            $r->price()
                ->total(PriceHelper::cost($m['Total']));
            $r->getPrice()->setCurrencySign($m['Currency']);
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total without taxes'))}]/ancestor::td[1]/following-sibling::td[1]");
        $sum = $this->getTotalCurrency($cost);

        if (!empty($sum['Total']) && $r->getPrice() && $r->getPrice()->getCurrencyCode() === $sum['Currency']) {
            $r->price()->cost($sum['Total']);
        } elseif (preg_match("#^(?<Total>\d[,.'\d]*)[ ]*(?<Currency>[^\d)(])$#", $totalPrice, $m)
            && $r->getPrice() && $r->getPrice()->getCurrencySign() === $m['Currency']
        ) {
            $r->price()
                ->cost(PriceHelper::cost($m['Total']));
        }

        $tax = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Taxes and fees'))}]/ancestor::td[1]/following-sibling::td[1]");
        $sum = $this->getTotalCurrency($tax);

        if (!empty($sum['Total']) && $r->getPrice() && $r->getPrice()->getCurrencyCode() === $sum['Currency']) {
            $r->price()->tax($sum['Total']);
        } elseif (preg_match("#^(?<Total>\d[,.'\d]*)[ ]*(?<Currency>[^\d)(])$#", $tax, $m)
            && $r->getPrice() && $r->getPrice()->getCurrencySign() === $m['Currency']
        ) {
            $r->price()
                ->tax(PriceHelper::cost($m['Total']));
        }

        $this->detectDeadLine($r);

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            // lördag 21 mars 2015
            '#^([\-\w]+),?\s+(\d+)\s+(\w+)\s+(\d{4})$#u',
            // 18 aprilie 2019
            '#^(\d+)\s+(\w+)\s+(\d{4})$#u',
        ];
        $out = [
            '$2 $3 $4',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^[\d\.\,]+ .+? if you cancel your booking between (?<dayOff>\w+ \d+ \w+ \d{4}) to \w+ \d+ \w+ \d{4}/ui",
                $cancellationText, $m)
        || preg_match("/^[\d\.\,]+ .+?, wenn Sie Ihre Reservierung zwischen (?<dayOff>\w+ \d+ \w+ \d{4}) und \w+ \d+ \w+ \d{4} stornieren/ui",
                $cancellationText, $m)
        || preg_match("/^[\d\.\,]+ .+? dacă anulați sau modificați rezervarea dvs. între (?<dayOff>\d+ \w+ \d{4}) și \d+ \w+ \d{4}/ui",
                $cancellationText, $m)
        || preg_match("/^FREE cancellation before (?<dayOff>\d+ \w+ \d{4})/ui",
                $cancellationText, $m)
            || preg_match("/^\d+\s*\S{3}\sif you cancel your booking starting\s\w+\s(?<dayOff>\d+\s\w+\s\d{4})$/ui",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime("-1 minute", $this->normalizeDate($m['dayOff'])));
        }

        $h->booked()
            ->parseNonRefundable("#This total booking amount is not refundable in case of cancellation or modification#") // en
            ->parseNonRefundable("#Hela summan betalas inte tillbaka vid en avbokning eller ändring#"); // sv
    }

    private function getProviderCode()
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['body'] as $lang => $body) {
                foreach ($body as $phrase) {
                    if (strpos($this->http->Response["body"], $phrase) !== false) {
                        return $prov;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Cancellation policy"], $words["Booking n"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Cancellation policy'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Booking n'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹", "kr"], ["EUR", "GBP", "USD", "INR", "SEK"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function stripos($needle, $arrayHaystack)
    {
        $arrayHaystack = (array) $arrayHaystack;

        foreach ($arrayHaystack as $haystack) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
