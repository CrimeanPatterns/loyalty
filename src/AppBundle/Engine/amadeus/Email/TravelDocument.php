<?php

namespace AwardWallet\Engine\amadeus\Email;

// if pdf: parse by checkmytrip/PDF.php
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Common\TrainSegment;
use AwardWallet\Schema\Parser\Email\Email;

class TravelDocument extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-123794471.eml, amadeus/it-124667646.eml, amadeus/it-13019921.eml, amadeus/it-1725876.eml, amadeus/it-20021735.eml, amadeus/it-28622708.eml, amadeus/it-28787754.eml, amadeus/it-29622458.eml, amadeus/it-29670327.eml, amadeus/it-29877045.eml, amadeus/it-330298489.eml, amadeus/it-3577037.eml, amadeus/it-3577038.eml, amadeus/it-3583878.eml, amadeus/it-37792078.eml, amadeus/it-38763643.eml, amadeus/it-40840870.eml, amadeus/it-44632792.eml, amadeus/it-45199713.eml, amadeus/it-4578735.eml, amadeus/it-4599790.eml, amadeus/it-4638989.eml, amadeus/it-4698639.eml, amadeus/it-6350314.eml, amadeus/it-6445064.eml, amadeus/it-8640573.eml, amadeus/it-8847095.eml, amadeus/it-8940051.eml, amadeus/it-8976933.eml, amadeus/it-9472190.eml";

    public $reBody = [
        'en'  => ['Your trip', 'Booking ref'],
        'en2' => ['Invoice', 'Booking ref'],
        'en3' => ['OFFER NOTICE', 'Reference:'],
        'en4' => ['Itinerary', 'Booking ref'],
        'en5' => ['Invoice Details', 'General Information'],
        'en6' => ['General Information', 'Baggage allowance'],
        'en7' => ['Your FCm Quote', 'Reference:'],
        'en8' => ['Traveler', 'Itinerary'],
        'fr'  => ['Votre voyage', 'Ref. Dossier:'],
        'fr2' => ['Votre voyage', 'Référence de dossier:'],
        'fr3' => ['Reçu de Billet Electronique', 'Reference du dossier'],
        'es'  => ['Viajero', 'Localizador de reserva:'],
        'sv'  => ['Ovanstående information ges med förbehåll för ändringar', 'Bokningsreferens'],
        'da'  => ['Din rejse', 'Reservations nr'],
        'pt'  => ['A sua viagem', 'Ref. reserva'],
        'de'  => ['Ihre Reise', 'Buchungsreferenz'],
        'no'  => ['Din reise', 'Referanse'],
        'it'  => ['Viaggio', 'Rif. prenotazione'],
    ];

    public $reSubject = [
        '#Your E-ticket Receipt for .+? departure#',
        '#Booking ref: [A-Z\d]{5,6} #',
        '#You can also modify this[ !]+Here is your Itinerary#',
        '#(?:Votre voyage|Reiseplan fuer)[\s:]+.+?\s+\d+[A-Z]{3}\d{4}\s+#',
        '#CAMAFORT .+?\s+\d+[A-Z]{3}\d{4}\s+#',
        '#\w+\s*\/\s*\w+\s*\w*\s+\d+[A-Z]{3}\d{4}\s+[A-Z]{3}[ \-]+[A-Z]{3}#',
        '#Your FCm Quote, .+? on .+? for your trip in [A-Z]{3}[ \-]+[A-Z]{3}$#',
        '#CONFIRMED ETKT ITINERARY AND INVOICE#',
        '#E-Ticket on#',
    ];

    public $lang = 'en';
    public $airline;

    public $date;
    public static $dict = [
        "en" => [
            "Booking ref:" => ["Booking ref:", "Booking ref", "Reference:"],
            "Issued date"  => [
                "Issued date",
                "Issue date",
                "Issued date:",
                "Date:",
                "Document Issue Date",
                "Document Issue Date:",
            ],
            "Airline Booking Reference(s)" => ["Airline Booking Reference(s)", "Airline Booking Reference"],
            "Traveler"                     => ["Traveler", "Traveller"],
            "notPax"                       => ["Agency", "Telephone", "Fax", "Email", "Website", "Agent initial", "Ticket details"],
            //            "Booking status" => "",

            // Flight and Train
            "Ticket details" => ["Ticket details", "Rail ticket(s)"],
            //            "E-ticket" => "",
            "Ticket Number" => ["Ticket Number", "Ticket number"],
            //            "Departure" => "",
            "Operated by" => ["Operated by", "Operated By"],
            //            "Equipment" => "",
            //            "Class" => "",
            "Duration" => ["Duration", "Total duration"],
            "Distance" => ["Distance", "Flight Distance"],
            //            "Seat" => "",
            "confirmed for" => ["confirmed for", "Confirmed     for", "requested for", "Confirmed for"],
            "Meal"          => ["Flight meal", "Flight Meal", "Meal"],
            //            "Coach" => "",
            "Frequent Flyer number" => ["Frequent Flyer number", "Frequent flyer number"],
            //            "for" => "",

            // Hotel and rental
            "Confirmation number" => ["Confirmation number", "Hotel Confirmation Number:"],
            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "Tel " => "",
            //            "Fax " => "",
            //            "Name" => "",
            //            "Occupancy" => "",
            "Rate"                => ["Rate", "Hotel Cost Per Night:"],
            "Cancellation policy" => ["Cancellation policy", "Cancellation Policy"],
            "Room type"           => ["Room type", "Room Type:"],
            //            "Estimated total" => "",
            //            "Membership ID" => "",
            //            "Location" => "",
            //            "Contact" => "",
            //            "Pick up" => "",
            //            "Drop off" => "",
            //            "Car type" => "",
            //            "Car Rental" => "",
            //            "Other information" => "",
            //            "Miscellaneous" => "",
            "In-"  => ["In-", "Check in time"],
            "out-" => ["out-", "check out time"],
        ],
        "fr" => [
            "Booking ref:"                 => ["Référence de dossier:", "Ref. Dossier:", "Reference du dossier"],
            "Issued date"                  => ["Date d’émission:", "Date:", "Date d'émission"],
            "Airline Booking Reference(s)" => ["Référence(s) dossier compagnie aérienne", "Référence dossier compagnie aérienne"],
            "Traveler"                     => ["Voyageur", 'Passager'],
            "notPax"                       => ["Agence", "Téléphone", "Fax", 'Agent', 'Phone'],
            "Booking status"               => "Statut de la réservation",

            // Flight and Train
            "Ticket details" => "Détail du billet",
            "E-ticket"       => "Numéro de billet",
            "Ticket Number"  => ["Numéro De Billet", 'Numéro de billet'],
            "Departure"      => "Départ",
            "Arrival"        => "Arrivée",
            "Operated by"    => ["Exploité par", "Opéré Par"],
            "Equipment"      => "Équipement",
            "Class"          => "Classe",
            "Duration"       => "Durée",
            //            "Distance" => "",
            "Seat" => "Siège",
            //            "confirmed for" => "",
            "Meal"                  => ["Repas", "Flight meal"],
            "Coach"                 => "Numéro de voiture",
            "Frequent Flyer number" => "Numéro de carte de fidélité",
            "for"                   => "pour",

            // Hotel and rental
            "Confirmation number" => "Numéro de confirmation",
            "Check-in"            => "Arrivée",
            "Check-out"           => "Départ",
            "Tel "                => "Tél.",
            "Fax "                => "Fax ",
            "Name"                => "Nom",
            "Occupancy"           => "Occupation",
            "Rate"                => "Tarif",
            "Cancellation policy" => "Conditions d'annulation",
            "Room type"           => "Type de chambre",
            "Estimated total"     => "Total estimé",
            //            "Membership ID" => "",
            "Location" => "Emplacement",
            "Contact"  => "Contact",
            //            "Pick up" => "",
            //            "Drop off" => "",
            //            "Car type" => "",
            //            "Car Rental" => "",
            //            "Other information" => "",
            //            "Miscellaneous" => "",
            //            "In-" => "",
            //            "out-" => "",
        ],
        "es" => [
            "Booking ref:"                 => "Localizador de reserva:",
            "Issued date"                  => ["Fecha de emisión:", "Fecha:"],
            "Airline Booking Reference(s)" => [
                "Localizador(es) Aerolínea(s)",
                "Localizador(es) de reserva de la aerolínea",
            ],
            "Traveler"       => "Viajero",
            "notPax"         => ["Agencia", "Teléfono", "Fax", "Correo electrónico"],
            "Booking status" => "Estatus de la reserva",

            // Flight and Train
            "Ticket details" => ["Detalles de billete", "Ticket details"],
            //            "E-ticket" => "",
            //            "Ticket Number" => "",
            "Departure"   => "Salida",
            "Arrival"     => "Llegada",
            "Operated by" => "Operado por",
            "Equipment"   => "Equipo",
            "Class"       => "Clase",
            "Duration"    => ["Duración", "Duración total"],
            //            "Distance" => "",
            //            "Seat"=>"",
            "confirmed for" => "confirmado para",
            "Meal"          => ["Comida", "Flight meal"],
            //            "Coach" => "",
            "Frequent Flyer number" => "Número de viajero frecuente",
            "for"                   => "para",

            // Hotel and rental
            "Confirmation number" => "Número de confirmación",
            "Check-in"            => "Entrada",
            "Check-out"           => "Salida",
            "Tel "                => "Tel.",
            "Fax "                => "Fax ",
            "Name"                => "Nombre",
            "Occupancy"           => "Occupancy",
            "Rate"                => "Tarifa",
            "Cancellation policy" => "Condiciones de cancelación",
            "Room type"           => "Tipo de habitación",
            "Estimated total"     => "Importe total estimado",
            //            "Membership ID" => "",
            "Location"   => "Ubicación",
            "Contact"    => "Contacto",
            "Pick up"    => "Recogida",
            "Drop off"   => "Entrega",
            "Car type"   => "Tipo de coche",
            "Car Rental" => "Alquiler de coches",
            //            "Other information" => "",
            //            "Miscellaneous" => "",
            //            "In-" => "",
            //            "out-" => "",
        ],
        "it" => [
            "Booking ref:" => "Rif. prenotazione:",
            "Issued date"  => ["Data di emissione:"],
            //            "Airline Booking Reference(s)" => [""],
            "Traveler"       => "Viaggiatore",
            "notPax"         => ["Agenzia", "Telefono", "Fax"],
            "Booking status" => "Stato della prenotazione",

            // Flight and Train
            //            "Ticket details" => [""],
            //            "E-ticket" => "",
            //            "Ticket Number" => "",
            //            "Departure" => "",
            //            "Arrival" => "",
            //            "Operated by" => "",
            //            "Equipment" => "",
            //            "Class" => "",
            //            "Duration" => [""],
            //            "Distance" => "",
            //            "Seat"=>"",
            //            "confirmed for" => "",
            //            "Meal" => [""],
            //            "Coach" => "",
            //            "Frequent Flyer number" => "",
            //            "for" => "",

            // Hotel and rental
            "Confirmation number" => "Numero di conferma",
            "Check-in"            => "Arrivo",
            "Check-out"           => "Partenza",
            "Tel "                => "Tel.",
            "Fax "                => "Fax ",
            "Name"                => "Nome",
            "Occupancy"           => "Occupancy",
            "Rate"                => "Tariffa",
            "Cancellation policy" => "Regole di cancellazione",
            "Room type"           => "Tipo di camera",
            "Estimated total"     => "Totale stimato",
            "Taxes"               => "Tasse",
            //            "Membership ID" => "",
            "Location" => "Posizione",
            "Contact"  => "Contatto",
            //            "Pick up" => "",
            //            "Drop off" => "",
            //            "Car type" => "",
            //            "Car Rental" => "",
            //            "Other information" => "",
            //            "Miscellaneous" => "",
            //            "In-" => "",
            //            "out-" => "",
        ],
        //need to full words
        "sv" => [
            //            "Booking ref:" => "",
            //            "Issued date" => "",
            //            "Airline Booking Reference(s)" => "",
            //            "Traveler" => "",
            //            "notPax" => "",
            //            "Booking status" => "",

            // Flight and Train
            //            "Ticket details" => "",
            //            "E-ticket" => "",
            //            "Ticket Number" => "",
            "Departure" => "Avgång",
            "Arrival"   => "",
            //            "Operated by" => "",
            "Equipment" => "Flygplanstyp",
            "Class"     => "Klass",
            "Duration"  => "Restid",
            //            "Distance" => "",
            //            "Seat" => "",
            //            "confirmed for" => "",
            //            "Meal" => "",
            //            "Coach" => "",
            //            "Frequent Flyer number" => "",
            //            "for" => "",

            // Hotel and rental
            //            "Confirmation number" => "",
            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "Tel " => "",
            //            "Fax " => "",
            //            "Name" => "",
            //            "Occupancy" => "",
            //            "Rate" => "",
            //            "Cancellation policy" => "",
            //            "Room type" => "",
            //            "Estimated total" => "",
            //            "Membership ID" => "",
            //            "Location" => "",
            //            "Contact" => "",
            //            "Pick up" => "",
            //            "Drop off" => "",
            //            "Car type" => "",
            //            "Car Rental" => "",
            //            "Other information" => "",
            //            "Miscellaneous" => "",
            //            "In-" => "",
            //            "out-" => "",
        ],
        "da" => [
            "Booking ref:"                 => ["Reservations nr:"],
            "Issued date"                  => ["Udstedelsesdato"],
            "Airline Booking Reference(s)" => ["Flybookingreference(r)"],
            "Traveler"                     => "Rejsende",
            "notPax"                       => ["Rejsebureau", "Direkte nr", "Fax", "E-mail"],
            "Booking status"               => "Reservationsstatus",

            // Flight and Train
            "Ticket details" => "Billetdetaljer",
            //            "E-ticket" => "",
            //            "Ticket Number" => "",
            "Arrival"   => "",
            "Departure" => "Afgang",
            //            "Operated by" => "",
            "Equipment" => "Flytype",
            "Class"     => "Klasse",
            "Duration"  => "Varighed",
            //            "Distance" => "",
            //            "Seat" => "",
            //            "confirmed for" => "",
            "Meal" => ["Måltid"],
            //            "Coach" => "",
            "Frequent Flyer number" => "Bonuskort",
            //            "for" => "",

            // Hotel and rental
            "Confirmation number" => "Referencenummer",
            "Check-in"            => "Indcheckning",
            "Check-out"           => "Udcheckning",
            "Tel "                => "Tlf. ",
            //            "Fax " => "",
            "Name"                => "Navn",
            "Occupancy"           => "Værelse til",
            "Rate"                => "Pris",
            "Cancellation policy" => "Annulleringsbetingelser",
            "Room type"           => "Værelsetype",
            "Estimated total"     => "Anslået total pris",
            "Membership ID"       => "Firmakonto-id",
            "Location"            => ["Location", "Adresse"],
            //            "Contact" => "",
            //            "Pick up" => "",
            //            "Drop off" => "",
            //            "Car type" => "",
            //            "Car Rental" => "",
            "Other information" => "Andre oplysninger",
            //            "Miscellaneous" => "",
            //            "In-" => "",
            //            "out-" => "",
        ],
        "pt" => [
            "Booking ref:"                 => ["Ref. reserva:"],
            "Issued date"                  => ["Data de emissão:"],
            "Airline Booking Reference(s)" => [
                "Referência(s) de reserva da companhia aérea",
                "Referência de reserva da companhia aérea",
            ],
            "Traveler"       => "Passageiro",
            "notPax"         => ["Agência", "Telefone", "Fax"],
            "Booking status" => "Estado da reserva",

            // Flight and Train
            "Ticket details" => "Detalhes do bilhete",
            "E-ticket"       => "Bilhete eletrônico",
            //            "Ticket Number" => "",
            "Departure"   => "Partida",
            "Arrival"     => "Chegada",
            "Operated by" => "Operado por",
            "Equipment"   => "Equipamento",
            "Class"       => "Classe",
            "Duration"    => "Duração",
            //            "Distance" => "",
            "Seat" => ["Seat", "Lugar"],
            //            "confirmed for" => "",
            "Meal" => ["Flight meal"],
            //            "Coach" => "",
            //            "Frequent Flyer number" => "",
            //            "for" => "",

            // Hotel and rental
            "Confirmation number" => "Número de confirmação",
            "Check-in"            => "Check-in",
            "Check-out"           => "Check-out",
            "Tel "                => "Tel.",
            //                        "Fax " => "",
            "Name"                => "Nome",
            "Occupancy"           => "Occupancy",
            "Rate"                => "Tarifa",
            "Cancellation policy" => "Política de cancelamento",
            "Room type"           => "Tipo de quarto",
            "Estimated total"     => "Total estimado",
            //                        "Membership ID" => "",
            "Location" => "Local",
            "Contact"  => "Contato",
            //            "Pick up" => "",
            //            "Drop off" => "",
            //            "Car type" => "",
            //            "Car Rental" => "",
            //            "Other information" => "",
            //            "Miscellaneous" => "",
            //            "In-" => "",
            //            "out-" => "",
        ],
        "de" => [
            "Booking ref:"                 => ["Buchungsreferenz:"],
            "Issued date"                  => ["Ausstellungsdatum:"],
            "Airline Booking Reference(s)" => ["Buchungsreferenz(en) der Flug", 'Ticketnummer'],
            "Traveler"                     => "Reisender",
            "notPax"                       => ["Fax", "Telefon", "E-Mail-Adresse", "Reisebüro"],
            "Booking status"               => "Status",

            // Flight and Train
            "Ticket details" => "Ticketdetails",
            "E-ticket"       => "E-Ticket",
            "Ticket Number"  => "Ticketnummer",
            "Departure"      => "Abreise",
            "Arrival"        => "Ankunft",
            "Operated by"    => "Durchgeführt von",
            "Equipment"      => "Ausstattung",
            "Class"          => "Klasse",
            "Duration"       => "Dauer",
            //            "Distance" => "",
            "Seat"          => "Sitzplatz",
            "confirmed for" => "bestätigt für",
            "Meal"          => ["Mahlzeit"],
            //            "Coach" => "",
            "Frequent Flyer number" => "Vielfliegernummer",
            "for"                   => "für",

            // Hotel and rental
            "Confirmation number" => "Bestätigungsnummer",
            "Check-in"            => "Anreise",
            "Check-out"           => "Abreise",
            "Tel "                => "Telefon ",
            "Fax "                => "Fax ",
            "Name"                => "Name",
            "Occupancy"           => "Occupancy",
            "Rate"                => "Preis",
            "Cancellation policy" => "Stornobedingungen",
            "Room type"           => "Zimmerkategorie",
            "Estimated total"     => "Geschätzter Endpreis",
            //            "Membership ID" => "",
            "Location"   => "Ort",
            "Contact"    => "Kontakt",
            "Pick up"    => "Abholung",
            "Drop off"   => "Rückgabe",
            "Car type"   => "Fahrzeugtyp",
            "Car Rental" => "Mietwagenanbieter",
            //            "Other information" => "",
            //            "Miscellaneous" => "",
            //            "In-" => "",
            //            "out-" => "",
        ],
        "no" => [
            "Booking ref:"                 => ["Referanse:"],
            "Issued date"                  => ["Utstedelsesdato:"],
            "Airline Booking Reference(s)" => ["Booking referanse"],
            "Traveler"                     => "Reisende",
            "notPax"                       => ["Reisebyrå", "Telefon", "Fax"],
            //            "Booking status" => "",

            // Flight and Train
            "Ticket details" => "Billettdetaljer",
            "E-ticket"       => "E-ticket",
            //            "Ticket Number" => "",
            "Departure" => "Avreise",
            "Arrival"   => "",
            //            "Operated by" => "",
            "Equipment" => "Flytype",
            "Class"     => "Klasse",
            "Duration"  => "Reisetid",
            //            "Distance" => "",
            "Seat"          => "Sete",
            "confirmed for" => "bekreftet for",
            "Meal"          => ["Måltid"],
            //            "Coach" => "",
            "Frequent Flyer number" => "Vielfliegernummer",
            //            "for" => "",

            // Hotel and rental
            //            "Confirmation number" => "",
            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "Tel " => "Telefon ",
            //            "Fax " => "Fax ",
            //            "Name" => "",
            //            "Occupancy" => "",
            //            "Rate" => "",
            //            "Cancellation policy" => "",
            //            "Room type" => "",
            //            "Estimated total" => "",
            //            "Membership ID" => "",
            //            "Location" => "",
            //            "Contact" => "",
            //            "Pick up" => "",
            //            "Drop off" => "",
            //            "Car type" => "",
            //            "Car Rental" => "",
            //            "Other information" => "",
            //            "Miscellaneous" => "",
            //            "In-" => "",
            //            "out-" => "",
        ],
    ];

    private static $providers = [
        'fcmtravel'   => [
            'from' => ['.fcm.travel', '@fcmtravel.co'],
            'body' => ['FCm Travel Solutions'],
        ],
        'atpi'        => [
            'from' => 'atpi.com',
            'body' => ['atpi.com'],
        ],
        'fct'         => [
            'from' => ['flyingcolours@netspace.net.au', 'flyingcolourstravel.com.au'],
            'body' => ['FLYING COLOURS TRAVEL'],
        ],
        'egencia'     => [
            'from' => 'egencia.',
            'body' => ['VIA EGENCIA'],
        ],
        'autoeuro'    => [
            'from' => 'autoeurope.com',
            'body' => ['AUTO EUROPE LLC'],
        ],
        'ctraveller'  => [
            'from' => 'corptraveller.',
            'body' => ['Corporate Traveller'],
        ],
        'bcd'         => [
            'from' => 'bcdtravel.',
            'body' => ['bcdtravel'],
        ],
        'derpart'     => [
            //            'from' => '',
            'body' => ['DERPART CANNSTATTER'],
        ],
        'amadeus'     => [
            'from' => 'amadeus',
            'body' => ['amadeus'],
        ],
        'checkmytrip' => [
            'from' => 'checkmytrip',
            'body' => ['checkmytrip'],
        ],
        'flyerbonus' => [
            'from' => '@bangkokairline.org',
            'body' => ['booking@bangkokairline.org'],
        ],
    ];
    private $pax;
    private $keywordsRental = [
        'avis' => [
            'Avis',
        ],
        'hertz' => [
            'Hertz',
        ],
        'thrifty' => [
            'Thrifty',
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?',
        // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
        'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]',
        // Mr. Hao-Li Huang
        'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*\/(?:[[:upper:]]+ )*[[:upper:]]+',
        // KOH/KIM LENG MR
    ];

    private $xpath = [
        'firstTableBg' => 'contains(@bgcolor,"#b6bbbd") or contains(@style,"#b6bbbd")'
            . 'or contains(@bgcolor,"#B6BBBD") or contains(@style,"#B6BBBD" or contains(@style,"#9BCAEB"))',
        'flightCell' => 'normalize-space() and contains(normalize-space()," ") and not(starts-with(normalize-space(),"<"))',
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');
        }
        $tripNum = $this->http->FindSingleNode("(//td[" . $this->eq($this->t("Booking ref:")) . "])[1]/following-sibling::td[normalize-space(.)!='' and normalize-space(.)!=':'][1]");

        if (empty($tripNum)) {
            $tripNum = $this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Booking ref:")) . "])[1]/following::text()[normalize-space(.)!='' and normalize-space(.)!=':'][1]");
        }

        if (empty($tripNum)) {
            $tripNum = $this->http->FindSingleNode("(.//text()[" . $this->starts($this->t("Booking ref:")) . "])[1]",
                null, false, "#{$this->opt($this->t("Booking ref:"))}\s+([A-Z\d]{5,})$#");
        }
        $provider = $this->getProvider(implode(" ", $parser->getFrom()));

        $this->date = strtotime($parser->getHeader('date'));

        $email->setType('TravelDocument' . ucfirst($this->lang));

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Following \"offer(s)\" is not a confirmed reservation or booking and is subject to availability')]")->length > 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(),'Quote')]")->length > 0) {
            $email->setIsJunk(true);
        } else {
            if (!empty($tripNum)) {
                $email->ota()
                    ->confirmation($tripNum);
            }
            $email->ota()
                ->code($provider);

            $this->parseEmail($email);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(.),'Check My Trip')] | //a[contains(@href, 'checkmytrip.com')]"
                . " | //a[contains(@href, 'amadeus.com')] | //img[contains(@src, 'amadeus.')]")->length > 0) { // amadeus.com or amadeus.net
            return $this->assignLang();
        } else {
            foreach (self::$providers as $code => $data) {
                foreach ($data as $bodyValue) {
                    if ($this->http->XPath->query("//text()[{$this->contains($bodyValue)}]")->length > 0) {
                        return $this->assignLang();
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && $this->detectEmailFromProvider($headers['from'])) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        if (stripos($from, 'amadeus') !== false) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 5; //2 flights + train + hotel + rental
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    private function getProvider($from)
    {
        foreach (self::$providers as $code => $cond) {
            if (isset($cond['from'])) {
                if (is_array($cond['from'])) {
                    foreach ($cond['from'] as $cf) {
                        if (stripos($from, $cf) !== false) {
                            return $code;
                        }
                    }
                } elseif (stripos($from, $cond['from']) !== false) {
                    return $code;
                }
            }

            if (isset($cond['body'])) {
                foreach ($cond['body'] as $cb) {
                    if (stripos($this->http->Response['body'], $cb) !== false) {
                        return $code;
                    }
                }
            }
        }

        return 'checkmytrip';
    }

    private function parseTrips(Email $email): bool
    {
        //determinate ReservationDate
        $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Booking ref:")) . "]/following::td[" . $this->eq($this->t("Issued date")) . "])[1]/following-sibling::td[normalize-space(.)!='' and normalize-space(.)!=':'][1]")));

        if (empty($resDate)) {
            $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Booking ref:")) . "]/following::text()[" . $this->eq($this->t("Issued date")) . "])[1]/following::text()[normalize-space(.)!='' and normalize-space(.)!=':'][1]")));
        }

        if (empty($resDate)) {
            $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Booking ref:")) . "]/following::text()[" . $this->eq($this->t("Issued date")) . "])[1]/following::text()[normalize-space(.)!='' and normalize-space(.)!=':'][1]")));
        }

        //determinate Passengers
        $pax = [];

        $colspanTitle = $this->http->FindSingleNode("//tr[not(.//tr) and not({$this->contains($this->t('Ticket Number'))})]/td[1][{$this->eq($this->t('Traveler'))}]/@colspan");
        $colspanName = $this->http->FindSingleNode("//tr[not(.//tr) and not({$this->contains($this->t('Ticket Number'))})]/td[1][{$this->eq($this->t('Traveler'))}]/following-sibling::td[1]/@colspan");

        if (!empty($colspanTitle) && !empty($colspanName)) {
            $paxNodes = $this->http->XPath->query("//tr[not(.//tr) and not({$this->contains($this->t('Ticket Number'))})][td[1][{$this->eq($this->t('Traveler'))}]]/preceding-sibling::tr[1]/following-sibling::tr[position() < 10]");

            foreach ($paxNodes as $pRoot) {
                $name = $this->http->FindSingleNode("./td[1][{$this->eq($this->t('Traveler'))} or string-length(normalize-space()) = 0 and @colspan = {$colspanTitle}]/following-sibling::td[1][@colspan = {$colspanName}]",
                    $pRoot, null, "/^\s*[[:alpha:] \*\/]+\s*$/");

                if (!empty($name)) {
                    $pax[] = $name;
                } else {
                    break;
                }
            }
        }
        // it-37792078.eml
        if (is_array($this->t('notPax')) && count($this->t('notPax')) > 0 && count($pax) === 0) {
            $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('Traveler'))}]/ancestor::tr[1][not({$this->contains($this->t('Ticket Number'))})]/preceding-sibling::tr[1]/following-sibling::tr[position()<6 and count(descendant::td[normalize-space()!=''])>=2 and not({$this->contains($this->t('Ticket details'))} or {$this->contains($this->t("Class"))}) and not(preceding-sibling::tr[position()<6 and ({$this->contains($this->t('Ticket details'))} or {$this->contains($this->t("Class"))})])]/td[position()<3][./p or ./div][not({$this->contains($this->t('Traveler'))}) and not({$this->contains($this->t('notPax'))})]",
                null, "#^[^\d\@]+$#");
            $pax = array_values(array_filter($pax));
        }
        $this->logger->error(count($pax));

        if (count($pax) === 0) {
            // it-9472190.eml
            $travellerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Traveler'))}]/ancestor::tr[1][{$this->contains($this->t('Ticket Number'))} and not({$this->contains($this->t('Ticket details'))})]/following-sibling::tr[normalize-space()]");

            foreach ($travellerRows as $tRow) {
                if ($this->http->XPath->query("descendant-or-self::*[{$this->xpath['firstTableBg']}]",
                        $tRow)->length === 0) {
                    break;
                }
                $traveller_temp = $this->http->FindSingleNode('td[2]', $tRow, true,
                    "/^(?:{$this->patterns['travellerName']}|{$this->patterns['travellerName2']})$/");

                if ($traveller_temp) {
                    $pax[] = $traveller_temp;
                }
            }
        }

        if (count($pax) === 0) {
            // it-9472190.eml
            $travellerRows = $this->http->XPath->query("//tr[not(.//tr)][td[normalize-space()][1][{$this->eq($this->t('Traveler'))}] and td[normalize-space()][2][{$this->contains($this->t('Ticket Number'))}] and not({$this->contains($this->t('Ticket details'))})]/following-sibling::tr[normalize-space()][position() < 20]");

            foreach ($travellerRows as $tRow) {
                $traveller_temp = $this->http->FindSingleNode('td[normalize-space()][1]', $tRow, true,
                    "/^(?:{$this->patterns['travellerName']}|{$this->patterns['travellerName2']})$/");

                $ticket_temp = $this->http->FindSingleNode('td[normalize-space()][2]', $tRow, true,
                    "/^\s*\d{3}[ \-]?\d{10}[\/\d\- ]*\s*$/");

                if (!empty($traveller_temp) && !empty($ticket_temp)) {
                    $pax[] = $traveller_temp;
                } else {
                    break;
                }
            }
        }

        if (empty($pax)) {
            $pax = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'E-ticket ')]", null, "/{$this->opt($this->t('for'))}\s*(.+)/");
        }

        if (empty($pax)) {
            $pax = $this->http->FindNodes('//*[normalize-space(.)="Traveler"]/ancestor::tr[1]/following-sibling::tr/td[1]');
        }

        $this->pax = array_filter($pax, function ($s) {
            return strlen($s) > 3;
        });

        $xpathABR = "contains(.,'):') or contains(normalize-space(),': ') or contains(.,'/')";

        //determinate recLocs
        $rls = [];
        $tickets = [];
        $airlineReferences = $this->http->XPath->query("//text()[{$this->eq($this->t("Airline Booking Reference(s)"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1][{$xpathABR}]");

        if ($airlineReferences->length === 0) {
            $airlineReferences = $this->http->XPath->query("//text()[{$this->eq($this->t("Airline Booking Reference(s)"))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][position()<5 and count(descendant::td[normalize-space()])<3]/td[{$xpathABR}]");
        }

        if ($airlineReferences->length === 0) {
            $airlineReferences = $this->http->XPath->query("//text()[{$this->contains($this->t('Ticket details'))}]/ancestor::tr[count(td[normalize-space()])=2][1]/td[1] | //text()[{$this->contains($this->t('Ticket details'))}]/ancestor::tr[count(td[normalize-space()])=2][1]/following-sibling::tr[normalize-space()][position()<6]/td[1]");
        }

        if ($airlineReferences->length === 0) {
            $airlineReferences = $this->http->XPath->query("//text()[{$this->eq($this->t("Airline Booking Reference(s)"))}]/ancestor::tr[position() = 1 and {$this->contains($this->t('Airlines'))}]");
        }

        foreach ($airlineReferences as $root) {
            if (
                preg_match("#^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\(.*?\)\s*:\s*(?<rl>[A-Z\d]{5,})$#",
                    trim($root->nodeValue), $m) // SK (Scandinavian Airlines): 7KLAKG
                || preg_match("#^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?:\s*:\s*|\/)(?<rl>[A-Z\d]{5,})\s*$#",
                    trim($root->nodeValue), $m) // LX: WNKLPV
                || preg_match("#^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\/(?<rl>[A-Z\d]{5,})$#", trim($root->nodeValue),
                    $m)
                || preg_match("#(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z]).+?" . $this->opt($this->t("Airline Booking Reference(s)")) . "\s*(?<rl>[A-Z\d]{5,})\s*$#",
                    trim($root->nodeValue), $m)
            ) {
                $rls[$m['airline']] = $m['rl'];
            }
        }

        if (empty($rls)) {
            $xpath = "//text()[" . $this->eq($this->t("Airline Booking Reference(s)")) . "]/ancestor::td[1]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $rl = $this->http->FindSingleNode("./following-sibling::td[normalize-space(.)!=''][1]", $root, false,
                    "#^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\/*([A-Z\d]{5,})\s*$#");

                if (!empty($rl) && preg_match("#\b(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+#",
                        $this->http->FindSingleNode("./preceding-sibling::td[normalize-space(.)!=''][1]", $root), $m)
                ) {
                    $rls[$m['airline']] = $rl;
//                    $tn = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1][contains(.,'E-ticket')]/td[normalize-space(.)!=''][last()]", $root, false, "#^([\d\-]{5,})#");
                    $tn = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1][" . $this->contains($this->t("E-ticket")) . "]/td[normalize-space(.)!=''][last()]",
                        $root, false, "#^([\d\-]{5,})#");

                    if (!empty($tn)) {
                        $this->logger->debug('Added ticketNumber. Way #1');
                        $tickets[$rl][] = $tn;
                    }

                    if (count($this->pax) > 1) {
                        for ($i = 1; $i < count($this->pax); $i++) {
                            $tn = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1][" . $this->contains($this->t("E-ticket")) . "]/following-sibling::tr[normalize-space()!=''][{$i}]/td[normalize-space(.)!=''][last()]",
                                $root, false, "#^([\d\-]{5,})#");

                            if (!empty($tn)) {
                                $this->logger->debug('Added ticketNumber. Way #2');
                                $tickets[$rl][] = $tn;
                            }
                        }
                    }
                }
            }
        } else {
            //determinate TicketNumbers on recLocs
            $nodes = [$this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket details'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]")];

            if (($cnt = count($this->pax) * count($rls)) > 1) {//not work with v2
//            if (($cnt = count($this->pax)) > 1) {
                $nodes = array_merge($nodes,
                    $this->http->FindNodes("//text()[{$this->eq($this->t('Ticket details'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]/following-sibling::tr[normalize-space(.)!=''][position()<{$cnt}]"));
            }

            foreach ($nodes as $node) {
                if (preg_match_all("#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+([\d\-]+)#", $node, $m, PREG_SET_ORDER)) {
                    foreach ($m as $v) {
                        if (isset($rls[$v[1]])) {
                            $this->logger->debug('Added ticketNumber. Way #3');
                            $tickets[$rls[$v[1]]][] = $v[2];
                        } else {
                            $this->logger->debug('Added ticketNumber. Way #4');
                            $tickets[CONFNO_UNKNOWN][] = $v[2];
                        }
                    }
                }
            }

            if (count($tickets) == 0) {
                $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Traveler'))}]/ancestor::tr[1][{$this->contains($this->t('Ticket Number'))}]/following-sibling::tr[count(descendant::td)>2][normalize-space()]");

                foreach ($nodes as $node) {
                    if ($this->http->XPath->query("descendant-or-self::*[{$this->xpath['firstTableBg']}]",
                            $node)->length === 0) {
                        break;
                    }
                    $mas = $this->http->FindNodes("./td[normalize-space(.)!=''][position()=last() or position()=last()-1]",
                        $node);

                    if (count($mas) == 2) {
                        $str = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'{$mas[1]}') and not(normalize-space(.)='{$mas[1]}')]");

                        if (count($str) > 0 && preg_match("#{$mas[1]}\s+([A-Z\d]{2})\s+\d+#", $str[0],
                                $v) && isset($rls[$v[1]])
                        ) {
                            $this->logger->debug('Added ticketNumber. Way #5');
                            $tickets[$rls[$v[1]]][] = $mas[0];
                        }
                    }
                }
            }
        }

        if (empty($tickets)) {
            $travellerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Traveler'))}]/ancestor::tr[1][{$this->contains($this->t('Ticket Number'))} and not({$this->contains($this->t('Ticket details'))})]/following-sibling::tr[normalize-space()]");

            foreach ($travellerRows as $tRow) {
                if ($this->http->XPath->query('td[3]', $tRow)->length === 0) {
                    break;
                }
                $ticketNumbers_temp = array_filter($this->http->FindNodes('td', $tRow,
                    '/^\d{3}[- ]*\d{10}[- \d]*$/'));

                if (count($ticketNumbers_temp)) {
                    $this->logger->debug('Added ticketNumber. Way #6');
                    $tickets[CONFNO_UNKNOWN][] = array_shift($ticketNumbers_temp);
                }
            }
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Traveler')]/ancestor::tr[1]/descendant::text()[normalize-space()][last()][normalize-space()='Issuing Airline']")->length > 0) {
            $airlines = array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Traveler')]/ancestor::tr[1]/descendant::text()[normalize-space()][last()][normalize-space()='Issuing Airline']/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space()][last()]"));
            $this->airline = $airlines[0];
        }

        //determinate AccountNumbers on recLocs
        $accs = [];
        $node = implode("; ",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Frequent Flyer number'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]",
                null, "#(.+?)\s*(?:{$this->opt($this->t('for'))}|$)#"));

        if (preg_match_all("#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*([A-Z\d\-]{5,})#", $node, $m, PREG_SET_ORDER)) {
            foreach ($m as $v) {
                if (isset($rls[$v[1]])) {
                    $accs[$rls[$v[1]]][] = $v[2];
                } else {
                    $accs[CONFNO_UNKNOWN][] = $v[2];
                }
            }
        }

        $accountNum = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Account Number')]", null, true,
            '/Account Number:\s*([A-Z\d]{5,15})/');

        $currency = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Invoice Total') and not(.//td)]/following-sibling::td[normalize-space(.)][1]");
        $total = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Invoice Total') and not(.//td)]/following-sibling::td[normalize-space(.)][2]");
        $tot = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Montant total') and not(.//td)]/following-sibling::td[normalize-space(.)][1]");

        if (preg_match('/([A-Z]{3})[ ]+([\d\.]+)/', $tot, $m)) {
            $currency = $m[1];
            $total = $m[2];
        }
        //determinate Segments on recLocs
        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[1][not(" . $this->contains($this->t("Contact")) . ")]";

        if ($this->lang == 'fr') {
            // "Depature", "Arrival" in French for flight the same "Check-in", "Check-out"
            $xpath .= "[preceding::tr[normalize-space()][1][not(" . $this->starts($this->t("Check-in")) . ")]]";
        }
        $this->logger->debug("[XPATH]: " . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        if (0 === $nodes->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");

            return true;
        }
        $airs = [];

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/td[{$this->xpath['flightCell']}][1]",
                $root, true, '/\s([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+(?:\s|$)/');

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][{$this->contains($this->t('Airline Booking Reference(s)'))}][1]/td[{$this->xpath['flightCell']}][1]",
                    $root, true, '/\s([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+(?:\s|$)/');
            }

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode("ancestor::tr[1]/descendant::tr[normalize-space()][1]",
                    $root, true, '/\s([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+(?:\s|$)/');
            }

            if (empty($airline)) {
                if ($this->http->XPath->query("./preceding-sibling::tr[normalize-space(.)!=''][1]/descendant::text()[contains(normalize-space(.),'Chauffeur driven car')]",
                        $root)->length > 0
                ) {
                    $subAirline = 'TRANSFER';
                } else {
                    $subAirline = 'TRAIN';
                }
                $airline = $subAirline . $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][3]",
                        $root, true, "#^\s*([A-Z\d]{5,})\s*$#");
                $airs[$airline][] = $root;
            } else {
                if (isset($rls[$airline])) {
                    $rl = $rls[$airline];
                } else {
                    // if recLoc from operator
                    if (!$operator = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][1]",
                        $root, true, "#\(" . $this->opt($this->t("Operated by")) . ".*?\s+([A-Z\d]{2})\s*\)#")) {
                        if (!$operator = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][{$this->contains($this->t('Airline Booking Reference(s)'))}][1]",
                            $root, true,
                            "#\(" . $this->opt($this->t("Operated by")) . ".*?\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\)#")) {
                            $operator = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<14]//text()[" . $this->eq($this->t("Operated by")) . "])[1]/ancestor::td[1]/following-sibling::td[2]",
                                $root, true, "#.*?,\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+#");
                        }
                    }

                    if (isset($rls[$operator])) {
                        $rl = $rls[$operator];
                    } else {
                        $rl = CONFNO_UNKNOWN;
                    }
                }
                $airs[$rl][] = $root;
            }
        }

        //main parse
        foreach ($airs as $rl => $roots) {
//            $this->logger->debug('$rl = '.print_r( $rl,true));
            if (substr($rl, 0, 5) == 'TRAIN') {
                $r = $email->add()->train();

                $trainConfirmation = substr($rl, 5);
                $r->general()->confirmation($trainConfirmation);

                // E-ticket number: FPX0525U0001 for Mr Anders Ljungkvist
                $trainTickets = $this->http->FindNodes("//tr[{$this->starts($this->t('Ticket details'))}]/following-sibling::tr[{$this->contains($this->t('E-ticket'))}]",
                    null, "/[\s:]+({$trainConfirmation}\d+)(?:\s+{$this->opt($this->t('for'))}|$)/");
                $trainTickets = array_filter($trainTickets);

                if (count($trainTickets) && !empty($tickets[$rl])) {
                    $this->logger->debug('Added ticketNumber. Way #7 (TRAIN)');
                    $tickets[$rl] = array_merge($tickets[$rl], $trainTickets);
                } elseif (count($trainTickets)) {
                    $this->logger->debug('Added ticketNumber. Way #8 (TRAIN)');
                    $tickets[$rl] = $trainTickets;
                }

                if (!$this->parseTrain($r, $roots)) {
                    return false;
                }
            } elseif (substr($rl, 0, 8) == 'TRANSFER') {
                $this->logger->debug('skip transfer. not enough data');

                continue;
            } else {
                $r = $email->add()->flight();

                if ($rl === CONFNO_UNKNOWN) {
                    $r->general()->noConfirmation();
                } else {
                    $r->general()->confirmation($rl);
                }

                if (!$this->parseAir($r, $roots)) {
                    return false;
                }
            }

            if (count($airs) === 1) {
                if ($total !== null) {
                    $r->price()
                        ->total($total)
                        ->currency($currency);
                } else {
                    $r->price()
                        ->cost($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'BASE') and contains(.,'......')]",
                            null, false, "#(\d+[\d\.]*)$#"), false, true);

                    if (count($this->pax) < 2) {
                        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'PER ADULT') and contains(.,'......')]",
                            null, false, "#(\d+[\d\.]*)$#");

                        if (empty($total)) {
                            $total = str_replace(",", ".",
                                $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'TOTAL....')]",
                                    null, true, "/^TOTAL[\.]+[A-Z]{3}\s(\d+[\d,.]+)$/"));
                            $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'TOTAL....')]",
                                null, true, "/^TOTAL[\.]+([A-Z]{3})\s\d+[\d,.]+$/");
                        }

                        if (!empty($total)) {
                            $r->price()
                                ->total($total, false, true);
                        }

                        if (!empty($currency)) {
                            $r->price()
                                ->currency($currency, false, true);
                        }
                    }
                    $tax = str_replace(",", ".",
                        $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'TAX') and contains(.,'......')]",
                            null, false, "#(\d+[\d\.,]*)$#"));

                    if (!empty($tax)) {
                        $r->price()->tax($tax);
                    }
                }
            }

            $r->general()
                ->travellers(preg_replace("/^(?:Mrs|Mr|Ms)\s+/", "", $this->pax));

            if (!empty($resDate)) {
                $r->general()
                    ->date($resDate);
            }

            if (isset($tickets[$rl])) {
                $r->setTicketNumbers(array_values(array_unique($tickets[$rl])), false);
            }

            if (isset($accs[$rl])) {
                $r->program()
                    ->accounts(array_values(array_unique($accs[$rl])), false);
            }

            if (!empty($accountNum)) {
                $r->program()
                    ->account($accountNum, false);
            }
        }

        if (count($tickets) == 1 && count($airs) == 1 && $r->getType() == 'flight' && count($r->getTicketNumbers()) == 0) {
            $r->issued()
                ->tickets(array_shift($tickets), false);
        }

        return true;
    }

    private function parseAir(Flight $r, $roots)
    {
        foreach ($roots as $key => $root) {
            $s = $r->addSegment();

            if (!empty($this->airline)) {
                $r->setIssuingAirlineName($this->airline);
            }

            if ($d = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][2]/td[normalize-space(.)!=''][1]",
                $root)))) {
                $this->date = $d;
            }

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][{$this->contains($this->t('Airline Booking Reference(s)'))}][1]/td[{$this->xpath['flightCell']}][1]",
                $root, true, '/.*\s\w+\s+\d+(?:\s.*|$)/');

            if (!$flight) {
                $flight = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/td[{$this->xpath['flightCell']}][1]",
                    $root, true, '/.*\s\w+\s+\d+(?:\s.*|$)/');
            }

            if (!$flight) {
                $flight = $this->http->FindSingleNode("ancestor::tr[1]/descendant::tr[normalize-space()][1]",
                    $root, true, '/.*\s\w+\s+\d+(?:\s.*|$)/');
            }

            if (preg_match('/\s(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\s|$)/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            if (preg_match("/\(Operated By\s*(?<operator>.+)\,\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\)/", $flight, $m)) {
                $s->airline()
                    ->operator($m['operator']);
            }

            if (preg_match("#\d+:\d+#", $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]", $root))) {
                $this->parseDepArrFields_1($s, $root);
            } else {
                $this->parseDepArrFields_2($s, $root);
            }

            $xpathFragmentCell = '/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]';

            $seats = [];
            // TODO: separate segments when stops > 0 FE:  it-4599790.eml
            $nextRows = $this->http->XPath->query('./following-sibling::tr[normalize-space(.)!=""][position()<20]',
                $root);

            foreach ($nextRows as $row) {
                $this->logger->error(var_export($this->http->FindNodes("./ancestor::td[1]/descendant::text()[{$this->contains($this->t("confirmed for"))}]",
                    $row), true));

                if (!empty($roots[$key + 1]) && $roots[$key + 1] === $row) {
                    break;
                }

                if ($this->http->FindSingleNode("./td[1][normalize-space()]", $row)) {
                    break;
                }

                if ($duration = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Duration"))}]" . $xpathFragmentCell,
                    $row)) {
                    if (preg_match('/(\d+:\d+)[\sh]*(?:\(|$)/', $duration, $m)) {
                        $s->extra()->duration($m[1]);
                    }

                    if (preg_match('/\(.*(?:Non[-\s]+stop|Sans\s+escale|Sin\s+paradas|Sem\s*escalas)/iu', $duration)) {
                        $s->extra()->stops(0);
                    } elseif (preg_match('/\(.*(\d{1,3})\s+(?:Stop|Escala)/iu', $duration, $m)) {
                        $s->extra()->stops($m[1]);
                    }
                } elseif ($miles = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Distance"))}]" . $xpathFragmentCell,
                    $row)) {
                    $s->extra()->miles($miles);
                } elseif ($class = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Class"))}]" . $xpathFragmentCell,
                    $row)) {
                    if (preg_match("#(.*?)\s*(?:\([A-Z]{1,2}\)|$)#", $class, $m)) {
                        $s->extra()->cabin($m[1]);
                    }

                    if (preg_match("#\(([A-Z]{1,2})\)#", $class, $m)) {
                        $s->extra()->bookingCode($m[1]);
                    }
                } elseif ($seat = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t("confirmed for"))}]",
                    $row, true, '/^\d{1,3}[A-Z]\b/')) {
                    $seats[] = $seat;
                } elseif ($seat = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("confirmed for"))}]/preceding::text()[normalize-space()][1]",
                    $row, true, '/^\d{1,3}[A-Z]$/')) {
                    $seats[] = $seat;
                } elseif ($aircraft = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Equipment"))} or normalize-space(.)='Aircraft']" . $xpathFragmentCell,
                    $row)) {
                    $s->extra()->aircraft($aircraft);
                } elseif ($meal = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Meal"))}]" . $xpathFragmentCell,
                    $row)) {
                    $s->extra()->meal($meal);
                } elseif ($operator = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t("Operated by"))}]" . $xpathFragmentCell,
                    $row, true, "#(.*?),#")) {
                    $s->airline()->operator($operator);
                } elseif ($seat = $this->http->FindSingleNode("./descendant::text()[" . $this->eq($this->t("Seat")) . "]" . $xpathFragmentCell,
                    $row, true, "#^\s*(\d{1,3}[A-Z])\b#")) {
                    $seats[] = $seat;
                }
            }

            if (empty($s->getDuration())) {
                $duration = $this->http->FindSingleNode("ancestor::tr[1]/descendant::text()[starts-with(normalize-space(), 'Duration')][1]{$xpathFragmentCell}", $root, true, "/(\d+:\d+)[\sh]*/");

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }
            }

            if (empty($s->getAircraft())) {
                $aircraft = $this->http->FindSingleNode("ancestor::tr[1]/descendant::text()[starts-with(normalize-space(), 'Equipment')][1]{$xpathFragmentCell}", $root);

                if (!empty($aircraft)) {
                    $s->extra()
                        ->aircraft($aircraft);
                }
            }

            if (empty($s->getCabin()) && empty($s->getBookingCode())) {
                $cabinInfo = $this->http->FindSingleNode("ancestor::tr[1]/descendant::text()[starts-with(normalize-space(), 'Class')][1]{$xpathFragmentCell}", $root);

                if (preg_match("/(?<cabin>\D+)\s*\((?<bookingCode>[A-Z]{1,2})\)/", $cabinInfo, $m)) {
                    $s->extra()
                        ->cabin($m['cabin'])
                        ->bookingCode($m['bookingCode']);
                }
            }

            if (empty($s->getMeals())) {
                $meal = $this->http->FindSingleNode("ancestor::tr[1]/descendant::text()[starts-with(normalize-space(), 'Flight Meal')][1]{$xpathFragmentCell}", $root);

                if (!empty($meal)) {
                    $s->extra()
                        ->meal($meal);
                }
            }

            if (count($seats) === 0) {
                $seats = $this->http->FindNodes("./ancestor::td[1]/descendant::text()[{$this->contains($this->t("confirmed for"))}]", $row, '/^\d{1,3}[A-Z]/');
            }

            // seats
            if (count($seats)) {
                $s->extra()->seats($seats);
            }

            // operator
            if (empty($s->getOperatedBy())) {
                $operator = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)][{$this->contains($this->t('Airline Booking Reference(s)'))}][1]",
                    $root, true, "#\({$this->opt($this->t("Operated by"))} (.*?)\)#");

                if (!$operator) {
                    $operator = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)][1]", $root,
                        true, "#\({$this->opt($this->t("Operated by"))} (.*?)\)#");
                }

                if (preg_match("#(?:\s+-\s+|, )([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})\s*$#", $operator, $m)) {
                    $s->airline()
                        ->carrierName($m[1])
                        ->carrierNumber($m[2]);
                } elseif (!empty($operator)) {
                    $s->airline()->operator($operator);
                }
            }
        }

        return true;
    }

    private function parseTrain(Train $r, $roots)
    {
        foreach ($roots as $root) {
            $s = $r->addSegment();

            if ($d = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][2]/td[normalize-space(.)!=''][1]",
                $root)))) {
                $this->date = $d;
            }

            if (!$number = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][{$this->contains($this->t('Airline Booking Reference(s)'))}][1]/td[normalize-space(.)!=''][1]",
                $root, true, "#\s\w+\s+(\d+)(?:\s|$)#")) {
                $number = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][1]",
                    $root, true, "#\s\w+\s+(\d+)(?:\s|$)#");
            }
            $s->extra()->number($number);

            if (preg_match("#\d+:\d+#", $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]", $root))) {
                $this->parseDepArrFields_1($s, $root);
            } else {
                $this->parseDepArrFields_2($s, $root);
            }

            if (!$serviceName = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][{$this->contains($this->t('Airline Booking Reference(s)'))}][1]/td[normalize-space(.)!=''][1]",
                $root, true, "#\s(\w+)\s+\d+(?:\s|$)#")) {
                $serviceName = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][1]",
                    $root, true, "#\s(\w+)\s+\d+(?:\s|$)#");
            }

            if (!$serviceName) {
                $s->extra()->service($serviceName);
            }

            $class = $this->http->FindSingleNode("(./following-sibling::tr[normalize-space()][position()<14]//text()[{$this->eq($this->t("Class"))}])[1]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                $root);

            if (preg_match('/^(.+?)\s*\(\s*([A-Z]{1,2})\s*\)$/', $class, $m)) {
                // Econom (A)
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            } elseif (preg_match('/^\(?\s*([A-Z]{1,2})\s*\)?$/', $class, $m)) {
                // A
                $s->extra()->bookingCode($m[1]);
            } elseif ($class) {
                $s->extra()->cabin($class);
            }

            $s->extra()
                ->type($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<14]//text()[" . $this->eq($this->t("Equipment")) . "])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                    $root))
                ->miles($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<14]//text()[" . $this->eq($this->t("Distance")) . "])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                    $root), false, true)
                ->duration($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<14]//text()[" . $this->eq($this->t("Duration")) . "])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                    $root, true, "#(\d+:\d+)[\sh]*(?:\(|$)#"))
                ->meal($this->http->FindSingleNode("(./following-sibling::tr[normalize-space(.)!=''][position()<14]/descendant::text()[" . $this->eq($this->t("Meal")) . "])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                    $root), false, true)
                //Numéro de voiture 003 Siège 051 054
                ->car($this->http->FindSingleNode("(./following-sibling::tr[position()<14]//text()[" . $this->eq($this->t("Seat")) . "])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                    $root, true, "#{$this->opt($this->t('Coach'))}\s+(\d+)#"))
                ->seats(array_values(array_filter(preg_split("/[\s,]+/",
                    $this->http->FindSingleNode("(./following-sibling::tr[position()<14]//text()[" . $this->eq($this->t("Seat")) . "])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                        $root, true,
                        "#{$this->opt($this->t('Coach'))}\s+\d+\s+{$this->opt($this->t('Seat'))}\S*\s*([\dA-Z, ]+)#")))));

            // Stops
            $stops = $this->http->FindSingleNode("(./following-sibling::tr[position()<14]//text()[" . $this->eq($this->t("Duration")) . "])[1]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]",
                $root, true, "#\((.*?)\)#");

            if (preg_match('/(?:Non[-\s]+stop|Sans\s+escale|Sin\s+paradas|Sem\s*escalas)/i', $stops)) {
                $s->extra()->stops(0);
            } elseif (preg_match('/(\d{1,3})\s+(?:Stop|Escala)/i', $stops, $m)) {
                $s->extra()->stops($m[1]);
            }
        }

        return true;
    }

    private function parseDepArrFields_1($s, &$root)
    {
        $this->logger->debug('parse dep/arr 1');
        $classArr = explode('\\', get_class($s));
        $class = end($classArr);

        switch ($class) {
            case 'FlightSegment':
                /** @var FlightSegment $segment */
                $segment = $s;
                $isFlight = true;

                break;

            case 'TrainSegment':
                /** @var TrainSegment $segment */
                $segment = $s;
                $isFlight = false;

                break;

            default:
                return false;
        }

        $depCode = $this->http->FindSingleNode("./td[normalize-space(.)!=''][3]//a/@href", $root, true,
            "#.+(?:/|_|triptools%2[fF])([A-Z]{3})#");
        $depName = $this->http->FindSingleNode("./td[normalize-space(.)!=''][3]", $root, true,
            "#(.*?)\s*(?:\(\+\)|$)#");
        $departureTerminal = $this->http->FindSingleNode("./td[normalize-space(.)!=''][4]", $root, true,
            "#Terminal[\s:]+(\w+)#");
        $depDate = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)!=''][2]",
            $root)));

        $arrCode = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][{$this->contains($this->t('Arrival'))}][1]/td[normalize-space(.)!=''][not(contains(., 'Check-in'))][3]//a/@href",
            $root, true, "#.+(?:/|_|triptools%2[fF])([A-Z]{3})#");
        $arrName = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][{$this->contains($this->t('Arrival'))}][1]/td[normalize-space(.)!=''][not(contains(., 'Check-in'))][3]",
            $root, true, "#(.*?)\s*(?:\(\+\)|$)#");
        $arrivalTerminal = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][{$this->contains($this->t('Arrival'))}][1]/td[normalize-space(.)!=''][not(contains(., 'Check-in'))][4]",
            $root, true, "#Terminal[\s:]+(\w+)#");
        $arrDate = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)!=''][{$this->contains($this->t('Arrival'))}][1]/td[normalize-space(.)!=''][not(contains(., 'Check-in'))][2]",
            $root)));

        $segment->departure()
            ->name($depName)
            ->date($depDate);
        $segment->arrival()
            ->name($arrName)
            ->date($arrDate);

        if ($isFlight) {
            if (!empty($depCode)) {
                $segment->departure()->code($depCode);
            } else {
                $segment->departure()->noCode();
            }

            if (!empty($arrCode)) {
                $segment->arrival()->code($arrCode);
            } else {
                $segment->arrival()->noCode();
            }

            if ($departureTerminal !== null) {
                $segment->departure()->terminal($departureTerminal);
            }

            if ($arrivalTerminal !== null) {
                $segment->arrival()->terminal($arrivalTerminal);
            }
        }

        return true;
    }

    private function parseDepArrFields_2($s, &$root)
    {
        $this->logger->debug('parse dep/arr 2');
        $classArr = explode('\\', get_class($s));
        $class = end($classArr);

        switch ($class) {
            case 'FlightSegment':
                /** @var FlightSegment $segment */
                $segment = $s;
                $isFlight = true;

                break;

            case 'TrainSegment':
                /** @var TrainSegment $segment */
                $segment = $s;
                $isFlight = false;

                break;

            default:
                return false;
        }

        $depCode = $this->http->FindSingleNode("./td[normalize-space(.)!=''][4]//a/@href", $root, true,
            "#/([A-Z]{3})$#");
        $depName = $this->http->FindSingleNode("./td[normalize-space(.)!=''][4]", $root, true,
            "#(.*?)\s*(?:\(\+\)|$)#");
        $departureTerminal = $this->http->FindSingleNode("./td[normalize-space(.)!=''][5]", $root, true,
            "#Terminal[\s:]+(\w+)#");
        $depDate = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)!=''][2]",
                $root) . ' ' . $this->http->FindSingleNode("./td[normalize-space(.)!=''][3]", $root)));

        $arrCode = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)!=''][4]//a/@href",
            $root, true, "#/([A-Z]{3})$#");
        $arrName = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)!=''][4]",
            $root, true, "#(.*?)\s*(?:\(\+\)|$)#");
        $arrivalTerminal = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)!=''][5]",
            $root, true, "#Terminal[\s:]+(\w+)#");
        $arrDate = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)!=''][2]",
                $root) . ' ' . $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)!=''][3]",
                $root)));

        $segment->departure()
            ->name($depName)
            ->date($depDate);
        $segment->arrival()
            ->name($arrName)
            ->date($arrDate);

        if ($isFlight) {
            if ($aircraft = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][contains(normalize-space(.), 'Equipement')][1]/td[normalize-space(.)][2]",
                $root)) {
                $segment->extra()
                    ->aircraft($aircraft);
            }

            if (!empty($depCode)) {
                $segment->departure()->code($depCode);
            } else {
                $segment->departure()->noCode();
            }

            if (!empty($arrCode)) {
                $segment->arrival()->code($arrCode);
            } else {
                $segment->arrival()->noCode();
            }

            if ($departureTerminal !== null) {
                $segment->departure()->terminal($departureTerminal);
            }

            if ($arrivalTerminal !== null) {
                $segment->arrival()->terminal($arrivalTerminal);
            }
        }

        return true;
    }

    private function parseHotels(Email $email): bool
    {
        $xpath = "//text()[" . $this->eq($this->t("Check-in")) . "]/ancestor::tr[1][./following::text()[" . $this->contains($this->t("Check-out")) . "] and " . $this->contains($this->t("Location")) . "]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH-Hotel]: " . $xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->hotel();

            // cancellation
            $cancellation = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Cancellation policy")) . "]/ancestor::td[1]/following-sibling::td[2]",
                $root);

            if (empty($cancellation)) {
                $cancellation = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->starts($this->t("Cancellation policy")) . "]",
                    $root, false, "#{$this->opt($this->t('Cancellation policy'))}\s*(.+)#");
            }
            $r->general()->cancellation($cancellation, false, true);

            // hotelName
            // address
            // phone
            // fax
            $r->hotel()
                ->name($this->http->FindSingleNode("./preceding::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][1]",
                    $root))
                ->address($this->http->FindSingleNode("./td[normalize-space(.)!=''][last()]", $root))
                ->phone($this->http->FindSingleNode("(./following-sibling::tr[position()<16]//text()[{$this->starts($this->t("Tel "))}])[1]",
                    $root, true, "#" . $this->t("Tel ") . "(.+)#"), false, true)
                ->fax($this->http->FindSingleNode("(./following-sibling::tr[position()<16]//text()[{$this->starts($this->t("Fax "))}])[1]",
                    $root, true, "#" . $this->t("Fax ") . "(.+)#"), false, true);

            // checkInDate
            $dateCheckIn = $this->http->FindSingleNode('./descendant::text()[' . $this->eq($this->t("Check-in")) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]',
                $root);

            if ($dateCheckIn) {
                $dateCheckInNormal = $this->normalizeDate($dateCheckIn);

                if ($dateCheckInNormal) {
                    $checkInDate = strtotime($dateCheckInNormal);
                }
            }
            $timeCheckIn = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Other information")) . "]/ancestor::td[1]/following-sibling::td[2]",
                $root, true, "#" . $this->opt($this->t("In-")) . "[ ]*(\d+:\d+([ ]*[AaPp][Mm]\b)?)#");

            if (empty($timeCheckIn)) {
                $timeCheckIn = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Miscellaneous")) . "]/ancestor::td[1]/following-sibling::td[2]",
                    $root, true, '/chkin\s+(' . $this->patterns['time'] . ')/i');
            }

            if ($timeCheckIn && !empty($checkInDate)) {
                $checkInDate = strtotime($timeCheckIn, $checkInDate);
            }

            // checkOutDate
            $dateCheckOut = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/descendant::text()[' . $this->eq($this->t("Check-out")) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]',
                $root);

            if ($dateCheckOut) {
                $dateCheckOutNormal = $this->normalizeDate($dateCheckOut);

                if ($dateCheckOutNormal) {
                    $checkOutDate = strtotime($dateCheckOutNormal);
                }
            }
            $timeCheckOut = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Other information")) . "]/ancestor::td[1]/following-sibling::td[2]",
                $root, true, "#" . $this->opt($this->t("out-")) . "[ ]*(\d+:\d+([ ]*[AaPp][Mm]\b)?)#");

            if (empty($timeCheckOut)) {
                $timeCheckOut = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Miscellaneous")) . "]/ancestor::td[1]/following-sibling::td[2]",
                    $root, true, '/chkout\s+(' . $this->patterns['time'] . ')/i');
            }

            if ($timeCheckOut && !empty($checkOutDate)) {
                $checkOutDate = strtotime($timeCheckOut, $checkOutDate);
            }

            if (isset($checkInDate, $checkOutDate)) {
                $r->booked()
                    ->checkIn($checkInDate)
                    ->checkOut($checkOutDate);
            }

            // guestCount
            $r->booked()
                ->guests($this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Occupancy")) . "]/ancestor::td[1]/following-sibling::td[2]",
                    $root, false, "/^(\d+)\b/"), false, true);

            // travellers
            $guestName = $this->http->FindSingleNode("(./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Name")) . "]/ancestor::td[1]/following-sibling::td[2])[1]",
                $root);

            if ($guestName) {
                $r->general()->traveller($guestName);
            } else {
                $r->general()->travellers($this->pax);
            }

            $room = $r->addRoom();

            // r.rate
            $rate = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[{$this->eq($this->t("Rate"))}]/ancestor::td[1]/following-sibling::td[2]",
                $root);

            if ($rate) {
                $room->setRate($rate);
            }

            // r.type
            $roomType = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Room type")) . "]/ancestor::td[1]/following-sibling::td[2]/descendant::text()[normalize-space(.)][1]",
                $root);

            if ($roomType) {
                $room->setType($roomType);
            }

            // r.description
            $descriptionTexts = $this->http->FindNodes("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Room type")) . "]/ancestor::td[1]/following-sibling::td[2]/descendant::text()[normalize-space(.)][position()>1]",
                $root);

            if (count($descriptionTexts)) {
                $room->setDescription(implode(', ', $descriptionTexts));
            }

            // accountNumbers
            $acc = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Membership ID")) . "]/ancestor::td[1]/following-sibling::td[2]",
                $root, true, "#^\s*[A-Z\d]{5,}\s*$#");

            if (!empty($acc)) {
                $r->program()->account($acc, false);
            }

            // p.currencyCode
            // p.total
            $estimatedTotal = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[{$this->eq($this->t("Estimated total"))}]/ancestor::td[1]/following-sibling::td[2]",
                $root);

            if (preg_match("#^(?<currency>[A-Z]{3})\s+(?<amount>\d[,.\'\d]*)#", $estimatedTotal, $m)) {
                $r->price()
                    ->currency($m['currency'])
                    ->total($m['amount']);
            }
            $taxes = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[{$this->eq($this->t("Taxes"))}]/ancestor::td[1]/following-sibling::td[2]",
                $root);

            if (preg_match("#^(?<currency>[A-Z]{3})\s+(?<amount>\d[,.\'\d]*)#", $taxes, $m)) {
                $r->price()
                    ->tax($m['amount']);
            }

            $xpathDetails = "./following-sibling::tr[position()<16]/descendant::text()[{$this->eq($this->t('Details'))}]/ancestor::tr[1]/preceding-sibling::tr[1]/following-sibling::tr[normalize-space()!=''][position()<=2]";
            $details = $this->http->XPath->query($xpathDetails, $root);

            if ($details->length > 0) {
                // FE: it-44632792.eml, it-45199713.eml
                $hotelName = $this->http->FindSingleNode($xpathDetails . "/descendant::text()[{$this->eq($this->t('Details'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1][({$this->starts('Hotel')}) or ({$this->ends('Hotel')})]",
                    $root);

                if (!empty($hotelName)) {
                    $roomType = $this->http->FindSingleNode($xpathDetails . "/descendant::text()[{$this->eq($this->t('Details'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1][({$this->starts('Hotel')}) or ({$this->ends('Hotel')})]/following::text()[normalize-space()!=''][1]",
                        $root);

                    if (empty($room->getType()) && !empty($roomType)) {
                        $room->setType($roomType);
                    }
                } elseif (!empty($hotelName = $this->http->FindSingleNode($xpathDetails . "/descendant::text()[{$this->starts($this->t('Hotel Name:'))}][1]",
                    $root, false, "#{$this->opt($this->t('Hotel Name:'))}\s*(.+)#"))
                ) {
                    $detailText = implode("\n",
                        $this->http->FindNodes($xpathDetails . "/descendant::text()[{$this->starts($this->t('Hotel Name:'))}][1]/ancestor::td[1]/descendant::text()[normalize-space()!='']",
                            $root));

                    if (preg_match("#{$this->opt($this->t('Hotel Address:'))}\s*(.+?)\s+{$this->opt($this->t('Hotel Tel:'))}\s*([\d\-\(\)\+ ]+)\n#s",
                        $detailText, $m)) {
                        $r->hotel()
                            ->address(preg_replace("#\s+#", ' ', $m[1]))
                            ->phone(trim($m[2]));
                    }
                    $roomType = $this->re("#{$this->opt($this->t('Room type'))}\s*(.+)#", $detailText);

                    if (empty($room->getType()) && !empty($roomType)) {
                        $room->setType($roomType);
                    }
                    $rate = $this->re("#{$this->opt($this->t('Rate'))}\s*(.+)#", $detailText);

                    if (empty($room->getRate()) && !empty($rate)) {
                        $room->setRate($rate);
                    }
                } else {
                    $detailText = $this->http->FindSingleNode($xpathDetails . "/descendant::text()[{$this->eq($this->t('Details'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1][count(descendant::text()[normalize-space()!=''])=1]/descendant::text()[not(({$this->starts('Hotel')}) or ({$this->ends('Hotel')}))][string-length()>5]",
                        $root);

                    if (preg_match("#^[\w\-]+?\/Na\-(?<hotel>.+?)\/Cf\-(?<confNo>[\w\-]+)\/Rt\-(?<roomType>.+?)\/Cu\-(?<rateCur>[A-z]{3})\/R1\-(?<rateSum>[\d\.]+)#",
                        $detailText, $m)) {
                        $hotelName = $m['hotel'];
                        $confNo = $m['confNo'];
                        $roomType = $m['roomType'];
                        $rate = strtoupper($m['rateCur'] . ' ' . $m['rateSum']);

                        if (empty($room->getType()) && !empty($roomType)) {
                            $room->setType($roomType);
                        }

                        if (empty($room->getRate()) && !empty($rate)) {
                            $room->setRate($rate);
                        }
                    } elseif (preg_match("#^[\w\-]+?\/Hn\-(?<hotel>.+?)\/Hc\-(?<city>.+)\/Ad\-(?<address>.+)\/Ph\-(?<phone>[+\d\-]+)\/Rt\-(?<roomType>.+)\/Cf\-(?<confirmation>\d+)\/Rq\-(?<total>[A-z\d\.]+)#",
                    $detailText, $m)) {
                        $hotelName = $m['hotel'];
                        $confNo = $m['confNo'];
                        $roomType = $m['roomType'];
                        $rate = strtoupper($m['rateSum']);
                        $r->setAddress($m['city'] . ', ' . $m['address']);

                        if (!empty($roomType)) {
                            $room->setType($roomType);
                        }

                        if (empty($room->getRate()) && !empty($rate)) {
                            $room->setRate($rate);
                        }
                    }
                }

                if (!isset($confNo) && !empty($cf = $this->http->FindSingleNode($xpathDetails . "/descendant::text()[{$this->starts('Cf-')}]",
                        $root, false, "#{$this->opt('Cf-')}([\w\-]{5,})$#"))
                ) {
                    $confNo = $cf;
                }

                if ($r->getHotelName() === 'Hotel') {
                    $r->hotel()->name($hotelName);
                }
            }

            // confirmation numbers
            $confirmationNumber = $this->http->FindSingleNode("(./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Confirmation number")) . "]/ancestor::td[1]/following-sibling::td[2])[1]",
                $root);

            if (empty($confirmationNumber)) {
                $confirmationNumber = $this->http->FindSingleNode("./preceding::tr[normalize-space()!=''][1][./descendant::text()[{$this->starts($this->t("Confirmation number"))}]]",
                    $root, false, "#{$this->opt($this->t("Confirmation number"))}\s*([\w\-]+)$#");
            }

            if (empty($confirmationNumber)) {
                $confirmationNumber = $this->http->FindSingleNode("(./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Confirmation number")) . "]/ancestor::td[1]/following-sibling::td[2])[1]",
                    $root);
            }

            if ($confirmationNumber) {
                $r->general()->confirmation($confirmationNumber);
            } elseif (isset($confNo)) {
                $r->general()->confirmation($confNo);
            } elseif (
                empty($confirmationNumber)
                && $this->http->XPath->query("./following-sibling::tr[position()<16]//text()[{$this->eq($this->t("Confirmation number"))}] | ./preceding::tr[normalize-space()][1]//text()[{$this->eq($this->t("Confirmation number"))}]",
                    $root)->length === 0
            ) {
                $r->general()->noConfirmation();
            }

            // status
            $r->general()->status($this->http->FindSingleNode("(./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Booking status")) . "]/ancestor::td[1]/following-sibling::td[2])[1]",
                $root));

            if (!$rate && !$roomType && count($descriptionTexts) === 0) {
                $r->removeRoom($room);
            }

            $this->detectDeadLine($r);
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/No cancellation charge applies prior to (?<time>\d+:\d+)\s*\(.+\) on the\s*day of arrival./i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('0 days', $m['time']);
        } elseif (preg_match("/^Cxl (?<days>\d+) days? prior to arrival$/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['days'] . ' days', '00:00');
        } elseif (preg_match("/^Cancel on (?<date>.+) by (?<time>\d+:\d+) lt\s*.\s*reservations must be cancelled \d+ hours prior\s*to arrival to\s*avoid a penalty/i",
                $cancellationText, $m)
            || preg_match("/^Cxl (?:by )?(?<time>\d{4}) (?:htl|hotel) time on (?<date>.+)\-fee 1 night\-/i",
                $cancellationText, $m)
            || preg_match("/^Cxl by (?<date>.+) (?<time>\d+(?::\d+)?\s*(?:[ap]m)?)$/i", $cancellationText, $m)
            || preg_match("/^Cancel on (?<date>.+) by (?<time>\d+:\d+) lt to avoid 1 night\(s\) charge/i",
                $cancellationText, $m)
            || preg_match("/^Cancel on (?<date>.+) by (?<time>\d+:\d+) lt to avoid a charge of/i", $cancellationText,
                $m)
        ) {
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])));
        }

        $h->booked()->parseNonRefundable("#^Non Refundable#");
    }

    private function parseRentals(Email $email): bool
    {
        $xpath = "//text()[" . $this->eq($this->t("Pick up")) . "]/ancestor::tr[1][./following::text()[" . $this->contains($this->t("Drop off")) . "]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $number = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Confirmation number")) . "]/ancestor::td[1]/following-sibling::td[2]",
                $root, true, "#^\s*[A-Z\d]{5,}\b#");

            if (empty($number)) {
                $number = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()][1]//text()[" . $this->eq($this->t("Confirmation number")) . "]/ancestor::td[1]/following-sibling::td[2]",
                    $root, true, "#^\s*[A-Z\d]{5,}\b#");
            }

            if (empty($number)
                && ($prev = $this->http->XPath->query("./preceding-sibling::tr[string-length(normalize-space(.))>3][2][{$this->starts($this->t('Quote'))}]",
                    $root))->length === 1
            ) {
                $r->general()
                    ->noConfirmation();

                if (empty($this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Estimated total")) . "]",
                    $root))
                ) {
                    $r->price()
                        ->total(trim($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][last()-1][starts-with(.,'Total Price')]/following::text()[normalize-space()!=''][1]",
                            $prev->item(0), true, "#^[A-Z]{3}\s+(\d[\d,.]+)#"), ',. '))
                        ->currency($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][last()-1][starts-with(.,'Total Price')]/following::text()[normalize-space()!=''][1]",
                            $prev->item(0), true, "#^([A-Z]{3})\s+[\d,.]+#"));
                }
            } elseif (!empty($number)) {
                $r->general()->confirmation($number);
            }

            $r->extra()
                ->company($keyword = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][1]",
                    $root, true, "#" . $this->opt($this->t("Car Rental")) . "\s+(.+)#"));

            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            }
            $renterName = $this->http->FindSingleNode("following-sibling::tr[position()<16]//text()[{$this->eq($this->t("Name"))}]/ancestor::td[1]/following-sibling::td[2]",
                $root);
            $r->general()->traveller($renterName);

            $status = $this->http->FindSingleNode("following-sibling::tr[position()<16]//text()[{$this->eq($this->t("Booking status"))}]/ancestor::td[1]/following-sibling::td[2]",
                $root);

            if (!empty($status)) {
                $r->general()->status($status);
            }

            //PickUp

            $date = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Car')][1]/preceding::text()[normalize-space()][1]", $root);

            if (preg_match("/^\w+\s*\d+\s*\w+\s*\d{4}$/", $date)) {
                $this->date = (strtotime($date));
            }

            $datePickup = $this->http->FindSingleNode('./descendant::text()[' . $this->eq($this->t("Pick up")) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]',
                $root);

            if ($datePickup) {
                $datePickupNormal = $this->normalizeDate($datePickup);

                if ($datePickupNormal) {
                    $r->pickup()
                        ->date(strtotime($datePickupNormal));
                }
            }
            $r->pickup()
                ->location($this->http->FindSingleNode('./descendant::text()[' . $this->eq($this->t("Pick up")) . ']/ancestor::tr[1]//text()[' . $this->eq($this->t("Location")) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]',
                    $root))
                ->phone($this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][1]/descendant::text()[' . $this->eq($this->t("Contact")) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]',
                    $root, true, "#" . $this->opt($this->t("Tel ")) . "([\d\-\+\(\) ]{5,})#"));

            // Dropoff
            $dateDropoff = $this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][position()<3]/descendant::text()[' . $this->eq($this->t("Drop off")) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]',
                $root);

            if ($dateDropoff) {
                $dateDropoffNormal = $this->normalizeDate($dateDropoff);

                if ($dateDropoffNormal && (strtotime($dateDropoffNormal) - $r->getPickUpDateTime()) < 0) {
                    $r->dropoff()
                        ->date(strtotime('+1 year', strtotime($dateDropoffNormal)));
                } elseif ($dateDropoffNormal) {
                    $r->dropoff()
                        ->date(strtotime($dateDropoffNormal));
                }
            }
            $r->dropoff()
                ->location($this->http->FindSingleNode('./following-sibling::tr[normalize-space(.)][position()<3]/descendant::text()[' . $this->eq($this->t("Drop off")) . ']/ancestor::tr[1]//text()[' . $this->eq($this->t("Location")) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]',
                    $root))
                ->phone($this->http->FindSingleNode('./following-sibling::tr[descendant::text()[' . $this->eq($this->t("Drop off")) . ']][1]/following-sibling::tr[normalize-space(.)][1]/descendant::text()[' . $this->eq($this->t("Contact")) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]',
                    $root, true, "#" . $this->opt($this->t("Tel ")) . "([\d\-\+\(\) ]{5,})#"), false, true);

            $r->car()
                ->type($this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Car type")) . "]/ancestor::td[1]/following-sibling::td[2]",
                    $root));

            $node = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Estimated total")) . "]/ancestor::td[1]/following-sibling::td[2]",
                $root);

            if (preg_match("#^([A-Z]{3})\s+(\d[\d,.]+)#", $node, $m)) {
                $r->price()
                    ->currency($m[1])
                    ->total(trim($m[2], ',. '));
            }

            $acc = $this->http->FindSingleNode("./following-sibling::tr[position()<16]//text()[" . $this->eq($this->t("Membership ID")) . "]/ancestor::td[1]/following-sibling::td[2]",
                $root, true, "#^\s*[A-Z\d]{5,}\s*$#");

            if (!empty($acc)) {
                $r->program()
                    ->account($acc, false);
            }

            if (empty($r->getConfirmationNumbers()) && empty($r->getNoConfirmationNumber())
                && $this->http->XPath->query("//text()[{$this->contains($this->t("Confirmation number"))}]")->length === 0
            ) {
                $r->general()->noConfirmation();
            }
        }

        return true;
    }

    private function getRentalProviderByKeyword(?string $keyword): ?string
    {
        if (!empty($keyword)) {
            foreach ($this->keywordsRental as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                } else {
                    foreach ($kws as $kw) {
                        if (strpos($keyword, $kw) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email): void
    {
        if (!$this->parseTrips($email)) {
            return;
        }

        if (!$this->parseHotels($email)) {
            return;
        }

        if (!$this->parseRentals($email)) {
            return;
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        $this->logger->debug('YES');

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

    private function normalizeDate($str)
    {
        //$this->logger->debug("DATE: {$str}");
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+) ([^\s\d]+) (\d+:\d+)$#", //23 April 18:00
            "#^(\d+) ([^\s\d]+) (\d+:\d+ [AP]M)$#", //23 April 18:00 AM
            "#^(\d+) ([^\s\d]+)$#", //23 April
            "#^\s*\w+ (\d+ \w+ \d{4})\s*$#", //Viernes 04 Abril 2014
            "#^(\d+ [^\s\d]+ \d{4}) at (\d+:\d+:\d+) [A-Z]{3}$#", //Viernes 04 Abril 2014

            "#^(\d+)\s*([^\s\d]+)\s*(\d{4}), (\d+:\d+)#", //05dec2018, 15:00
            "#^(\d+)\s*([^\s\d]+)\s*(\d{2}), (\d{2})(\d{2})#", //05dec18, 1500
            "#^(\d+)\-([^\s\d]+)\-(\d{2}), (\d+(?::\d+)?\s*(?:[ap]m)?)#", //20-feb-16, \d+pm   | 20-feb-16, \d+:\d+pm
            "#^(\d+)\s+(\w+)\s+(\d{2})$#", //20 March 23
        ];
        $out = [
            "$1 $2 $year, $3",
            "$1 $2 $year, $3",
            "$1 $2 $year",
            "$1",
            "$1, $2",

            "$1 $2 $3, $4",
            "$1 $2 20$3, $4:$5",
            "$1 $2 20$3, $4",
            "$1 $2 20$3",
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $str));

        if (preg_match("#\b\d{4}\b#", $str)) {
            return $str;
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

    private function ends($field, $source = 'normalize-space()')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $rules = [];

        foreach ($field as $f) {
            $len = mb_strlen($f);

            if ($len > 0) {
                $rule = "substring({$source},string-length({$source})+1-{$len},{$len})='{$f}'";
                $rules[] = $rule;
            }
        }

        if (count($rules) == 0) {
            return 'false()';
        }

        return implode(' or ', $rules);
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

    private function opt($field, $delim = '/')
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) use ($delim) {
            return str_replace(' ', '\s+', preg_quote($s, $delim));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
