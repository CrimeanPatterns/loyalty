<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class BConfirmation extends \TAccountChecker
{
    public $mailFiles = "edreams/it-1.eml, edreams/it-10856542.eml, edreams/it-11236488.eml, edreams/it-11885499.eml, edreams/it-12081635.eml, edreams/it-13121496.eml, edreams/it-13135914.eml, edreams/it-13150074.eml, edreams/it-13393528.eml, edreams/it-13495704.eml, edreams/it-13517135.eml, edreams/it-14740718.eml, edreams/it-14903002.eml, edreams/it-1672002.eml, edreams/it-1672012.eml, edreams/it-1672027.eml, edreams/it-1694578.eml, edreams/it-17710953.eml, edreams/it-1915457.eml, edreams/it-1979464.eml, edreams/it-2.eml, edreams/it-2110618.eml, edreams/it-2212953.eml, edreams/it-2484896.eml, edreams/it-2614212.eml, edreams/it-2863230.eml, edreams/it-29985117.eml, edreams/it-3093316.eml, edreams/it-3131725.eml, edreams/it-3185301.eml, edreams/it-33979499.eml, edreams/it-34023868.eml, edreams/it-4003007.eml, edreams/it-4006444.eml, edreams/it-4014336.eml, edreams/it-4028458.eml, edreams/it-4039120.eml, edreams/it-4109710.eml, edreams/it-4153178.eml, edreams/it-4153185.eml, edreams/it-5024018.eml, edreams/it-5030813.eml, edreams/it-5049375.eml, edreams/it-5086223.eml, edreams/it-5101734.eml, edreams/it-5101741.eml, edreams/it-5101742.eml, edreams/it-5111269.eml, edreams/it-5248083.eml, edreams/it-5308531.eml, edreams/it-5320148.eml, edreams/it-5351850.eml, edreams/it-5612601.eml, edreams/it-5706715.eml, edreams/it-5717366.eml, edreams/it-5717393.eml, edreams/it-5723916.eml, edreams/it-5723921.eml, edreams/it-5742623.eml, edreams/it-5742630.eml, edreams/it-5756219.eml, edreams/it-5768360.eml, edreams/it-5818777.eml, edreams/it-5822099.eml, edreams/it-5873347.eml, edreams/it-5951372.eml, edreams/it-61623296.eml, edreams/it-6243442.eml, edreams/it-6246531.eml, edreams/it-7172763.eml, edreams/it-7172848.eml, edreams/it-8553945.eml, edreams/it-8556656.eml, edreams/it-8601348.eml, edreams/it-8640040.eml, edreams/it-8654846.eml";
    public $reBody = [
        'edreams' => 'eDreams',
        'opodo'   => 'Opodo',
        'tllink'  => 'Travellink',
    ];

    private static $supportedProviders = ['edreams', 'opodo', 'tllink'];

    public static $dictionary = [
        "de" => [
            // "Confirmed booking" => "",
            "Booking references"                         => ["Buchungsnummern", "Buchungsnummern der Fluggesellschaft", "Bestätigungsnummern"],
            "Confirmation numbers for your booking are:" => "Ihre Buchungsnummern sind:",
            " to "                                       => [" nach ", " mit "],
            "Departure"                                  => "Abflug",
            "Arrival"                                    => "Ankunft",
            "ReferenceNumber"                            => ["Opodo-Referenznummer:", "Opodo-Buchungsnummer:", "Flug:", 'eDreams-Buchungsnummer:'],
            "ETKT"                                       => "ETKT",
            "Terminal"                                   => "Terminal",
            "Aircraft type - "                           => "Flugzeugtyp - ",
            "Class - "                                   => "Klasse - ",
            "The total cost of your reservation is :"    => ["Der Gesamtpreis Ihrer Buchung ist :", "Der Gesamtpreis Ihrer Buchung ist:"],
            //            "SeatsHeader" => "",
            "IN PROCESS" => "Buchungsanfrage erhalten",
        ],
        "fr" => [
            "Confirmed booking"                          => ["Réservation confirmée"],
            "Booking references"                         => ["Références de réservation", "Références de réservation de la compagnie aérienne", "Numéros de confirmation"],
            "Confirmation numbers for your booking are:" => "Numéros de référence pour votre réservation sont:",
            "IN PROCESS"                                 => ["EN COURS", "Nous traitons votre demande de réservation"],
            " to "                                       => " à ",
            "Departure"                                  => "Départ",
            "Arrival"                                    => "Arrivée",
            "ReferenceNumber"                            => ["Numéro de référence Opodo:", "Référence de réservation Opodo :", "Numéro de référence eDreams:", "Référence de réservation eDreams:"],
            "ETKT"                                       => "ETKT",
            "Terminal"                                   => "Terminal",
            "Aircraft type - "                           => "Type d’avion - ",
            "Class - "                                   => "Classe - ",
            "The total cost of your reservation is :"    => ["Le coût total de votre réservation est de :", "Le coût total de votre réservation est de:"],
            "SeatsHeader"                                => "Siège ou préférence",
            //			"IN PROCESS" => "",
        ],
        "pl" => [
            // "Confirmed booking" => "",
            "Booking references"                      => "Numery rezerwacji",
            " to "                                    => " do ",
            "Departure"                               => "Wylot",
            "Arrival"                                 => "Przylot",
            "ReferenceNumber"                         => "Numer Opodo:",
            "ETKT"                                    => "ETKT",
            "Terminal"                                => "Terminal",
            "Aircraft type - "                        => "Rodzaj samolotu - ",
            "Class - "                                => "Klasa - ",
            "The total cost of your reservation is :" => ["Całkowity koszt rezerwacji wynosi :", "Całkowity koszt rezerwacji wynosi:"],
            //            "SeatsHeader" => "",
            //			"IN PROCESS" => "",
        ],
        "it" => [
            // "Confirmed booking" => "",
            "Booking references"                         => ["Numeri di conferma", "Riferimenti della prenotazione", "Riferimenti prenotazione della compagnia aerea"],
            " to "                                       => " a ",
            "Confirmation numbers for your booking are:" => "I tuoi codici di prenotazione sono:",
            "Departure"                                  => "Partenza",
            "Arrival"                                    => "Arrivo",
            "ReferenceNumber"                            => ["Opodo riferimento prenotazione:", "Numero di riferimento eDreams:", "eDreams riferimento prenotazione:"],
            "ETKT"                                       => "ETKT",
            "Terminal"                                   => "Terminal",
            "Aircraft type - "                           => "Modello di aereo - ",
            "Class - "                                   => "Classe - ",
            // "The total cost of your reservation is :" => "",
            //            "SeatsHeader" => "",
            //			"IN PROCESS" => "",
        ],
        "da" => [
            "Confirmed booking"                       => "Bekræftet booking",
            "Booking references"                      => ["Bookingnumre", "Flyselskabets bookingnumre"],
            " to "                                    => [" til ", " to "],
            "Departure"                               => "Afrejse",
            "Arrival"                                 => "Ankomst",
            "ReferenceNumber"                         => ["Opodo-referencenummer:", "Travellink-referencenummer:", "Travellink-bookingnummer:", "Travellink bookingnummer:", "Opodo bookingnummer:"],
            "ETKT"                                    => "ETKT",
            "Terminal"                                => "Terminal",
            "Aircraft type - "                        => "Flytype - ",
            "Class - "                                => "Klasse - ",
            "The total cost of your reservation is :" => ["Den samlede pris for din reservation er :", "Den samlede pris for din reservation er:"],
            "SeatsHeader"                             => "Sæde eller præference",
            "IN PROCESS"                              => "Vi behandler din bookingforespørgsel",
        ],
        "es" => [
            "Confirmed booking"                          => "Reserva confirmada",
            "Booking references"                         => ["Números de confirmación", "Referencias de la reserva", "Códigos de reserva de tus vuelos", "Referencias de la reserva de la aerolínea", "Estado de tu solicitud de reserva"],
            "Confirmation numbers for your booking are:" => "Los números de localizador para tu reserva son:",
            " to "                                       => " a ",
            "IN PROCESS"                                 => ["EN CURSO", "Estamos procesando tu solicitud de reserva"],
            "Ida"                                        => ["Ida", "Vuelta"],
            "Pendiente"                                  => ["Pendiente", "En proceso"],
            "Departure"                                  => "Salida",
            "Arrival"                                    => "Llegada",
            "ReferenceNumber"                            => ["Número de referencia de eDreams:", "Localizador de tu reserva de eDreams:", "Localizador de tu solicitud de reserva de eDreams:", "Referencia de la reserva de eDreams:"],
            "ETKT"                                       => "ETKT",
            "Terminal"                                   => "Terminal",
            "Aircraft type - "                           => "Tipo de avión - ",
            "Class - "                                   => "Clase - ",
            "The total cost of your reservation is :"    => ["El precio total de tu reservación es de:", "El precio total de tu reservación es de :", "Coste total de tu reserva:"],
            //            "SeatsHeader" => "",
            //			"IN PROCESS" => "",
        ],
        "pt" => [
            "Confirmed booking"                          => "Reserva confirmada",
            "Booking references"                         => ["Números de confirmação", "Referências de reserva das companhias aéreas"],
            "Confirmation numbers for your booking are:" => "Números de localizador para a reserva são:",
            " to "                                       => " a ",
            "IN PROCESS"                                 => ["Estamos a processar o seu pedido de reserva", 'Estamos processando sua solicitação de reserva'],
            "Departure"                                  => "Partida",
            "Arrival"                                    => "Chegada",
            "ReferenceNumber"                            => ["Número de referência da eDreams:", 'Referência da reserva da eDreams', 'eDreams referência da reserva:', 'Referência de reserva da eDreams:'],
            "ETKT"                                       => "ETKT",
            "Terminal"                                   => "Terminal",
            "Aircraft type - "                           => "Tipo de avião - ",
            "Class - "                                   => "Classe - ",
            "The total cost of your reservation is :"    => "O custo total da sua reserva é de:",
            //            "SeatsHeader" => "",
        ],
        "nl" => [
            "Confirmed booking"                          => "Reservering bevestigd",
            "Booking references"                         => ["Bevestigingsnummers", "Reserveringsnummers van luchtvaartmaatschappij"],
            "Confirmation numbers for your booking are:" => "Het bevestigingsnummer van uw boeking is:",
            " to "                                       => [" tot ", ' naar '],
            "Departure"                                  => "Vertrek",
            "Arrival"                                    => "Aankomst",
            "ReferenceNumber"                            => ["eDreams-referentienummer:", "eDreams-reserveringsnummer:"],
            "ETKT"                                       => "ETKT",
            "Terminal"                                   => "Terminal",
            "Aircraft type - "                           => "Vliegtuigtype - ",
            "Class - "                                   => "Klasse - ",
            "The total cost of your reservation is :"    => "De totale prijs van uw reservering is:",
            "SeatsHeader"                                => "Stoelvoorkeur",
            "IN PROCESS"                                 => "Reserveringsverzoek ontvangen",
        ],
        "tr" => [
            // "Confirmed booking" => "",
            "Booking references" => "Onay numaraları",
            // "Confirmation numbers for your booking are:" => "",
            " to "             => [" te ", "’te "],
            "Departure"        => "Kalkış",
            "Arrival"          => "Varış",
            "ReferenceNumber"  => "eDreams referans numarası:",
            "ETKT"             => "ETKT",
            "Terminal"         => "Terminal",
            "Aircraft type - " => "Uçak tipi - ",
            "Class - "         => "Sınıf - ",
            //			"The total cost of your reservation is :" => "",
            //			"SeatsHeader" => "",
            //			"IN PROCESS" => "",
        ],
        "el" => [
            // "Confirmed booking" => "",
            "Booking references" => "Κωδικοί επιβεβαίωσης",
            // "Confirmation numbers for your booking are:" => "",
            " to "                                    => [" για ", ' προς '],
            "Departure"                               => "Αναχώρηση",
            "Arrival"                                 => "Άφιξη",
            "ReferenceNumber"                         => ["Αριθμός αναφοράς eDreams:", "eDreams αριθμός κράτησης:"],
            "ETKT"                                    => "ETKT",
            "Terminal"                                => ["Terminal", "Τερματικός σταθμός"],
            "Aircraft type - "                        => "Τύπος αεροσκάφους - ",
            "Class - "                                => "Θέση - ",
            "The total cost of your reservation is :" => "Το συνολικό ποσό της κράτησής σας είναι :",
            "SeatsHeader"                             => "Θέση ή προτίμηση",
            "IN PROCESS"                              => "Ελήφθη το αίτημα κράτησης",
        ],

        "sv" => [
            // "Confirmed booking" => "",
            "Booking references" => ["Flygbolagets bokningsnummer"],
            // "Confirmation numbers for your booking are:" => "",
            " to "                                    => [" till "],
            "Departure"                               => "Utresa",
            "Arrival"                                 => "Ankomst",
            "ReferenceNumber"                         => ["Opodo bokningsnummer:", "Travellink bokningsnummer:"],
            "ETKT"                                    => "ETKT",
            "Terminal"                                => "Terminal",
            "Aircraft type - "                        => "Aircraft type - ",
            "Class - "                                => "Klass - ",
            "The total cost of your reservation is :" => ["Totalpris:", "Totalpris :"],
            //            "SeatsHeader" => "",
            //			"IN PROCESS" => "",
        ],
        "no" => [
            // "Confirmed booking" => "",
            "Booking references" => ["Bestillingsreferanser"],
            // "Confirmation numbers for your booking are:" => "",
            " to "                                    => [" til "],
            "Departure"                               => "Avreise",
            "Arrival"                                 => "Ankomst",
            "ReferenceNumber"                         => ["Travellink-bestillingsreferanse:"],
            "ETKT"                                    => "ETKT",
            "Terminal"                                => "Terminal",
            "Aircraft type - "                        => "Flytype - ",
            "Class - "                                => "Klasse - ",
            "The total cost of your reservation is :" => "Totalpris :",
            //            "SeatsHeader" => "",
            //			"IN PROCESS" => "",
        ],
        "fi" => [
            // "Confirmed booking" => "",
            "Booking references" => ["Lentoyhtiön varausnumerot"],
            // "Confirmation numbers for your booking are:" => "",
            " to "                                    => [" – ", " to "],
            "Departure"                               => "Meno",
            "Arrival"                                 => "Saapuminen",
            "ReferenceNumber"                         => ["Travellink-varausnumero:", "Opodo-varausnumero:"],
            "ETKT"                                    => "ETKT",
            "Terminal"                                => "Terminaali",
            "Aircraft type - "                        => "Aircraft type - ",
            "Class - "                                => "Luokka - ",
            "The total cost of your reservation is :" => ["Varauksesi kokonaishinta on :", "Varauksesi kokonaishinta on:"],
            "SeatsHeader"                             => "Istumapaikka tai toive",
            //			"IN PROCESS" => "",
        ],
        "ja" => [
            "Confirmed booking"                          => "予約リクエストが受付されました",
            "Booking references"                         => ["予約リクエストの状況"],
            "Confirmation numbers for your booking are:" => "Het bevestigingsnummer van uw boeking is:",
            " to "                                       => [" tot ", ' naar '],
            "Departure"                                  => "往路",
            "Arrival"                                    => "到着",
            "ReferenceNumber"                            => ['eDreams予約番号:'],
            "ETKT"                                       => "ETKT",
            "Terminal"                                   => "Terminal",
            "Aircraft type - "                           => "Vliegtuigtype - ",
            "Class - "                                   => "搭乗クラス - ",
            "The total cost of your reservation is :"    => "予約計金額：:",
            //            "SeatsHeader" => "Stoelvoorkeur",
            //            "IN PROCESS" => "Reserveringsverzoek ontvangen",
        ],
        "en" => [
            "Confirmed booking"                         => ["Confirmed booking", "Booking confirmed"],
            "Booking references"                        => ["Booking references", "Airline booking references", "Confirmation numbers"],
            "ReferenceNumber"                           => ["Opodo reference number:", "Opodo booking reference:", "eDreams booking reference:"],
            "Trip summary"                              => ["Trip summary", "Confirmed booking"],
            "Confirmation numbers for your booking are:"=> ["Confirmation numbers for your booking are:", "Confirmed booking – pending of payment"],
            "The total cost of your reservation is :"   => ["The total cost of your reservation is :", "The total cost of your reservation is:"],
            //            "SeatsHeader"=>"",
            //			"IN PROCESS" => "",
        ],
    ];

    private $reSubject = [
        "de" => ["Buchungsbestätigung"],
        "fr" => ["Confirmation de réservation:"],
        "pl" => ["Potwierdzenie rezerwacji:"],
        "it" => ["Richiesta di prenotazione in elaborazione"],
        "da" => ["Bookingbekræftelse:", "Bookingforespørgslen er i gang (referencenr"],
        "es" => ["Confirmación reservación:"],
        "pt" => ["Confirmaçâo reserva:"],
        "nl" => ["Boekingsbevestiging:"],
        "tr" => ["Rezervasyon onayı:"],
        "el" => ["Επικύρωση κράτησης:"],
        "en" => ["Booking confirmation:", "Booking request in process"],
        "sv" => ["Din bokning har bekräftats! (Nummer:"],
        "no" => ["Bestillingsbekreftelse:"],
        "fi" => ["Varauksesi on vahvistettu! (Varausnumero:"],
        "ja" => ["予約リクエスト状況の更新 (予約番号:"],
    ];

    private $lang = '';

    private $langDetectors = [
        "de" => ["Abflug"],
        "fr" => ["Départ"],
        "pl" => ["Wylot"],
        "it" => ["Partenza"],
        "da" => ["Afrejse"],
        "es" => ["Salida"],
        "pt" => ["Partida"],
        "nl" => ["Vertrek"],
        "tr" => ["Kalkış"],
        "el" => ["Αναχώρηση"],
        "sv" => ["Utresa"],
        "no" => ["Avreise"],
        "fi" => ["Meno"],
        "ja" => ["往路"],
        "en" => ["Departure"], // last
    ];

    private $pax = [];

    private $date = 0;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@opodo.com') !== false
            || stripos($from, '@mailer.opodo.com') !== false
            || stripos($from, '@edreams.com') !== false;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ($this->getProvider($body) === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (($provider = $this->getProvider($parser->getHTMLBody())) === false) {
            $this->http->log("provider not detected");

            return null;
        }

        $this->http->FilterHTML = true;

        if ($this->assignLang() === false) {
            return false;
        }

        $this->date = strtotime($parser->getHeader('date'));

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
            'providerCode' => $provider,
        ];

        $payment = $this->http->FindSingleNode('//text()[' . $this->starts($this->t("The total cost of your reservation is :")) . ']/ancestor::div[1]', null, true, "#:\s*(.+)#");

        if (empty($payment) && !empty($this->http->FindSingleNode('//text()[' . $this->starts($this->t("The total cost of your reservation is :")) . ']/ancestor::div[1]', null, true, "#:\s*$#"))) {
            $payment = $this->http->FindSingleNode('//text()[' . $this->starts($this->t("The total cost of your reservation is :")) . ']/ancestor::div[2]', null, true, "#:\s*(.+)#");
        }

        // £ 1,642.49    |    1 501,67 €    |    1'619.40 Fr.
        if (preg_match('/^\s*(?<currency>[^\d)( ][^\d)(]*)\s*(?<amount>\d[,.\'\d]*)/', $payment, $matches) || preg_match('/(?<amount>\d[,.\'\d\s]*?)\s*(?<currency>[^\d)( ][^\d)(]*)$/', $payment, $matches)) {
            $result['parsedData']['TotalCharge']['Currency'] = $this->normalizeCurrency($matches['currency']);
            $result['parsedData']['TotalCharge']['Amount'] = $this->normalizeAmount($matches['amount']);
        }

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

    public static function getEmailProviders()
    {
        return self::$supportedProviders;
    }

    private function parseHtml(&$itineraries)
    {
        if ($lnk = $this->http->FindSingleNode("(//a[contains(@href, 'Year=')])[1]/@href")) {
            $this->date = strtotime($this->re("#Day=(\d+)#", $lnk) . '.' . $this->re("#Month=(\d+)#", $lnk) . '.' . $this->re("#Year=(\d+)#", $lnk));
        }

        $nodes = $this->http->XPath->query('//text()[' . $this->eq($this->t("Booking references")) . ']/following::table[1]/descendant::tr[not(.//tr) and count(./td)=3][normalize-space(.)]');

        $canceled = [];
        $rls = [];

        foreach ($nodes as $root) {
            if ($this->http->FindSingleNode("./td[3][" . $this->contains($this->t("Cancelado")) . "]", $root)) {
                $node = $this->http->FindSingleNode("./td[1]", $root);

                if (preg_match("#\(([A-Z]{3})\)" . $this->opt($this->t(" to ")) . ".*\(([A-Z]{3})\)#", $node, $m)) {
                    $canceled[$m[1]] = $m[2];
                }

                continue;
            }

            if ($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1][" . $this->eq($this->t("Hotel")) . "]", $root)) {
                continue;
            }

            if ($this->http->FindSingleNode("./td[3][" . $this->contains($this->t("Pendiente")) . "]", $root)) {
                $rl = CONFNO_UNKNOWN;
            } elseif (!$rl = $this->http->FindSingleNode("./td[2]", $root)) {
                $rls = [];

                continue;
            }

            if (substr_count($this->http->FindSingleNode("./td[1]", $root), "(") == 1) {
                $isOneCode = true;
                $r = [];
                $r['city'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^\s*\w+\s+(.+?)\(#u");
                $r['code'] = $rl;
                $rls[] = $r;
            } else {
                $r = [];
                $r['dep'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\(([A-Z]{3})\)" . $this->opt($this->t(" to ")) . ".*\([A-Z]{3}\)#");
                $r['arr'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\([A-Z]{3}\)" . $this->opt($this->t(" to ")) . ".*\(([A-Z]{3})\)#");
                $r['code'] = $rl;
                $rls[] = $r;
            }
        }

        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[1][not(.//tr)]/following-sibling::tr[1][" . $this->contains($this->t("Arrival")) . "]/..";
        $segments = $this->http->XPath->query($xpath);
        $airs = [];
        // without RL, booking in process
        if (empty($rls)) {
            if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("IN PROCESS")) . "]")) {
                $rl = CONFNO_UNKNOWN;
            } else {
                if (!$rl = $this->http->FindSingleNode("//tr[" . $this->starts($this->t("ReferenceNumber")) . " and not(.//tr)]", null, true, '/:\s*(.+)/')) {
                    $rl = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation numbers for your booking are:")) . "]/following::text()[normalize-space(.)][1]", null, true, '/:\s*(?:. )?(?:\d+-)?([A-Z\d]{5,7})$/u');
                }
            }

            foreach ($segments as $i=>$root) {
                if ($this->http->XPath->query("./ancestor::tr[.//img][1]//img[contains(@src, 'salida_tren') or contains(@src, 'llegada_tren')]", $root)->length === 0) {
                    $airs[$rl][] = $root;
                }
            }
        } else {
            $airs = [];

            foreach ($segments as $i=>$root) {
                if (!empty($isOneCode)) {
                    foreach ($rls as $key => $value) {
                        if (
                            !empty($this->http->FindSingleNode("(./ancestor::table[2]//tr[not(.//tr) and " . $this->contains($this->t("Departure")) . "])[1]/td[2]", $root, true, "#^\s*" . $value['city'] . "#")) && empty($this->http->FindSingleNode("ancestor::tr[count(td)=2 and descendant::img[contains(@src, 'tren')]]", $root))
                        ) {
                            $airs[$value['code']][] = $root;
                            unset($rls[$key]);

                            break;
                        }
                    }
                } else {
                    $depcode = $this->http->FindSingleNode("(./ancestor-or-self::table[2]//tr[not(.//tr) and " . $this->contains($this->t("Departure")) . "])[1]/td[2]", $root, true, "#.+\(([A-Z]{3})\)#");
                    $arrcode = $this->http->FindSingleNode("(./ancestor-or-self::table[2]//tr[not(.//tr) and " . $this->contains($this->t("Arrival")) . "])[last()]/td[2]", $root, true, "#.+\(([A-Z]{3})\)#");

                    if (isset($canceled[$depcode]) && $canceled[$depcode] = $arrcode) {
                        continue;
                    }

                    foreach ($rls as $value) {
                        if ($depcode == $value['dep'] && $arrcode == $value['arr'] && empty($this->http->FindSingleNode("ancestor::tr[count(td)=2 and descendant::img[contains(@src, 'tren')]]", $root))) {
                            $airs[$value['code']][] = $root;

                            continue 2;
                        }
                    }
                    $company = trim($this->http->FindSingleNode(".//tr[" . $this->contains($this->t("Departure")) . "]/td[3]/descendant::text()[normalize-space()][1]", $root));

                    if (empty($company)) {
                        $airs = [];

                        break;
                    }

                    $xpath2 = "./ancestor-or-self::table[1]/preceding-sibling::table[" . $this->contains($this->t("Arrival")) . "]";
                    $flights = $this->http->XPath->query($xpath2, $root);

                    foreach ($flights as $key => $value) {
                        $dep = $this->http->FindSingleNode(".//tr[not(.//tr) and " . $this->contains($this->t("Departure")) . " and (contains(normalize-space(), '" . $company . "'))]/td[2]", $value, true, "#.+\(([A-Z]{3})\)#");

                        if ($key == 0) {
                            $depcode = $dep;
                        }

                        if (empty($dep)) {
                            $depcode = '';
                        } elseif (empty($depcode) && !empty($dep)) {
                            $depcode = $dep;
                        }
                    }

                    if (empty($depcode)) {
                        $depcode = $this->http->FindSingleNode("(.//tr[not(.//tr) and " . $this->contains($this->t("Departure")) . "])[1]/td[2]", $root, true, "#\(([A-Z]{3})\)#");
                    }

                    $arrcode = $this->http->FindSingleNode("(.//tr[not(.//tr) and " . $this->contains($this->t("Arrival")) . "])[1]/td[2]", $root, true, "#\(([A-Z]{3})\)#");
                    $xpath2 = "./ancestor-or-self::table[1]/following-sibling::table[" . $this->contains($this->t("Arrival")) . "]";
                    $flights = $this->http->XPath->query($xpath2, $root);

                    foreach ($flights as $key => $value) {
                        $arr = $this->http->FindSingleNode(".//tr[not(.//tr) and " . $this->contains($this->t("Arrival")) . " and (contains(./ancestor::*[1], '" . $company . "'))]/td[2]", $value, true, "#.+\(([A-Z]{3})\)#");

                        if (!empty($arr)) {
                            $arrcode = $arr;
                        } else {
                            break;
                        }
                    }

                    foreach ($rls as $value) {
                        if ($depcode == $value['dep'] && $arrcode == $value['arr'] && empty($this->http->FindSingleNode("ancestor::tr[count(td)=2 and descendant::img[contains(@src, 'tren')]]", $root))) {
                            $airs[$value['code']][] = $root;

                            continue 2;
                        }
                    }

                    $depcode = $this->http->FindSingleNode("(.//tr[not(.//tr) and " . $this->contains($this->t("Departure")) . "])[1]/td[2]", $root, true, "#.+\(([A-Z]{3})\)#");
                    $arrcode = $this->http->FindSingleNode("(.//tr[not(.//tr) and " . $this->contains($this->t("Arrival")) . "])[last()]/td[2]", $root, true, "#.+\(([A-Z]{3})\)#");

                    foreach ($rls as $value) {
                        if ($depcode == $value['dep'] && $arrcode == $value['arr'] && empty($this->http->FindSingleNode("ancestor::tr[count(td)=2 and descendant::img[contains(@src, 'tren')]]", $root))) {
                            $airs[$value['code']][] = $root;

                            continue 2;
                        }
                    }

                    $airs = [];

                    break;
                }
            }
        }

        $passengerValues = array_values(array_unique(array_filter(array_map(function ($s) { return trim($s, ' .'); }, $this->http->FindNodes("//img[contains(@src,'/passenger.gif') or contains(@src,'/persona.gif') or contains(@src,'/persona_new.png')]/following::text()[normalize-space(.)][1]")))));
        $this->pax = $passengerValues;

        $seats = [];
        $seatRows = $this->http->XPath->query('//text()[' . $this->eq($this->t('SeatsHeader')) . ']/following::table[normalize-space(.)][1]/descendant::tr[1]/ancestor::*[1]/tr[normalize-space(.) and count(./td)=3]'); // example: it-13495704.eml

        foreach ($seatRows as $seatRow) {
            $routeArr = $routeDep = null;
            $route = $this->http->FindSingleNode('./td[1]', $seatRow);

            if (preg_match('/^\s*(.+?)\s*' . $this->opt($this->t(" to ")) . '\s*(.+?)\s*$/', $route, $matches)) {
                $routeDep = $matches[1];
                $routeArr = $matches[2];
            }
            $routeSeats = $this->http->FindNodes('./td[3]/descendant::td[not(.//td)]', $seatRow, '/^(\d{1,3}[A-Z])\s*(?:\b|$)/');
            $routeSeatValues = array_values(array_filter($routeSeats));

            if (!empty($routeDep) && !empty($routeArr)) {
                $seats[] = [
                    'DepName' => $routeDep,
                    'ArrName' => $routeArr,
                    'Seats'   => empty($routeSeatValues[0]) ? [] : $routeSeatValues,
                ];
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = [];
            $it['Kind'] = 'T';

            // Status
            if ($this->http->FindSingleNode('//text()[' . $this->eq($this->t("Confirmed booking")) . ']')) {
                $it['Status'] = 'confirmed';
            }

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("//tr[" . $this->starts($this->t("ReferenceNumber")) . " and not(.//tr)]", null, true, '/:\s*(.+)/');

            // Passengers
            if (!empty($passengerValues[0])) {
                $it['Passengers'] = $passengerValues;
            }

            // TicketNumbers
            $ticketNumbers = $this->http->FindNodes("//img[contains(@src,'/passenger.gif') or contains(@src,'/persona.gif') or contains(@src,'/persona_new.png')]/ancestor::tr[1]/..//text()[" . $this->eq($rl) . "]/following::text()[normalize-space(.)][1]", null, "#" . $this->t("ETKT") . "\s+(.+)#");
            $ticketNumberValues = array_values(array_unique(array_filter($ticketNumbers)));

            if (!empty($ticketNumberValues[0])) {
                $it['TicketNumbers'] = array_values(array_unique(array_filter($ticketNumberValues)));
            }

            // TripSegments
            $it['TripSegments'] = [];

            foreach ($roots as $root) {
                $itsegment = [];

                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#^\w{2}\s+(\d+)$#");

                if (empty($itsegment['FlightNumber'])) {
                    $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                }

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[1]/td[2]", $root, true, "#.+\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[1]/td[2]", $root, true, "#(.*)\s+\([A-Z]{3}\)#");

                // DepartureTerminal
                $terminalDep = $this->http->FindSingleNode("./tr[1]/td[2]", $root, true, "#" . $this->opt($this->t("Terminal")) . "\s+(.+)#");

                if ($terminalDep) {
                    $itsegment['DepartureTerminal'] = $terminalDep;
                }

                // DepDate
                $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("./tr[1]/td[1]/descendant::text()[normalize-space(.)][2]", $root));

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[2]/td[2]", $root, true, "#.+\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[2]/td[2]", $root, true, "#(.*)\s+\([A-Z]{3}\)#");

                // ArrivalTerminal
                $terminalArr = $this->http->FindSingleNode("./tr[2]/td[2]", $root, true, "#" . $this->opt($this->t("Terminal")) . "\s+(.+)#");

                if ($terminalArr) {
                    $itsegment['ArrivalTerminal'] = $terminalArr;
                }

                // ArrDate
                $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("./tr[2]/td[1]/descendant::text()[normalize-space(.)][2]", $root));

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#^(\w{2})\s+\d+$#");

                if (empty($itsegment['AirlineName'])) {
                    $itsegment['AirlineName'] = AIRLINE_UNKNOWN;
                }

                // Operator
                $operator = $this->http->FindSingleNode("./tr[1]/td[4]", $root);

                if ($operator) {
                    $itsegment['Operator'] = $operator;
                }

                // Aircraft
                $itsegment['Aircraft'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Aircraft type - ")) . "]", $root, true, "#" . $this->t("Aircraft type - ") . "(.+)#");

                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Class - ")) . "]", $root, true, "#" . $this->t("Class - ") . "(.+)#");

                $it['TripSegments'][] = $itsegment;
            }

            // Seats
            if (count($seats) !== 0) {
                foreach ($it['TripSegments'] as $key => $segment) {
                    foreach ($seats as $i => $value) {
                        if (
                            strpos($segment['DepName'], $seats[$i]['DepName']) !== false
                            && strpos($segment['ArrName'], $seats[$i]['ArrName']) !== false
                            && count($seats[$key]['Seats'])
                        ) {
                            $it['TripSegments'][$key]['Seats'] = $seats[$i]['Seats'];
                        }
                    }
                }
            }

            $itineraries[] = $it;
        }

        // TRAIN
        if (0 < ($roots = $this->http->XPath->query("//img[contains(@src, 'salida_tren') or contains(@src, 'llegada_tren')]/ancestor::tr[1]"))->length) {
            $itineraries[] = $this->parseTrain($roots);
        }
    }

    private function parseTrain(\DOMNodeList $roots)
    {
        /** @var \AwardWallet\ItineraryArrays\TrainTrip $it */
        $it = ['Kind' => 'T'];
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        if (0 < count($this->pax)) {
            $it['Passengers'] = $this->pax;
        }

        $tot = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Coste total de tu reserva')]/following::text()[normalize-space(.)][1]");

        if (preg_match('/([\d,]+)\s*(\D+)/', $tot, $m)) {
            $it['TotalCharge'] = str_replace(',', '.', $m[1]);
            $it['Currency'] = str_replace('€', 'EUR', $m[2]);
        }

        foreach ($roots as $root) {
            //TODO: only for es. need more examples
            $header = ucfirst(strtolower($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root))); //$this->t('Ida')

            if ($rl = $this->http->FindSingleNode("//tr[({$this->starts($header)}) and not(.//tr)]/following-sibling::tr[1]/td[2]", null, true, '/([A-Z\d]{5,9})/')) {
                $it['RecordLocator'] = $rl;
            } else {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }

            if ($status = $this->http->FindSingleNode("//tr[({$this->starts($header)}) and not(.//tr)]/following-sibling::tr[1]/td[3]", null, true, '/\s*(Confirmado)\s*/')) {
                $it['Status'] = $status;
            }

            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            if ($dur = $this->http->FindSingleNode('td[1]', $root, true, '/Duración\s*\:\s*(.+)/')) {
                $seg['Duration'] = $dur;
            }
            $xpathDep = "descendant::tr[contains(normalize-space(.), 'Salida') and not(.//tr)]";

            if ($depDate = $this->http->FindSingleNode($xpathDep . '/td[2]', $root)) {
                $seg['DepDate'] = $this->normalizeDate($depDate);
            }

            if ($depName = $this->http->FindSingleNode($xpathDep . '/td[3]', $root)) {
                $seg['DepName'] = $depName;
            }
            $xpathArr = "descendant::tr[contains(normalize-space(.), 'Llegada') and not(.//tr)]";

            if ($arrDate = $this->http->FindSingleNode($xpathArr . '/td[2]', $root)) {
                $seg['ArrDate'] = $this->normalizeDate($arrDate);
            }

            if ($arrName = $this->http->FindSingleNode($xpathArr . '/td[3]', $root)) {
                $seg['ArrName'] = $arrName;
            }
            $node = $this->http->FindSingleNode("//tr[({$this->starts($header)}) and not(.//tr)]/following-sibling::tr[1]/td[1]");

            if (preg_match("#\(([A-Z]{3})\)" . $this->opt($this->t(" to ")) . ".*\(([A-Z]{3})\)#", $node, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
            } else {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function assignLang()
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        $year = date("Y", $this->date);
        $in = [
            // 09:35 mer., 2 oct.
            "/^(\d+:\d+)\s*([^\d\W]{2,})[\s\.]*,\s*(\d{1,2})\s+([^\d\W]{3,})[.]*$/u",
            // 07/12 日 18:50
            '#^(\d+/\d+) (.) (\d+:\d+)$#u',
        ];
        $out = [
            "$4 $3 {$year}, $1",
            "$1/{$year}, $3",
        ];
        $outWeek = [
            '$2',
            '$2',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $date = $this->dateStringToEnglish(preg_replace($in, $out, $date), $this->lang);
            $date = EmailDateHelper::parseDateUsingWeekDay($date, $weeknum, 5);
        } else {
            $date = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date), $this->lang), false);
        }

        return $date;
    }

    private function dateStringToEnglish($date, $lang = 'en')
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $string): string
    {
        $string = preg_replace('/\s+/', '', $string);             // 11 507.00    ->    11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string); // 2,790        ->    2790    |    4.100,00    ->    4100,00    |    1'619.40    ->    1619.40
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00     ->    18800.00

        return $string;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        //		$this->http->log('$string = '.print_r( $string,true));
        $string = trim($string);
        $currences = [
            'INR' => ['₹'],
            'GBP' => ['£'],
            'EUR' => ['€', 'в‚¬'],
            'ARS' => ['AR $'],
            'AUD' => ['AU$'],
            'USD' => ['$', 'US$'],
            'CHF' => ['Fr.'],
            'ZAR' => ['R'],
            'THB' => ['฿'],
            'PEN' => ['S/.'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        if (preg_match("#\b([A-Z]{3})\b#", $string, $m)) {
            return $m[1];
        }

        return $string;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function getProvider($body)
    {
        foreach ($this->reBody as $prov=>$re) {
            if (strpos($body, $re) !== false) {
                return $prov;
            }
        }

        return false;
    }
}
