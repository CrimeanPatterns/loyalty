<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationConfirmed extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-1.eml, airbnb/it-1586931.eml, airbnb/it-1590725.eml, airbnb/it-1621825.eml, airbnb/it-1737800.eml, airbnb/it-1946671.eml, airbnb/it-1955403.eml, airbnb/it-1955526.eml, airbnb/it-1972205.eml, airbnb/it-2215449.eml, airbnb/it-2263570.eml, airbnb/it-2269367.eml, airbnb/it-2314868.eml, airbnb/it-2431018.eml, airbnb/it-2577101.eml, airbnb/it-2813401.eml, airbnb/it-2874430.eml, airbnb/it-2904535.eml, airbnb/it-9673308.eml";
    public $reFrom = "@airbnb.com";
    public $reSubject = [
        "en" => "Reservation Confirmed",
        "en2"=> "Booking inquiry for",
        "en3"=> "Request sent for",
        "da" => "Reservation bekræftet",
        "pt" => "Itinerário de Reserva de",
        "fr" => "Réservation confirmée",
        "it" => "Prenotazione Confermata",
        "tr" => "adlı kullanıcının Rezervasyon Seyahat Planı",
        "de" => "Buchung bestätigt",
        "hu" => "Foglalás megerősítve",
        "es" => "Reserva confirmada",
        "nl" => "Reservering Bevestigd",
        "el" => "Η κράτηση για το χώρο",
        'Reservation at',
        'Pre-approval at',
        'Prenotazione per',
        'Aanvraag bij',
        'nodigt je uit om',
        'Buchungsanfrage für',
        'invited the guest to book',
        'Réservation à ',
        'Consulta para',
        'Consulta sobre',
        'Fai una domanda per',
        'Foglalás',
        'Pre-approvazione per',
        'Preaprobación en',
        'Pyyntö koskien kohdetta',
        'Reserva en',
        'Reservasjon på',
        'Varaus kohteelle',
        'Бронирование:',
        'Αίτημα κράτησης για το χώρο',
        '回覆',
        'Reserva para',
        'Bokning av',
        'Rezerwacja oferty',
        'Rezervace',
        'Reserva a',
    ];
    public $reBody = 'Airbnb';
    public $reBody2 = [
        "en" => [
            ['www.airbnb.com', 'www.airbnb.ca', 'www.airbnb.ie', 'www.airbnb.co.uk'],
            [
                "Itinerary",
                "Inquiry", "Enquiry", "Enquiry Closed",
                "This is not a confirmed reservation",
                "Reply",
                "Pre-approval",
                "Pre-approve / Decline",
                "Book it now!",
            ], ],
        "da" => [
            ['www.airbnb.dk'],
            ["Din vært", "Indtjekning", "Se rejseplan"],
        ],
        "pt" => [
            ['www.airbnb.pt', 'www.airbnb.com.br'],
            ["Itinerário", "Reserve já", "Reserva", "Responder"], ],
        "fr" => [
            ['www.airbnb.fr', 'fr.airbnb.be'],
            ["Récapitulatif de votre voyage", "Répondre", "Réservez maintenant !"], ],
        "it" => [
            ['www.airbnb.it'],
            [
                "Incontra il tuo host",
                "Richiesta di Prenotazione",
                "Il tuo Host",
                "Pre-approva / Rifiuta",
                "Rispondi",
                "Pre-approvazione",
                "Prenotazione",
                "Richiesta",
            ], ],
        "tr" => [
            ['www.airbnb.com.tr'], [
                "Seyahat Planı",
            ], ],
        "de" => [
            ['www.airbnb.de'],
            ["Gastgeber", "Buchungsanfrage", "Antworten"], ],
        "hu" => [
            ['www.airbnb.hu'],
            ["A te házigazdád", "Válasz"], ],
        "es" => [
            ['www.airbnb.es', 'es.airbnb.com'],
            [
                "Tu anfitrión",
                "Ver itinerario",
                "Reserva",
                "Solicitud de reserva",
                "Consulta",
                "¡Resérvalo!",
            ], ],
        "nl" => [['www.airbnb.nl', 'www.airbnb.be'], ["Reisschema", "Aanvraag", "Reserveren", "Reserveer", "Antwoorden"]],
        "el" => [['www.airbnb.gr'], ["Δρομολόγιο", "Απάντηση"]],
        'fi' => [['www.airbnb.fi'], ['Varaa', 'Vastaa']],

        'no' => [['www.airbnb.no'], ['Svar']],
        'ru' => [['www.airbnb.ru'], ['Ответить', 'Забронируйте!']],

        'zh' => [['www.airbnb.com.tw', 'zh.airbnb.com'], ['回覆', '预准或拒绝']],
        'sv' => [['www.airbnb.se'], ['Svara']],
        'pl' => [['www.airbnb.pl'], ['Odpowiedz']],
        'cs' => [['www.airbnb.cz'], ['Odpovědět']],
        'ca' => [['www.airbnb.cat'], ['Respondre']],
    ];

    public static $dictionary = [
        "en" => [
            "Arrive"        => ["Arrive", "Check In", "Check-in", "CHECK IN"],
            "Depart"        => ["Depart", "Check Out", "Checkout", "CHECK OUT"],
            "Hi there "     => ["Hi there ", "Hello ", "Hi "],
            "Apartment -"   => ["Apartment -", "House -", "Loft -", "Villa -", "Vacation home -", "Flat -", "Bed & Breakfast -"],
            "Guests"        => ["Guests", "Guest", "Guests •"],
            'You paid'      => ['You paid', 'You would pay', 'You could earn'],
            "textGuestName" => [
                "You'll be traveling with "         => "#You'll be traveling with (.*?),#",
                "You'll be travelling with "        => "#You'll be travelling with (.*?),#",
                " is waiting to hear from"          => "#^(.+)\s+is waiting to hear from#",
                "reached out—don't miss the chance" => "#hours since (.+) reached out—don't miss#",
                "has sent you an inquiry about"     => "#^(.+)\s+has sent you an inquiry about#",
                "you've exchanged messages with"    => "#exchanged messages with ([^,]+), are #",
            ],
            //			"Pre-approve / Decline" => "",
            //			"your potential payout for this reservation is" => "",
            //			"This is not a confirmed reservation" => "",
        ],
        "da" => [
            "Confirmation Code:" => "Bekræftelseskode:",
            "Apartment -"        => ["Lejlighed -", "Hus -", "Bed & Breakfast -"],
            "Arrive"             => ["Tjek ind", "INDTJEKNING", "Ankommer", "Indtjekning"],
            "Depart"             => ["Tjek ind", "UDTJEKNING", "Tager af sted", "Rejser"],
            "Your Host"          => "Din vært",
            "Hi there "          => ["Hej ", "Hejsa "],
            "Guests"             => ["Gæster", "Gæst"],
            'You paid'           => 'Du har betalt',
            "textGuestName"      => [],
            "Cancellation Policy"=> "Afbestillingspolitik",
            "Accommodations"     => "Overnatningsmuligheder",
            "Total"              => "Total",
        ],
        "pt" => [
            "Confirmation Code:"=> "Código de Confirmação:",
            "Apartment -"       => ["Apartamento -", "Casa -", "Alojamento ecológico -"],
            "Arrive"            => ["Check-in", "Chega"],
            "Depart"            => ["Checkout"],
            "Your Host"         => "Seu Anfitrião",
            "Hi there "         => "NOTRANSLATED",
            "textGuestName"     => [
                "compartilhou planos de sua próxima viagem com você" => "#(.*?) compartilhou planos de sua próxima viagem com você#",
            ],
            "Guests"               => ["Hóspede", "Hóspedes"],
            'You paid'             => ['Você pagaria', 'Você pagou', 'Você irá ganhar'],
            "Cancellation Policy"  => "Política de Cancelamento",
            "Accommodations"       => "NOTTRANSLATED",
            "Total"                => "Total",
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.pt/rooms', 'airbnb.com.br/rooms'],
        ],
        "fr" => [
            "Confirmation Code:"   => "Code de confirmation :",
            "Apartment -"          => ["Loft -", "Appartement -", "Maison -", "Autre -"],
            "Arrive"               => ["Arrivée"],
            "Depart"               => ["Départ"],
            "Your Host"            => "Votre hôte",
            "Hi there "            => "Bonjour ",
            "textGuestName"        => [],
            "Guests"               => ["voyageurs", "voyageur"],
            'You paid'             => ['Vous avez payé', 'Vous gagnerez', 'Vous pourriez gagner', 'Vous devriez payer'],
            "Cancellation Policy"  => "Conditions d'annulation",
            "Accommodations"       => "NOTTRANSLATED",
            "Total"                => "Total",
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.fr/rooms', 'fr.airbnb.be/rooms'],
        ],
        "it" => [
            "Confirmation Code:"                            => ["Codice di conferma:", "Codice di Conferma:"],
            "Apartment -"                                   => ["Appartamento -", "Villa -", "Casa -", "Bungalow -"],
            "Arrive"                                        => ["Arrivo", "Check-in"],
            "Depart"                                        => ["Check-Out", "Check-out", "Partenza"],
            "Your Host"                                     => ["Incontra il tuo host", "Il tuo Host"],
            "Hi there "                                     => ["Ciao ", "Gentile "],
            "textGuestName"                                 => [],
            "Guests"                                        => ["Ospite", "Ospiti"],
            'You paid'                                      => ['Guadagnerai', 'Potresti guadagnare', 'Dovresti pagare', "Hai pagato"],
            "Cancellation Policy"                           => "Termini di Cancellazione",
            "Accommodations"                                => "NOTTRANSLATED",
            "Total"                                         => "Totale",
            "Pre-approve / Decline"                         => ["Accettare/Rifiutare", "Pre-approva / Rifiuta"],
            "your potential payout for this reservation is" => "potenziale per questa prenotazione è di ",
            //			"This is not a confirmed reservation" => "",
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.it/rooms'],
        ],
        "tr" => [
            "Confirmation Code:" => "Onay Kodu:",
            "Apartment -"        => ["Daire -"],
            "Arrive"             => ["Giriş"],
            "Depart"             => ["Çıkış"],
            "Your Host"          => "Ev Sahibiniz",
            "Hi there "          => "Merhaba ",
            "textGuestName"      => [],
            "Guests"             => ["Misafir"],
            "Cancellation Policy"=> "İptal Politikası",
            "Accommodations"     => "NOTTRANSLATED",
            "Total"              => "Toplam",
        ],
        "de" => [
            "Confirmation Code:" => "Bestätigungscode:",
            "Apartment -"        => ["Haus -", "Wohnung -"],
            "Arrive"             => ["Ankunft", 'Check-in'],
            "Depart"             => ["Abreise", "Check-Out"],
            "Your Host"          => ["Dein Gastgeber", "Triff deinen Gastgeber"],
            "Hi there "          => "Hallo ",
            "textGuestName"      => [],
            "Guests"             => ["Gäste", "Gast"],
            'You paid'           => ['Du könntest', 'Du würdest'],
            "Cancellation Policy"=> "Stornierungsbedingungen",
            "Accommodations"     => "NOTTRANSLATED",
            "Total"              => "Gesamtsumme",
        ],
        "hu" => [
            "Confirmation Code:" => "Visszaigazoló kód:",
            "Apartment -"        => ["Ház -", "Lakás -"],
            "Arrive"             => ["Érkezés"],
            "Depart"             => ["Távozás"],
            "Your Host"          => "A te házigazdád",
            "Hi there "          => "Szia ",
            "textGuestName"      => [],
            "Guests"             => ["Vendég"],
            'You paid'           => ['Fizetve:'],
            "Cancellation Policy"=> "Lemondási Feltételek",
            "Accommodations"     => "NOTTRANSLATED",
            "Total"              => "Összesen",
        ],
        "es" => [
            "Confirmation Code:"   => "Código de confirmación:",
            "Apartment -"          => ["Bed & Breakfast -", "Apartamento -", "Otros -", "Casa -", "Departamento -"],
            "Arrive"               => ["Llegada"],
            "Depart"               => ["Salida"],
            "Your Host"            => ["Tu anfitrión", "Conoce a tu anfitrión"],
            "Hi there "            => "Hola, ",
            "textGuestName"        => [],
            "Guests"               => ["huéspedes", 'huésped'],
            'You paid'             => ['Pagarías', 'Ganarás', 'Has pagado'],
            "Cancellation Policy"  => "Política de cancelación",
            "Accommodations"       => "NOTTRANSLATED",
            "Total"                => "Total",
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.es/rooms', 'es.airbnb.com/rooms'],
        ],
        "nl" => [
            "Confirmation Code:" => "Bevestigingscode:",
            "Apartment -"        => ["Appartement -", "Huis -", "Chalet -", "Loft -", "Bed & Breakfast -"],
            "Arrive"             => ["Aankomst"],
            "Depart"             => ["Vertrek"],
            "Your Host"          => ["Je Verhuurder"],
            "Hi there "          => "Beste ",
            "textGuestName"      => [],
            "Guests"             => ["Gasten", "Gast"],
            'You paid'           => ['Je zou', 'Jij betaalde'],
            "Cancellation Policy"=> "Annuleringsvoorwaarden",
            "Accommodations"     => "Accommodaties",
            "Total"              => "Totaal",
            //			"This is not a confirmed reservation" => ""
        ],
        "el" => [
            "Confirmation Code:" => "Κωδικός επιβεβαίωσης:",
            "Apartment -"        => ["Διαμέρισμα -"],
            "Arrive"             => ["Άφιξη"],
            "Depart"             => ["Αποχώρηση"],
            "Your Host"          => ["Ο οικοδεσπότης σας"],
            "Hi there "          => "Χαίρετε ",
            "textGuestName"      => [],
            "Guests"             => ["Επισκέπτες"],
            'You paid'           => ['Θα πληρώνατε', 'Πληρώσατε'],
            "Cancellation Policy"=> "Πολιτική ακύρωσης",
            "Accommodations"     => "",
            "Total"              => "Σύνολο",
            //			"This is not a confirmed reservation" => ""
        ],
        "fi" => [
            //			"Confirmation Code:"=>"",
            "Apartment -"=> ["Huoneisto -"],
            "Arrive"     => ["Sisäänkirjautuminen"],
            //			"Depart"=>[],
            //			"Your Host"=>[],
            //			"Hi there "=>"",
            "textGuestName" => [],
            "Guests"        => ["vierasta"],
            'You paid'      => ['Voisit ansaita rahaa', 'Ansaitset'],
            //			"Cancellation Policy"=>"",
            //			"Accommodations"=>"",
            //			"Total"=> "",
            //			"This is not a confirmed reservation" => ""
        ],
        "no" => [
            //			"Confirmation Code:"=>"",
            "Apartment -"=> ["Leilighet -"],
            "Arrive"     => ["Innsjekking"],
            //			"Depart"=>[],
            //			"Your Host"=>[],
            //			"Hi there "=>"",
            "textGuestName" => [],
            "Guests"        => ['Gjester', 'Gjest'],
            'You paid'      => ['Du vil tjene'],
            //			"Cancellation Policy"=>"",
            //			"Accommodations"=>"",
            //			"Total"=> "",
            //			"This is not a confirmed reservation" => ""
        ],
        "ru" => [
            //			"Confirmation Code:"=>"",
            "Apartment -"=> ['Квартира - ', 'Лодка - ', 'Гостевые апартаменты -'],
            "Arrive"     => ["Прибытие"],
            //			"Depart"=>[],
            //			"Your Host"=>[],
            //			"Hi there "=>"",
            "textGuestName" => [],
            "Guests"        => ['гостя', 'гость'],
            'You paid'      => ['Вы заработаете', 'Вы заплатили', 'Сумма к оплате'],
            //			"Cancellation Policy"=>"",
            //			"Accommodations"=>"",
            //			"Total"=> "",
            //			"This is not a confirmed reservation" => ""
        ],
        "zh" => [
            //			"Confirmation Code:"=>"",
            "Apartment -"=> ['公寓 - '],
            "Arrive"     => ["入住"],
            //			"Depart"=>[],
            //			"Your Host"=>[],
            //			"Hi there "=>"",
            "textGuestName" => [],
            "Guests"        => ['位房客'],
            'You paid'      => ['您已支付', '您将收取'],
            //			"Cancellation Policy"=>"",
            //			"Accommodations"=>"",
            //			"Total"=> "",
            //			"This is not a confirmed reservation" => ""
        ],
        "sv" => [
            //			"Confirmation Code:"=>"",
            "Apartment -"=> ['Lägenhet - '],
            "Arrive"     => ["Incheckning"],
            //			"Depart"=>[],
            //			"Your Host"=>[],
            //			"Hi there "=>"",
            "textGuestName" => [],
            "Guests"        => ['Gäster'],
            'You paid'      => ['Du betalade'],
            //			"Cancellation Policy"=>"",
            //			"Accommodations"=>"",
            //			"Total"=> "",
            //			"This is not a confirmed reservation" => ""
        ],
        "pl" => [
            //			"Confirmation Code:"=>"",
            "Apartment -"=> ['Apartament - '],
            "Arrive"     => ["Zameldowanie"],
            //			"Depart"=>[],
            //			"Your Host"=>[],
            //			"Hi there "=>"",
            "textGuestName" => [],
            "Guests"        => ['Gości'],
            'You paid'      => ['Zapłacono'],
            //			"Cancellation Policy"=>"",
            //			"Accommodations"=>"",
            //			"Total"=> "",
            //			"This is not a confirmed reservation" => ""
        ],
        "cs" => [
            //			"Confirmation Code:"=>"",
            "Apartment -"=> ['Byt - '],
            "Arrive"     => ["Příjezd"],
            //			"Depart"=>[],
            //			"Your Host"=>[],
            //			"Hi there "=>"",
            "textGuestName" => [],
            "Guests"        => ['Hosté'],
            'You paid'      => ['Zaplatil/a jsi'],
            //			"Cancellation Policy"=>"",
            //			"Accommodations"=>"",
            //			"Total"=> "",
            //			"This is not a confirmed reservation" => ""
        ],
        "ca" => [
            //			"Confirmation Code:"=>"",
            "Apartment -"=> ['Casa - '],
            "Arrive"     => ["Arribada"],
            //			"Depart"=>[],
            //			"Your Host"=>[],
            //			"Hi there "=>"",
            "textGuestName" => [],
            "Guests"        => ['Hostes'],
            'You paid'      => ['Heu pagat'],
            //			"Cancellation Policy"=>"",
            //			"Accommodations"=>"",
            //			"Total"=> "",
            //			"This is not a confirmed reservation" => ""
        ],
    ];

    public $lang = "";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        $beforeOrder = false;

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Code:")) . "]", null, true, "#" . $this->opt($this->t("Confirmation Code:")) . "\s+(.+)#");

        if (empty($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation Code:")) . "]"))) {
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
        }
        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Apartment -")) . "]/preceding::text()[normalize-space(.)][1]");

        if (empty($it['HotelName'])) {
            $it['HotelName'] = $this->http->FindSingleNode('(//a[' . $this->contains($this->t('www.airbnb.com/rooms'), '@href') . '][normalize-space(.)][1]//text()[normalize-space(.)])[1]');
        }

        if (empty($it['HotelName']) && !empty($this->t('www.airbnb.com/rooms'))) {
            if (is_array($this->t('www.airbnb.com/rooms'))) {
                $link = $names = array_map(function ($n) { return str_replace('/', "%2F", $n); }, $this->t('www.airbnb.com/rooms'));
            } else {
                $link = str_replace('/', "%2F", $this->t('www.airbnb.com/rooms'));
            }
            $it['HotelName'] = $this->http->FindSingleNode('(//a[' . $this->contains($link, '@href') . '][normalize-space(.)][1]//text()[normalize-space(.)])[1]');
        }
        // 2ChainName

        // CheckInDate
        // CheckOutDate
        $table = $this->http->FindNodes('//img[contains(@src,"caret")]/ancestor::table[1][' . $this->contains($this->t('Arrive')) . ']//text()[normalize-space(.)]');

        if (count($table) === 4) {
            $it['CheckInDate'] = strtotime($this->normalizeDate($table[1]));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($table[3]));
        }

        if (empty($it['CheckOutDate']) && empty($it['CheckOutDate'])) {
            if (!$date = trim(implode(" ", $this->http->FindNodes("//tr[" . $this->eq($this->t("Arrive")) . "]/following-sibling::tr[position()<3]")))) {
                $date = $this->nextText($this->t("Arrive"));
            }
            $it['CheckInDate'] = strtotime($this->normalizeDate($date));

            if (!$date = trim(implode(" ", $this->http->FindNodes("//tr[" . $this->eq($this->t("Depart")) . "]/following-sibling::tr[position()<3]")))) {
                $date = $this->nextText($this->t("Depart"));
            }
            $it['CheckOutDate'] = strtotime($this->normalizeDate($date));
        }
        $timeDep = $this->http->FindSingleNode("//text()[{$this->contains($this->t('check-in anytime after'))}]", null,
            true, "#{$this->opt($this->t('check-in anytime after'))}\s+(.+)#");

        if (!empty($timeDep)) {
            $it['CheckInDate'] = strtotime($timeDep, $it['CheckInDate']);
        }
        $timeArr = $this->http->FindSingleNode("//text()[{$this->contains($this->t('check-out anytime before'))}]",
            null, true, "#{$this->opt($this->t('check-out anytime before'))}\s+(.+)#");

        if (!empty($timeArr)) {
            $it['CheckOutDate'] = strtotime($timeArr, $it['CheckOutDate']);
        }
        // Address
        $it['Address'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Code:")) . "]/following::text()[normalize-space(.)][1]");

        if (empty($it['Address'])) {
            $it['Address'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("ADDRESS")) . "]/following::*[normalize-space(.)!=''][1]");
        }
        //		if (empty($it['Address'])) { // don't do this
        //			$it['Address'] = $it['HotelName'];
        //		}

        // DetailedAddress
        $regex = '#(?P<Addr>.*),\s+(?P<City>.*),\s+(?P<State>[\w\s\-]+)\s+(?P<PCode>\d+),\s+(?P<Country>.*)#';

        if (preg_match($regex, $it['Address'], $m)) {
            $da['AddressLine'] = trim($m['Addr']);
            $da['City'] = $m['City'];
            $da['StateProv'] = $m['State'];
            $da['PostalCode'] = $m['PCode'];
            $da['Country'] = $m['Country'];
            $it['DetailedAddress'] = $da;
        }

        // Phone
        $it['Phone'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your Host")) . "]/following::img[1]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]");

        // Fax
        // GuestNames
        $it['GuestNames'] = array_filter([trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hi there ")) . "]", null, true, "#^" . $this->opt($this->t("Hi there ")) . "(.+)$#"), '!:, ')]);

        if (isset($it['GuestNames'][0]) && strlen($it['GuestNames'][0]) > 30) {
            unset($it['GuestNames']);
        }

        if (empty($it['GuestNames']) && !empty($this->t("textGuestName"))) {
            foreach ($this->t("textGuestName") as $key => $value) {
                $it['GuestNames'] = array_filter([$this->http->FindSingleNode("//text()[" . $this->contains($key) . "]", null, true, $value)]);

                if (!empty($it['GuestNames'])) {
                    break;
                }
            }
        }

        // Guests
        $it['Guests'] = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Guests'))}]/ancestor::*[1][{$this->starts($this->t('Apartment -'))}]", null, true, "#(\d+)\s*" . $this->opt($this->t('Guests')) . "#");
        // Kids
        // Rooms
        // Rate
        $it['Rate'] = $this->amount($this->http->FindSingleNode("(.//text()[normalize-space(.) = '" . $this->t("Total") . "'])[1]/following::text()[normalize-space(.)][1]/ancestor::div[1]/preceding-sibling::div[contains(., ' x ')][last()]//td[normalize-space(.)][1]", null, true, "#(.+) x #"));

        // RateType
        // CancellationPolicy
        $it['CancellationPolicy'] = $this->nextText($this->t("Cancellation Policy"));

        // RoomType
        $it['RoomType'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Apartment -")) . "]");

        // RoomTypeDescription
        // Cost
        $it['Cost'] = $this->amount($this->nextText($this->t("Accommodations")));

        if (empty($it['Cost'])) {
            $it['Cost'] = $this->amount($this->http->FindSingleNode("(.//text()[normalize-space(.) = '" . $this->t("Total") . "'])[1]/following::text()[normalize-space(.)][1]/ancestor::div[1]/preceding-sibling::div[contains(., ' x ')][last()]//td[normalize-space(.)][last()]"));
        }

        // Taxes
        // Total
        // Currency
        $total = $this->nextText($this->t("Total"));

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("your potential payout for this reservation is")) . "]/following::b[1]");
        }

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[{$this->contains($this->t('You paid'))}]/ancestor::*[1]", null, true, "#" . $this->opt($this->t('You paid')) . "\s*(.+)#");
        }

        $it['Total'] = $this->amount($total);
        $it['Currency'] = $this->currency($total);

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $values) {
            $site = false;

            foreach ($values[0] as $value) {
                if (strpos($body, $value) !== false) {
                    $site = true;

                    break;
                }
            }
            $phrase = false;

            foreach ($values[1] as $value) {
                if (strpos($body, $value) !== false) {
                    $phrase = true;

                    break;
                }
            }

            if ($site && $phrase) {
                return true;
            } elseif ($phrase && strpos('airbnb.com', $value) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang => $values) {
            $site = false;

            foreach ($values[0] as $value) {
                if (strpos($this->http->Response["body"], $value) !== false) {
                    $site = true;

                    break;
                }
            }
            $phrase = false;

            foreach ($values[1] as $value) {
                if (strpos($this->http->Response["body"], $value) !== false) {
                    $phrase = true;

                    break;
                }
            }

            if ($site && $phrase) {
                $this->lang = substr($lang, 0, 2);

                break;
            }

            if ($phrase && strpos($this->http->Response["body"], '.airbnb.com') !== false && !isset($mbLang)) {
                $mbLang = substr($lang, 0, 2);
            }

            if ($site && $lang !== 'en') {
                $mbLang = substr($lang, 0, 2);
            }
        }

        if (empty($this->lang) && isset($mbLang)) {
            $this->lang = $mbLang;
        }

        if (empty($this->lang)) {
            $this->lang = "en";
        }

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

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+, ([^\s\d]+) (\d+)$#", //Fri, May 16
            "#^[^\s\d]+,? ([^\s\d]+) (\d+), (\d{4})$#", //Fri, December 05, 2014
            "#^[^\s\d]+, (\d+)\. ([^\s\d]+) (\d{4})$#", //lø, 26. juli 2014
            "#^[^\s\d]+, (\d+) de ([^\s\d]+) de (\d{4})$#", //Sáb, 14 de Fevereiro de 2015
            "#^[^\s\d]+ (\d+ [^\s\d]+?),? (\d{4})$#", //ven 19 juin 2015   |   Fri, 14 November, 2014
            "#^(\d{4})\. ([^\s\d]+) (\d+)\., [^\s\d]+\.$#", //2015. január 7., sze.
            '#^\s*\S+,?\s+(\d+)\.?\s+(\w+),?\s+(\d{4})\s*$#u', // wed, 12 may, 2014
            '#^\s*(\d{4})年(\d+)月(\d{1,2})日.*$#u', //2017年10月28日（六）
        ];
        $out = [
            "$2 $1 $year",
            "$2 $1 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2",
            "$3 $2 $1",
            '$1 $2 $3',
            '$3.$2.$1',
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
        $s = $this->re("#([^\d\,\.]+)#", $s);

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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(" . $text . ", \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
