<?php

namespace AwardWallet\Engine\cytric\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers ifao/Itinerary1 (in favor of cytric/BookingConfirmation)

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "cytric/it-10712848.eml, cytric/it-10714038.eml, cytric/it-10738108.eml, cytric/it-10760885.eml, cytric/it-10790430.eml, cytric/it-10790934.eml, cytric/it-10880482.eml, cytric/it-108952322.eml, cytric/it-137761053-fi.eml, cytric/it-145176155.eml, cytric/it-18400633.eml, cytric/it-19837711.eml, cytric/it-20420230.eml, cytric/it-20490158.eml, cytric/it-223796351.eml, cytric/it-66721683.eml, cytric/it-79815830.eml, cytric/it-92887033.eml, cytric/it-94355198.eml, cytric/it-94355237.eml, cytric/it-94512396.eml";

    public $reBody = [
        'en'  => ['Itinerary', 'Payment Information'],
        'en2' => ['Itinerary', 'Booking Code'],
        'de'  => ['Reiseplan', 'Zahlungsinformation'],
        'de2' => ['Reiseplan', 'Buchungscode'],
        'sv'  => ['Resplan', 'Betalningsinformation'],
        'sv2' => ['Resplan', 'Bokningsnummer'],
        'da'  => ['Rejseplan', 'Betalings Information'],
        'da2' => ['Rejseplan', 'Reservationsnr'],
        'cs'  => ['Itinerář', 'Potvrzení pro'],
        'it'  => ['Itinerario', 'Informazioni relative al pagamento'],
        'it2' => ['Itinerario', 'Codice di prenotazione'],
        'no'  => ['Reiserute', 'Betalingsinformasjon'],
        'nl'  => ['Reisroute', 'Luchtvaartmaatschappij referentie'],
        'fr'  => ['Itinéraire', 'Infos sur le paiement'],
        'fr2' => ['Itinéraire', 'Informations sur le paiement'],
        'fr3' => ['Itinéraire', 'Type de voyage'],
        'pl'  => ['Plan podróży', 'Informacje dot. płatności'],
        'es'  => ['Itinerario', 'Información de Pago'],
        'fi'  => ['Matkasuunnitelma', 'Maksutiedot'],
    ];
    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Nights'           => ['Nights', 'Night'],
            'Tel'              => ['Tel', 'Telephone', 'Phone'],
            'Fax'              => ['Fax', 'Telefax'],
            'availableInfo'    => [
                'Please find below the room and rate description and the information available at this time',
                'Here are the room and rate description and the information currently available',
            ],
            "Sitzplatzbuchung" => ["Sitzplatzbuchung", "Reserved seat"],

            //            "Cancellation Date:" => "",
            //            "Cancellation Number:" => "",
            //            "Cancellation Number:" => "",
            'Bonus card information' => 'Frequent Traveller Information',
            'The Ticket Number is:'  => 'The Ticket Number is:',
        ],
        'nl' => [
            'Itinerary'     => 'Reisroute',
            'Booking Code:' => 'Boekingscode:',
            //            "Cancellation Date:" => "",
            //            "Cancellation Number:" => "",
            'to'                        => 'to',
            'Booking Date:'             => 'Boekingsdatum:',
            // 'Terminal' => '',
            'Flight Duration'           => 'Vluchtduur',
            'Miles'                     => 'Mijlen',
            // 'Ticket #' => '',
            'Fare for all travelers in' => [
                'Tarief voor alle reizigers in',
            ],
            'Total Cost of the complete Trip in' => 'Totaaltarief voor alle reizigers voor alle luchtvervoersegmenten in',
            //Car
            //'Flight'               => 'Flug',
            //'Type of Car'          => 'Mietwagen-Typ',
            //'Total rate amount in' => ['Totaalprijs van de volledige reis in'],
            //Hotel
            //'Nights'          => ['Nächte', 'Nächt', 'Nacht'],
            //'Tel'             => 'Telefon',
            //'Fax'             => ['Fax', 'Telefax'],
            //'Hotel Reference' => 'Hotel Referenz',
            //'Reservation ID'  => 'Reservierungs-ID',
            //'availableInfo'   => [
            //    'Bitte finden Sie hier die Zimmer- und Ratenbeschreibung und die gegenwärtig verfügbaren Informationen',
            //    'Hier sind die Zimmer- und Preisbeschreibung und die derzeit verfügbaren Informationen', ],
            //Train
            //'Coach' => 'Wagen',
            //'Seat'  => 'Sitz',
            //            'The booking has been canceled' => '',
        ],
        'de' => [
            'Itinerary'     => 'Reiseplan',
            'Booking Code:' => 'Buchungscode:',
            //            "Cancellation Date:" => "",
            //            "Cancellation Number:" => "",
            'to'                        => 'nach',
            'Booking Date:'             => 'Buchungsdatum:',
            'Status:'                   => ['Status der Buchung:', 'Status:'],
            // 'Terminal' => '',
            'Flight Duration'           => ['Flugdauer'],
            'Miles'                     => 'Meilen',
            'Ticket #'                  => 'Ticket #',
            'Fare for all travelers in' => [
                'Tarif für alle Reisenden in',
                'Gesamtpreis für alle Reisenden für alle Flugsegmente in',
            ],
            'Total Cost of the complete Trip in' => 'Gesamtbetrag der gesamten Reise in',
            //Car
            'Flight'               => 'Flug',
            'Type of Car'          => 'Mietwagen-Typ',
            'Total rate amount in' => ['Gesamtbetrag in', 'Gesamtbetrag einschließlich Steuern und Entgelten'],
            //Hotel
            'Nights'          => ['Nächte', 'Nächt', 'Nacht'],
            'Tel'             => 'Telefon',
            'Fax'             => ['Fax', 'Telefax'],
            'Hotel Reference' => ['Hotel Referenz', 'Hotelreferenz'],
            'Reservation ID'  => 'Reservierungs-ID',
            'availableInfo'   => [
                'Bitte finden Sie hier die Zimmer- und Ratenbeschreibung und die gegenwärtig verfügbaren Informationen',
                'Hier sind die Zimmer- und Preisbeschreibung und die derzeit verfügbaren Informationen', ],
            //Train
            'Coach' => 'Wagen',
            'Seat'  => 'Sitz',
            //            'The booking has been canceled' => '',
            'Bonus card information' => 'Vielreisenden-Information',
            'The Ticket Number is:'  => 'Die Ticketnummer ist:',
        ],
        'sv' => [
            'Itinerary'          => 'Resplan',
            'Booking Code:'      => 'Bokningsnummer:',
            "Cancellation Date:" => "Avbokningsdatum:",
            //            "Cancellation Number:" => "",
            'to'                        => 'till',
            'Booking Date:'             => ['Bokningsdatum:', 'Datum för förfrågan:'],
            // 'Terminal' => '',
            'Flight Duration'           => 'Flygtid',
            'Miles'                     => 'Miles',
            'Ticket #'                  => 'Biljett #',
            'Fare for all travelers in' => [
                'Pris för alla resenärer i',
            ],
            'Total Cost of the complete Trip in' => ['Totalbelopp för hela resan i', 'Total price of the complete trip in'],
            //Car
            'Flight'               => 'Flyg',
            'Type of Car'          => 'Typ av bil',
            'Total rate amount in' => ['Totalbelopp i'],
            //Hotel
            'in'              => 'i',
            'Nights'          => ['Nätter', 'övernattning'],
            'Tel'             => ['Telefonnummer', 'Phone', 'Telefon:'],
            'Fax'             => ['Faxnummer', 'Fax'],
            'Hotel Reference' => 'Hotellreferens',
            //            'Reservation ID' => '',
            'availableInfo' => 'Nedan följer en beskrivning av rummet och den prisinformation som finns tillgänglig just nu',
            //Train
            'Coach'                         => 'Vagn',
            'Seat'                          => 'Sittplats',
            'Sitzplatzbuchung'              => 'Reserverad(e) sittplats(er):',
            'The booking has been canceled' => 'Reservationen avbokades den',
            //Programm
            'Bonus card information' => 'Information om bonuskort',
        ],
        'da' => [
            'Itinerary'     => 'Rejseplan',
            'Booking Code:' => 'Reservationsnr.:',
            //            "Cancellation Date:" => "",
            //            "Cancellation Number:" => "",
            'to'                        => 'til',
            'Booking Date:'             => 'Reservationsdato:',
            // 'Terminal' => '',
            'Flight Duration'           => 'Flyvetid',
            'Miles'                     => 'Mil',
            // 'Ticket #' => '',
            'Fare for all travelers in' => [
                'Totalpris for hele rejsen i',
            ],
            'Total Cost of the complete Trip in' => 'Takst i alt uden skatter og afgifter i',
            //Car
            //            'Flight' => '',
            //            'Type of Car' => '',
            'Total rate amount in' => ['Total ratebeløb i'],
            //Hotel
            'in'     => 'i',
            'Nights' => ['Nat', 'Nætter'],
            'Tel'    => ['Tlf.', 'Telefon:'],
            'Fax'    => 'Fax',
            //            'Hotel Reference' => '',
            //            'Reservation ID' => '',
            'availableInfo' => 'Se den værelsespris og beskrivelse som er tilgængelig på nuværende tidspunkt nedenfor',
            //Train
            //            'Coach' => '',
            //            'Seat' => '',
            //            'The booking has been canceled' => '',
        ],
        'cs' => [
            'Itinerary'     => 'Itinerář',
            'Booking Code:' => 'Kód rezervace:',
            //            "Cancellation Date:" => "",
            //            "Cancellation Number:" => "",
            //            'to' => '',
            'Booking Date:' => 'Datum rezervace:',
            // 'Terminal' => '',
            //            'Flight Duration' => '',
            //            'Miles' => '',
            // 'Ticket #' => '',
            //            'Fare for all travelers in' => [
            //                'Totalpris for hele rejsen i'
            //            ],
            'Total Cost of the complete Trip in' => 'Celkové náklady za tuto cestu v',
            //Car
            //            'Flight' => '',
            //            'Type of Car' => '',
            //            'Total rate amount in' => ['Total ratebeløb i', ],
            //Hotel
            'in'              => 'v',
            'Nights'          => ['Noci', 'Noc'],
            'Tel'             => 'Telefon',
            'Fax'             => 'Fax',
            'Hotel Reference' => 'Referenční číslo hotelu',
            //            'Reservation ID' => '',
            'availableInfo' => 'Níže naleznete popis pokoje, ceny a informací, které jsou momentálně k dispozici',
            //Train
            //            'Coach' => '',
            //            'Seat' => '',
            //            'The booking has been canceled' => '',
        ],
        'it' => [
            'Itinerary'     => 'Itinerario',
            'Status:'       => 'Stato:',
            'Booking Code:' => 'Codice di prenotazione:',
            //            "Cancellation Date:" => "",
            //            "Cancellation Number:" => "",
            'to'                        => 'per',
            'Booking Date:'             => 'Data di prenotazione:',
            // 'Terminal' => '',
            'Flight Duration'           => 'Durata volo',
            'Miles'                     => 'Miglia',
            // 'Ticket #' => '',
            'Fare for all travelers in' => [
                'Tariffa per tutti i passeggeri in',
            ],
            'Total Cost of the complete Trip in' => 'Costo complessivo del viaggio in',
            //Car
            'Flight'               => 'Volo',
            'Type of Car'          => 'Tipo di veicolo',
            'Total rate amount in' => ['Totale in'],
            //Hotel
            'in'              => 'in',
            'Nights'          => ['Notti', 'Notte'],
            'Tel'             => 'Telefono',
            'Fax'             => 'Fax',
            'Hotel Reference' => 'Riferimento Hotel',
            //            'Reservation ID' => '',
            'availableInfo' => 'Di seguito, è visualizzata la descrizione della camera e della tariffa ed ulteriori informazioni attualmente disponibili',
            //Train
            //            'Coach' => '',
            //            'Seat' => '',
            //            'The booking has been canceled' => '',
        ],

        'no' => [
            'Itinerary'     => 'Reiserute',
            'Status:'       => 'Status:',
            'Booking Code:' => 'Referansenummer:',
            //            "Cancellation Date:" => "",
            //            "Cancellation Number:" => "",
            'to'                        => 'til',
            'Booking Date:'             => 'Bestillingsdato:',
            // 'Terminal' => '',
            'Flight Duration'           => 'Flyreisens varighet',
            'Miles'                     => 'Miles',
            'Ticket #'                  => 'Billett #',
            'Fare for all travelers in' => [
                'Billettpris for alle reisende i',
            ],
            'Total Cost of the complete Trip in' => ['Samlet kostnad for hele reisen i', 'Total price of the complete trip in', 'Totalpris på hele reisen i'],
            //Car
            'Flight'               => 'Volo',
            'Type of Car'          => 'Tipo di veicolo',
            'Total rate amount in' => ['Totale in'],
            //Hotel
            'in'              => ['in', 'i'],
            'Nights'          => ['Notti', 'Notte', 'Netter'],
            'Tel'             => ['Telefono', 'Telefon:'],
            'Fax'             => 'Fax',
            'Hotel Reference' => ['Riferimento Hotel', 'Hotellreferanse:'],
            //            'Reservation ID' => '',
            'availableInfo' => ['Di seguito, è visualizzata la descrizione della camera e della tariffa ed ulteriori informazioni attualmente disponibili',
                'Her er rom- og prisbeskrivelsen samt informasjonen som er tilgjengelig for øyeblikket:', ],
            //Train
            //            'Coach' => '',
            //            'Seat' => '',
            //            'The booking has been canceled' => '',
        ],

        'fr' => [
            'Itinerary'          => 'Itinéraire',
            'Status:'            => ['Statut:', 'Status:'],
            'Booking Code:'      => 'Code de réservation:',
            "Cancellation Date:" => "Date d'annulation:",
            //            "Cancellation Number:" => "",
            'to'                        => ['vers'],
            'Booking Date:'             => 'Date de réservation:',
            // 'Terminal' => '',
            'Flight Duration'           => 'Durée de vol',
            'Miles'                     => 'Miles',
            'Ticket #'                  => 'Billet #',
            'Fare for all travelers in' => [
                'Prix pour tous les passagers en',
            ],
            'Total Cost of the complete Trip in' => [
                'Prix total pour tous les segments de train de tous les voyageurs en',
                'Total price of the complete trip in',
            ],
            //Car
            //'Flight' => 'Volo',
            'Type of Car'          => 'Type de voiture',
            'Total rate amount in' => ['Montant total en'],

            //Hotel
            'in'              => 'en',
            'Nights'          => ['Nuit'],
            'Tel'             => ['Phone', 'Téléphone'],
            'Fax'             => 'Fax',
            'Hotel Reference' => "Référence de l'hôtel",
            // 'Reservation ID' => '',
            'availableInfo' => 'Veuillez trouvez, ci-dessous, la description détaillée de la chambre et du tarif',

            //Train
            'Coach'                         => 'Voiture',
            'Seat'                          => 'Siège',
            'Sitzplatzbuchung'              => 'Siège(s) réservé(s)',
            'Coach'                         => 'Voiture',
            'Seat'                          => 'Siège',
            'The booking has been canceled' => 'La réservation a été annulée',

            'Bonus card information' => 'Infos Frequent Traveller',
        ],
        'pl' => [ // it-94355237.eml
            'Itinerary'          => 'Plan podróży',
            'Status:'            => 'Status:',
            'Booking Code:'      => 'Kod rezerwacji:',
            "Cancellation Date:" => "Data anulacji:",
            //            "Cancellation Number:" => "",
            'to'                        => ['do'],
            'Booking Date:'             => 'Data rezerwacji:',
            // 'Terminal' => '',
            'Flight Duration'           => 'Czas lotu',
            'Miles'                     => 'Mile',
            'Ticket #'                  => 'Bilet #',
            'Fare for all travelers in' => [
                'Stawka dla wszystkich podróżujących w',
            ],
            'Total Cost of the complete Trip in' => [
                'Total price of the complete trip in',
            ],
            //Car
            //'Flight' => 'Volo',
            'Type of Car'          => 'Rodzaj samochodu',
            'Total rate amount in' => ['Cena całkowita w'],

            //Hotel
            //            'in' => 'en',
            //            'Nights' => ['Nuit'],
            //            'Tel' => 'Phone',
            //            'Fax' => 'Fax',
            //            'Hotel Reference' => 'Référence de l\'hôtel',
            //            'Reservation ID' => '',
            //            'availableInfo' => 'Veuillez trouvez, ci-dessous, la description détaillée de la chambre et du tarif',

            //Train
            //            'Coach' => 'Voiture',
            //            'Seat' => 'Siège',
            //            'Sitzplatzbuchung' => 'Siège(s) réservé(s)',
            //            'Coach' => 'Voiture',
            //            'Seat' => 'Siège',

            'The booking has been canceled' => 'Rezerwacja została anulowana',
        ],
        'es' => [ // it-108952322.eml
            'Itinerary'          => 'Itinerario',
            'Status:'            => 'Estado:',
            'Booking Code:'      => 'Código de Reserva:',
            // 'Cancellation Date:' => '',
            // 'Cancellation Number:' => '',
            'to'                                 => 'a',
            'Booking Date:'                      => 'Fecha de la Reserva:',
            // 'Terminal' => '',
            'Flight Duration'                    => 'Duración del Vuelo',
            'Miles'                              => 'Millas',
            'Ticket #'                           => 'Billete #',
            'Fare for all travelers in'          => 'Tarifa para todos los Viajeros en',
            'Total Cost of the complete Trip in' => 'Precio total del viaje completo en',

            // Car
            // 'Flight' => '',
            // 'Type of Car' => '',
            'Total rate amount in' => 'Importe Total en',

            // Hotel
            'in'     => 'en',
            'Nights' => ['Noche', 'Noches'],
            'Tel'    => 'Teléfono',
            // 'Fax' => '',
            'Hotel Reference' => 'Referencia de Hotel',
            // 'Reservation ID' => '',
            'availableInfo' => 'Aquí encontrarás las descripciones detalladas de la habitación y de la tarifa, al igual que las demás informaciones actualmente disponibles',

            // Train
            // 'Coach' => '',
            // 'Seat' => '',
            // 'Sitzplatzbuchung' => '',
            // 'Coach' => '',
            // 'Seat' => '',

            // 'The booking has been canceled' => '',
        ],
        'fi' => [ // it-137761053-fi.eml
            'Itinerary'     => 'Matkasuunnitelma',
            'Status:'       => 'Tila:',
            'Booking Code:' => 'Varauskoodi:',
            // 'Cancellation Date:' => '',
            // 'Cancellation Number:' => '',
            'to'                        => 'päättyen',
            'Booking Date:'             => 'Varauspäivämäärä:',
            'Terminal'                  => 'Terminaali',
            'Flight Duration'           => 'Lentoaika',
            'Miles'                     => 'Mailit',
            // 'Ticket #' => '',
            'Fare for all travelers in' => [
                'Matkalipun hinta kaikille matkustajille',
                'Total fare for all travelers for all air segments in',
            ],
            'Total Cost of the complete Trip in' => 'Total price of the complete trip in',
            //Car
            // 'Flight' => '',
            // 'Type of Car' => '',
            // 'Total rate amount in' => '',
            //Hotel
            // 'Nights' => '',
            // 'Tel' => '',
            // 'Fax' => '',
            // 'Hotel Reference' => '',
            // 'Reservation ID'  => '',
            // 'availableInfo'   => '',
            //Train
            // 'Coach' => '',
            // 'Seat'  => '',
            // 'The booking has been canceled' => '',
        ],
    ];

    private $code;
    private static $headers = [
        'cytric' => [
            'from' => ["cytric.net"],
            'subj' => [
                'Booking Confirmation',
                'Booking Change',
                'Cancelation',
                'Buchungs-Bestätigung',
                'Bokningsbekräftelse', // sv
                'Avbokning', // sv
                'Confirmation de la réservation', // fr
                'Annulation', // fr
                'Potwierdzenie rezerwacji', // pl
                'Anulowanie', // pl
                'Confirmación de Reserva', // es
                'Varauksen vahvistus', // fi
            ],
        ],
        'fcmtravel' => [
            'from' => ["@de.fcm.travel", '@fcm.com'],
            'subj' => [
                'Booking Confirmation',
                'Booking Change',
                'Cancelation',
                'Buchungs-Bestätigung',
                'Bokningsbekräftelse', // sv
                'Avbokning', // sv
                'Confirmation de la réservation', // fr
                'Annulation', // fr
                'Potwierdzenie rezerwacji', // pl
                'Anulowanie', // pl
                'Confirmación de Reserva', // es
                'Varauksen vahvistus', // fi
            ],
        ],
        'amadeus' => [
            'from' => ["@amadeus.com"],
            'subj' => [
                'Booking Confirmation',
                'Booking Change',
                'Cancelation',
                'Buchungs-Bestätigung',
                'Bokningsbekräftelse', // sv
                'Avbokning', // sv
                'Confirmation de la réservation', // fr
                'Annulation', // fr
                'Potwierdzenie rezerwacji', // pl
                'Anulowanie', // pl
                'Confirmación de Reserva', // es
                'Varauksen vahvistus', // fi
                'Boekingsbevestiging', //nl
            ],
        ],
        'bcd' => [
            'from' => ["BCD Travel", "@bcdtravel."],
            'subj' => [
                'Booking Confirmation',
                'Booking Change',
                'Cancelation',
                'Buchungs-Bestätigung',
                'Bokningsbekräftelse', // sv
                'Avbokning', // sv
                'Confirmation de la réservation', // fr
                'Annulation', // fr
                'Potwierdzenie rezerwacji', // pl
                'Anulowanie', // pl
                'Confirmación de Reserva', // es
                'Varauksen vahvistus', // fi
            ],
        ],
        'wagonlit' => [ // it-137761053-fi.eml
            'from' => ['CWT Finland', '@carlsonwagonlit.fi', '@mycwt.com'],
            'subj' => [
                'Booking Confirmation',
                'Booking Change',
                'Cancelation',
                'Buchungs-Bestätigung',
                'Bokningsbekräftelse', // sv
                'Avbokning', // sv
                'Confirmation de la réservation', // fr
                'Annulation', // fr
                'Potwierdzenie rezerwacji', // pl
                'Anulowanie', // pl
                'Confirmación de Reserva', // es
                'Varauksen vahvistus', // fi
            ],
        ],
    ];
    private $keywords = [
        'foxrewards' => [
            'Fox Rent-A-Car',
        ],
        'sixt' => [
            'Sixt',
        ],
        'avis' => [
            'Avis',
            'AVIS',
        ],
        'ezrentacar' => [
            'EZ Rent A Car',
            'E-Z',
        ],
        'thrifty' => [
            'Thrifty Car Rental',
        ],
        'hertz' => [
            'Hertz',
        ],
    ];

    private $xpath = [
        'time'  => 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")',
        'time2' => '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))',
    ];

    private $cntFlights;
    private $cntHotels;
    private $cntCars;
    private $cntTrains;
    private $date;

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->code = $this->getProvider($parser);

        if (!empty($this->code)) {
            $email->setProviderCode($this->code);
            $this->logger->debug("[PROVIDER]: {$this->code}");
        } else {
            $this->logger->debug("[PROVIDER]: default");
        }

        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->logger->debug("[LANG]: {$this->lang}");

        $this->date = strtotime($parser->getHeader('date'));

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[starts-with(normalize-space(@alt),'Cytric') or starts-with(normalize-space(@alt),'cytric')] | //a[contains(@href,'cytric.net')] | //text()[starts-with(normalize-space(),'Cytric') or starts-with(normalize-space(),'cytric')]")->length > 0
            || stripos(implode('', $parser->getRawBody()), 'cytric_iCalendar1.ics') > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'Bitte berücksichtigen Sie vor Reisebeginn die')]/preceding::text()[contains(normalize-space(), '@bcdtravel.')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'fcm.travel')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'amadeus.com')]")->length > 0
        ) {
            return $this->assignLang();
        }

        if ($this->http->XPath->query("(//*[contains(normalize-space(.),'Important: The information enclosed here may change without notice.')])[1]")->length > 0) {
            $this->lang = 'en';

            return true;
        }

        return false;
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
        return array_keys(self::$headers);
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }
        // from body "From:"
        $from = ['From:', 'Van:', 'Von:', 'Lähettäjä:', 'De:'];
        $node = $this->http->FindSingleNode("(//text()[{$this->contains($from)}]/ancestor::*[contains(.,'@')][1])[last()]");

        if (!empty($node)) {
            foreach (self::$headers as $code => $arr) {
                foreach ($arr['from'] as $search) {
                    if ((stripos($node, $search) !== false)) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email): void
    {
        $email->obtainTravelAgency();

        $tripIdNames = ['Cytric interne Trip ID', 'cytric interne Trip ID', 'Cytric - sisäinen matkatunnus'];
        $tripId = $this->http->FindSingleNode("//text()[{$this->starts($tripIdNames)}]");

        if (preg_match("/^({$this->opt($tripIdNames)})\s*:\s*([-A-Z\d]{5,})$/", $tripId, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        $xpathContainer = "(self::table or self::div or self::blockquote)";

        $xpath = "//text()[{$this->eq($this->t('Itinerary'))}]/ancestor::*[{$xpathContainer}][ following-sibling::*[{$xpathContainer} and normalize-space()] ][1]/following-sibling::*[{$xpathContainer}][{$this->contains($this->t('Booking Code:'))}]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH - root]: " . $xpath);

        $subj = str_replace(":", '',
            $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Total Cost of the complete Trip in'))}])[1]/ancestor::tr[1]",
                null, false, "#{$this->opt($this->t('Total Cost of the complete Trip in'))}\s+(.+)#"));

        $tot = $this->getTotalCurrency($subj);

        if ($tot['Total'] !== null) {
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $flightXpath = "descendant::img[1][contains(@alt,'Air')]";
        $flightXpath2 = "descendant::text()[{$this->starts($this->t('Flight Duration'))}]";
        $hotelXpath = "descendant::img[1][contains(@alt,'Hotel')]";
        $carXpath = "descendant::img[1][contains(@alt,'Car')]";
        $trainXpath = "descendant::img[1][contains(@alt,'Rail')]";

        $this->cntFlights = $this->http->XPath->query($xpath . '/' . $flightXpath)->length;

        if ($this->cntFlights == 0) {
            $this->cntFlights = $this->http->XPath->query($xpath . "/" . $flightXpath2)->length;
        }
        $this->cntHotels = $this->http->XPath->query($xpath . '/' . $hotelXpath)->length;
        $this->cntCars = $this->http->XPath->query($xpath . '/' . $carXpath)->length;
        $this->cntTrains = $this->http->XPath->query($xpath . '/' . $trainXpath)->length;

        foreach ($nodes as $root) {
            if ($this->http->XPath->query($flightXpath, $root)->length > 0) {
                if (!$this->parseFlight($root, $email)) {
                    return;
                }
            } elseif ($this->http->XPath->query($hotelXpath, $root)->length > 0) {
                $newRoots = $this->http->XPath->query("descendant::tr[count(*)=2 and *[1][normalize-space()='']/{$hotelXpath}][1]/*[2][normalize-space()]");

                if ($newRoots->length === 1) {
                    $root = $newRoots->item(0);
                }

                if (!$this->parseHotel($root, $email)) {
                    return;
                }
            } elseif ($this->http->XPath->query($carXpath, $root)->length > 0) {
                if (!$this->parseCar($root, $email)) {
                    return;
                }
            } elseif ($this->http->XPath->query($trainXpath, $root)->length > 0) {
                if (!$this->parseTrain($root, $email)) {
                    return;
                }
            } elseif ($this->http->XPath->query($flightXpath2, $root)->length > 0) {
                // after detect with img/@alt
                if (!$this->parseFlight($root, $email)) {
                    return;
                }
            }
        }

        if ($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("The booking has been canceled")) . " or " . $this->contains('Cancellation of the trip on') . "])[1]")) {
            foreach ($email->getItineraries() as $it) {
                $it->general()
                    ->status('Cancelled')
                    ->cancelled();
            }
        }
    }

    private function parseFlight(\DOMNode $root, Email $email): bool
    {
        $segFormat = 'normal';

        if ($this->http->XPath->query("descendant::text()[normalize-space()][1][{$this->xpath['time2']}]", $root)->length > 0) {
            $segFormat = 'crushed-999';
        }
        $this->logger->debug('Flight segment format: ' . $segFormat);

        $hRoot = $root;

        if ($segFormat === 'crushed-999') {
            $newRoots = $this->http->XPath->query("preceding-sibling::*[normalize-space()][1]", $root);

            if ($newRoots->length > 0) {
                $hRoot = $newRoots->item(0);
            }
        }

        $conf = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Booking Code:'))}]",
            $root, false, "#{$this->opt($this->t('Booking Code:'))} ([A-Z\d]{5,})#");

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'flight' && in_array($conf, array_column($it->getConfirmationNumbers(), 0)) === true) {
                $r = $it;

                break;
            }
        }

        if (!isset($r)) {
            $r = $email->add()->flight();

            $r->general()
//            ->travellers($this->http->FindNodes("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][2]//text()[normalize-space(.)!='']",
//                $hRoot, "#(.+?) *(?:\(|$)#"))
                ->travellers($this->http->FindNodes("./descendant::text()[normalize-space(.)!=''][{$this->contains($this->t('to'))}][1]/ancestor::tr[1]/following::tr[1]",
                    $hRoot, "#(.+?) *(?:\(|$)#"))
                ->confirmation($conf)
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Booking Date:'))}]",
                    $root, false, "#{$this->opt($this->t('Booking Date:'))} (.+)#")))
                ->status($this->http->FindSingleNode("(./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][{$this->starts($this->t('Status:'))}])[1]",
                    $root, false, "#{$this->opt($this->t('Status:'))} *(.+?)(?:,|$)#"));

            // Program
            $acc = array_filter($this->http->FindNodes("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][1]//text()[normalize-space(.)!='']" .
                "[not(" . $this->contains(["Government", "regeringen"]) . ")]",
                $root, "# \(.*\b([A-Z\d]{7,})\b.*\)$#"));

            foreach ($acc as $ac) {
                $r->program()->account($ac, preg_match("/X{5,}/i", $ac) > 0);
            }

            if (!empty($this->http->FindSingleNode("(./descendant::text()[" . $this->contains($this->t("Cancellation Date:")) . "])[1]", $root))) {
                $r->general()->cancelled();
                $canNum = $this->http->FindSingleNode("(./descendant::text()[" . $this->starts($this->t("Cancellation Number:")) . "])[1]", $root, null,
                    "/" . $this->opt($this->t("Cancellation Number:")) . "\s*(\d{4,})(\W|$)/");

                if (!empty($canNum)) {
                    $r->general()->cancellationNumber($canNum);
                }
            }
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[normalize-space()][{$this->contains($this->t('to'))}][1]/ancestor::tr[1]", $hRoot, true, "#(.+) {$this->opt($this->t('to'))} #"));

        $xpathSeg = "descendant::text()[{$this->xpath['time2']}]/ancestor::table[2]";
        $nodeSeg = $this->http->XPath->query($xpathSeg, $root);

        foreach ($nodeSeg as $i => $rootSeg) {
            $s = $r->addSegment();

            if ($i === 0) {
                $num = 2;
            } else {
                $num = 1;
            }

            for ($p = 1; $p < 5; $p++) {
                $node = $this->http->FindSingleNode("descendant::tr[not(.//tr) and normalize-space()][$p]/descendant::text()[{$this->xpath['time']}]", $rootSeg);

                if (!empty($node)) {
                    $num = $p - 1;

                    break;
                }
            }

            $node = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]",
                $rootSeg);

            $seatText = $this->http->FindSingleNode("./descendant::tr[starts-with(normalize-space(), 'Status')]", $rootSeg);

            if (preg_match_all("/(\d{1,2}[A-Z])/", $seatText, $seatMatches)) {
                $s->extra()->seats($seatMatches[1]);
            }

            if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)\s+(.+?), .+?: *([A-Z\d]{5,})/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                    ->confirmation($m[4]);
                $s->extra()->cabin($m[3]);

                if ($this->cntFlights === 1) {
                    $flight = $m[1] . ' ' . $m[2];
                    $subj = str_replace(":", '',
                        $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket #'))}]/ancestor::table[1][contains(normalize-space(.),'{$flight}')]/following-sibling::table[{$this->contains($this->t('Fare for all travelers in'))}]",
                            null, false, "#{$this->opt($this->t('Fare for all travelers in'))}\s+(.+)#u"));
                    $tot = $this->getTotalCurrency($subj);

                    if ($tot['Total'] !== null) {
                        $r->price()
                            ->total($tot['Total'])
                            ->currency($tot['Currency']);
                    }
                }
            } else {
                if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)\s+(.+?Class)$/", $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                    $s->extra()->cabin($m[3]);
                }
            }

            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                $airlineStr = $s->getAirlineName() . ' ' . $s->getFlightNumber();
                $accounts = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Bonus card information'))}]/following::text()[{$this->contains($airlineStr)}][1]/ancestor::tr[1]",
                    null, "/\s(\d{6,})/"));

                if (!empty($accounts)) {
                    foreach ($accounts as $account) {
                        if (!in_array($account, array_column($r->getAccountNumbers(), 0))) {
                            $r->program()
                                ->account($account, false);
                        }
                    }
                }

                $tickets = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('The Ticket Number is:'))}][preceding::text()[normalize-space()][1][{$this->contains($airlineStr)}][1]]",
                    null, "/{$this->opt($this->t('The Ticket Number is:'))}\s*(\d{3,}[\d\-]+)\s*$/"));

                if (!empty($tickets)) {
                    foreach ($tickets as $ticket) {
                        if (!in_array($ticket, array_column($r->getTicketNumbers(), 0))) {
                            $r->issued()
                                ->ticket($ticket, false);
                        }
                    }
                }
            }

            $reRoute = "#^\s*(?:[[:alpha:]]+ (?<anotherDay>(?:(?:\d+|[[:alpha:]]+)[ \.,]{0,3}){3}\d{4})\s*,\s*)?(?<name>.+)\s+\((?<code>[A-Z]{3})\)(?:, [^,]*{$this->opt($this->t('Terminal'))}(?<terminal>.*))?$#u";

            $num++;
            $time = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]/descendant::text()[normalize-space(.)!=''][1]",
                $rootSeg);

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            }
            $node = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]/descendant::text()[normalize-space(.)!=''][2]",
                $rootSeg);

            if (preg_match($reRoute, $node, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code']);

                if (isset($m['terminal']) && !empty(trim($m['terminal']))) {
                    $s->departure()
                        ->terminal(trim($m['terminal']));
                }

                if (!empty($m['anotherDay'])) {
                    $date2 = $this->normalizeDate($m['anotherDay']);

                    if (!empty($date2) && !empty($time)) {
                        $date = $date2;
                        $s->departure()
                            ->date(strtotime($time, $date));
                    }
                }
            }
            $num++;

            $time = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]/descendant::text()[normalize-space(.)!=''][1]",
                $rootSeg);

            if (!empty($date) && !empty($time)) {
                $s->arrival()
                    ->date(strtotime($time, $date));
            }
            $node = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]/descendant::text()[normalize-space(.)!=''][2]",
                $rootSeg);

            if (preg_match($reRoute, $node, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code']);

                if (isset($m['terminal']) && !empty(trim($m['terminal']))) {
                    $s->arrival()
                        ->terminal(trim($m['terminal']));
                }

                if (!empty($m['anotherDay'])) {
                    $date2 = $this->normalizeDate($m['anotherDay']);

                    if (!empty($date2) && !empty($time)) {
                        $date = $date2;
                        $s->arrival()
                            ->date(strtotime($time, $date));
                    }
                }
            }

            $node = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$this->starts($this->t('Flight Duration'))}]",
                $rootSeg);
            $s->extra()
                ->duration($this->re("#{$this->opt($this->t('Flight Duration'))}: *(.+?),#u", $node))
                ->miles($this->re("#{$this->opt($this->t('Miles'))}: *(\d+)#u", $node));
        }

        return true;
    }

    private function parseTrain(\DOMNode $root, Email $email): bool
    {
        $r = $email->add()->train();
        $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][{$this->contains($this->t('to'))}][1]",
            $root, false, "#(.+) {$this->opt($this->t('to'))} #"));
        $r->general()
            ->travellers($this->http->FindNodes("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][1]//text()[normalize-space(.)!='']",
                $root, "#(.+?) *(?:\(|$)#"))
            ->confirmation($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Booking Code:'))}]",
                $root, false, "#{$this->opt($this->t('Booking Code:'))} ([A-Z\d]{5,})#"))
            ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Booking Date:'))}]",
                $root, false, "#{$this->opt($this->t('Booking Date:'))}\s*(.+)#")));
        $status = $this->http->FindSingleNode("(./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][{$this->starts($this->t('Status'))}])[1]",
            $root, false, "#{$this->opt($this->t('Status:'))} *(.+?)(?:,|$)#");

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        if (!empty($this->http->FindSingleNode("(./descendant::text()[" . $this->contains($this->t("Cancellation Date:")) . "])[1]", $root))) {
            $r->general()->cancelled();
            $canNum = $this->http->FindSingleNode("(./descendant::text()[" . $this->starts($this->t("Cancellation Number:")) . "])[1]", $root, null,
                "/" . $this->opt($this->t("Cancellation Number:")) . "\s*(\d{4,})(\W|$)/");

            if (!empty($canNum)) {
                $r->general()->cancellationNumber($canNum);
            }
        }

        $acc = array_filter($this->http->FindNodes("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][1]//text()[normalize-space(.)!='']",
            $root, "# \(.*\b([A-Z\d]{7,})\b.*\)$#"));

        if (count($acc) > 0) {
            if (preg_match("#^X{5,}#", $acc[0])) {
                $r->program()->accounts($acc, true);
            } else {
                $r->program()->accounts($acc, false);
            }
        }

        $xpathSeg = "./descendant::text()[{$this->contains($this->t('Seat'))}]/ancestor::table[1]";
        $nodeSeg = $this->http->XPath->query("$xpathSeg", $root);

        foreach ($nodeSeg as $i => $rootSeg) {
            $s = $r->addSegment();

            if ($i === 0) {
                $num = 2;
            } else {
                $num = 1;
            }
            $node = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]/descendant::text()[normalize-space(.)!=''][1]",
                $rootSeg);

            if (preg_match("#(.+?) \- (.+?)(?: (\d+))?$#", $node, $m)) {
                $s->extra()
                    ->type($m[2])
                    ->service($m[1]);

                if (isset($m[3])) {
                    $s->extra()->number($m[3]);
                } else {
                    $s->extra()->noNumber();
                }
            } elseif (preg_match("#^([A-Z\d]+)[ ]*(\d+)$#", $node, $m)) {
                $s->extra()
                    ->type($m[1])
                    ->number($m[2]);
            }

            if ($this->cntTrains === 1) {
                $subj = str_replace(":", '',
                    $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket #'))}]/ancestor::table[1][contains(normalize-space(.),'{$node}')]/following-sibling::table[{$this->contains($this->t('Fare for all travelers in'))}]",
                        null, false, "#{$this->opt($this->t('Fare for all travelers in'))}\s+(.+)#"));
                $tot = $this->getTotalCurrency($subj);

                if ($tot['Total'] !== null) {
                    $r->price()
                        ->total($tot['Total'])
                        ->currency($tot['Currency']);
                }
            }
            $node = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]/descendant::text()[normalize-space(.)!=''][2]",
                $rootSeg, false, "#(.+?),#");
            $s->extra()->cabin($node);

            $num++;
            $time = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]/descendant::text()[normalize-space(.)!=''][1]",
                $rootSeg);
            $s->departure()
                ->date(strtotime($time, $date));
            $node = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]/descendant::text()[normalize-space(.)!=''][2]",
                $rootSeg);
            $s->departure()
                ->name($node);
            $num++;
            $time = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]/descendant::text()[normalize-space(.)!=''][1]",
                $rootSeg);
            $s->arrival()
                ->date(strtotime($time, $date));
            $node = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$num}]/descendant::text()[normalize-space(.)!=''][2]",
                $rootSeg);
            $s->arrival()
                ->name($node);

            $node = $this->http->FindSingleNode("./descendant::tr[not(.//tr) and normalize-space(.)!=''][{$this->starts($this->t('Sitzplatzbuchung'))}]",
                $rootSeg);

            if (!empty($node)) {
                $s->extra()
                    ->car($this->re("#{$this->opt($this->t('Coach'))}: (\w+)#u", $node))
                    ->seat($this->re("#{$this->opt($this->t('Seat'))}: (\w+)#u", $node));
            }
        }

        return true;
    }

    private function parseHotel(\DOMNode $root, Email $email): bool
    {
        // examples: it-10790934.eml

        $r = $email->add()->hotel();

        // General
        $confs = [];
        $conf = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Booking Code:'))}]",
            $root);

        if (preg_match("#^\s*({$this->opt($this->t('Booking Code:'))}) ([A-z\d]{5,})\b#u", $conf, $m)) {
            $confs[trim($m[1], " :")] = $m[2];
        }
        $conf = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][{$this->starts($this->t('Hotel Reference'))}]",
            $root);

        if (preg_match("#({$this->opt($this->t('Hotel Reference'))})[: ]+([A-Z\d]{3,})\b#u", $conf, $m)
            && !in_array($m[1], $confs)
        ) {
            $confs[trim($m[1], " :")] = $m[2];
        }

        if (preg_match("#({$this->opt($this->t('Reservation ID'))})[: ]+([-A-Z\d]{5,})(?:[\s,]|$)#", $conf, $m)
            && !in_array($m[1], $confs)
        ) {
            $confs[trim($m[1], " :")] = $m[2];
        }

        $conf = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Externe Buchungsreferenz'))}]", $root);

        if (preg_match("#({$this->opt($this->t('Externe Buchungsreferenz'))})[: ]+([A-Z\d]{3,})#u", $conf, $m) && !in_array($m[1], $confs)
        ) {
            $confs[trim($m[1], " :")] = $m[2];
        }

        foreach ($confs as $name => $value) {
            $r->general()
                ->confirmation($value, $name);
        }

        $r->general()
            ->travellers($this->http->FindNodes("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][1]//text()[normalize-space(.)!='']",
                $root, "#(.+?) *(?:\(|$)#"));

        if (!empty($this->http->FindSingleNode("(./descendant::text()[" . $this->contains($this->t("Cancellation Date:")) . "])[1]", $root))) {
            $r->general()->cancelled();
            $canNum = $this->http->FindSingleNode("(./descendant::text()[" . $this->starts($this->t("Cancellation Number:")) . "])[1]", $root, null,
                "/" . $this->opt($this->t("Cancellation Number:")) . "\s*(\d{4,})(\W|$)/");

            if (!empty($canNum)) {
                $r->general()->cancellationNumber($canNum);
            }
        }

        // Booked
        $node = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]", $root, true, "#.*\d.*#");

        if (empty($node)) {
            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][2]", $root, true, "#.*\d.*#");
        }

        if (preg_match("#(.+),? {$this->opt($this->t('in'))} .+?, (\d+) {$this->opt($this->t('Nights'))}#", $node, $m)) {
            $date = $this->normalizeDate($m[1]);
            $r->booked()
                ->checkIn($date)
                ->checkOut(strtotime("+ " . $m[2] . " days", $date));
        }

        $r->hotel()
            ->name($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][2]",
                $root))
            ->address($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][3]",
                $root));
        $phones = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]/following::table[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][{$this->starts($this->t('Tel'))}]", $root);
        $r->hotel()
            ->phone($this->re("#{$this->opt($this->t('Tel'))}[:\s]+([+(\d][-+. \d)(]{5,}[\d)])(?:[,;\s]|$)#", $phones))
            ->fax($this->re("#{$this->opt($this->t('Fax'))}[:\s]+([+(\d][-+. \d)(]{5,}[\d)])(?:[,;\s]|$)#", $phones), false, true);

        $totalAmount = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Total rate amount in'))}]/ancestor::tr[1]", $root, false, "#{$this->opt($this->t('Total rate amount in'))}\s+(.+)#");
        $tot = $this->getTotalCurrency(str_replace(':', '', $totalAmount));

        if ($tot['Total'] !== null) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $availableInfo = '';
        $dictAvailableInfo = $this->lang === 'en' ? $this->t('availableInfo') : array_merge((array) $this->t('availableInfo'), (array) $this->t('availableInfo', 'en'));
        $aInfoRows = $this->http->XPath->query("descendant::text()[{$this->contains($dictAvailableInfo)}]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[normalize-space()]", $root);

        foreach ($aInfoRows as $row) {
            if ($this->http->XPath->query("descendant::text()[normalize-space()][1][contains(.,':')]", $row)->length === 0) {
                break;
            }
            $availableInfo .= $this->http->FindSingleNode('.', $row) . ', ';
        }
        $availableInfo = rtrim($availableInfo, ', ');

        if (!empty($availableInfo)) {
            $arr = [];
            $keys = preg_split("#(?<=^|[^A-z_ \-])(?:[A-z_ \-]{1,30}| ?Check-in\/Check-out):#u", $availableInfo);
            array_shift($keys);

            if (preg_match_all("#(?<=^|[^A-z_ \-])(?:[A-z_ \-]{1,30}| ?Check-in\/Check-out):#", $availableInfo, $values) && (count($keys) === count($values[0]))) {
                foreach ($values[0] as $i => $k) {
                    $arr[ucwords(strtolower(trim($k, ' :')))] = trim($keys[$i], ' ,');
                }
            }

            if (empty($arr['Rate Amount']) && !empty($arr['Rate Amount Per Night'])) {
                $arr['Rate Amount'] = $arr['Rate Amount Per Night'];
            }

            if (count($arr)) { // de -> en
                if (!empty($arr['Preis'])) {
                    $arr['Rate Amount'] = $arr['Preis'];
                }

                if (!empty($arr['Rate'])) {
                    $arr['Rate Description'] = $arr['Rate'];
                }

                if (!empty($arr['Zimmer'])) {
                    $arr['Room Description'] = $arr['Zimmer'];
                }

                if (empty($arr['Cancellation Policy'])) {
                    $arr['Cancellation Policy'] = '';
                }

                if (!empty($arr['Stornierungsbedingung'])) {
                    $arr['Cancellation Policy'] = $arr['Stornierungsbedingung'];
                } elseif (!empty($arr['Stornierung'])) {
                    $arr['Cancellation Policy'] = $arr['Stornierung'];
                }

                if (!empty($arr['Cancellation Deadline']) || !empty($arr['Stornierungsfrist'])) {
                    $arr['Cancellation Policy'] .= ' Cancellation Deadline: ' . (
                        empty($arr['Cancellation Deadline']) ? $arr['Stornierungsfrist'] : $arr['Cancellation Deadline']
                    );
                }
                $arr['Cancellation Policy'] = trim($arr['Cancellation Policy']);

                $this->logger->debug($arr['Cancellation Policy']);
            }

            // for text like:
            // Room Description: FREE: BRKFAST, PARK, WIFI;1 DOUBLE BED,NO-SMOKING,STANDARD,
            //
            // 'Room Description:' => ''
            // 'Free:' => 'BRKFAST, PARK, WIFI;1 DOUBLE BED,NO-SMOKING,STANDARD'
            $keys = array_keys($arr);
            $nextKey = $keys[array_search("Room Description", $keys) + 1];

            if ($nextKey == 'Free') {
                $arr['Room Description'] .= ' Free: ' . $arr['Free'];
            }

            if (!empty($arr['Rate Amount']) || !empty($arr['Rate Description']) || !empty($arr['Room Description'])) {
                $s = $r->addRoom();

                if (!empty($arr['Rate Amount'])) {
                    if (strlen($arr['Rate Amount']) > 200) {
                        if (preg_match_all("#([A-Z]{3}) ([\d\.]+) (PER NIGHT|PRO NACHT)#", $arr['Rate Amount'], $rateMatches)) {
                            $s->setRate('Avg: ' . $rateMatches[1][0] . ' ' . array_sum($rateMatches[2]) / count($rateMatches[2]) . ' ' . $rateMatches[3][0]);
                        } else {
                            $s->setRate($this->re("#([A-Z]{3} [\d\.]+ (?:PER NIGHT|PRO NACHT))#", $arr['Rate Amount']));
                        }
                    } else {
                        $s->setRate($arr['Rate Amount']);
                    }
                }

                if (!empty($arr['Rate Description'])) {
                    $s->setRateType($arr['Rate Description']);
                }

                if (!empty($arr['Room Description'])) {
                    $s->setDescription($arr['Room Description']);
                }
            }

            if (!empty($arr['Cancellation Policy'])) {
                if (!empty($arr['Cancellation Fee'])) {
                    $arr['Cancellation Policy'] .= ' Cancellation Fee: ' . $arr['Cancellation Fee'];
                }
                $r->general()->cancellation($arr['Cancellation Policy']);
            }

            if (!empty($r->getCheckInDate()) && !empty($r->getCheckOutDate())) {
                if (!empty($str = $this->re("#Check-in\/Check-out:\s+(.+)#", $availableInfo))) {
                    if (preg_match("#CHECK-IN:? (\d+:\d+)[ \/]CHECK-OUT:? (\d+:\d+)#", $str, $m)) {
                        $r->booked()
                            ->checkIn(strtotime($m[1], $r->getCheckInDate()))
                            ->checkOut(strtotime($m[2], $r->getCheckOutDate()));
                    }
                } else {
                    if (preg_match("#Check[-]?In:? ?(\d+:\d+) *(?:[^\w\s]|$)#i", $availableInfo, $m)) {
                        $r->booked()
                            ->checkIn(strtotime($m[1], $r->getCheckInDate()));
                    }

                    if (preg_match("#Check[-]?Out:? ?(\d+:\d+) *(?:[^\w\s]|$)#i", $availableInfo, $m)) {
                        $r->booked()
                            ->checkOut(strtotime($m[1], $r->getCheckOutDate()));
                    }
                }
            }

            $this->detectDeadLine($r);
        }

        $bookingDate = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Booking Date:'))}]", $root, false, "#{$this->opt($this->t('Booking Date:'))}\s*(.{3,})#");

        if (preg_match('/^(?<day>\d{1,2})[ ]*(?<month>[[:alpha:]]{3,})$/u', $bookingDate, $m) && $r->getCheckInDate()) {
            // 22NOV
            $r->general()->date(EmailDateHelper::parseDateRelative($m['day'] . ' ' . $m['month'], $r->getCheckInDate(), false));
        } elseif ($bookingDate) {
            // 22NOV19 and other
            $r->general()->date($this->normalizeDate($bookingDate));
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        $patterns['date'] = '\d{1,2}\.\d{1,2}\.\d{4}|\d{4}-\d{1,2}-\d{1,2}|\d{1,2}\/\d{1,2}\/\d{4}';
        $patterns['time'] = '\d{1,2}(?:[:]+\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        if (preg_match("/Eine kostenfreie Stornierung ist bis (?<date>{$patterns['date']}), (?<time>{$patterns['time']}) Uhr \(Ortszeit des Hotels\) moeglich/i", $cancellationText, $m)
            || preg_match("/^Kostenfrei stornierbar vor (?<date>{$patterns['date']}) um (?<time>{$patterns['time']}) Uhr Hotelzeit,/i", $cancellationText, $m) // de
            || preg_match("/Cancell?ation Deadline: (?<date>{$patterns['date']})T(?<time>{$patterns['time']}):\d/i", $cancellationText, $m)
            || preg_match("/Cancell? (?i)before (?<date>{$patterns['date']})[,\s]*(?<time>{$patterns['time']}).{0,25} to avoid.{0,20} charge\./", $cancellationText, $m)
            || preg_match("/Cancell?ation free of charge when cancell?ing before (?<date>{$patterns['date']})\s*(?<time>{$patterns['time']})/i", $cancellationText, $m)
            || preg_match("/CANCELL?ATION POSSIBLE UNTIL (?<date>{$patterns['date']}), (?<time>{$patterns['time']})\./i", $cancellationText, $m)
            || preg_match("/Stornieren Sie vor\s*(?<date>[\d\.]+\d{4})\s*(?<time>[\d\:]+)\s*Ortszeit\, um eine Gebühr in Höhe von/i", $cancellationText, $m)
        ) {
            // Cancellation Deadline: 2019-03-10T18:00:00.
            // CANCELLATION POSSIBLE UNTIL 2019-11-23, 23:59.
            $h->booked()->deadline2($m['date'] . ' ' . $m['time']);
        } elseif (preg_match("/^CANCEL LATEST BY (?<day>\d{1,2})-(?<month>\d{1,2})\-(?<year>\d{2}) (?<time>{$patterns['time']}) TO AVOID PENALTY OF \d+/i", $cancellationText, $m)
        ) {
            // CANCEL LATEST BY 12-01-20 2PM TO AVOID PENALTY OF 1080.00 . (checkin - 12 Jan 2020)
            $date = implode('-', ['20' . $m['year'], $m['month'], $m['day']]);
            $h->booked()->deadline2($date . ', ' . $m['time']);
        } elseif (preg_match("/^MUST BE CANCELLED BY (?<h>\d{2})(?<m>\d{2}) ON (?<month>\d{2})\/(?<day>\d{2})\/(?<year>\d{2})\b/i", $cancellationText, $m)
        ) {
            // MUST BE CANCELLED BY 1600 ON 01/12/20    (checkin - 13 Jan 2020)
            $date = implode('-', ['20' . $m['year'], $m['month'], $m['day']]);
            $h->booked()->deadline2($date . ', ' . $m['h'] . ':' . $m['m']);
        } elseif (preg_match("/^(?<hour>{$patterns['time']}) HOTEL TIME DAY OF ARRIV TO AVOID 1NT FEE/i", $cancellationText, $m)
        || preg_match("/^ANNULLAMENTO SENZA SPESE FINO ALLE (?<hour>{$patterns['time']}) \(ORA LOCALE\) DEL GIORNO DI ARRIVO/i", $cancellationText, $m)) {
            $h->booked()->deadlineRelative('0 days', $m['hour']);
        } elseif (preg_match("/^CXL (?<prior>\d{1,3} DAYS?) PRIOR TO ARRIVAL-CXL FEE FULL STAY/i", $cancellationText, $m) // es
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        }

        $h->booked()
            ->parseNonRefundable("#^CANCEL PERMITTED UP TO \d+ DAYS? BEFORE ARRIVAL [\d\.]+ CANCEL FEE#")
            ->parseNonRefundable("#^CXL PENALTY IS \d+ NIGHTS[ \.]*$#");
    }

    private function parseCar(\DOMNode $root, Email $email): bool
    {
        $r = $email->add()->rental();
        $date = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), ' dddd,')][1]",
            $root, false, "#^(.+ \d{4}|[^\d\s]+, \d+\s*[^\d\s]+),\s*[^\d\s]+#");
        $date = !empty($date) ? $this->normalizeDate($date) : null;

        if (empty($date)) {
            $date = $this->http->FindSingleNode("./descendant::img[ @alt = '[vCalendar]'][1]/ancestor::tr[normalize-space()!=''][1]",
                $root, false, "#^(.+),#");
            $date = !empty($date) ? $this->normalizeDate($date) : null;
        }
        $confNo = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Booking Code:'))}]",
            $root, false, "#{$this->opt($this->t('Booking Code:'))} ([A-Z\d]{5,})#");

        $travellers = $this->http->FindNodes("./descendant::text()[normalize-space(.)!=''][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), ' dddd,')][1]/following::tr[normalize-space()!=''][1]",
            $root, "#(.+?) *(?:\(|$)#");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("./descendant::img[ @alt = '[vCalendar]'][1]/following::tr[normalize-space()!=''][1]",
                $root, "#(.+?) *(?:\(|$)#");
        }
        $r->general()
            ->travellers($travellers)
            ->confirmation($confNo, trim($this->t('Booking Code:'), " :"))
            ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Booking Date:'))}]",
                $root, false, "#{$this->opt($this->t('Booking Date:'))} (.+)#")));
        $node = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][2]",
            $root);

        if (preg_match("#(.+?), (.+?):? *\b(\w+) *$#u", $node, $m)) {
            if ($m[3] !== $confNo) {
                $r->general()->confirmation($m[3], $m[2], true);
            }
            $r->extra()->company($m[1]);
        }

        if (!empty($keyword = $r->getCompany())) {
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            } else {
                $r->program()->keyword($keyword);
            }
        }

        if (!empty($this->http->FindSingleNode("(./descendant::text()[" . $this->contains($this->t("Cancellation Date:")) . "])[1]", $root))) {
            $r->general()->cancelled();
            $canNum = $this->http->FindSingleNode("(./descendant::text()[" . $this->starts($this->t("Cancellation Number:")) . "])[1]", $root, null,
                "/" . $this->opt($this->t("Cancellation Number:")) . "\s*(\d{4,})(\W|$)/");

            if (!empty($canNum)) {
                $r->general()->cancellationNumber($canNum);
            }
        }

        if ($this->cntCars === 1) {
            $subj = str_replace(":", '',
                $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Total rate amount in'))}]/ancestor::tr[1]",
                    $root, false, "#{$this->opt($this->t('Total rate amount in'))}\s+(.+)#"));
            $tot = $this->getTotalCurrency($subj);

            if ($tot['Total'] !== null) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        $time = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')][1]/descendant::text()[normalize-space(.)!=''][1]",
            $root);
        $r->pickup()
            ->date(!empty($date) ? strtotime($time, $date) : null);
        $node = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][3]/descendant::text()[normalize-space(.)!=''][last()]",
            $root);

        if (preg_match("#(.+?)\s*(?:\((.+)\))?(?:, {$this->opt($this->t('Flight'))}.+)?$#", $node, $m)) {
            $r->pickup()
                ->location($m[1]);

            if (isset($m[2]) && !empty($m[2])) {
                $r->pickup()
                    ->openingHours($m[2]);
            }
        }

        $doXpath = ".//img[@alt = '[Map]'][1]/ancestor::tr[count(.//img[@alt = '[Map]']) = 1 and count(.//td[not(.//td)][normalize-space()]) = 2]/following-sibling::tr[1][count(.//img[@alt = '[Map]']) = 1 and count(.//td[not(.//td)][normalize-space()]) = 2]";

        if ($this->http->XPath->query(".//img[@alt = '[Map]']", $root)->length == 2 && !empty($this->http->FindSingleNode($doXpath))) {
            $time = $this->http->FindSingleNode($doXpath . "/descendant::td[not(.//td)][normalize-space()][1][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')]",
                $root);
            $node = implode(' ', $this->http->FindNodes($doXpath . "/descendant::td[not(.//td)][normalize-space()][2]/descendant::text()[normalize-space()]", $root));
            $dateArr = $this->http->FindSingleNode($doXpath . "/descendant::td[not(.//td)][normalize-space()][2]/descendant::text()[normalize-space()][1]", $root, true, "#^(.+),\s*\S#");
            $dateArr = !empty($dateArr) ? $this->normalizeDate($dateArr) : null;
        } else {
            $xpathDate = "contains(translate(normalize-space(),'0123456789','∆∆∆∆∆∆∆∆∆∆'),' ∆∆∆∆,') and contains(translate(normalize-space(),'0123456789','∆∆∆∆∆∆∆∆∆∆'),', ∆∆ ') or contains(translate(normalize-space(),'0123456789','∆∆∆∆∆∆∆∆∆∆'),' ∆∆ ∆∆∆∆,')";

            $time = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')][2]/descendant::text()[normalize-space(.)!=''][1]",
                $root);
            $node = implode(' ',
                $this->http->FindNodes("descendant::text()[normalize-space()][1]/following::table[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][{$xpathDate}][position()<=4]/descendant::text()[normalize-space()][position()>1]",
                    $root));
            $dateArr = $this->normalizeDate($this->re("#^(.+? \d{4}|[^\d\s]+, \d+\s*[^\d\s]+),\s*[^\d\s]+#", $node));
        }
        $r->dropoff()
            ->date(!empty($dateArr) ? strtotime($time, $dateArr) : null);
        $node = $this->re("#^(?:.+? \d{4}|[^\d\s]+, \d+\s*[^\d\s]+),\s*([^\d\s]+.+)#", $node);

        if (preg_match("#(.+?)\s*(?:\((.+)\))?(?:, {$this->opt($this->t('Flight'))}.+)?$#", $node, $m)) {
            $r->dropoff()
                ->location($m[1]);

            if (isset($m[2]) && !empty($m[2])) {
                $r->dropoff()
                    ->openingHours($m[2]);
            }
        }

        $r->car()->type($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]/following::table[normalize-space(.)!=''][1]/descendant::tr[not(.//tr) and normalize-space(.)!=''][{$this->starts($this->t('Type of Car'))}]",
            $root, false, "#{$this->opt($this->t('Type of Car'))} ?: (.+)#u"));

        return true;
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

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish(string $date): string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug($date);
        $noYear = (preg_match("#\d{4}#", $date)) ? false : true;

        $year = date('Y', $this->date);
        $in = [
            //Tuesday, 29 May 2018 | Tuesday, 29May2018
            '#^(\w+),\s+(\d+)\s*([\D\S]+)\s*(\d{4})$#u',
            //01.08.2018 | 30/08/2018
            '#^\s*(\d+)[\.\/](\d+)[\.\/](\d{4})\s*$#',
            //06JUL18
            '#^\s*(\d+)(\D+)(\d{2})\s*$#',
            //05APR2019
            '#^\s*(\d+)(\D+)(\d{4})\s*$#',
            //06JUL
            '#^\s*(\d+)(\D+)\s*$#',
            //Tuesday, 13November
            '#^\s*([^\d\s,.]+), (\d+)\s*([^\d\s]+)\s*$#u',
        ];
        $out = [
            '$2 $3 $4',
            '$3-$2-$1',
            '$1 $2 20$3',
            '$1 $2 $3',
            "$1 $2 $year",
            "$2 $3 $year",
        ];
        $outWeek = [
            '',
            '',
            '',
            '',
            '',
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        if ($noYear === true && $str < $this->date - 60 * 60 * 24 * 30) {
            $str = strtotime("+1 year", $str);
        }

        if (empty($str)) { // 01/23/2019
            return strtotime(preg_replace('/(\d{1,2})\/(\d{1,2})\/(\d{2,4})/', '$2-$1-$3', $date));
        }

        return $str;
    }

    private function getTotalCurrency($node): array
    {
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
            $m)
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function getRentalProviderByKeyword(string $keyword): ?string
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }
}
