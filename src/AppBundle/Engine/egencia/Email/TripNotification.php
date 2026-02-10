<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TripNotification extends \TAccountChecker
{
    public $mailFiles = "egencia/it-154376222.eml, egencia/it-18897088.eml, egencia/it-19161476.eml, egencia/it-19167763.eml, egencia/it-19325963.eml, egencia/it-20467785.eml, egencia/it-22406151.eml, egencia/it-29080018.eml, egencia/it-29368320.eml, egencia/it-29558176.eml, egencia/it-29561355.eml, egencia/it-55481046.eml, egencia/it-55516175.eml, egencia/it-55519070.eml, egencia/it-55519391.eml";
    public $reFrom = ["egencia."]; //egencia.fi, egencia.dk, egencia.fr, egencia.es etc...
    public $reBody = [
        'en' => [
            ['Trip Notification', 'Confirmation'],
            ['Trip Notification', 'VIEW ONLINE'],
            ['Trip Notification', 'Booking details'],
            ['Trip Notification', 'Reservation details'],
            ['Hotel Booked', 'Booking Confirmation'],
            ['Booking Modified', 'Modification Update'],
            ['Train Booked', 'Booking Confirmation'],
            ['Flight Booked', 'Booking Confirmation'],
            ['Flight Canceled', 'Reservation details'],
            ['Cancellation Notification', 'Car Canceled'],
            ['Booking Confirmation', 'Car Booked'],
            ['Cancellation Notification', 'Train Canceled'],
            ['Cancellation Notification', 'Train Cancelled'],
            ['Flight Cancelled', 'Confirmation'],
        ],
        'da' => [
            ['Rejsemeddelelse', 'Bekræftelse'],
            ['Rejsemeddelelse', 'Reservationsoplysninger'],
            ['Hotelopholdet er reserveret', 'VÆRELSESTYPE'],
        ],
        'fi' => [
            ['Matkailmoitukset', 'Vahvistus'],
            ['MATKUSTAJAT', 'Egencia vahvistaa seuraavan varauksen'],
            ['Matkailmoitukset', 'NÄYTÄ SIVUSTOLLA'],
        ],
        'fr' => [
            ['Avis relatif au voyage', 'VOIR EN LIGNE'],
            ['Avis relatif au voyage', 'Bonjour'],
            ['Avis relatif au voyage', 'Informations relatives à la réservation'],
            ['Vol réservé', 'Confirmation de réservation'],
            ['Train réservé', 'Confirmation de réservation'],
            ['Hôtel réservé', 'Confirmation de réservation'],
            ['Voyage réservé', 'Confirmation de réservation'],
        ],
        'it' => [
            ['Notifica di viaggio', 'VISUALIZZA ONLINE'],
            ['Notifica di viaggio', 'Dettagli della prenotazione'],
        ],
        'es' => [
            ['Notificación de viaje', 'VER EN LÍNEA'],
            ['Notificación de viaje', 'SALIDA'],
            ['Notificación de viaje', 'Buenos días'],
            ['Notificación de viaje', 'Buenas tardes'],
        ],
        'pt' => [
            ['Notificação de Viagem', 'VER ON-LINE'],
            ['Trip Notification', 'Dados da reserva'],
            ['Notificação de Viagem', 'Dados da reserva'],
        ],
        'de' => [
            ['Reisebenachrichtigung', 'ONLINE ANSEHEN'],
            ['Reisebenachrichtigung', 'Buchungsdetails'],
            ['Flug gebucht', 'Buchungsdetails'],
            ['Gebuchte Reise', 'Buchungsbestätigung'],
        ],
        'no' => [
            ['Reisevarsel', 'VIS ONLINE'],
            ['Fly bestilt', 'Bestillingsbekreftelse'],
            ['Hotell bestilt', 'Bestillingsbekreftelse'],
            ['Fly kansellert', 'Bestillingsdetaljer'],
            ['Leiebil bestilt', 'Bestillingsbekreftelse'],
            ['Reise bestilt', 'Bestillingsbekreftelse'],
            ['Administrer bestilling', 'Bekreftelse'],
        ],
        'pl' => [
            ['Powiadomienie o podróży', 'ZOBACZ ONLINE'],
            ['Hotel zarezerwowany', 'Szczegóły rezerwacji'],
        ],
        'sv' => [
            ['Resemeddelande', 'SE ONLINE'],
            ['Resemeddelande', 'Bekräftelse'],
        ],
        'nl' => [
            ['Reisnotificatie', 'ONLINE WEERGEVEN'],
            ['Reisnotificatie', 'AANKOMST'],
        ],
        'cs' => [
            ['Oznámení o cestě', 'ZOBRAZIT ONLINE'],
        ],
    ];
    public $reSubject = [
        'Flight - BOOKED -',
        'Flight - CANCELLED -',
        'I BERO, BEKRÆFT VENLIGST',
        'Change in ticketing date -',
        'Ticketing date -',
        'Schedule change due to the airline',
        'Automóvil - ', //es
        'Auto - ', //it
        'Bil - ',
        'Flug - ', //de
        'Fly - ',
        'Voo - ', //pr
        'Hotel - ',
        'Hotell - ', //no
        'Leiebil - ', //no
        'Hotelli - ', //fi
        'Hôtel - ', //fr
        'Hotel booking confirmation -',
        'Lento - ', //fi
        'Let - ', //cs
        'Lot - ', //pl
        'Mietwagen - ', //de
        'Tåg - ', //sv
        'Train - ', //en, fr
        'Trein - ',
        'Vlucht - ', //nl
        'Voiture - ', //fr
        'Volo - ', //it
        'Vuelo - ', //es
    ];
    public $lang = '';
    public $langMainPart = '';
    public static $dict = [
        'en' => [
            //            'Itinerary/Egencia reference #' => '',
            //            'Confirmation' => '',
            'Cancelled'           => ['Cancelled', 'CANCELLED'],
            'Status'              => ['On Request', 'Not Booked', 'Booked', 'Cancelled', 'CANCELLED', 'Pending'], //, 'Confirmation'
            'Pay online'          => ['Pay online', 'Paid by my company', 'Paid', 'Card ending'],
            'Reservation details' => ['Reservation details', 'Booking details'],
            'TRAVELLERS'          => ['TRAVELLERS', 'TRAVELERS'],
            //            ' to ' => '',
            //            'Ticket' => '',
            //            'DEPARTURE' => '',
            //            'ARRIVAL' => '',
            //            'TERMINAL' => '',
            //            'CLASS' => '',
            //            'SEAT' => '',
            //            'DURATION' => '',
            //            'Layover in' => '',

            // CAR
            'AFHENTNING'           => 'PICK-UP',
            'AFLEVERING'           => 'DROP-OFF',
            'Samme som afhentning' => 'Same as pick-up',
            'TELEFON'              => 'PHONE',
            'FACILITETER'          => 'FEATURES',
            'TIMER'                => 'HOURS',

            // HOTEL
            'CHECK-IN'  => ['CHECK-IN', 'Check-in'],
            'CHECK-OUT' => ['CHECK-OUT', 'Check-out'],
            //			'ADDRESS' => '',
            'ROOM TYPE' => ['ROOM TYPE', '34 ROOM TYPE'],
            //			'ROOMS' => '',
            //			'PHONE' => '',
            //			'ADULTS' => '',
            //            'Cancellation and Changes' => '',
            'Cancellation' => ['Cancellation', 'cancellation'],
            'hotel'        => ['Hotel', 'hotel'],

            // TRAIN
            //			'Seat' => '',
            //			'Coach' => '',
            //			'AMENITIES' => '',
        ],
        'da' => [
            'Itinerary/Egencia reference #' => 'Rejseplan/Egencia-reference #',
            'Confirmation'                  => 'Bekræftelse',
            'Cancelled'                     => ['Afbestilt', 'AFBESTILT'],
            'Status'                        => ['Afbestilt', 'AFBESTILT', 'Afventer din bekræftelse', 'Godkendt', 'Afventende'],
            //            'Pay online' => '',
            'Reservation details' => 'Reservationsoplysninger',
            'TRAVELLERS'          => 'REJSENDE',
            ' to '                => ' til ',
            'Ticket'              => ['Billet', 'BILLET'],
            'DEPARTURE'           => 'AFREJSE',
            'ARRIVAL'             => 'ANKOMST',
            'TERMINAL'            => 'TERMINAL',
            'CLASS'               => 'KLASSE',
            'SEAT'                => 'SÆDE',
            'DURATION'            => 'VARIGHED',
            //			'Layover in' => '',

            // CAR
            'AFHENTNING'           => 'AFHENTNING',
            'AFLEVERING'           => 'AFLEVERING',
            'Samme som afhentning' => 'Samme som afhentning',
            'TELEFON'              => 'TELEFON',
            'FACILITETER'          => 'FACILITETER',
            'TIMER'                => 'TIMER',

            // HOTEL
            'CHECK-IN'  => 'INDTJEKNING',
            'CHECK-OUT' => 'UDTJEKNING',
            'ADDRESS'   => 'ADRESSE',
            'ROOM TYPE' => 'VÆRELSESTYPE',
            'ROOMS'     => 'VÆRELSER',
            'PHONE'     => 'TELEFON',
            'ADULTS'    => 'VOKSNE',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            //			'Seat' => '',
            //			'Coach' => '',
            //			'AMENITIES' => '',
        ],
        'fi' => [
            'Itinerary/Egencia reference #' => 'Matkasuunnitelma/Egencian varaustunnus #',
            'Confirmation'                  => 'Vahvistus',
            //			'Cancelled' => '',
            'Status' => ['Odotetaan vahvistusta'],
            //            'Pay online' => '',
            'Reservation details' => 'Varaustiedot',
            'TRAVELLERS'          => 'MATKUSTAJAT',
            ' to '                => ' – ',
            'Ticket'              => 'Lipunkirjoitus',
            'DEPARTURE'           => 'LÄHTÖ',
            'ARRIVAL'             => 'SAAPUMINEN',
            'TERMINAL'            => 'TERMINAALI',
            'CLASS'               => 'LUOKKA',
            'SEAT'                => 'ISTUINPAIKKA',
            'DURATION'            => 'KESTO',
            //			'Layover in' => '',

            // CAR
            //			'AFHENTNING' => '',
            //			'AFLEVERING' => '',
            //			'Samme som afhentning' => '',
            //			'TELEFON' => '',
            //			'FACILITETER' => '',
            //			'TIMER' => '',

            // HOTEL
            'CHECK-IN'  => 'SISÄÄNKIRJAUTUMINEN',
            'CHECK-OUT' => 'ULOSKIRJAUTUMINEN',
            'ADDRESS'   => 'OSOITE',
            'ROOM TYPE' => 'HUONETYYPPI',
            'ROOMS'     => 'HUONEET',
            'PHONE'     => 'PUHELINNUMERO',
            'ADULTS'    => 'AIKUISIA',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            //			'Seat' => '',
            //			'Coach' => '',
            //			'AMENITIES' => '',
        ],
        'fr' => [
            'Itinerary/Egencia reference #' => 'Voyage/Référence Egencia #',
            'Confirmation'                  => 'Confirmation',
            'Cancelled'                     => 'Annulé',
            'Status'                        => ['Annulé', 'Réservé', 'En attente dapprobation', 'Non réservé', 'En Demande'],
            //            'Pay online' => '',
            'Reservation details' => ['Informations relatives à la réservation', 'Reservation details', 'Renseignements sur la réservation'],
            'TRAVELLERS'          => 'VOYAGEURS',
            ' to '                => ' à ',
            'Ticket'              => 'Billet',
            'DEPARTURE'           => 'DÉPART',
            'ARRIVAL'             => 'ARRIVÉE',
            'TERMINAL'            => ['TERMINAL', 'AÉROGARE'],
            'CLASS'               => ['CLASSE', 'CATÉGORIE'],
            'SEAT'                => 'SIÈGE',
            'DURATION'            => 'DURÉE',
            'Layover in'          => 'Escale à',

            // CAR
            'AFHENTNING'           => 'PRISE EN CHARGE DU VÉHICULE',
            'AFLEVERING'           => 'RESTITUTION',
            'Samme som afhentning' => 'Même lieu que la prise en charge',
            'TELEFON'              => 'TÉLÉPHONE',
            'FACILITETER'          => 'CARACTÉRISTIQUES',
            'TIMER'                => 'HORAIRES',

            // HOTEL
            'CHECK-IN'  => 'ARRIVÉE',
            'CHECK-OUT' => 'DÉPART',
            'ADDRESS'   => 'ADRESSE',
            'ROOM TYPE' => 'TYPE DE CHAMBRE',
            'ROOMS'     => 'CHAMBRES',
            'PHONE'     => 'TÉLÉPHONE',
            'ADULTS'    => 'ADULTES',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            'Seat'      => 'Siège(s)',
            'Coach'     => 'Voiture',
            'AMENITIES' => 'SERVICES ET ÉQUIPEMENTS',
        ],
        'it' => [
            'Itinerary/Egencia reference #' => 'Itinerario/Rif. agenzia #',
            'Confirmation'                  => 'Conferma',
            'Cancelled'                     => 'Cancellato',
            'Status'                        => ['Cancellato', 'In sospeso', 'Prenotato', 'Non prenotato', 'Su richiesta'],
            //            'Pay online' => '',
            'Reservation details' => ['Dettagli della prenotazione'],
            'TRAVELLERS'          => 'VIAGGIATORI',
            ' to '                => ' - ',
            'Ticket'              => 'Biglietto',
            'DEPARTURE'           => 'PARTENZA',
            'ARRIVAL'             => 'ARRIVO',
            'TERMINAL'            => 'TERMINAL',
            'CLASS'               => 'CLASSE',
            'SEAT'                => 'POSTO A SEDERE',
            'DURATION'            => 'DURATA',
            //			'Layover in' => '',

            // CAR
            'AFHENTNING'           => 'PRELIEVO',
            'AFLEVERING'           => 'RICONSEGNA',
            'Samme som afhentning' => 'Stessa località del ritiro',
            'TELEFON'              => 'TELEFONO',
            'FACILITETER'          => 'CARATTERISTICHE',
            'TIMER'                => 'ORE',

            // HOTEL
            'CHECK-IN'  => 'CHECK-IN',
            'CHECK-OUT' => 'CHECK-OUT',
            'ADDRESS'   => 'INDIRIZZO',
            'ROOM TYPE' => 'TIPOLOGIA DI CAMERA',
            'ROOMS'     => 'CAMERE',
            'PHONE'     => 'TELEFONO',
            'ADULTS'    => 'ADULTI',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            //			'Seat' => 'Siège(s)',
            //			'Coach' => 'Voiture',
            //			'AMENITIES' => 'SERVICES ET ÉQUIPEMENTS',
        ],
        'es' => [
            'Itinerary/Egencia reference #' => 'Itinerario/Ref. agencia #',
            'Confirmation'                  => 'Confirmación',
            'Cancelled'                     => 'Cancelado',
            'Status'                        => ['Cancelado', 'Reservado', 'A petición', 'Pendiente', 'Esperando aprobación', 'No reservado'],
            //            'Pay online' => '',
            'Reservation details' => ['Datos de la reserva'],
            'TRAVELLERS'          => 'VIAJEROS',
            ' to '                => ' - ',
            'Ticket'              => 'Billete',
            'DEPARTURE'           => 'SALIDA',
            'ARRIVAL'             => 'LLEGADA',
            'TERMINAL'            => 'TERMINAL',
            'CLASS'               => 'CLASE',
            'SEAT'                => 'ASIENTO',
            'DURATION'            => 'DURACIÓN',
            'Layover in'          => 'Escala en',

            // CAR
            'AFHENTNING'           => 'RECOGIDA',
            'AFLEVERING'           => 'ENTREGA',
            'Samme som afhentning' => 'Misma información que en recogida',
            'TELEFON'              => 'TELÉFONO',
            'FACILITETER'          => 'CARACTERÍSTICAS',
            'TIMER'                => 'HORAS',

            // HOTEL
            'CHECK-IN'  => 'ENTRADA',
            'CHECK-OUT' => 'SALIDA',
            'ADDRESS'   => 'DIRECCIÓN',
            'ROOM TYPE' => 'TIPO DE HABITACIÓN',
            'ROOMS'     => 'HABITACIONES',
            'PHONE'     => 'TELÉFONO',
            'ADULTS'    => 'ADULTOS',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            //			'Seat' => 'Siège(s)',
            //			'Coach' => 'Voiture',
            //			'AMENITIES' => 'SERVICES ET ÉQUIPEMENTS',
        ],
        'pt' => [
            'Itinerary/Egencia reference #' => 'Itinerário/Referência da Egencia #',
            'Confirmation'                  => 'Confirmação',
            //			'Cancelled' => '',
            'Status' => ['Reservada', 'Pendente'],
            //            'Pay online' => '',
            'Reservation details' => ['Dados da reserva'],
            'TRAVELLERS'          => 'VIAJANTES',
            ' to '                => ' para ',
            'Ticket'              => 'Bilhete',
            'DEPARTURE'           => 'PARTIDA',
            'ARRIVAL'             => 'CHEGADA',
            'TERMINAL'            => 'TERMINAL',
            'CLASS'               => 'CLASSE',
            'SEAT'                => 'LUGAR',
            'DURATION'            => 'DURAÇÃO',
            'Layover in'          => 'Escala em',

            // CAR
            //			'AFHENTNING' => 'RECOGIDA',
            //			'AFLEVERING' => 'ENTREGA',
            //			'Samme som afhentning' => 'Misma información que en recogida',
            //			'TELEFON' => 'TELÉFONO',
            //			'FACILITETER' => 'CARACTERÍSTICAS',
            //			'TIMER' => 'HORAS',

            // HOTEL
            'CHECK-IN'  => 'CHECK-IN',
            'CHECK-OUT' => 'CHECK-OUT',
            'ADDRESS'   => 'ENDEREÇO',
            'ROOM TYPE' => 'TIPO DE QUARTO',
            'ROOMS'     => 'QUARTOS',
            'PHONE'     => 'TELEFONE',
            'ADULTS'    => 'ADULTOS',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            //			'Seat' => 'Siège(s)',
            //			'Coach' => 'Voiture',
            //			'AMENITIES' => 'SERVICES ET ÉQUIPEMENTS',
        ],
        'de' => [
            'Itinerary/Egencia reference #' => 'Reiseplan/Egencia Buchungsnr #',
            'Confirmation'                  => 'Bestätigung',
            'Cancelled'                     => ['STORNIERT', 'Storniert'],
            'Status'                        => ['Auf Anfrage', 'STORNIERT', 'Storniert', 'Nicht gebucht', 'Ausstehend'],
            //            'Pay online' => '',
            'Reservation details' => ['Buchungsdetails'],
            'TRAVELLERS'          => 'REISENDE',
            ' to '                => ' nach ',
            'Ticket'              => 'Ticket',
            'DEPARTURE'           => 'ABREISE',
            'ARRIVAL'             => 'ANKUNFT',
            'TERMINAL'            => 'TERMINAL',
            'CLASS'               => 'KLASSE',
            'SEAT'                => 'SITZPLATZ',
            'DURATION'            => 'DAUER',
            'Layover in'          => 'Zwischenstopp in',

            // CAR
            'AFHENTNING'           => 'ABHOLUNG',
            'AFLEVERING'           => 'RÜCKGABE',
            'Samme som afhentning' => 'Am Abholort',
            'TELEFON'              => 'TELEFON',
            'FACILITETER'          => 'AUSSTATTUNG',
            'TIMER'                => 'UHRZEIT',

            // HOTEL
            'CHECK-IN'  => 'CHECK-IN',
            'CHECK-OUT' => 'CHECK-OUT',
            'ADDRESS'   => 'ADRESSE',
            'ROOM TYPE' => 'ZIMMERTYP',
            'ROOMS'     => 'ZIMMER',
            'PHONE'     => 'TELEFON',
            'ADULTS'    => 'ERWACHSENE',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            'Seat'      => 'Sitzplatz',
            'Coach'     => 'Waggon',
            'AMENITIES' => 'AUSSTATTUNG',
        ],
        'no' => [
            'Itinerary/Egencia reference #' => 'Reiserute/Egencias referanse #',
            'Confirmation'                  => 'Bekreftelse',
            'Cancelled'                     => 'Kansellert',
            'Status'                        => ['Bestilt'],
            //            'Pay online' => '',
            'Reservation details' => ['Bestillingsdetaljer'],
            'TRAVELLERS'          => ['REISENDE'],
            ' to '                => [' til '],
            'Ticket'              => ['Billett'],
            'DEPARTURE'           => ['AVREISE'],
            'ARRIVAL'             => ['ANKOMST'],
            'TERMINAL'            => 'TERMINAL',
            'CLASS'               => ['KLASSE'],
            'SEAT'                => ['SETE'],
            'DURATION'            => ['VARIGHET'],
            'Layover in'          => ['Mellomlanding i'],

            // CAR
            'AFHENTNING'           => 'HENTING',
            'AFLEVERING'           => 'LEVERING',
            'Samme som afhentning' => 'Samme som ved henting',
            'TELEFON'              => 'TELEFON',
            'FACILITETER'          => 'EGENSKAPER',
            'TIMER'                => 'ÅPNINGSTIDER',

            // HOTEL
            'CHECK-IN'  => 'INNSJEKKING',
            'CHECK-OUT' => 'UTSJEKKING',
            'ADDRESS'   => 'ADRESSE',
            'ROOM TYPE' => ['ROMTYPE', '34 ROMTYPE'],
            'ROOMS'     => 'ROM',
            'PHONE'     => 'TELEFON',
            'ADULTS'    => 'VOKSNE',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            //			'Seat' => 'Siège(s)',
            //			'Coach' => 'Voiture',
            //			'AMENITIES' => 'SERVICES ET ÉQUIPEMENTS',
        ],
        'pl' => [
            'Itinerary/Egencia reference #' => 'Plan podróży/Numer referencyjny Egencia #',
            'Confirmation'                  => 'Potwierdzenie',
            'Cancelled'                     => 'Niezarezerwowane',
            'Status'                        => ['Niezarezerwowane'],
            //            'Pay online' => '',
            'Reservation details' => ['Szczegóły rezerwacji'],
            'TRAVELLERS'          => 'PORÓŻNI',
            ' to '                => ' do ',
            'Ticket'              => 'Bilet',
            'DEPARTURE'           => 'WYLOT',
            'ARRIVAL'             => 'PRZYLOT',
            'TERMINAL'            => 'TERMINAL',
            'CLASS'               => 'KLASA',
            'SEAT'                => 'MIEJSCE',
            'DURATION'            => 'CZAS TRWANIA',
            //			'Layover in' => '',

            // CAR
            //			'AFHENTNING' => 'PRISE EN CHARGE DU VÉHICULE',
            //			'AFLEVERING' => 'RESTITUTION',
            //			'Samme som afhentning' => 'Même lieu que la prise en charge',
            //			'TELEFON' => 'TÉLÉPHONE',
            //			'FACILITETER' => 'CARACTÉRISTIQUES',
            //			'TIMER' => 'HORAIRES',

            // HOTEL
            'CHECK-IN'                 => 'ZAMELDOWANIE',
            'CHECK-OUT'                => 'WYMELDOWANIE',
            'ADDRESS'                  => 'ADRES',
            'ROOM TYPE'                => 'RODZAJ POKOJU',
            'ROOMS'                    => 'POKOJE',
            'PHONE'                    => 'TELEFON',
            'ADULTS'                   => 'DOROŚLI',
            'Cancellation and Changes' => 'Anulowanie i zmiany rezerwacji',
            'Cancellation'             => ['Anulowanie', 'anulowanie'],
            'hotel'                    => ['Hotelu', 'hotelu'],

            // TRAIN
            //			'Seat' => 'Siège(s)',
            //			'Coach' => 'Voiture',
            //			'AMENITIES' => 'SERVICES ET ÉQUIPEMENTS',
        ],
        'sv' => [
            'Itinerary/Egencia reference #' => 'Resplan/Egencias referens #',
            'Confirmation'                  => 'Bekräftelse',
            //			'Cancelled' => '',
            'Status' => ['Bokad', 'På förfrågan'],
            //            'Pay online' => '',
            'Reservation details' => ['Bokningsuppgifter'],
            'TRAVELLERS'          => 'RESENÄRER',
            ' to '                => ' till ',
            'Ticket'              => 'Biljett',
            'DEPARTURE'           => 'AVGÅNG',
            'ARRIVAL'             => 'ANKOMST',
            'TERMINAL'            => 'TERMINAL',
            'CLASS'               => 'KLASS',
            'SEAT'                => 'SITTPLATS',
            'DURATION'            => 'RESTID',
            //			'Layover in' => '',

            // CAR
            'AFHENTNING'           => 'HÄMTNING',
            'AFLEVERING'           => 'AVLÄMNING',
            'Samme som afhentning' => 'Samma som hämtning',
            'TELEFON'              => 'TELEFON',
            'FACILITETER'          => 'TILLVAL',
            'TID'                  => 'HORAIRES',

            // HOTEL
            'CHECK-IN'  => 'INCHECKNING',
            'CHECK-OUT' => 'UTCHECKNING',
            'ADDRESS'   => 'ADRESS',
            'ROOM TYPE' => 'RUMSTYP',
            'ROOMS'     => 'RUM',
            'PHONE'     => 'TELEFON',
            'ADULTS'    => 'VUXNA',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            'Seat'      => 'Sittplats',
            'Coach'     => 'Vagn',
            'AMENITIES' => 'BEKVÄMLIGHETER',
        ],
        'nl' => [
            'Itinerary/Egencia reference #' => 'Reisplan/Ref. agentschap #',
            'Confirmation'                  => 'Bevestiging',
            'Cancelled'                     => 'Geannuleerd',
            'Status'                        => ['Geannuleerd', 'Op verzoek', 'Niet geboekt'],
            //            'Pay online' => '',
            'Reservation details' => ['Boekingsinfo'],
            'TRAVELLERS'          => 'REIZIGERS',
            ' to '                => ' naar ',
            'Ticket'              => 'Ticket',
            'DEPARTURE'           => 'VERTREK',
            'ARRIVAL'             => 'AANKOMST',
            'TERMINAL'            => 'TERMINAL',
            'CLASS'               => 'KLASSE',
            'SEAT'                => 'STOEL',
            'DURATION'            => 'DUUR',
            //			'Layover in' => '',

            // CAR
            'AFHENTNING'           => 'OPHALEN',
            'AFLEVERING'           => 'INLEVEREN',
            'Samme som afhentning' => 'Zelfde als ophaallocatie',
            'TELEFON'              => 'TELEFOON',
            'FACILITETER'          => 'KENMERKEN',
            'TIMER'                => 'UUR',

            // HOTEL
            'CHECK-IN'  => 'INCHECKEN',
            'CHECK-OUT' => 'UITCHECKEN',
            'ADDRESS'   => 'ADRES',
            'ROOM TYPE' => 'KAMERTYPE',
            'ROOMS'     => 'KAMERS',
            'PHONE'     => 'TELEFOON',
            'ADULTS'    => 'VOLWASSENEN',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            //			'Seat' => 'Siège(s)',
            //			'Coach' => 'Voiture',
            'AMENITIES' => 'VOORZIENINGEN',
        ],
        'cs' => [
            'Itinerary/Egencia reference #' => 'Itinerář/Reference společnosti Egencia #',
            'Confirmation'                  => 'Potvrzení',
            //			'Cancelled' => '',
            'Status' => ['Rezervováno'],
            //            'Pay online' => '',
            'Reservation details' => ['Údaje o rezervaci'],
            'TRAVELLERS'          => 'CESTUJÍCÍ',
            ' to '                => ' – ',
            'Ticket'              => 'Jízdenka/letenka',
            'DEPARTURE'           => 'ODLET',
            'ARRIVAL'             => 'PŘÍLET',
            'TERMINAL'            => 'TERMINÁL',
            'CLASS'               => 'TŘÍDA',
            'SEAT'                => 'SEDADLO',
            'DURATION'            => 'DÉLKA',
            //			'Layover in' => '',

            // CAR
            //			'AFHENTNING' => 'PRISE EN CHARGE DU VÉHICULE',
            //			'AFLEVERING' => 'RESTITUTION',
            //			'Samme som afhentning' => 'Même lieu que la prise en charge',
            //			'TELEFON' => 'TÉLÉPHONE',
            //			'FACILITETER' => 'CARACTÉRISTIQUES',
            //			'TIMER' => 'HORAIRES',

            // HOTEL
            'CHECK-IN'  => 'PŘÍJEZD',
            'CHECK-OUT' => 'ODJEZD',
            'ADDRESS'   => 'ADRESA',
            'ROOM TYPE' => 'TYP POKOJE',
            'ROOMS'     => 'POKOJE',
            'PHONE'     => 'TELEFON',
            'ADULTS'    => 'DOSPĚLÍ',
            //            'Cancellation and Changes' => '',
            //            'Cancellation' => '',
            //            'hotel' => '',

            // TRAIN
            //			'Seat' => 'Siège(s)',
            //			'Coach' => 'Voiture',
            //			'AMENITIES' => 'SERVICES ET ÉQUIPEMENTS',
        ],
    ];
    private $date;
    private $otaConf;
    private $keywords = [
        'sixt' => [
            'Sixt',
        ],
        'hertz' => [
            'Hertz',
        ],
        'national' => [
            'National Car Rental',
        ],
        'rentacar' => [
            'Enterprise',
        ],
        'perfectdrive' => [
            'Budget',
        ],
        'avis' => [
            'Avis',
        ],
        'alamo' => [
            'Alamo Rent A Car',
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?',
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            $email->add()->flight();
        }// for broke 100%;

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'egencia.com')] | //img[contains(@src,'egencia.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers['subject'], $reSubject) === 0
                        || stripos($headers['subject'], ': ' . $reSubject) !== false
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseFlight(Email $email): bool
    {
        $xpath = "//text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::table[(contains(.,'#') or {$this->contains($this->t('Status'))}) and not({$this->contains($this->t('ROOMS'))}) and (count(./descendant::img[contains(@src, '/flightIcon.png') or contains(@src, '/railIcon.png') or contains(@alt, 'Flight')])=1)][1][./descendant::text()[{$this->starts($this->t('TERMINAL'))}]]";
        $this->logger->debug("flight xpath: " . $xpath);
        $nodesRes = $this->http->XPath->query($xpath);

        if ($nodesRes->length === 0) {
            $currentLang = $this->lang;

            foreach (self::$dict as $lang => $d) {
                $this->lang = $lang;
                $xpath = "//text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::table[(contains(.,'#') or {$this->contains($this->t('Status'))}) and not({$this->contains($this->t('ROOMS'))}) and (count(./descendant::img[contains(@src, '/flightIcon.png') or contains(@src, '/railIcon.png') or contains(@alt, 'Flight')])=1)][1][./descendant::text()[{$this->starts($this->t('TERMINAL'))}]]";
                $nodesRes = $this->http->XPath->query($xpath);
                $words = ['ARRIVAL', 'CLASS', 'DURATION', 'SEAT'];
                $condition = [];

                foreach ($words as $word) {
                    $condition[] = ".//text()[{$this->eq($this->t($word))}]";
                }

                if ($nodesRes->length > 0
                    && !empty($condition) && !empty($this->http->FindSingleNode("(descendant-or-self::*[.//text()[{$this->contains($this->t(' to '))}] and " . implode(' and ', $condition) . "])[1]", $nodesRes->item(0)))
                    && $this->http->XPath->query("//*[.//text()[{$this->contains($this->t('Reservation details'))}] and .//text()[{$this->contains($this->t('TRAVELLERS'))}]]")->length > 0
                ) {
                    $this->logger->debug('(Flight) New lang is ' . $this->lang);

                    break;
                }
                $this->lang = $currentLang;
            }
        }

        if ($nodesRes->length === 0) {
            $this->logger->debug('Flights not found.');
        }

        $flightsByPNR = [];

        foreach ($nodesRes as $rootRes) {
            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation'))}]/following::text()[normalize-space()][1]", $rootRes, true, "/^([A-Z\d]{5,})\s*(?:\(|$)/");

            if (!empty($confirmation)) {
                if (empty($flightsByPNR[$confirmation])) {
                    $flightsByPNR[$confirmation] = [$rootRes];
                } else {
                    $flightsByPNR[$confirmation][] = $rootRes;
                }
            } elseif ($this->http->XPath->query("descendant::text()[{$this->eq($this->t('Confirmation'))}]", $rootRes)->length === 0) {
                if (empty($flightsByPNR['unknown'])) {
                    $flightsByPNR['unknown'] = [$rootRes];
                } else {
                    $flightsByPNR['unknown'][] = $rootRes;
                }
            } else {
                $this->logger->debug('Wrong confirmation number in flight!');

                return false;
            }
        }

        foreach ($flightsByPNR as $pnr => $fRoots) {
            $rootRes = $fRoots[0];

            $f = $email->add()->flight();

            if ($pnr === 'unknown') {
                $f->general()->noConfirmation();
            } else {
                $confirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation'))}]", $rootRes, true, '/^(.+?)[\s:：]*$/u');
                $f->general()->confirmation($pnr, $confirmationTitle);
            }

            $status = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation'))}]/preceding::text()[normalize-space()][1]", $rootRes)
                ?? $this->http->FindSingleNode("(descendant::text()[{$this->eq($this->t('DEPARTURE'))}]/preceding::text()[normalize-space()][position()<7][ancestor::td[1]/preceding::td[1]//img])[last()]", $rootRes)
                ?? $this->http->FindSingleNode("(descendant::text()[{$this->eq($this->t('Ticket'))}]/preceding::text()[normalize-space()][position()<5][ancestor::td[1]/preceding::td[1]//img])[last()]", $rootRes)
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('DEPARTURE'))}]/preceding::text()[normalize-space()][position()<7][{$this->contains($this->t("Status"))}]", $rootRes)
            ;

            if (!empty($status)) {
                $f->general()->status($status);
            }

            if (in_array($status, (array) $this->t('Cancelled'))) {
                $f->general()->cancelled();
            }

            $tot = $this->getTotalCurrency($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Ticket'))}]/preceding::text()[normalize-space()][1]", $rootRes));

            if ($tot['Total'] !== null) {
                $f->price()->total($tot['Total'])->currency($tot['Currency']);
            }

            $dateRes = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Ticket'))}][1]/following::text()[normalize-space()][1]", $rootRes));

            if (!empty($dateRes)) {
                $f->general()->date($dateRes);
            }

            $travellers = array_filter($this->http->FindNodes("following::table[normalize-space()][1][{$this->contains($this->t('Reservation details'))}][1]/descendant::text()[{$this->contains($this->t('TRAVELLERS'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()]//div", $rootRes, "/^{$this->patterns['travellerName']}$/u"));

            if (count($travellers) > 0) {
                $f->general()->travellers($travellers);
            }

            $tickets = $accounts = [];

            foreach ($fRoots as $fRoot) {
                $fTickets = array_filter(array_unique(array_map("trim", explode(",",
                        $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Ticket'))}]/following::text()[normalize-space()][1]", $fRoot)
                    ))), function ($s) {
                        return preg_match("/^\d{7,}$/", $s);
                    }
                );

                if (count($fTickets) > 0) {
                    $tickets = array_merge($tickets, $fTickets);
                }

                $fAccounts = array_filter($this->http->FindNodes("descendant::text()[{$this->eq($this->t('DEPARTURE'))}]/preceding::text()[normalize-space()][1][contains(.,'#')]", $fRoot, "/.+?\s*#\s*([-\w]+)/"));

                if (count($fAccounts) > 0) {
                    $accounts = array_merge($accounts, $fAccounts);
                }

                $header = array_filter(array_map("trim", preg_split("/{$this->preg_implode($this->t(' to '))}/",
                        $this->http->FindSingleNode("descendant::text()[normalize-space()][1][{$this->contains($this->t(' to '))}]", $fRoot)
                    )), function ($s) {
                        return preg_match("/^[A-Z]{3}$/", $s);
                    }
                );

                if (count($header) !== 2) {
                    $this->logger->debug('Wrong flight header format!');

                    return false;
                }

                $segments = $this->http->XPath->query("descendant::text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::table[1]", $fRoot);

                foreach ($segments as $i => $rootSeg) {
                    $s = $f->addSegment();

                    if ($i === 0) {
                        $s->departure()->code($header[0]);
                    } elseif (!empty($code = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[normalize-space()][1][" . $this->starts($this->t("Layover in")) . "]", $rootSeg, true, "#" . $this->preg_implode($this->t("Layover in")) . " .+? ([A-Z]{3}) \d+#"))) {
                        $s->departure()->code($code);
                    } else {
                        $s->departure()->noCode();
                    }

                    if ($i === $segments->length - 1) {
                        $s->arrival()->code($header[1]);
                    } elseif (!empty($code = $this->http->FindSingleNode("./ancestor::tr[1]//following-sibling::tr[normalize-space()][2][" . $this->starts($this->t("Layover in")) . "]", $rootSeg, true, "#" . $this->preg_implode($this->t("Layover in")) . " .+? ([A-Z]{3}) \d+#"))) {
                        $s->arrival()->code($code);
                    } else {
                        $s->arrival()->noCode();
                    }
                    $node = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $rootSeg);

                    if (preg_match("/([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", $node, $m)) {
                        $s->airline()->name($m[1])->number($m[2]);
                    } else {
                        $node = $this->http->FindSingleNode("descendant::text()[normalize-space()][2]", $rootSeg);

                        if (preg_match("/([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", $node, $m)) {
                            $s->airline()->name($m[1])->number($m[2]);
                        }
                    }

                    $operator = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Operated by'))}]/ancestor::*[self::div or self::p or self::tr][1][ descendant::text()[normalize-space()][2] ]", $rootSeg, true, "/{$this->preg_implode($this->t('Operated by'))}\s*(.+)/");
                    $s->airline()->operator($operator, false, true);

                    $s->departure()
                        ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTURE'))}][1]/following::text()[normalize-space()][1]", $rootSeg)))
                        ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTURE'))}][1]/following::text()[normalize-space()][2]", $rootSeg))
                    ;
                    $terminal = trim($this->http->FindSingleNode("./following::table[1]/descendant::text()[{$this->eq($this->t('TERMINAL'))}]/ancestor::*[1]/following-sibling::*[1]", $rootSeg));

                    if (!empty($terminal)) {
                        $s->departure()->terminal($terminal);
                    }

                    $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ARRIVAL'))}][1]/following::text()[normalize-space()][1]", $rootSeg);

                    if (preg_match("/^([-+])\s*(\d+)$/", $node, $m)) {
                        $s->arrival()
                            ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ARRIVAL'))}][1]/following::text()[normalize-space()][2]", $rootSeg)))
                            ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ARRIVAL'))}][1]/following::text()[normalize-space()][3]", $rootSeg))
                        ;
                    } else {
                        $s->arrival()
                            ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ARRIVAL'))}][1]/following::text()[normalize-space()][1]", $rootSeg)))
                            ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('ARRIVAL'))}][1]/following::text()[normalize-space()][2]", $rootSeg))
                        ;
                    }

                    $s->extra()
                        ->cabin($this->getField($this->t('CLASS'), $rootSeg, null, '/following::table[1]'), true)
                        ->duration($this->getField($this->t('DURATION'), $rootSeg, null, '/following::table[1]'));

                    $account = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('DEPARTURE'))}][1]/preceding::text()[normalize-space()][1]", $rootSeg, true, "/^(\d+x[x\d]*|[x\d]*x\d+)$/i");

                    if (!empty($account)) {
                        $f->program()->account($account, true);
                    }

                    $seatsText = $this->getField($this->t('SEAT'), $rootSeg, null, '/following::table[1]');
                    $seats = array_filter(array_map("trim", explode(",", $seatsText)), function ($s) {
                        return preg_match("/^\d+[A-Z]/i", $s);
                    });

                    if (!empty($seats)) {
                        $s->extra()->seats($seats);
                    }
                }
            }

            if (count($tickets) > 0) {
                $f->issued()->tickets(array_unique($tickets), false);
            }

            if (count($accounts) > 0) {
                $f->program()->accounts(array_unique($accounts), false);
            }
        }

        return true;
    }

    private function parseCar(Email $email): bool
    {
        $xpath = "//text()[{$this->eq($this->t('AFLEVERING'))}]/ancestor::table[(contains(.,'#') or count(.//img[contains(@src,'/carIcon.png')])=1) and ({$this->contains($this->t('Confirmation'))} or {$this->contains($this->t('Status'))})][1][./descendant::text()[{$this->contains($this->t('AFHENTNING'))}]]";
        $this->logger->debug('Car xpath: ' . $xpath);
        $nodesRes = $this->http->XPath->query($xpath);

        if ($nodesRes->length === 0) {
            $currentLang = $this->lang;

            foreach (self::$dict as $lang => $d) {
                $this->lang = $lang;
                $nodesRes = $this->http->XPath->query($xpath);

                $words = ['AFLEVERING', 'TELEFON', 'FACILITETER', 'TIMER'];
                $condition = [];

                foreach ($words as $word) {
                    $condition[] = ".//text()[{$this->eq($this->t($word))}]";
                }

                if ($nodesRes->length > 0
                    && !empty($condition) && !empty($this->http->FindSingleNode("(descendant-or-self::*[" . implode(' and ', $condition) . "])[1]", $nodesRes->item(0)))
                    && $this->http->XPath->query("//*[.//text()[{$this->contains($this->t('Reservation details'))}] and .//text()[{$this->contains($this->t('TRAVELLERS'))}]]")->length > 0
                ) {
                    $this->logger->debug('(Car) New lang is ' . $this->lang);

                    break;
                }
                $this->lang = $currentLang;
            }
        }

        if ($nodesRes->length === 0) {
            $this->logger->debug('Cars not found.');
        }

        foreach ($nodesRes as $rootRes) {
            $r = $email->add()->rental();
            $keyword = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]", $rootRes);

            if (!empty($keyword) && !empty($code = $this->getProviderByKeyword($keyword))) {
                $r->program()
                    ->code($code);
            }

            $r->extra()->company($keyword);

            $acc = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('LOYALTY CARD'))}]",
                $rootRes, false, "/{$this->preg_implode($this->t('LOYALTY CARD'))}\s*\#\s*([\w\-]+)/");

            if (!empty($acc)) {
                $r->program()
                    ->account($acc, false);
            }
            $status = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation'))}]/preceding::text()[normalize-space(.)!=''][1]",
                $rootRes);

            if (empty($status)) {
                $status = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('AFHENTNING'))}]/preceding::text()[normalize-space(.)!=''][position()<7][{$this->contains($this->t("Status"))}]",
                    $rootRes);
            }

            if (in_array($status, (array) $this->t('Cancelled'))) {
                $r->general()->cancelled();
            }

            if (!empty($status)) {
                $r->general()
                    ->status($status);
            }

            if (empty($confno = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation'))}]/following::text()[normalize-space(.)!=''][1]",
                    $rootRes)) && !empty($status)
            ) {
                $r->general()
                    ->noConfirmation();
            } else {
                if (preg_match("#^\w+ PEXP$#", $confno)) {
                    $confno = str_replace(' ', '-', $confno);
                }
                $r->general()
                    ->confirmation($confno);
            }

            if (count($node = $this->http->FindNodes("./following::table[1][{$this->contains($this->t('Reservation details'))}]/descendant::text()[{$this->contains($this->t('TRAVELLERS'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!='']//div",
                    $rootRes)) > 0
            ) {
                $pax = $node;
            }

            if (isset($pax)) {
                $r->general()
                    ->travellers($pax);
            }

            if (empty($node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Status'))}]/following::text()[normalize-space(.)!=''][1][not({$this->eq($this->t('Confirmation'))})]",
                $rootRes))
            ) {
                if (empty($node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation'))}]/following::text()[normalize-space(.)!=''][2]",
                    $rootRes))) {
                    $node = $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Status'))}]/following::text()[normalize-space(.)!=''][1]",
                        $rootRes);
                }
            }
            $tot = $this->getTotalCurrency($node);

            if ($tot['Total'] !== null) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }

            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('AFHENTNING'))}]/ancestor::table[1]/descendant::tr[1]/descendant::text()[normalize-space(.)!=''][last()]",
                $rootRes);
            $type = "";
            $arr = array_map("trim", explode(' - ', $node));

            if (count($arr) === 2) {
                $type = $arr[1];
                $r->car()
                    ->model($arr[0]);
            } elseif (count($arr) === 1) {
                $r->car()
                    ->model($arr[0], $r->getCancelled());
            }

            $node = implode(', ',
                $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('FACILITETER'))}]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]",
                    $rootRes));
            $type = trim($type . ', ' . $node, ' ,');

            if (!empty($type)) {
                if (strlen($type) > 196) {
                    $type = substr($type, 0, 196) . '...';
                }
                $r->car()
                    ->type($type);
            }
            $node = $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('AFHENTNING'))}]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]",
                $rootRes);

            if (count($node) > 1) {
                $r->pickup()
                    ->date($this->normalizeDate($node[0]))
                    ->location($node[1]);
            }
            $node = $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('TELEFON'))}]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]",
                $rootRes, "#^\s*([\d\-\+\.\(\) ]{5,})\b#");

            if (count($node) == 1) {
                $r->pickup()
                    ->phone(rtrim($node[0], ' (,.'));
            }
            $node = $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('TIMER'))}]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]",
                $rootRes);

            if (count($node) == 1 && $node[0] != '-') {
                $r->pickup()
                    ->openingHours($node[0]);
            }
            $node = $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('AFLEVERING'))}]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][position()>1]",
                $rootRes);

            if (count($node) > 1) {
                $r->dropoff()
                    ->date($this->normalizeDate($node[0]));

                if (in_array($node[1], (array) $this->t('Samme som afhentning'))) {
                    $r->dropoff()->same();
                } else {
                    $r->dropoff()
                        ->location($node[1]);
                }
            }
        }

        return true;
    }

    private function parseHotel(Email $email): bool
    {
        $xpath = "//text()[{$this->eq($this->t('CHECK-IN'))}]/ancestor::table[(.//text()[starts-with(normalize-space(),'#')]  and (count(./descendant::img[contains(@src, '/hotelIcon.png')])=1 or " . $this->contains($this->t('Confirmation')) . " or " . $this->contains($this->t('Status')) . ")) and not({$this->contains($this->t('SEAT'))})][1][./descendant::text()[{$this->contains($this->t('ROOMS'))}]]";
        $this->logger->debug('Hotel xpath: ' . $xpath);
        $nodesRes = $this->http->XPath->query($xpath);

        if ($nodesRes->length === 0) {
            $currentLang = $this->lang;

            foreach (self::$dict as $lang => $d) {
                $this->lang = $lang;
                $nodesRes = $this->http->XPath->query($xpath);

                $words = ['CHECK-OUT', 'ADDRESS', 'ROOM TYPE', 'ROOMS'];
                $condition = [];

                foreach ($words as $word) {
                    $condition[] = ".//text()[{$this->eq($this->t($word))}]";
                }

                if ($nodesRes->length > 0
                    && !empty($condition) && !empty($this->http->FindSingleNode("(descendant-or-self::*[" . implode(' and ', $condition) . "])[1]", $nodesRes->item(0)))
                    && $this->http->XPath->query("//*[.//text()[{$this->contains($this->t('Reservation details'))}] and .//text()[{$this->contains($this->t('TRAVELLERS'))}]]")->length > 0
                ) {
                    $this->logger->debug('(Hotel) New lang is ' . $this->lang);

                    break;
                }
                $this->lang = $currentLang;
            }
        }

        foreach ($nodesRes as $rootRes) {
            $h = $email->add()->hotel();
            $phone = $this->getField($this->t('PHONE'), $rootRes);
            $h->hotel()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space(.)!=''][1]", $rootRes))
                ->address($this->getField($this->t('ADDRESS'), $rootRes));

            if (!empty($phone) && strlen($phone) > 4) {
                $h->hotel()
                    ->phone($phone);
            }

            $status = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation'))}]/preceding::text()[normalize-space(.)!=''][1][ancestor::td[1]/preceding::td[1]//img]",
                $rootRes);

            if (empty($status)) {
                $status = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK-IN'))}]/preceding::text()[normalize-space(.)!=''][2][ancestor::td[1]/preceding::td[1]//img]",
                    $rootRes);
            }

            if (empty($status)) {
                $status = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK-IN'))}]/preceding::text()[normalize-space(.)!=''][position()<7][{$this->contains($this->t("Status"))}]",
                    $rootRes);
            }

            if (in_array($status, (array) $this->t('Cancelled'))) {
                $h->general()->cancelled();
            }

            if (!empty($status)) {
                $h->general()
                    ->status($status);
            }
            $acc = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK-IN'))}]/preceding::text()[normalize-space()!=''][1][contains(.,'#')]",
                $rootRes, false, "/.+?\s*\#\s*([\w\-]+)/");

            if (!empty($acc)) {
                $h->program()
                    ->account($acc, false);
            }

            $confirm = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Confirmation'))}]/following::text()[normalize-space()][1]",
                $rootRes);
            $tripN = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.),'#')][1]",
                $rootRes, false, "/^# *([\w\-]+)$/");

            if (!empty($confirm)) {
                $confs = explode('/', $confirm);

                if (count($confs) == 1) {//Alica Černá
                    $confs = explode(',', $confirm);
                }

                if (count($confs) == 1 && preg_match("/^\w+ \w+$/u", $confs[0])) {
                    $confs[0] = 'Email ' . $confs[0];
                }
                $confs = array_map(function ($s) {
                    return trim(str_replace(' ', '', $s));
                }, $confs);

                if (count($confs) == 1) {
                    $conf = trim(array_shift($confs));
                    // Confirmation: Email Werner Theisen  || Alica Černá
                    if (preg_match("/^Email/", $conf)) {
                        if (!empty($tripN) && (!isset($this->otaConf) || ($tripN !== $this->otaConf))) {
                            $h->general()
                                ->confirmation($tripN);
                        } else {
                            $h->general()
                                ->noConfirmation();
                        }
                    } else {
                        $h->general()
                            ->confirmation($conf);
                    }
                } else {
                    if (count($confs) > 1 && $h->getCancelled()) {
                        if (!empty($tripN) && (!isset($this->otaConf) || $tripN !== $this->otaConf)) {
                            $h->general()
                                ->confirmation($tripN, '#', true);
                        } else {
                            $descr = (array) $this->t('Confirmation');
                            $descr = array_shift($descr);
                            $value = array_shift($confs);
                            $h->general()
                                ->confirmation($value, $descr, true);
                        }
                    }

                    foreach ($confs as $value) {
                        $h->general()
                            ->confirmation($value);
                    }
                }
            } elseif (!empty($tripN) && (!isset($this->otaConf) || ($tripN !== $this->otaConf))) {//&& $h->getCancelled()
                $h->general()
                    ->confirmation($tripN);
            } elseif ($this->http->XPath->query("./descendant::text()[{$this->eq($this->t('Confirmation'))}]",
                    $rootRes)->length === 0 && !empty($status)
            ) {
                $h->general()
                    ->noConfirmation();
            } elseif (!empty($this->otaConf) && !empty($tripN) && $this->otaConf === $tripN) {
                $h->general()
                    ->noConfirmation();
            }

            $node = $this->http->FindNodes("./following::table[{$this->contains($this->t('Reservation details'))}][1]/descendant::text()[{$this->contains($this->t('TRAVELLERS'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!='']//div",
                    $rootRes);

            if (count($node) === 0) {
                $node = $this->http->FindNodes(".//table[{$this->contains($this->t('Reservation details'))}][1]/descendant::text()[{$this->contains($this->t('TRAVELLERS'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!='']//div",
                    $rootRes);
            }

            if (count($node) > 0) {
                $pax = $node;
            }

            if (isset($pax)) {
                $h->general()
                    ->travellers(array_unique($pax));
            }

            $tot = [];

            $total = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK-IN'))}]/ancestor::table[1]/preceding::text()[normalize-space(.)!=''][position() < 5][" . $this->contains($this->t("Pay online")) . "]/ancestor::td[1]/descendant::text()[normalize-space(.)!=''][1]",
                $rootRes, true, "/^\s*(\d[\d,. ]*\s*\D{1,5}|\D{1,5}\s*\d[\d,. ]*)\s*$/");

            if (!empty($total)) {
                $tot = $this->getTotalCurrency($total);
            }

            if (empty($tot['Total'])) {
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK-IN'))}]/ancestor::table[1]/preceding::text()[normalize-space(.)!=''][1]/ancestor::td[1][.//img[contains(@src, '/email/trips/success.png')]]/descendant::text()[normalize-space(.)!=''][1]",
                    $rootRes, true, "/^\s*(\d[\d,. ]*\s*\D{1,5}|\D{1,5}\s*\d[\d,. ]*)\s*$/"));
            }

            if (empty($tot['Total'])) {
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('CHECK-IN'))}]/ancestor::table[1]/preceding::text()[normalize-space(.)!=''][1][not(contains(normalize-space(), 'Card ending'))]",
                    $rootRes, true, "/^\s*(\d[\d,. ]*\s*\D{1,5}|\D{1,5}\s*\d[\d,. ]*)\s*$/"));
            }

            if ($tot['Total'] !== null) {
                $h->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }

            $h->booked()
                ->checkIn($this->normalizeDate($this->getField($this->t('CHECK-IN'), $rootRes)))
                ->checkOut($this->normalizeDate($this->getField($this->t('CHECK-OUT'), $rootRes)))
                ->rooms($this->getField($this->t('ROOMS'), $rootRes))
                ->guests($h->getRoomsCount() * $this->getField($this->t('ADULTS'), $rootRes, "#(?:^|:\s*)(\d+)#"));

            $type = $this->getField($this->t('ROOM TYPE'), $rootRes);

            if (strlen($type) > 200) {
                $h->addRoom()
                    ->setDescription($this->getField($this->t('ROOM TYPE'), $rootRes));
            } else {
                $h->addRoom()
                    ->setType($this->getField($this->t('ROOM TYPE'), $rootRes));
            }

            $cancellation = implode(' ',
                array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Cancellation and Changes'))}]/following::*[self::li or self::p[{$this->starts('·')}]][position()<=3 and {$this->contains($this->t('Cancellation'))} and {$this->contains($this->t('hotel'))}]", null, '/^[·\s]*(.+)$/'))
            );

            if (!empty($cancellation)) {
                $h->general()->cancellation($cancellation);
            }
            $deadline = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("There is no Hotel penalty for cancellations made before")) . "]");

            if (preg_match("/^There is no Hotel penalty for cancellations made before (?<time>{$this->patterns['time']}) local hotel time on (?<date>\d{1,2}\/\d{1,2}\/\d{4})\./i", $deadline, $m) // en
                || preg_match("/^Nie obowiązują opłaty za anulowanie przed (?<time>{$this->patterns['time']}) czasu lokalnego hotelu dnia (?<date>\d{1,2}\/\d{1,2}\/\d{4})\./i", $cancellation, $m) // pl
            ) {
                if (!empty($deadDate = $this->normalizeDate($m['date']))) {
                    $h->booked()->deadline(strtotime($m['time'], $deadDate));
                }
            }
        }

        return true;
    }

    private function parseTrain(Email $email): bool
    {
        $xpath = "//text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::table[(contains(.,'#') or {$this->contains($this->t('Status'))}) and (.//img[contains(@src, '/flightIcon.png')] or .//img[contains(@src, '/railIcon.png')])][1][not({$this->contains($this->t('ROOMS'))})][./descendant::text()[{$this->contains($this->t('AMENITIES'))}]]";
        $this->logger->debug('train xpath: ' . $xpath);
        $nodesRes = $this->http->XPath->query($xpath);

        if ($nodesRes->length === 0) {
            $currentLang = $this->lang;

            foreach (self::$dict as $lang => $d) {
                $this->lang = $lang;
                $nodesRes = $this->http->XPath->query($xpath);

                $words = ['ARRIVAL', 'CLASS', 'SEAT', 'DURATION'];
                $condition = [];

                foreach ($words as $word) {
                    $condition[] = ".//text()[{$this->eq($this->t($word))}]";
                }

                if ($nodesRes->length > 0
                    && !empty($condition) && !empty($this->http->FindSingleNode("(descendant-or-self::*[" . implode(' and ', $condition) . "])[1]", $nodesRes->item(0)))
                    && $this->http->XPath->query("//*[.//text()[{$this->contains($this->t('Reservation details'))}] and .//text()[{$this->contains($this->t('TRAVELLERS'))}]]")->length > 0
                ) {
                    $this->logger->debug('(Train) New lang is ' . $this->lang);

                    break;
                }
                $this->lang = $currentLang;
            }
        }

        if ($nodesRes->length === 0) {
            $this->logger->debug('Trains not found.');
        }

        $trainsByPNR = [];

        foreach ($nodesRes as $rootRes) {
            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation'))}]/following::text()[normalize-space()][1]", $rootRes, true, "/^([A-Z\d]{5,})\s*(?:\(|$)/");

            if (!empty($confirmation)) {
                if (empty($trainsByPNR[$confirmation])) {
                    $trainsByPNR[$confirmation] = [$rootRes];
                } else {
                    $trainsByPNR[$confirmation][] = $rootRes;
                }
            } elseif ($this->http->XPath->query("descendant::text()[{$this->eq($this->t('Confirmation'))}]", $rootRes)->length === 0) {
                if (empty($trainsByPNR['unknown'])) {
                    $trainsByPNR['unknown'] = [$rootRes];
                } else {
                    $trainsByPNR['unknown'][] = $rootRes;
                }
            } else {
                $this->logger->debug('Wrong confirmation number in train!');

                return false;
            }
        }

        foreach ($trainsByPNR as $pnr => $tRoots) {
            $rootRes = $tRoots[0];

            $t = $email->add()->train();

            if ($pnr === 'unknown') {
                $t->general()->noConfirmation();
            } else {
                $confirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation'))}]", $rootRes, true, '/^(.+?)[\s:：]*$/u');
                $t->general()->confirmation($pnr, $confirmationTitle);
            }

            $status = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation'))}]/preceding::text()[normalize-space()][1]", $rootRes)
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('DEPARTURE'))}]/preceding::text()[normalize-space()][position()<7][ancestor::td[1]/preceding::td[1]//img]", $rootRes)
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Ticket'))}]/preceding::text()[normalize-space()][position()<5][ancestor::td[1]/preceding::td[1]//img]", $rootRes)
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('DEPARTURE'))} or {$this->eq($this->t('Ticket'))}]/preceding::text()[normalize-space()][position()<7][{$this->contains($this->t("Status"))}]", $rootRes)
            ;

            if (!empty($status)) {
                $t->general()->status($status);
            }

            if (in_array($status, (array) $this->t('Cancelled'))) {
                $t->general()->cancelled();
            }

            $travellers = array_filter($this->http->FindNodes("following::table[normalize-space()][1][{$this->contains($this->t('Reservation details'))}][1]/descendant::text()[{$this->contains($this->t('TRAVELLERS'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()]//div", $rootRes, "/^{$this->patterns['travellerName']}$/u"));

            if (count($travellers) > 0) {
                $t->general()->travellers($travellers);
            }

            $total = 0;

            foreach ($tRoots as $tRoot) {
                $segments = $this->http->XPath->query("descendant::text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::table[1]", $tRoot);

                foreach ($segments as $rootSeg) {
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('Confirmation'))}]/ancestor::td[2]/following-sibling::td[1]/descendant::*[normalize-space()][1]", $rootSeg));

                    if ($tot['Total'] === null) {
                        $tot = $this->getTotalCurrency($this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('Ticket'))}]/ancestor::td[2]/descendant::*[normalize-space()][1]", $rootSeg));
                    }

                    if ($tot['Total'] === null) {
                        $tot = $this->getTotalCurrency($this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('Ticket'))}][1]/preceding::text()[normalize-space()][1]", $rootSeg));
                    }

                    if ($tot['Total'] !== null) {
                        $total += $tot['Total'];
                        $t->price()->total($total)->currency($tot['Currency']);
                    }

                    $s = $t->addSegment();

                    $node = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $rootSeg);

                    if (preg_match("/(.+)\s+([A-Z\d]+)\s*$/", $node, $m)) {
                        $s->extra()->type($m[1])->number($m[2]);
                    } else {
                        $s->extra()->type($node)->noNumber();
                    }

                    $s->departure()
                        ->date($this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('DEPARTURE'))}][1]/following::text()[normalize-space()][1]", $rootSeg)))
                        ->name($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('DEPARTURE'))}][1]/following::text()[normalize-space()][2]", $rootSeg))
                    ;

                    $node = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('ARRIVAL'))}][1]/following::text()[normalize-space()][1]", $rootSeg);

                    if (preg_match("/^([-+])\s*(\d+)$/", $node, $m)) {
                        $s->arrival()
                            ->date($this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('ARRIVAL'))}][1]/following::text()[normalize-space()][2]", $rootSeg)))
                            ->name($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('ARRIVAL'))}][1]/following::text()[normalize-space()][3]", $rootSeg))
                        ;
                    } else {
                        $s->arrival()
                            ->date($this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('ARRIVAL'))}][1]/following::text()[normalize-space()][1]", $rootSeg)))
                            ->name($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('ARRIVAL'))}][1]/following::text()[normalize-space()][2]", $rootSeg))
                        ;
                    }

                    $s->extra()
                        ->cabin($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('CLASS'))}]/ancestor::*[{$this->eq($this->t('CLASS'))}]/following-sibling::*[normalize-space()][1]", $rootSeg))
                        ->duration($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('DURATION'))}]/ancestor::*[{$this->eq($this->t('DURATION'))}]/following-sibling::*[normalize-space()][1]", $rootSeg))
                        ->seat($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('SEAT'))}][1]/ancestor::*[{$this->eq($this->t('SEAT'))}]/following-sibling::*[normalize-space()][1]", $rootSeg, true, "#{$this->preg_implode($this->t("Seat"))}\s*([A-Z\d]{1,5})\b#"), false, true)
                        ->car($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('SEAT'))}]/ancestor::*[{$this->eq($this->t('SEAT'))}]/following-sibling::*[normalize-space()][2]", $rootSeg, true, "#{$this->preg_implode($this->t("Coach"))}\s*([A-Z\d]{1,5})\b#"), false, true)
                    ;
                }
            }
        }

        return true;
    }

    private function parseEmail(Email $email): bool
    {
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Itinerary/Egencia reference #")) . "]",
            null, true,
            "#" . $this->preg_implode($this->t("Itinerary/Egencia reference #")) . "\s*([A-Za-z\d\-]{5,})\b#"); // may be # rkn8ri

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[normalize-space()='Booked']/preceding::text()[contains(normalize-space(), '#')][1]", null, true, "/[#]\s*([A-Za-z\d\-]{5,})\b/u");
        }

        if (!empty($conf)) {
            $this->otaConf = $conf;
            $email->ota()
                ->confirmation($conf);
        }

        if (!$this->parseFlight($email)) {
            return false;
        }

        if (!$this->parseCar($email)) {
            return false;
        }

        if (!$this->parseHotel($email)) {
            return false;
        }
        // TODO: need more examples
        if ($this->http->XPath->query("//img[contains(@src,'transferIcon')]")->length > 0) {
            $this->logger->info("it seems there is also transfer reservations");

            return false;
        }

        if (!$this->parseTrain($email)) {
            return false;
        }

        return true;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug($date);
        $year = date('Y', $this->date);
        $in = [
            // without year
            // Thu 20 Dec at 04:45; on 22 aug. i 06:40; ma 06 elokuuta, 21:50, fr 02 nov på 16:45
            '#^([^\d\s.,]+)\s+(\d+)\s+([^\d\s,.]+)[.,\s]+(?:at|i|på|)\s*(\d+:\d+(?:\s*[ap]m)?)$#ui',
            // Tue 25 Sep
            '#^\s*([^\d\s.,]+)\s+(\d+)\s+([^\d\s.,]+)[.\s]*$#ui',

            // with year and time
            // 11-Nov-2018 at 15:25; 13 nov. 2018 à 17:00; 3-dic-2018 alle 16.10; 14-nov-2018 a las 19:30; 01.des.2018 på 18:15
            '#^\s*(\d{1,2})[\-\s.]+([^\d\s.,]+)[\-\s.,]+(\d{4})\s+(?:at|à|alle|a las|på|om)\s+(\d+)[:.](\d+(?:\s*[ap]m)?)\s*$#ui',
            // 2018-nov-23 kl. 14:50
            '#^\s*(\d{4})[\-\s.]+([^\d\s.,]+)[\-\s.,]+(\d{1,2})\s*(?:kl.|\s)\s*(\d+)[:.](\d+(?:\s*[ap]m)?)\s*$#ui',
            // Jan 8, 2019 at 8:40 am
            '#^\s*([^\d\s.,]+)[\-\s.]+(\d{1,2})[,\-\s.]+(\d{4})\s*(?:at|\s)\s*(\d+)[:.](\d+(?:\s*[ap]m)?)\s*$#ui',
            // 1.2.2019, 20:20; 22.11.2018 um 19:45; 19.11.2018 v 18:10
            '/^\s*(\d{1,2})[.\-](\d{1,2})[.\-](\d{4})[.,]?\s*(?:um|i|v|\s)\s*(\d{1,2}:\d{2})\s*$/',
            // 2018-12-12o godzinie17:55
            '#^\s*(\d{4})[\-\s.]+(\d{1,2})[\-\s.,]+(\d{1,2})\s*(?:\s|o godzinie)\s*(\d+)[:.](\d+(?:\s*[ap]m)?)\s*$#ui',
            //17/01/2019 at 10:35 pm
            '/^\s*(\d+)[\/]+(\d+)[\/](\d{4})\s*\w+\s+(\d+:\d+(?:\s*[ap]m)?)$/iu',
            //17/jan/2019 at 10:35 pm
            '/^\s*(\d+)[\/]+(\w+)[\/](\d{4})\s*\w+\s+(\d+:\d+(?:\s*[ap]m)?)$/iu',

            // with year and without time
            // 20-Nov-2018; 14 nov. 2018
            '/^\s*(\d{1,2})[\- .]+([^\d\s]+)[\- \.](\d{4})\s*$/',
            // 2018-nov-20
            '/^\s*(\d{4})[\- .]+([^\d\s]+)[\- \.](\d{1,2})\s*$/',
            // 22.11.2018; 21-11-2018
            '/^\s*(\d{1,2})[\-.](\d{1,2})[\-.](\d{4})\s*$/',
            // 2018-12-12
            '/^\s*(\d{4})[\-.](\d{1,2})[\-.](\d{1,2})\s*$/',
            // Jan 8, 2019
            '#^\s*([^\d\s.,]+)[\-\s.]+(\d{1,2})[,\-\s.]+(\d{4})\s*$#ui',
            //17/01/2019
            '/^\s*(\d+)[\/]+(\d+)[\/](\d{4})\s*$/',
            //17/jan/2019
            '/^\s*(\d+)[\/]+(\w+)[\/](\d{4})\s*$/',
        ];
        $out = [
            '$2 $3 ' . $year . ' $4',
            '$2 $3 ' . $year,
            '$1 $2 $3, $4:$5',
            '$3 $2 $1, $4:$5',
            '$2 $1 $3, $4:$5',
            '$1.$2.$3, $4',
            '$3.$2.$1, $4:$5',
            '$1.$2.$3, $4',
            '$1 $2 $3, $4',
            '$1.$2.$3',
            '$3.$2.$1',
            '$1.$2.$3',
            '$3.$2.$1',
            '$2 $1 $3',
            '$1.$2.$3',
            '$1 $2 $3',
        ];
        $outWeek = [
            '$1',
            '$1',

            '',
            '',
            '',
            '',
            '',
            '',
            '',

            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBodies) {
                foreach ($reBodies as $reBody) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                        && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                    ) {
                        $this->lang = $lang;
                        $this->langMainPart = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        if (preg_match("#\bkr\b#", $node)) {
            switch (true) {
                case $this->lang == 'da' || $this->langMainPart == 'da':
                    $node = preg_replace("#\bkr\b#", "DKK", $node);

                    break;

                case $this->lang == 'no' || $this->langMainPart == 'no':
                    $node = preg_replace("#\bkr\b#", "NOK", $node);

                    break;

                case $this->lang == 'sv' || $this->langMainPart == 'sv':
                    $node = preg_replace("#\bkr\b#", "SEK", $node);

                    break;
            }
        }
        $node = str_replace(["SFr.", "SFr"], "CHF", $node);
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("Rs.", "INR", $node);
        $node = str_replace("zł", "PLN", $node);
        $node = str_replace("Kč", "CZK", $node);
        $tot = null;
        $cur = null;

        if (
            preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $tot = PriceHelper::cost($m['t']);

            if (empty($tot)) {
                $tot = PriceHelper::cost($m['t'], '.', ',');
            }
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function getProviderByKeyword(string $keyword)
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {
            return preg_quote($v, '#');
        }, $field)) . ')';
    }

    private function getField($title, $root = null, $regexp = null, $addXpath = '')
    {
        $result = $this->http->FindSingleNode("." . $addXpath . "/descendant::text()[{$this->eq($title)}]/ancestor::*[1]/following-sibling::*[1]",
            $root, true, $regexp);

        if (empty($result)) {
            $result = $this->http->FindSingleNode("." . $addXpath . "/descendant::text()[{$this->eq($title)}]/ancestor::div[1][not(.//td)]/following-sibling::*[1]",
                $root, true, $regexp);
        }

        return $result;
    }
}
