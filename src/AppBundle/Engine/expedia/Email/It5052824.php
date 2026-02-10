<?php

namespace AwardWallet\Engine\expedia\Email;

class It5052824 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "expedia/it-2144982.eml, expedia/it-2146106.eml, expedia/it-2146196.eml, expedia/it-4116291.eml, expedia/it-4159459.eml, expedia/it-42.eml, expedia/it-4204969.eml, expedia/it-4327829.eml, expedia/it-5052824.eml";

    public $reFrom = "expediamail.com";
    public $reSubject = [
        "en"=> "Your Trip Details",
        "it"=> "Dettagli finali sul tuo viaggio",
        "es"=> "Confirmación de la información de viaje",
        "fr"=> "Derniers détails du voyage",
        "nl"=> "De laatste details voor je op reis vertrekt",
        "de"=> "Ihre Reisedetails",
        "da"=> "Dine endelige rejsedetaljer",
        "no"=> "Dine endelige rejsedetaljer",
    ];
    public $reBody = 'Expedia';
    public $reBody2 = [
        "en" => "Traveler details",
        "en2"=> "Traveller details",
        "it" => "Dettagli sul viaggiatore",
        "es" => "Detalles del viajero",
        "fr" => "Coordonnées du voyageur",
        "nl" => "Reizigersgegevens",
        "de" => "Angaben zu den Reisenden",
        "da" => "Rejsendes oplysninger",
        "no" => "Den reisendes informasjon",
    ];

    public static $dictionary = [
        "en" => [],
        "it" => [
            // all
            "Expedia Itinerary Number(s)" => "Numero(i) di itinerario Expedia",
            "Booking Reference:"          => "Riferimento prenotazione:",
            "Confirmation Code:"          => "NOTTRANSLATED",
            "Main contact:"               => "Contatto principale:",
            "Number of traveller(s):"     => "NOTTRANSLATED",
            "Number of traveler(s):"      => "NOTTRANSLATED",
            "Traveller's name(s):"        => "NOTTRANSLATED",
            "Traveler's name(s):"         => "NOTTRANSLATED",
            "Address:"                    => "Indirizzo:", //hotel, car

            // Flight, Bus
            "Airline rules and regulations" => "Regole e direttive della compagnia aerea",
            "THIS IS BUS SERVICE"           => "NOTTRANSLATED",
            "Flight Number:"                => "Numero volo:",
            "Depart"                        => "Partenza",
            "Arrive"                        => "Arrivo",
            "Duration"                      => "Durata",

            // Hotel
            "Hotel rules and regulations" => "Regole e direttive dell'hotel",
            "Check-in"                    => "Arrivo",
            "Check-out"                   => "Partenza",
            "Telephone:"                  => "Telefono:",
            "Room(s) Booked:"             => "Camera/e prenotata/e:",
            "Room type:"                  => "Tipo di camera:",

            // Car
            "Car rules and regulations" => "NOTTRANSLATED",
            "Pick-up:"                  => "NOTTRANSLATED",
            "Drop-off:"                 => "NOTTRANSLATED",
            "Hours of Operation:"       => "NOTTRANSLATED",
        ],
        "es" => [
            // all
            "Expedia Itinerary Number(s)" => "Número de itinerario de Expedia",
            "Booking Reference:"          => "NOTTRANSLATED",
            "Confirmation Code:"          => "Código de confirmación:",
            "Main contact:"               => "Contacto principal:",
            "Number of traveller(s):"     => "Nº de viajeros:",
            "Number of traveler(s):"      => "NOTTRANSLATED",
            "Traveller's name(s):"        => "Nombre de los viajeros:",
            "Traveler's name(s):"         => "NOTTRANSLATED",
            "Address:"                    => "Dirección:", //hotel, car

            // Flight, Bus
            "Airline rules and regulations" => "Reglas y regulaciones de la línea aérea",
            "THIS IS BUS SERVICE"           => "NOTTRANSLATED",
            "Flight Number:"                => "Número de vuelo:",
            "Depart"                        => "Salida",
            "Arrive"                        => "Llegada",
            "Duration"                      => "Duración",

            // Hotel
            "Hotel rules and regulations" => "Reglas y regulaciones del hotel",
            "Check-in"                    => "Entrada",
            "Check-out"                   => "Salida",
            "Telephone:"                  => "Teléfono:",
            "Room(s) Booked:"             => "Número de habitaciones reservadas:",
            "Room type:"                  => "Tipo de habitación:",

            // Car
            "Car rules and regulations" => "NOTTRANSLATED",
            "Pick-up:"                  => "NOTTRANSLATED",
            "Drop-off:"                 => "NOTTRANSLATED",
            "Hours of Operation:"       => "NOTTRANSLATED",
        ],
        "fr" => [
            // all
            "Expedia Itinerary Number(s)" => "Numéro(s) de voyage Expedia",
            "Booking Reference:"          => "Référence de la réservation :",
            "Confirmation Code:"          => "Code de confirmation :",
            "Main contact:"               => "Contact principal :",
            "Number of traveller(s):"     => "NOTTRANSLATED",
            "Number of traveler(s):"      => "NOTTRANSLATED",
            "Traveller's name(s):"        => "NOTTRANSLATED",
            "Traveler's name(s):"         => "NOTTRANSLATED",
            "Address:"                    => "NOTTRANSLATED", //hotel, car

            // Flight, Bus
            "Airline rules and regulations" => "Conditions et règlement de la compagnie aérienne",
            "THIS IS BUS SERVICE"           => "NOTTRANSLATED",
            "Flight Number:"                => "Numéro de vol :",
            "Depart"                        => "Départ",
            "Arrive"                        => "Arrivée",
            "Duration"                      => "Durée ",

            // Hotel
            "Hotel rules and regulations" => "NOTTRANSLATED",
            "Check-in"                    => "NOTTRANSLATED",
            "Check-out"                   => "NOTTRANSLATED",
            "Telephone:"                  => "NOTTRANSLATED",
            "Room(s) Booked:"             => "NOTTRANSLATED",
            "Room type:"                  => "NOTTRANSLATED",

            // Car
            "Car rules and regulations" => "NOTTRANSLATED",
            "Pick-up:"                  => "NOTTRANSLATED",
            "Drop-off:"                 => "NOTTRANSLATED",
            "Hours of Operation:"       => "NOTTRANSLATED",
        ],
        "nl" => [
            // all
            "Expedia Itinerary Number(s)" => "Expedia reisplannummer(s)",
            "Booking Reference:"          => "Boekingsreferentie:",
            "Confirmation Code:"          => "Bevestigingscode:",
            "Main contact:"               => "NOTTRANSLATED",
            "Number of traveller(s):"     => "NOTTRANSLATED",
            "Number of traveler(s):"      => "NOTTRANSLATED",
            "Traveller's name(s):"        => "NOTTRANSLATED",
            "Traveler's name(s):"         => "NOTTRANSLATED",
            "Address:"                    => "NOTTRANSLATED", //hotel, car

            // Flight, Bus
            "Airline rules and regulations" => "Regels en voorschriften van de luchtvaartmaatschappij",
            "THIS IS BUS SERVICE"           => "NOTTRANSLATED",
            "Flight Number:"                => "Vluchtnummer:",
            "Depart"                        => "Vertrek",
            "Arrive"                        => "Aankomst",
            "Duration"                      => "Duur",

            // Hotel
            "Hotel rules and regulations" => "NOTTRANSLATED",
            "Check-in"                    => "NOTTRANSLATED",
            "Check-out"                   => "NOTTRANSLATED",
            "Telephone:"                  => "NOTTRANSLATED",
            "Room(s) Booked:"             => "NOTTRANSLATED",
            "Room type:"                  => "NOTTRANSLATED",

            // Car
            "Car rules and regulations" => "NOTTRANSLATED",
            "Pick-up:"                  => "NOTTRANSLATED",
            "Drop-off:"                 => "NOTTRANSLATED",
            "Hours of Operation:"       => "NOTTRANSLATED",
        ],
        "de" => [
            // all
            "Expedia Itinerary Number(s)" => "Expedia Reiseplannummer(n)",
            "Booking Reference:"          => "Buchungsreferenz:",
            "Confirmation Code:"          => "Bestätigungscode: ",
            "Main contact:"               => "Hauptkontakt:",
            "Number of traveller(s):"     => "Anzahl der Reisenden:",
            "Number of traveler(s):"      => "NOTTRANSLATED",
            "Traveller's name(s):"        => "Name(n) des/der Reisenden:",
            "Traveler's name(s):"         => "NOTTRANSLATED",
            "Address:"                    => "Adresse:", //hotel, car

            // Flight, Bus
            "Airline rules and regulations" => "Richtlinien der Fluggesellschaft",
            "THIS IS BUS SERVICE"           => "NOTTRANSLATED",
            "Flight Number:"                => "Flugnummer:",
            "Depart"                        => "Von",
            "Arrive"                        => "Nach",
            "Duration"                      => "Dauer",

            // Hotel
            "Hotel rules and regulations" => "Allgemeine Geschäftsbedingungen Hotel",
            "Check-in"                    => "Anreise",
            "Check-out"                   => "Abreise",
            "Telephone:"                  => "Telefon:",
            "Room(s) Booked:"             => "Gebuchte(s) Zimmer:",
            "Room type:"                  => "Zimmertyp:",

            // Car
            "Car rules and regulations" => "NOTTRANSLATED",
            "Pick-up:"                  => "NOTTRANSLATED",
            "Drop-off:"                 => "NOTTRANSLATED",
            "Hours of Operation:"       => "NOTTRANSLATED",
        ],
        "da" => [
            // all
            "Expedia Itinerary Number(s)" => "Expedia-rejseplansnummer",
            "Booking Reference:"          => "Reservationsreference:",
            "Confirmation Code:"          => "Bekræftelseskode:",
            "Main contact:"               => "Primære kontaktperson:",
            "Number of traveller(s):"     => "NOTTRANSLATED",
            "Number of traveler(s):"      => "NOTTRANSLATED",
            "Traveller's name(s):"        => "NOTTRANSLATED",
            "Traveler's name(s):"         => "NOTTRANSLATED",
            "Address:"                    => "NOTTRANSLATED", //hotel, car

            // Flight, Bus
            "Airline rules and regulations" => "Luftfartsselskabets regler og bestemmelser",
            "THIS IS BUS SERVICE"           => "NOTTRANSLATED",
            "Flight Number:"                => "Flynummer:",
            "Depart"                        => "Afrejse",
            "Arrive"                        => "Ankomst",
            "Duration"                      => "Varighed",

            // Hotel
            "Hotel rules and regulations" => "NOTTRANSLATED",
            "Check-in"                    => "NOTTRANSLATED",
            "Check-out"                   => "NOTTRANSLATED",
            "Telephone:"                  => "NOTTRANSLATED",
            "Room(s) Booked:"             => "NOTTRANSLATED",
            "Room type:"                  => "NOTTRANSLATED",

            // Car
            "Car rules and regulations" => "NOTTRANSLATED",
            "Pick-up:"                  => "NOTTRANSLATED",
            "Drop-off:"                 => "NOTTRANSLATED",
            "Hours of Operation:"       => "NOTTRANSLATED",
        ],
        "no" => [
            // all
            "Expedia Itinerary Number(s)" => "Expedia-reiserutenummer",
            "Booking Reference:"          => "Bestillingsreferanse:",
            "Confirmation Code:"          => "Bekreftelseskode:",
            "Main contact:"               => "Primær kontaktperson:",
            "Number of traveller(s):"     => "Antall reisende:",
            "Number of traveler(s):"      => "NOTTRANSLATED",
            "Traveller's name(s):"        => "Den/de reisendes navn:",
            "Traveler's name(s):"         => "NOTTRANSLATED",
            "Address:"                    => "NOTTRANSLATED", //hotel, car

            // Flight, Bus
            "Airline rules and regulations" => "Flyselskapets regler og forskrifter",
            "THIS IS BUS SERVICE"           => "NOTTRANSLATED",
            "Flight Number:"                => "Flightnummer:",
            "Depart"                        => "Avreise",
            "Arrive"                        => "Ankomst",
            "Duration"                      => "Varighet",

            // Hotel
            "Hotel rules and regulations" => "NOTTRANSLATED",
            "Check-in"                    => "NOTTRANSLATED",
            "Check-out"                   => "NOTTRANSLATED",
            "Telephone:"                  => "NOTTRANSLATED",
            "Room(s) Booked:"             => "NOTTRANSLATED",
            "Room type:"                  => "NOTTRANSLATED",

            // Car
            "Car rules and regulations" => "NOTTRANSLATED",
            "Pick-up:"                  => "NOTTRANSLATED",
            "Drop-off:"                 => "NOTTRANSLATED",
            "Hours of Operation:"       => "NOTTRANSLATED",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        //##################
        //##   FLIGHTS   ###
        //##################

        $xpath = "//text()[normalize-space(.)='" . $this->t("Airline rules and regulations") . "']/ancestor::tr[contains(., '" . $this->t("Depart") . "')][1][not(contains(., '" . $this->t("THIS IS BUS SERVICE") . "'))]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            // echo $this->nextText($this->t("Expedia Itinerary Number(s)"))."\n";
            $airs = [];

            foreach ($nodes as $root) {
                if ($rl = $this->nextText($this->t("Booking Reference:"), $root)) {
                    $airs[$rl][] = $root;
                } elseif ($rl = $this->nextText($this->t("Confirmation Code:"), $root)) {
                    $airs[$rl][] = $root;
                } elseif ($rl = $this->nextText($this->t("Expedia Itinerary Number(s)"))) {
                    $airs[$rl][] = $root;
                }
            }

            foreach ($airs as $rl=>$roots) {
                $it = [];

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $rl;

                // TripNumber
                // Passengers
                $it['Passengers'] = array_unique(array_filter(array_merge(
                    $this->http->FindNodes("//td[not(.//td) and starts-with(normalize-space(.), '" . $this->t("Main contact:") . "')]", null, "#" . $this->t("Main contact:") . "\s*(.+)#"),
                    $this->http->FindNodes("//text()[normalize-space(.)=\"" . $this->t("Traveller's name(s):") . "\" or normalize-space(.)=\"" . $this->t("Traveler's name(s):") . "\"]/ancestor::tr[1]/following-sibling::tr/descendant::text()[string-length(normalize-space(.))>2]")
                )));

                // AccountNumbers
                // Cancelled
                // TotalCharge
                // BaseFare
                // Currency
                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                foreach ($roots as $si=>$root) {
                    $details = array_map("trim", explode(",", preg_replace_callback(
                        "#^((?:\d+\w,\s*)+)#",
                        function ($matches) {
                            return trim(str_replace(',', '|', $matches[1]), ' |') . ',';
                        },
                        $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("Airline rules and regulations") . "']/ancestor::td[normalize-space(.)='" . $this->t("Airline rules and regulations") . "'][last()]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1]/ancestor::td[1]", $root)
                    )));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), '" . $this->t("Flight Number:") . "')]", $root, true, "#:\s+(\d+)$#");

                    // DepCode
                    $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})(?:\)|$)#", $this->nextText($this->t("Depart"), $root));

                    // DepName
                    $itsegment['DepName'] = $this->re("#(.*?)\s*\(([A-Z]{3})(?:\)|$)#", $this->nextText($this->t("Depart"), $root));

                    // DepDate
                    if (count($this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Depart") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::td[normalize-space(.)][1]", $root)) == 2) {
                        $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(",", $this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Depart") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::td[normalize-space(.)][1]", $root))));
                    } else {
                        $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(",", $this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Arrive") . "']/ancestor::tr[./following-sibling::tr[1]][1]/following-sibling::tr[1]/descendant::tr[not(.//tr)][position()=1 or position()=2]", $root))));
                    }
                    // ArrCode
                    $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})(?:\)|$)#", $this->nextText($this->t("Arrive"), $root));

                    // ArrName
                    $itsegment['ArrName'] = $this->re("#(.*?)\s*\(([A-Z]{3})(?:\)|$)#", $this->nextText($this->t("Arrive"), $root));

                    // ArrDate
                    if (count($this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Depart") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::td[normalize-space(.)][2]", $root)) == 2) {
                        $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(",", $this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Depart") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::td[normalize-space(.)][2]", $root))));
                    } elseif (count($this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Arrive") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::td[normalize-space(.)][1]", $root)) == 2) {
                        $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(",", $this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Arrive") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::td[normalize-space(.)][1]", $root))));
                    } else {
                        $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(",", $this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Arrive") . "']/ancestor::tr[./following-sibling::tr[1]][1]/following-sibling::tr[1]/descendant::tr[not(.//tr)][position()=3 or position()=4]", $root))));
                    }

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./descendant::text()[string-length(normalize-space(.))>1][1]", $root);

                    // Operator
                    // Aircraft
                    if (isset($details[2])) {
                        $itsegment['Aircraft'] = $details[2];
                    }

                    // TraveledMiles
                    // Cabin
                    if (isset($details[1])) {
                        $itsegment['Cabin'] = $details[1];
                    }

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    if (isset($details[0]) && preg_match("#\d+\w$#", $details[0])) {
                        $itsegment['Seats'] = implode(",", array_map("trim", explode("|", $details[0])));
                    }

                    // Duration
                    $itsegment['Duration'] = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), '" . $this->t("Duration") . "')]/ancestor::td[1]", $root, true, "#" . $this->t("Duration") . ":?\s+(.+)$#");

                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            }
        }

        //##############
        //##   BUS   ###
        //##############

        $xpath = "//text()[normalize-space(.)='" . $this->t("Airline rules and regulations") . "']/ancestor::tr[contains(., '" . $this->t("Depart") . "')][1][contains(., '" . $this->t("THIS IS BUS SERVICE") . "')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $airs = [];

            foreach ($nodes as $root) {
                if ($rl = $this->nextText($this->t("Booking Reference:"), $root)) {
                    $airs[$rl][] = $root;
                } elseif ($rl = $this->nextText($this->t("Confirmation Code:"), $root)) {
                    $airs[$rl][] = $root;
                } elseif ($rl = $this->nextText($this->t("Expedia Itinerary Number(s)"))) {
                    $airs[$rl][] = $root;
                }
            }

            foreach ($airs as $rl=>$roots) {
                $it = [];

                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $rl;

                // TripNumber
                // Passengers
                $it['Passengers'] = array_unique(array_filter(array_merge(
                    $this->http->FindNodes("//td[not(.//td) and starts-with(normalize-space(.), '" . $this->t("Main contact:") . "')]", null, "#" . $this->t("Main contact:") . "\s*(.+)#"),
                    $this->http->FindNodes("//text()[normalize-space(.)=\"" . $this->t("Traveller's name(s):") . "\" or normalize-space(.)=\"" . $this->t("Traveler's name(s):") . "\"]/ancestor::tr[1]/following-sibling::tr/descendant::text()[string-length(normalize-space(.))>2]")
                )));

                // AccountNumbers
                // Cancelled
                // TotalCharge
                // BaseFare
                // Currency
                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_BUS;

                foreach ($roots as $root) {
                    $details = array_map("trim", explode(",", preg_replace("#^((?:\d+\w,\s*)+)#e", "trim(str_replace(',', '|', '$1'), ' |').','", $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("Airline rules and regulations") . "']/ancestor::td[normalize-space(.)='" . $this->t("Airline rules and regulations") . "'][last()]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1]/ancestor::td[1]", $root))));

                    $itsegment = [];

                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), '" . $this->t("Flight Number:") . "')]", $root, true, "#:\s+(\d+)$#");

                    // DepCode
                    $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})(?:\)|$)#", $this->nextText($this->t("Depart"), $root));

                    // DepName
                    $itsegment['DepName'] = $this->re("#(.*?)\s*\(([A-Z]{3})(?:\)|$)#", $this->nextText($this->t("Depart"), $root));

                    // DepAddress
                    // DepDate
                    $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(",", $this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Depart") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::td[normalize-space(.)][1]", $root))));

                    // ArrCode
                    $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})(?:\)|$)#", $this->nextText($this->t("Arrive"), $root));

                    // ArrName
                    $itsegment['ArrName'] = $this->re("#(.*?)\s*\(([A-Z]{3})(?:\)|$)#", $this->nextText($this->t("Arrive"), $root));

                    // ArrAddress
                    // ArrDate
                    if (count($this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Depart") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::td[normalize-space(.)][2]", $root)) == 2) {
                        $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(",", $this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Depart") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::td[normalize-space(.)][2]", $root))));
                    } else {
                        $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(",", $this->http->FindNodes(".//text()[normalize-space(.)='" . $this->t("Arrive") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][position()<3]/descendant::td[normalize-space(.)][1]", $root))));
                    }

                    // Type
                    $itsegment['Type'] = "Bus";

                    // TraveledMiles
                    // Cabin
                    if (isset($details[1])) {
                        $itsegment['Cabin'] = $details[1];
                    }

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    if (isset($details[0]) && preg_match("#\d+\w$#", $details[0])) {
                        $itsegment['Seats'] = implode(",", array_map("trim", explode("|", $details[0])));
                    }

                    // Duration
                    $itsegment['Duration'] = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), '" . $this->t("Duration") . "')]/ancestor::td[1]", $root, true, "#" . $this->t("Duration") . ":?\s+(.+)$#");

                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            }
        }

        //################
        //##   Hotel   ###
        //################
        $xpath = "//text()[normalize-space(.)=\"" . $this->t("Hotel rules and regulations") . "\"]/ancestor::tr[contains(., '" . $this->t("Check-in") . "')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            if (!($it['ConfirmationNumber'] = $this->re("#^([\w-]+)$#", $this->nextText($this->t("Confirmation Code:"), $root)))) {
                $it['ConfirmationNumber'] = $this->nextText($this->t("Expedia Itinerary Number(s)"));
            }

            // TripNumber
            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][1]", $root);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Check-in"), $root)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText($this->t("Check-out"), $root)));

            // Address
            $it['Address'] = $this->nextText($this->t("Address:"), $root);

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->nextText($this->t("Telephone:"), $root);

            // Fax
            // GuestNames
            $it['GuestNames'] = array_unique(array_filter(array_merge(
                $this->http->FindNodes("//td[not(.//td) and starts-with(normalize-space(.), '" . $this->t("Main contact:") . "')]", null, "#" . $this->t("Main contact:") . "\s*(.+)#"),
                $this->http->FindNodes("//text()[normalize-space(.)=\"" . $this->t("Traveller's name(s):") . "\" or normalize-space(.)=\"" . $this->t("Traveler's name(s):") . "\"]/ancestor::tr[1]/following-sibling::tr/descendant::text()[string-length(normalize-space(.))>2]")
            )));

            // Guests
            if (!($it['Guests'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '" . $this->t("Number of traveller(s):") . "') or starts-with(normalize-space(.), '" . $this->t("Number of traveler(s):") . "')]", null, true, "#\s+(\d+)$#"))) {
                if (!($it['Guests'] = $this->nextText($this->t("Number of traveller(s):")))) {
                    $it['Guests'] = $this->nextText($this->t("Number of traveler(s):"));
                }
            }

            // Kids
            // Rooms
            $it['Rooms'] = $this->nextText($this->t("Room(s) Booked:"), $root);

            // Rate
            // RateType
            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->nextText($this->t("Room type:"), $root);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //##############
        //##   Car   ###
        //##############
        $xpath = "//text()[normalize-space(.)=\"" . $this->t("Car rules and regulations") . "\"]/ancestor::tr[contains(., '" . $this->t("Pick-up:") . "')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "L";

            // Number
            if (!($it['Number'] = $this->re("#^([\w-]+)$#", $this->nextText($this->t("Confirmation Code:"), $root)))) {
                $it['Number'] = $this->nextText($this->t("Expedia Itinerary Number(s)"));
            }
            // TripNumber
            // PickupDatetime
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->nextText($this->t("Pick-up:"), $root)));

            // PickupLocation
            $it['PickupLocation'] = $this->nextText($this->t("Address:"), $root);

            // DropoffDatetime
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->nextText($this->t("Drop-off:"), $root)));

            // DropoffLocation
            $it['DropoffLocation'] = $this->nextText($this->t("Address:"), $root, 2);

            // PickupPhone
            // PickupFax
            // PickupHours
            $it['PickupHours'] = $this->nextText($this->t("Hours of Operation:"), $root);

            // DropoffPhone
            // DropoffHours
            $it['DropoffHours'] = $this->nextText($this->t("Hours of Operation:"), $root, 2);

            // DropoffFax
            // RentalCompany
            $it['RentalCompany'] = $this->http->FindSingleNode("./descendant::text()[string-length(normalize-space(.))>1][1]", $root);

            // CarType
            $it['CarType'] = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][1]", $root);

            // CarModel
            // CarImageUrl
            // RenterName
            // PromoCode
            // TotalCharge
            // Currency
            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // ServiceLevel
            // Cancelled
            // PricedEquips
            // Discount
            // Discounts
            // Fees
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }
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
            if (strpos($headers["subject"], $re) !== false) {
                return true;
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

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
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
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = trim($lang, "1234567890");

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
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

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[string-length(normalize-space(.))>1 or contains(translate(., '1234567890', 'dddddddddd'), 'd')][1]", $root);
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
        // echo $str."\n";
        $in = [
            "#^(\d+:\d+(?:\s+[AP]M)?),[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#",			// 03:00,Monday, November 05, 2012    |   03:00 PM,Monday, November 05, 2012
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#",								// Monday, November 05, 2012
            "#^(\d+:\d+(?:\s+[AP]M)?),[^\d\s]+,\s+(\d+)\s+([^\d\s]+),\s+(\d{4})$#",			// 03:00,Monday, 05 November, 2012    |   03:00 PM,Monday, 05 November, 2012
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+),\s+(\d{4})$#",								// Monday, 05 November, 2012
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",									// Monday, 05 November 2012
            "#^[^\d\s]+,\s+(\d+)\s+de\s+([^\d\s]+),\s+(\d{4})$#",							// Mié, 15 de mayo, 2013
            "#^(\d+:\d+),[^\d\s]+,\s+(\d+)\s+de\s+([^\d\s]+),\s+(\d{4})$#",					// 03:00,Mié, 15 de mayo, 2013
            "#^(\d+:\d+(?:\s+[AP]M)?),[^\d\s]+\s+([^\d\s]+)\s+(\d+)\s+(\d{4})$#",			// 03:00,Monday November 05 2012      |   03:00 PM,Monday November 05 2012
            "#^(\d+:\d+(?:\s+[AP]M)?),[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",			// 03:00,Monday 05 November 2012      |   03:00 PM,Monday 05 November 2012
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+\((\d+:\d+(?:\s+[AP]M))?\)$#",	// Monday, November 05, 2012 (03:00)  |   Monday, November 05, 2012 (03:00 PM)
            "#^(\d+:\d+),[^\d\s]+,\s+(\d+)\.([^\d\s]+)\s+(\d{4})$#",						// 09:55,Fr, 08.Nov 2013
            "#^[^\d\s]+,\s+(\d+)\.([^\d\s]+)\s+(\d{4})$#",									// Fr, 08.November 2013
            "#^(\d+:\d+),[^\d\s]+\.\s+(\d+)\.\s+([^\d\s]+),\s+(\d{4})$#",					// 15:50,Lør. 15. februar, 2014
            "#^[^\d\s]+,\s+(\d+),\s+([^\d\s]+),\s+(\d{4})$#",								// Sat, 20, July, 2013
            "#^(\d+)\.(\d+),[^\d\s]+\s+(\d+)\.\s+([^\d\s]+),\s+(\d{4})$#",					//08.15,man 23. juni, 2014
        ];
        $out = [
            "$3 $2 $4, $1",
            "$2 $1 $3",
            "$2 $3 $4, $1",
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2 $3, $4",
            "$3 $2 $4, $1",
            "$2 $3 $4, $1",
            "$2 $1 $3, $4",
            "$2 $3 $4, $1",
            "$1 $2 $3",
            "$2 $3 $4, $1",
            "$1 $2 $3",
            "$3 $4 $5, $1:$2",
        ];
        $str = preg_replace($in, $out, $str);
        $str = $this->dateStringToEnglish($str);

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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
