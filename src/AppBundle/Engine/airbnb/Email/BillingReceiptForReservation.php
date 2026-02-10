<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Engine\MonthTranslate;

class BillingReceiptForReservation extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-1845485.eml, airbnb/it-1937358.eml, airbnb/it-1946674.eml, airbnb/it-1994899.eml, airbnb/it-2022265.eml, airbnb/it-2248627.eml, airbnb/it-2269365.eml, airbnb/it-2314869.eml, airbnb/it-2789760.eml, airbnb/it-3782886.eml, airbnb/it-3795456.eml, airbnb/it-3816574.eml, airbnb/it-6330618.eml, airbnb/it-9470700.eml, airbnb/it-9673314.eml";

    public $reFrom = '@airbnb.com';
    public $reSubject = [
        'da' => ['Kvittering for reservation'],
        'fr' => ['Reçu de la réservation'],
        'de' => ['Rechnungsbeleg für Buchung'],
        'hu' => ['Foglalási bizonylat a következő foglaláshoz'],
        'es' => ['Recibo de facturación para la reserva', 'Recibo para la reserva'],
        'nl' => ['Factuur voor reservering'],
        'ru' => ['Квитанция об оплате бронирования'],
        'sv' => ['Faktureringskvitto för bokning'],
        'el' => ['Απόδειξη χρέωσης για την κράτηση'],
        'it' => ['Ricevuta per la prenotazione'],
        'pl' => ['Rachunek dla rezerwacji'],
        'cs' => ['Faktura pro rezervaci'],
        'pt' => ['Recibo de faturamento para a reserva'],
        'tr' => ['kodlu rezervasyon için makbuz'],
        'en' => ['Billing receipt for reservation'],
    ];

    public $lang = '';

    public $reBody = 'Airbnb';
    public $langDetectors = [
        'da' => ['Kundekvittering'],
        'fr' => ['Reçu client'],
        'de' => ['Rechnungsbeleg für den Kunden'],
        'hu' => ['Ügyfél számla'],
        'es' => ['Recibo del cliente'],
        'nl' => ['Factuur'],
        'ru' => ['Квитанция клиента'],
        'sv' => ['Kundkvitto'],
        'el' => ['Απόδειξη επισκέπτη'],
        'it' => ['Ricevuta per il cliente', 'Ricevuta per il Cliente'],
        'pl' => ['Rachunek dla klienta'],
        'cs' => ['Účtenka zákazníka'],
        'pt' => ['Recibo do Cliente', 'Recibo de Cliente'],
        'tr' => ['Müşteri Faturası'],
        'en' => ['Customer Receipt'],
    ];

    public static $dictionary = [
        'da' => [
            "Confirmation Code:"   => "Bekræftelseskode:",
            "Travel Property"      => "Rejsebolig",
            "Arrive"               => ["Tjek ind", "Ankommer"],
            "Depart"               => ["Check ud", "Tager af sted", "Rejser"],
            "Accommodation Address"=> "Boligens adresse",
            "Guests"               => ["Gæster", "Gæst"],
            "Accommodation Type"   => "Boligtype",
            "Total"                => "Total",
        ],
        'fr' => [
            "Confirmation Code:"   => "Code de confirmation :",
            "Travel Property"      => "Nom du logement",
            "Arrive"               => "Arrivée",
            "Depart"               => "Départ",
            "Accommodation Address"=> "Adresse du logement",
            "Guests"               => ["Voyageurs", "Voyageur"],
            "Accommodation Type"   => "Type de propriété",
            "Total"                => "Total",
        ],
        'de' => [
            "Confirmation Code:"   => "Bestätigungscode:",
            "Travel Property"      => "Reise-Unterkunft",
            "Arrive"               => ["Check-in", "Ankunft"],
            "Depart"               => ["Check-out", "Abreise"],
            "Accommodation Address"=> "Adresse der Unterkunft",
            "Guests"               => "Gäste",
            "Accommodation Type"   => "Typ der Unterkunft",
            "Total"                => "Gesamtsumme",
        ],
        'hu' => [
            "Confirmation Code:"   => "Visszaigazoló kód:",
            "Travel Property"      => "Utazási tulajdon",
            "Arrive"               => "Érkezés",
            "Depart"               => "Távozás",
            "Accommodation Address"=> "Szálláshely címe",
            "Guests"               => "Vendég",
            "Accommodation Type"   => "Szálláshely típusa",
            "Total"                => "Összesen",
        ],
        'es' => [
            "Confirmation Code:"   => "Código de confirmación:",
            "Travel Property"      => "Propiedad del viaje",
            "Arrive"               => "Llegada",
            "Depart"               => "Salida",
            "Accommodation Address"=> "Dirección del alojamiento",
            "Guests"               => "huéspedes",
            "Accommodation Type"   => "Tipo de alojamiento",
            "Total"                => "Total",
        ],
        'nl' => [
            "Confirmation Code:"   => "Bevestigingscode:",
            "Travel Property"      => "Vakantiewoning",
            "Arrive"               => "Aankomst",
            "Depart"               => "Vertrek",
            "Accommodation Address"=> "Accommodatie Adres",
            "Guests"               => "Gasten",
            "Accommodation Type"   => "Accommodatie Type",
            "Total"                => "Totaal",
        ],
        'ru' => [
            "Confirmation Code:"   => "Код подтверждения:",
            "Travel Property"      => "Место проживания",
            "Arrive"               => "Прибытие",
            "Depart"               => "Выезд",
            "Accommodation Address"=> "Адрес жилья",
            "Guests"               => "гостя",
            "Accommodation Type"   => "Тип жилья",
            "Total"                => "Итого",
        ],
        'sv' => [
            "Confirmation Code:"   => "Bekräftelsekod:",
            "Travel Property"      => "Reseboende",
            "Arrive"               => "Incheckning",
            "Depart"               => "Utcheckning",
            "Accommodation Address"=> "Boendets adress",
            "Guests"               => "Gäster",
            "Accommodation Type"   => "Typ av boende",
            "Total"                => "Totalt",
        ],
        'el' => [
            "Confirmation Code:"   => "Κωδικός επιβεβαίωσης:",
            "Travel Property"      => "Ιδιοκτησία ταξιδιού",
            "Arrive"               => "Άφιξη",
            "Depart"               => "Αποχώρηση",
            "Accommodation Address"=> "Διεύθυνση χώρου",
            "Guests"               => "Επισκέπτες",
            "Accommodation Type"   => "Τύπος χώρου",
            "Total"                => "Σύνολο",
        ],
        'it' => [
            "Confirmation Code:"   => ["Codice di conferma:", "Codice di Conferma:"],
            "Travel Property"      => "Proprietà del Viaggio",
            "Arrive"               => ["Check-in", "Arrivo"],
            "Depart"               => ["Check-out", "Partenza"],
            "Accommodation Address"=> ["Indirizzo dell'alloggio", "Indirizzo dell'Alloggio"],
            "Guests"               => ["Ospiti", "Ospite"],
            "Accommodation Type"   => ["Tipo di alloggio", "Tipo di Alloggio"],
            "Total"                => "Totale",
        ],
        'pl' => [
            "Confirmation Code:"   => "Kod potwierdzający:",
            "Travel Property"      => "Podsumowanie oferty",
            "Arrive"               => "Zameldowanie",
            "Depart"               => "Wymeldowanie",
            "Accommodation Address"=> "Cel podróży",
            "Guests"               => "Gości",
            "Accommodation Type"   => "Typ kwatery",
            "Total"                => "Suma",
        ],
        'cs' => [
            "Confirmation Code:"   => "Potvrzovací kód:",
            "Travel Property"      => "Cestovní nemovitosti",
            "Arrive"               => "Příjezd",
            "Depart"               => "Odjezd",
            "Accommodation Address"=> "Adresa ubytování",
            "Guests"               => "Hosté",
            "Accommodation Type"   => "Typ ubytování",
            "Total"                => "Celkem",
        ],
        'pt' => [
            "Confirmation Code:"   => ["Código de Confirmação:", "Código de confirmação:"],
            "Travel Property"      => "Propriedade de Viagem",
            "Arrive"               => ["Check-in", "Chega"],
            "Depart"               => ["Checkout", "Parte"],
            "Accommodation Address"=> ["Endereço da Acomodação", "Endereço do Alojamento"],
            "Guests"               => ["Hóspede", "Hóspedes"],
            "Accommodation Type"   => ["Tipo de Acomodação", "Tipo de Alojamento"],
            "Total"                => "Total",
        ],
        'tr' => [
            "Confirmation Code:"   => "Onay kodu:",
            "Travel Property"      => "Seyahat Mekanı",
            "Arrive"               => "Giriş",
            "Depart"               => "Çıkış",
            "Accommodation Address"=> "Konaklama Adresi",
            "Guests"               => "Misafir",
            "Accommodation Type"   => "Konaklama Türü",
            "Total"                => "Toplam",
        ],
        'en' => [
            "Arrive"               => ["Arrive", "Check In", "Check-in"],
            "Depart"               => ["Depart", "Check Out", "Checkout", "Check-out"],
            "Guests"               => ["Guest", "Guests"],
            "Accommodation Address"=> ["Accommodation Address", "Travel Destination"],
        ],
    ];

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'R';

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Code:")) . "]", null, true, "#" . $this->opt($this->t("Confirmation Code:")) . "\s+(.+)#");

        // ReservationDate
        $it['ReservationDate'] = $this->date = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Code:")) . "]/following::text()[normalize-space(.)][1]")));

        // Hotel Name
        $it['HotelName'] = $this->nextText($this->t("Travel Property"));

        // CheckInDate
        if (!$dateCheckIn = trim(implode(' ', $this->http->FindNodes("//tr[" . $this->eq($this->t("Arrive")) . "]/following-sibling::tr[position()<3]")))) {
            $dateCheckIn = $this->nextText($this->t("Arrive"));
        }
        $it['CheckInDate'] = strtotime($this->normalizeDate($dateCheckIn));

        if (!$timeCheckIn = $this->http->FindSingleNode('//text()[' . $this->eq($this->t("Arrive")) . ']/ancestor::td[1]/span[position()<3][last()]')) {
            $timeCheckIn = $this->http->FindSingleNode('//text()[' . $this->eq($this->t("Arrive")) . ']/following::text()[normalize-space(.)!=""][2][not(' . $this->eq($this->t("Depart")) . ')]');
        }

        if (!empty($it['CheckInDate']) && $timeCheckIn) {
            $timeCheckIn = preg_replace(['/^(\d+s*[ap]m)\s*-\s*\d+s*[ap]m$/i'], ['$1'], $timeCheckIn);                   // 11AM - 15PM    ->    11PM
            $timeCheckIn = preg_replace(['/^[^-\d]*\b(\d{1,2}s*[ap]m)$/i'], ['$1'], $timeCheckIn);                       // After 3PM    ->    3PM
            $timeCheckIn = preg_replace(['/^(\d+:\d+(?:s*[ap]m)?)\s*-\s*\d+:\d+(?:s*[ap]m)?$/i'], ['$1'], $timeCheckIn); // 17:00 - 23:00    ->    17:00
            $timeCheckIn = preg_replace(['/^.*?\s*(\d+:\d+(?:s*[ap]m)?)$/iu'], ['$1'], $timeCheckIn);                    // À partir de 15:00    ->    15:00

            if (preg_match('/^\d+s*[ap]m$/i', $timeCheckIn) || preg_match('/^\d+:\d+(?:\s*[ap]m)?$/i', $timeCheckIn)) {
                $it['CheckInDate'] = strtotime($timeCheckIn, $it['CheckInDate']);
            }
        }

        // CheckOutDate
        if (!$dateCheckOut = trim(implode(' ', $this->http->FindNodes("//tr[" . $this->eq($this->t("Depart")) . "]/following-sibling::tr[position()<3]")))) {
            $dateCheckOut = $this->nextText($this->t("Depart"));
        }
        $it['CheckOutDate'] = strtotime($this->normalizeDate($dateCheckOut));
        $timeCheckOut = $this->http->FindSingleNode('//text()[' . $this->eq($this->t("Depart")) . ']/ancestor::td[1]/span[2]');

        if (!empty($it['CheckOutDate']) && $timeCheckOut && (preg_match('/^\d+s*[ap]m$/i', $timeCheckOut) || preg_match('/^\d+:\d+(?:\s*[ap]m)?$/i', $timeCheckOut))) {
            $it['CheckOutDate'] = strtotime($timeCheckOut, $it['CheckOutDate']);
        }

        // Address
        $it['Address'] = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Accommodation Address"))}])[1]/ancestor::td[1]/following-sibling::td[1]");

        if (empty($it['Address'])) {
            $it['Address'] = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Accommodation Address"))}])[1]/following::text()[normalize-space(.)!=''][1]");
        }

        // Phone
        $phone = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Phone number")) . "]/ancestor::td[1]/following-sibling::td[1]", null, true, "#([\d\-\+ \(\)]{5,})#");

        if ($phone) {
            $it['Phone'] = $phone;
        }

        // GuestNames
        $it['GuestNames'] = array_filter(explode(", ", $this->nextText($this->t("Guests"))), function ($s) { return !preg_match("#\d#", $s); });

        // Guests
        $guest = $this->nextText($this->t("Guests"));

        if (preg_match("#\s+(\d+)\s+#", $guest, $m)) {
            $it['Guests'] = count($it['GuestNames']) + $m[1];
        } else {
            $it['Guests'] = count($it['GuestNames']);
        }

        // Kids
        // Rooms
        // Rate
        $it['Rate'] = $this->amount($this->http->FindSingleNode("(.//text()[normalize-space(.) = '" . $this->t("Total") . "'])[1]/following::text()[normalize-space(.)][1]/ancestor::div[1]/preceding-sibling::div[contains(., ' x ')][last()]/descendant::td[normalize-space(.)][1]", null, true, "#(.+) x #"));

        // RateType
        // Fees
        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->nextText($this->t("Accommodation Type"));

        // RoomTypeDescription
        // Cost
        $it['Cost'] = $this->amount($this->http->FindSingleNode("(.//text()[normalize-space(.) = '" . $this->t("Total") . "'])[1]/following::text()[normalize-space(.)][1]/ancestor::div[1]/preceding-sibling::div[contains(., ' x ')][last()]/descendant::td[normalize-space(.)][last()]"));

        // Taxes
        // Total
        $it['Total'] = $this->amount($this->nextText($this->t("Total")));

        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Total")));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled

        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        return $this->assignLang($body);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        if ($this->assignLang($this->http->Response['body']) === false) {
            return false;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
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

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+, ([^\s\d]+?),? (\d+), (\d{4})$#", //Thu, October 30, 2014
            "#^[^\s\d]+, ([^\s\d]+?),? (\d+)$#", //Wed, Jul 23
            "#^[^\s\d]+, (\d+)\. ([^\s\d]+?),? (\d{4})$#", //ti, 8. juli 2014
            "#^[^\s\d]+ (\d+ [^\s\d]+?),? (\d{4})$#", //[^\s\d]+ \d+ [^\s\d]+ \d{4}
            "#^(\d{4})\. ([^\s\d]+) (\d+)\., [^\s\d]+\.$#", //2014. november 16., v.
            "#^[^\s\d]+, (\d+) de ([^\s\d]+) de (\d{4})$#", //Sab, 17 de Enero de 2015
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $year",
            "$1 $2 $3",
            "$1 $2",
            "$3 $2 $1",
            "$1 $2 $3",
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
        $amount = (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));

        if ($amount == 0) {
            $amount = null;
        }

        return $amount;
    }

    private function currency($s)
    {
        $sym = [
            '€'   => 'EUR',
            'R$'  => 'BRL',
            '$'   => 'USD',
            '£'   => 'GBP',
            '₽'   => 'RUB',
            'S/.' => 'PEN',
            'Ft'  => 'HUF',
            'Kč'  => 'CZK',
            '₺'   => 'TRY',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        if (mb_strpos($s, '￥') !== false) {
            if ($this->lang = 'zh') {
                return 'CNY';
            }

            if ($this->lang = 'ja') {
                return 'JPY';
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
