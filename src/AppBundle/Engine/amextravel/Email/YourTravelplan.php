<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTravelplan extends \TAccountCheckerExtended
{
    public $mailFiles = "amextravel/it-10.eml, amextravel/it-11.eml, amextravel/it-11181708.eml, amextravel/it-11597372.eml, amextravel/it-11785830.eml, amextravel/it-13.eml, amextravel/it-14.eml, amextravel/it-144016252.eml, amextravel/it-16.eml, amextravel/it-1617595.eml, amextravel/it-1687004.eml, amextravel/it-1702454.eml, amextravel/it-1702455.eml, amextravel/it-1807744.eml, amextravel/it-1808256.eml, amextravel/it-185098149.eml, amextravel/it-1979951.eml, amextravel/it-1999806.eml, amextravel/it-2.eml, amextravel/it-2006307.eml, amextravel/it-2071759.eml, amextravel/it-21.eml, amextravel/it-2321207.eml, amextravel/it-2321804.eml, amextravel/it-2355846.eml, amextravel/it-2366035.eml, amextravel/it-2411004.eml, amextravel/it-2479922.eml, amextravel/it-2481220.eml, amextravel/it-2753196.eml, amextravel/it-2762126.eml, amextravel/it-2824899.eml, amextravel/it-2932050.eml, amextravel/it-3048897.eml, amextravel/it-3452173.eml, amextravel/it-34964321.eml, amextravel/it-35422619.eml, amextravel/it-4.eml, amextravel/it-43382952.eml, amextravel/it-510273169.eml, amextravel/it-6.eml, amextravel/it-6867262.eml, amextravel/it-7.eml, amextravel/it-75519389.eml, amextravel/it-75563866.eml, amextravel/it-8.eml, amextravel/it-9.eml, amextravel/it-9770628.eml";

    private $detectFrom = ['axway', 'myamextravel', 'mytravelplans.'];

    private $detectCompany = 'American Express';

    private $detectsBody = [
        'en'  => 'is pleased to deliver your travel',
        'en2' => 'Thank you for choosing American Express Platinum Online Travel Service',
        'de'  => 'American Express Reise Service übersendet Ihnen gerne folgenden Reiseplan',
        'fr'  => 'American Express Voyages d’Affaires a le plaisir de vous transmettre le récapitulatif de votre voyage',
        'en3' => 'Thank you for choosing American Express',
        'es'  => 'nosotros en el teléfono habitual de American Express Travel and Lifestyle Services',
        'fr2' => 'Veuillez vérifier que tous les détails sont corrects et contactez votre équipe de Conseillers - Voyages American Express immédiatement si vous avez',
        'en4' => 'Thank you for choosing American Express Platinum Travel Service and have a pleasant trip',
        'fi'  => 'Tästä linkistä saat esille matkavahvistuksen, joka on optimoitu kaikille laitteille',
        'en5' => 'Travel Arrangements for',
        'en6' => 'American Express Global Business Travel',
        'fr3' => 'AMERICAN EXPRESS VOYAGES D’AFFAIRES',
        'fr4' => 'American Express Voyages d’Affaires',
        'de2' => 'Reiseplan fuer',
        'es2' => 'Itinerario de Viaje para',
    ];

    private static $dict = [
        'en' => [
            "Booking Reference" => ["Booking Reference", "Trip ID", "American Express Travel Record Locator"],
            "mainSectionEnds"   => ["Marketing Information", "Additional Messages", "Loyalty Programs"],
            //            "Travel Arrangements for" => "",
            "Itinerary for" => ["Itinerary\s+for", "Invoice \d+ for", "Travel invoice for"], // subject regexp
            //            "Airline Code" => "",
            //            "Ticket Number" => "",
            //            "Travel Details" => "",
            //            "Flight Information" => "",
            "Hotel Information" => ["Hotel Information", "HOTEL INFORMATION"],
            "Car Information"   => ["Car Information", "Rental Car Information"],
            //            "Informations Rail" => "",
            //            "Flight" => "",
            //            "Class" => "",
            "Estimated Time" => ["Estimated Time", "Estimated time"],
            "Stops"          => ["Stops", "Number of Stops"],
            //            "Non-stop" => "", // regexp
            "Aircraft" => ["Aircraft", "Equipment", "Plane"],
            //            "Airline" => "",
            "Airline Record Locator" => ["Airline Record Locator", "Airline Booking Ref"],
            //            "Airline Record Locators" => "",
            //            "Operated By" => "",
            //            "Date" => "",
            //            "Origin" => "",
            //            "Departure Terminal" => "",
            //            "Departing" => "",
            //            "Destination" => "",
            //            "Arrival Terminal" => "",
            //            "Arriving" => "",
            "Meal" => ["Meal  Service", "Meal Service", "Meal"],
            //            "Distance" => "",
            "Seat" => ["Seat", "Seats"],

            // Hotel
            "Reference Number" => ["Reference Number", "Confirmation Number"],
            //            "Cancellation Policy" => "",
            //            "Hotel" => "",
            "Address"   => ["Address", "Hotel Address"],
            "Telephone" => ["Telephone", "Phone Number"],
            "Fax"       => ["Fax", "Fax Number"],
            //            "Check In Date" => "",
            //            "Check Out Date" => "",
            //            "Rooms" => "",
            "RoomsReg" => "#(\d+)\s+(?:Rooms|Room)#i",
            //            "Room Type" => [""], // to translate
            "Base Rate"      => ["Base Rate", "Hotel Rate", "Rate"],
            "Frequent Guest" => ["Frequent Guest", "Membership"],

            // Car
            //            "Confirmation Number" => "",
            "Location"         => ["Location", "Pick Up Address", "Pick Up Details"],
            'Location DropOff' => ["Drop Off Details", "Location"],
            "Pick Up Date"     => ["Pick Up Date", "Pick Up"],
            "Drop Off Date"    => ["Drop Off Date", "Drop Off"],
            "Car Size"         => ["Car Size", "Size"],
            "CarTotal"         => ["Approximate price including taxes -", "Approximate price", "Rate"],
            //            "Category" => "",
            "Agency" => ["Agency", "Car Hire Company", "Car Company"],

            // Rail
            'Informations Rail' => ['Informations Rail', 'Rail Information'],
            "Rail Booking Ref"  => ["Rail Booking Ref", "Confirmation Number"],
            //            "Voiture" => "",// to translate
            "RailProvider" => "Provider",
            "RailCabin"    => "Seat",
            //            "Train" => "",
            'Ground Transportation Information' => ['Limousine Information'],
            //'Transfer' => '',
            //Ferry
        ],
        'de' => [
            "Booking Reference"       => ["Buchungsnummer"],
            "mainSectionEnds"         => ["Flugtarif Information", "Zusaetzliche Informationen"],
            "Travel Arrangements for" => "Reiseplan fuer",
            "Itinerary for"           => ["REISEPLAN INKL TICKETNR FUER"], // subject regexp
            //            "Airline Code" => "",
            "Ticket Number"      => "Ticket Nummer",
            "Travel Details"     => "Reisedetails",
            "Flight Information" => "Flug Information",
            "Hotel Information"  => "Hotel Information",
            //            "Car Information" => [""],
            //            "Informations Rail" => "",
            "Flight"         => ["Flugnummer", "Flugnr"],
            "Class"          => ["Buchungsklasse", "Buchungskl."],
            "Estimated Time" => ["Flugzeit"],
            "Stops"          => ["Zwischenstop", "Stops"],
            //            "Non-stop" => "", // regexp
            "Aircraft"               => ["Flugzeugtyp"],
            "Airline"                => "Fluggesellschaft",
            "Airline Record Locator" => ["Buchungsnummer der", "Buchungsnummer der Fluggesellschaft"],
            //            "Airline Record Locators" => "",
            "Operated By"        => "Durchgefuehrt von",
            "Date"               => "Datum",
            "Origin"             => "Von",
            "Departure Terminal" => "Abflug Terminal",
            "Departing"          => ["Abflug", "Abreise"],
            "Destination"        => "Nach",
            "Arrival Terminal"   => "Ankunft Terminal",
            "Arriving"           => "Ankunft",
            "Meal"               => ["Mahlzeiten"],
            //            "Distance" => "",
            "Seat" => ["Sitzplatz"],

            // Hotel
            "Reference Number"    => ["Bestaetigungsnummer"],
            "Cancellation Policy" => "Stornobedingungen",
            "Hotel"               => "Hotel",
            "Address"             => ["Adresse"],
            "Telephone"           => ["Telefon"],
            "Fax"                 => ["Fax"],
            "Check In Date"       => "Anreise",
            "Check Out Date"      => "Abreise",
            "Rooms"               => "Zimmer",
            "RoomsReg"            => "#(\d+)\s+(?:Zimmer)#i",
            "Room Type"           => ["Zimmertyp"],
            "Base Rate"           => ["Basispreis"],
            //            "Frequent Guest" => [""],

            // Car
            //            "Confirmation Number" => "",
            //            "Location" => "",
            //            "Pick Up Date" => "",
            //            "Drop Off Date" => "",
            //            "Car Size" => "",
            //            "Category" => "",
            //            "Agency" => "",

            // Rail
            //            "Rail Booking Ref" => "",
            //            "Voiture" => "",
            //            "RailProvider" => "",
            //            "RailProvider" => ""
            //            "Train" => "",
        ],
        'fr' => [
            "Booking Reference"       => ["Référence de Réservation"],
            "mainSectionEnds"         => ["Autres Informations"],
            "Travel Arrangements for" => "Nom du Passager(s)",
            "Itinerary for"           => ["ITINERAIRE DE"], // subject regexp
            //            "Airline Code" => "",
            //            "Ticket Number" => "",
            "Travel Details"     => "Détails du Voyage",
            "Flight Information" => ["Informations Vol", "Informations de Vol"],
            "Hotel Information"  => ["Informations Hôtel"],
            "Car Information"    => ["Informations Voiture"],
            "Informations Rail"  => "Informations Rail",
            "Flight"             => "Vol",
            "Class"              => "Classe",
            "Estimated Time"     => ["Durée Estimée"],
            "Stops"              => ["Nombre d'Escales"],
            "Non-stop"           => "sans escale", // regexp
            "Aircraft"           => ["Appareil"],
            //            "Airline" => "",
            "Airline Record Locator" => ["Numéro de Dossier", "Numéro de Dossier Compagnie Aérienne"],
            //            "Airline Record Locators" => "",
            "Operated By" => "Opéré par",
            //            "Date" => "",
            "Origin"             => "Origine",
            "Departure Terminal" => "Terminal de Départ",
            "Departing"          => "Heure de Départ",
            "Destination"        => "Destination",
            "Arrival Terminal"   => "Terminal d'Arrivée",
            "Arriving"           => "Heure d'Arrivée",
            //            "Meal" => [""],
            //            "Distance" => "",
            "Seat" => ["Siège"],

            // Hotel
            "Reference Number"    => ["Réf. de Confirmation", "Référence de Confirmation"],
            "Cancellation Policy" => "Conditions d'annulation",
            "Hotel"               => "Hôtel",
            "Address"             => ["Adresse"],
            "Telephone"           => ["Téléphone"],
            "Fax"                 => ["Numéro de Fax"],
            "Check In Date"       => "Date d'Arrivée",
            "Check Out Date"      => "Date de Départ",
            "Rooms"               => "Chambres",
            "RoomsReg"            => "#(\d+)\s+(?:Chambres|Chambre)#i",
            "Room Type"           => ["Type de chambre"],
            "Base Rate"           => ["Tarif de base"],
            //            "Frequent Guest" => [""],

            // car
            "Confirmation Number" => "Réf. de Confirmation",
            "Location"            => ["Lieu de Prise en", "Lieu de Restitution"],
            "Pick Up Date"        => "Date",
            "Drop Off Date"       => "Date de Restitution",
            "Car Size"            => "Taille",
            //            "Category" => "",
            "Agency"    => "Loueur de Voitures",
            "Confirmed" => "Confirmée",

            // Rail
            //            "Confirmation Number" => "",
            //            "Location" => "",
            //            "Pick Up Date" => "",
            //            "Drop Off Date" => "",
            //            "Car Size" => "",
            //            "Category" => "",
            //            "Agency" => "",
            "RailProvider" => "Fournisseur",
            "RailCabin"    => "Classe",
        ],
        'es' => [
            "Booking Reference"       => ["Referencia de la reserva", "Registro Localizador", 'Clave de reservación'],
            "mainSectionEnds"         => ["Mensajes Adicionales"],
            "Travel Arrangements for" => ["Itinerario de Viaje para", "Arreglos de Viaje para"],
            //            "Itinerary for" => [""], // subject regexp
            //            "Airline Code" => "",
            "Ticket Number"      => ["Número de boleto", 'Número de billete'],
            "Travel Details"     => ["Detalles del viaje", "Detalles del Viaje"],
            "Flight Information" => "Información de Vuelo",
            "Hotel Information"  => ["Información de Hotel", "Información del Hotel"],
            "Car Information"    => ["Información Coche de Alquiler"],
            //            "Informations Rail" => "",
            "Flight"                 => "Vuelo",
            "Class"                  => "Clase",
            "Estimated Time"         => ["Duración estimada", "Tiempo Estimado"],
            "Stops"                  => ["Número de escalas"],
            "Non-stop"               => "Sin escalas", // regexp
            "Aircraft"               => ["Avión"],
            "Airline"                => "Aerolínea",
            "Airline Record Locator" => ["Localizador", 'Código de confirmación'],
            //            "Airline Record Locators" => "",
            //            "Operated By" => "",
            "Date"               => "Fecha",
            "Origin"             => "Origen",
            "Departure Terminal" => "Terminal de salida",
            "Departing"          => ["Hora de Salida", "Salida"],
            "Destination"        => "Destino",
            "Arrival Terminal"   => ["Terminal de Llegada", 'Terminal de llegada'],
            "Arriving"           => ["Hora de llegada", "Llegada"],
            "Meal"               => ["Servicio de Alimentos"],
            "Distance"           => "millas Distancia",
            "Seat"               => ["Asiento"],

            // Hotel
            "Reference Number"    => ["Número de confirmación", "Clave de reservación"],
            "Cancellation Policy" => "Politica de cancelación",
            "Hotel"               => "Hotel",
            "Address"             => ["Dirección del Hotel", "Dirección"],
            "Telephone"           => ["Número de Teléfono", "Teléfono"],
            "Fax"                 => ["Número de Fax"],
            "Check In Date"       => ["Fecha de Ingreso", "Fecha de Registro de", "Fecha de entrada"],
            "Check Out Date"      => "Fecha de Salida",
            "Rooms"               => "Habitaciones",
            "RoomsReg"            => "#(\d+)\s+(?:Habitación|Habitaciones)#i",
            //            "Room Type" => [""],
            "Base Rate"      => ["Tarifa de hotel", "Tarifa"],
            "Frequent Guest" => ["Usuario Corporativo"],

            // Car
            "Confirmation Number" => ["Número de", "Número de confirmación"],
            "Location"            => "Localidad",
            "Pick Up Date"        => "Recogida",
            "Drop Off Date"       => "Devolución",
            "Car Size"            => "Tipo de coche",
            "Category"            => "Categoría",
            "Agency"              => "Compania de alquiler",

            // Rail
            //            "Rail Booking Ref" => "",
            //            "Voiture" => "",
            //            "RailProvider" => "",
            //            "Train" => "",
        ],
        'fi' => [
            //            "Booking Reference" => [""],
            //            "mainSectionEnds" => [""],
            "Travel Arrangements for" => "Matkavahvistus",
            //            "Itinerary for" => [""], // subject regexp
            //            "Airline Code" => "",
            //            "Ticket Number" => "",
            "Travel Details"     => "Matkan tiedot",
            "Flight Information" => "Lennon tiedot",
            "Hotel Information"  => ["Hotellin tiedot"],
            "Car Information"    => ["Autovarauksen tiedot"],
            //            "Informations Rail" => "",
            "Flight"                 => "Lennon numero",
            "Class"                  => "Varausluokka",
            "Estimated Time"         => ["Arvioitu lentoaika"],
            "Stops"                  => ["Välilaskut"],
            "Non-stop"               => "Non-stop", // regexp
            "Aircraft"               => ["Konetyyppi"],
            "Airline"                => "Lentoyhtiö",
            "Airline Record Locator" => ["Lentoyhtiön"],
            //            "Airline Record Locators" => "",
            //            "Operated By" => "",
            "Date"               => "Päivämäärä",
            "Origin"             => "Lähtökaupunki",
            "Departure Terminal" => "Lähtöterminaali",
            "Departing"          => "Lähtöaika",
            "Destination"        => "Kohde",
            "Arrival Terminal"   => "Saapumisterminaali",
            "Arriving"           => "Saapumisaika",
            "Meal"               => ["Ateria(t)", "Comida"],
            //            "Distance" => "",
            "Seat" => ["Paikkanumero"],

            // Hotel
            "Reference Number"    => ["Vahvistusnumero"],
            "Cancellation Policy" => "Cancellation Policy",
            "Hotel"               => "Hotelli",
            "Address"             => ["Osoite"],
            "Telephone"           => ["Puhelinnumero"],
            "Fax"                 => ["Fax numero"],
            "Check In Date"       => "Tulopäivä",
            "Check Out Date"      => "Lähtöpäivä",
            "Rooms"               => "Huoneet",
            "RoomsReg"            => "#(\d+)\s+(?:Huoneet|huone)#i",
            "Room Type"           => ["Huonetyyppi"],
            "Base Rate"           => ["Hinta"],
            //            "Frequent Guest" => [""],

            // Car
            "Confirmation Number" => "Vahvistusnumero",
            //            "Location" => "",
            "Pick Up Date"  => "Nouto",
            "Drop Off Date" => "Palautus",
            //            "Car Size" => "",
            //            "Category" => "Autoluokka",
            "Agency" => "Autovuokraamo",

            // Rail
            //            "Rail Booking Ref" => "",
            //            "Voiture" => "",
            //            "RailProvider" => "",
            //            "Train" => "",
        ],
    ];

    private $emailText = '';
    private $subject = '';

    private $lang = 'en';
    private $commonProperty = [];

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     * @throws \Exception
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $detect = false;

        $this->subject = $parser->getSubject();

        $type = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $this->emailText = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detectsBody as $dBody) {
                if (stripos($this->emailText, $dBody) !== false) {
                    $detect = true;
                    $this->assignLang();
                    $type = 'Pdf';
                    $this->parseEmail($email);

                    continue 2;
                }
            }
        }

        if ($detect === false || empty($email->getItineraries())) {
            $this->emailText = $parser->getPlainBody();

            foreach ($this->detectsBody as $dBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$dBody}')]")->length > 0
                    || stripos($this->emailText, $dBody) !== false) {
                    $this->assignLang();
                    $type = 'Html';
                    $this->parseEmail($email);

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();
        $foundCompany = false;

        if (stripos($body, $this->detectCompany) !== false
            || $this->http->XPath->query("//*[contains(normalize-space(.),'{$this->detectCompany}')]")->length > 0) {
            foreach ($this->detectsBody as $dBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$dBody}')]")->length > 0
                    || stripos($body, $dBody) !== false) {
                    return true;
                }
            }
            $foundCompany = true;
        }

        $pdfs = $parser->searchAttachmentByName('.*\.pdf');
        $body = '';

        foreach ($pdfs as $pdf) {
            $body .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        if ($foundCompany === false && stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectsBody as $dBody) {
            if (stripos($body, $dBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($headers['from'], $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 2; //pdf + html
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     * @throws \Exception
     */
    private function parseEmail(Email $email): Email
    {
        $text = $this->emailText;

        // Travel Agency
        $email->obtainTravelAgency();

        if ($otaConf = $this->re("#\n\s*" . $this->preg_implode($this->t("Booking Reference")) . "[ ]*([A-Z\d]{5,15})\b#", $text)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $text = preg_replace("#\n([ ]+TM\n)?\s+ONLINE \| OFFLINE \| ALL AROUND THE WORLD(.*\n){1,5}?\s*Page \d+ of \d+\s*\n#", "\n", $text);
        $text = preg_replace("#^>+ #m", "", $text);

        foreach ((array) $this->t("mainSectionEnds") as $value) {
            $pos = strpos($text, $value . "\n");

            if (!empty($pos)) {
                $dopInfo = substr($text, $pos);
                $text = substr($text, 0, $pos);
            }
        }

        if (preg_match_all("#^\s*(.{5,}?)(?:[ ]+(?:MR|MS|MRS|MISS))?[ ]+[A-Z]*Ticket\s*([A-Z\d]{2})/ETKT\s+([\d ]{7,})(?:[ ]+.*)?\s*$#um", $text, $m)) {
            // MAKARA/TIBOR MR TicketOK/ETKT 064 3119149217
            $this->commonProperty['travellers'] = $m[1];

            foreach ($m[2] as $key => $value) {
                $this->commonProperty['tickets'][$m[2][$key]][] = $m[3][$key];
            }
        } elseif (preg_match("#" . $this->preg_implode($this->t("Travel Arrangements for")) . "\s+([A-Z\- /]{5,}(?:\s*\n\s*[A-Z\- /]+)*)\s*\n#u", $text, $m)) {
            $this->commonProperty['travellers'] = preg_replace("#[ ]+(MR|MS|MRS|MISS)\s*$#", '', array_map('trim', explode("\n", $m[1])));
        } elseif (preg_match("#(?:" . implode('|', (array) $this->t("Itinerary for")) . ") ([A-Z\- ]+?/[A-Z\- ]+?)(?: (?:MR|MS|MRS|MISS))?(?: [A-Z]{3}) \d{1,2}[A-Z]{3}\b#ui", $this->subject, $m)) {
            $this->commonProperty['travellers'][] = $m[1];
        }

        if (empty($this->commonProperty['tickets'])) {
            if (preg_match_all("#\n\s*" . $this->preg_implode($this->t("Airline Code")) . "\s*(\d{3})(?:\s+.*)?\n\s*" . $this->preg_implode($this->t("Ticket Number")) . "\s*(\d{10})\s+#u", $text, $m)) {
                foreach ($m[1] as $key => $value) {
                    $this->commonProperty['tickets'][0][] = $m[1][$key] . $m[2][$key];
                }
            }
        }

        if (empty($this->commonProperty['tickets'])) {
            if (preg_match_all("#[ ]{3,}(?:Ticket|Billets) ([A-Z\d][A-Z]|[A-Z][A-Z\d])(?:/ETKT)? (\d{13}|\d{3}[ ]\d{10}(-\d{2})?)(?:-\d{2}[A-Z]{3})?\s*\n#u", $text, $m)) {
                foreach ($m[1] as $key => $value) {
                    $this->commonProperty['tickets'][0] = $m[2];
                }
            }
        }

        if (empty($this->commonProperty['tickets'])) {
            if (preg_match_all("#Ticket Number\s+(\d{10}(?:[/-]\d{2})?)\s+#u", $text, $m)) {
                $this->commonProperty['tickets'][0] = $m[1];
            }
        }

        if (empty($this->commonProperty['tickets'])) {
            if (preg_match_all("#" . $this->preg_implode($this->t("Ticket Number")) . "[ ]+([\d ]{10,}(?:[/-]\d{2})?)\n#u", $text, $m)) {
                $this->commonProperty['tickets'][0] = $m[1];
            }
        }

        $airs = [];
        $hotels = [];
        $cars = [];
        $rails = [];
        $ferry = [];
        $transfer = [];

        $text = preg_replace("/(Ticket AZ.+)\n+(Travel Details\s*.+)/", "$1", $text);
        $date = $this->re("#\n\s*" . $this->preg_implode($this->t("Travel Details")) . "\s+(.+)#", $text);
        $variants = array_merge((array) $this->t('Flight Information'), (array) $this->t('Hotel Information'), (array) $this->t('Car Information'), (array) $this->t('Informations Rail'), (array) $this->t('Sea Information'), (array) $this->t('Ground Transportation Information'));
        $segments = $this->split('#\n([ ]*(?:' . $this->preg_implode($variants) . '))#', $text, false);

        if (count($segments) == 0) {
            $segments = $this->split('#\n*([ ]*(?:' . $this->preg_implode($variants) . '))#i', $text, false);
        }

        foreach ($segments as $i => $segment) {
            if (!empty($nextDate)) {
                $date = $nextDate;
                unset($nextDate);
            }

            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Travel Details")) . "\s+(.+)#", $segment, $m)) {
                $nextDate = $m[1];
            }

            if ($this->striposAll($segment, $this->t('Hotel Information')) !== false) {
                $hotels[] = ['date' => $date, 'text' => $segment];

                continue;
            }

            if ($this->striposAll($segment, $this->t('Informations Rail')) !== false) {
                $rails[] = ['date' => $date, 'text' => $segment];

                continue;
            }

            if ($this->striposAll($segment, $this->t('Car Information')) !== false) {
                $cars[] = ['date' => $date, 'text' => $segment];

                continue;
            }

            if ($this->striposAll($segment, $this->t('Sea Information')) !== false) {
                $ferry[] = ['date' => $date, 'text' => $segment];

                continue;
            }

            if ($this->striposAll($segment, $this->t('Ground Transportation Information')) !== false) {
                $transfer[] = ['date' => $date, 'text' => $segment];

                continue;
            }

            if ($this->striposAll($segment, $this->t('Flight Information')) !== false) {
                if (!empty($this->commonProperty['tickets'])) {
                    if (preg_match("#" . $this->preg_implode($this->t("Flight")) . "(?:/" . $this->preg_implode($this->t("Class")) . ")?\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]?\d{1,5}\s+#", $segment, $m)
                        && !empty($this->commonProperty['tickets'][$m[1]])) {
                        $airs[$m[1]][] = ['date' => $date, 'text' => $segment];
                    } else {
                        $airs[0][] = ['date' => $date, 'text' => $segment];
                    }
                } else {
                    $airs[0][] = ['date' => $date, 'text' => $segment];
                }

                continue;
            }

            $this->logger->debug("segments type not detect: $segment");
            $email->add()->flight();

            return $email;
        }

        if (!empty($airs)) {
            $this->parseAir($email, $airs);
        }

        if (!empty($hotels)) {
            $this->parseHotel($email, $hotels);
        }

        if (!empty($rails)) {
            $this->parseRail($email, $rails);
        }

        if (!empty($cars)) {
            $this->parseCar($email, $cars);
        }

        if (!empty($ferry)) {
            $this->parseFerry($email, $ferry);
        }

        if (!empty($transfer)) {
            $this->parseTransfer($email, $transfer);
        }

        return $email;
    }

    private function parseTransfer(Email $email, array $segments)
    {
        foreach ($segments as $seg) {
            $stext = $seg['text'];
            $date = $seg['date'];

            $tr = $email->add()->transfer();

            $confNumber = $this->re("/{$this->opt($this->t('Confirmation'))}\s*([A-Z\d\-]+)/u", $stext);

            if (!empty($confNumber)) {
                $tr->general()
                    ->confirmation($confNumber);
            } else {
                $tr->general()
                    ->noConfirmation();
            }

            if (count($this->commonProperty['travellers']) > 0) {
                $tr->general()
                    ->travellers(array_filter($this->commonProperty['travellers']));
            }

            $s = $tr->addSegment();

            /*if (preg_match("/{$this->opt($this->t('Pick Up '))}\s*(?<depName>.+)\s(?<depTime>\d{4}A?P?)\s*{$this->opt($this->t('Drop Off'))}/", $stext, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date($this->normalizeDate($date . ', ' . $m['depTime']));
            }*/

            $depName = '';

            if (preg_match("/{$this->opt($this->t('Pick Up '))}\s*(?<depName>.+)\s(?<depTime>\d{4}A?P?)\s*{$this->opt($this->t('Drop Off'))}/", $stext, $m)) {
                //Pick Up  Af23 (AirlineName, FlightNumber) it-510273169.eml
                if (preg_match("/^(?<airlineN>(?:[A-z][A-z\d]|[A-z\d][A-z]))\s*(?<flightN>\d{1,4})$/iu", $m['depName'], $match)) {
                    if (preg_match("/{$this->opt($this->t('Flight'))}\s+{$match['airlineN']}\s*{$match['flightN']}\s*(?:.+\n){1,3}{$this->opt($this->t('Destination'))}\s+(?<address>.+\,\s+.+)\b[ ]{20,}/iu", $this->emailText, $match)) {
                        $depName = preg_replace("/\s+/", " ", $match['address']);
                    }
                }

                if (preg_match("/{$this->opt($this->t('Hotel'))}\s*{$this->opt($m['depName'])}.*\n+{$this->opt($this->t('Address'))}\s*(?<address>.+)\b[ ]{20}/iu", $this->emailText, $match)) {
                    $depName = $match['address'];
                }

                if (empty($depName)) {
                    $depName = $m['depName'];
                }

                $s->departure()
                    ->name($depName)
                    ->date($this->normalizeDate($date . ', ' . $m['depTime']));
            }

            $arrName = $this->re("/{$this->opt($this->t('Drop Off'))}\s*(.+)/", $stext);

            if (preg_match("/{$this->opt($this->t('Hotel'))}\s*{$this->opt($arrName)}.*\n+{$this->opt($this->t('Address'))}\s*(?<address>.+)\b[ ]{20}/iu", $this->emailText, $match)) {
                $arrName = $match['address'];
            }

            $s->arrival()
                ->noDate()
                ->name($arrName);

            $s->setCarType($this->re("/{$this->opt($this->t('Vehicle Type'))}\s*(.+)/", $stext));
        }
    }

    private function parseFerry(Email $email, array $segments)
    {
        foreach ($segments as $seg) {
            $stext = $seg['text'];

            $fr = $email->add()->ferry();

            $confNumber = $this->re("/{$this->opt($this->t('Confirmation Number'))}\s*(\d+)/u", $stext);

            if (!empty($confNumber)) {
                $fr->general()
                    ->confirmation($confNumber);
            } else {
                $fr->general()
                    ->noConfirmation();
            }

            $s = $fr->addSegment();

            $s->departure()
                ->name($this->re("/{$this->opt($this->t('Origin'))}\s*(.+)/", $stext));

            $s->arrival()
                ->name($this->re("/{$this->opt($this->t('Destination'))}\s*(.+)/", $stext));

            if (preg_match("/{$this->opt($this->t('Provider'))}\s*(.+)\s*({$this->opt($this->t('Confirmed'))})/", $stext, $m)) {
                $s->extra()
                    ->carrier($m[1]);
                $s->setStatus($m[2]);
            } else {
                $carrier = $this->re("/{$this->opt($this->t('Provider'))}\s*(.+)/", $stext);

                if (!empty($carrier)) {
                    $s->extra()
                        ->carrier($carrier);
                }
            }

            $s->extra()
                ->vessel($this->re("/{$this->opt($this->t('Ship'))}\s*(.+)/", $stext));

            $s->booked()
                ->accommodation($this->re("/{$this->opt($this->t('Space Booked'))}\s*(.+)/", $stext));

            $date = $this->http->FindSingleNode("//text()[normalize-space()='Sea Information']/following::text()[normalize-space()='" . $fr->getConfirmationNumbers()[0][0] . "'][1]/preceding::text()[normalize-space()='Sea Information'][1]/preceding::text()[normalize-space()][1]");

            if (preg_match("/{$this->opt($this->t('Departing'))}\s*(\d{2})(\d{2})/", $stext, $m)) {
                $s->departure()
                    ->date(strtotime($date . ', ' . $m[1] . ':' . $m[2]));
            }

            if (preg_match("/{$this->opt($this->t('Arriving'))}\s*(\d{2})(\d{2})\s*\/\s*(\d+)(\w+)/", $stext, $m)) {
                $s->arrival()
                    ->date(strtotime($m[3] . ' ' . $m[4] . ' ' . date('Y', strtotime($date)) . ', ' . $m[1] . ':' . $m[2]));
            } elseif (preg_match("/{$this->opt($this->t('Arriving'))}\s*(\d{2})(\d{2})/", $stext, $m)) {
                $s->arrival()
                    ->date(strtotime($date . ', ' . $m[1] . ':' . $m[2]));
            }
        }
    }

    /**
     * @param string $text
     * @param int $i
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     * @throws \Exception
     */
    private function parseAir(Email $email, array $segments): Email
    {
        foreach ($segments as $al => $segs) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->noConfirmation();

            $accounts = $this->http->FindNodes("//text()[normalize-space()='Loyalty Program']/following::text()[normalize-space()][1]", null, "/^[A-Z]*\s*([A-Z\d]*)$/");

            if (count($accounts) > 0) {
                $f->setAccountNumbers(array_unique(array_filter($accounts)), false);
            }

            if (!empty($this->commonProperty['travellers'])) {
                if (isset($this->commonProperty['tickets'][$al]) && count($this->commonProperty['tickets'][$al]) !== count($this->commonProperty['travellers'])) {
                    $f->general()->travellers(array_unique(array_filter($this->commonProperty['travellers'])));
                } else {
                    $f->general()->travellers(array_filter($this->commonProperty['travellers']));
                }
            }

            // Issued
            if (!empty($this->commonProperty['tickets'][$al])) {
                $f->issued()->tickets($this->commonProperty['tickets'][$al], false);
            }

            $tickets = [];

            foreach ($segs as $seg) {
                $date = $seg['date'];
                $stext = $seg['text'];

                $stext = preg_replace('/\n +Page \d+ of \d+\n/', "\n", $stext);

//                $this->logger->debug('$date = '."\n".print_r( $date,true));
//                $this->logger->debug('$stext = '."\n".print_r( $stext,true));

                if (preg_match_all("#^(.{30,}[ ]{3,})" . $this->preg_implode(array_merge((array) $this->t('Estimated Time'), (array) $this->t('Stops'), (array) $this->t('Aircraft'))) . "#m", $stext, $m)) {
                    $pos = min(array_map(function ($v) {return strlen($v); }, $m[1]));
                    $table = $this->SplitCols($stext, [0, $pos]);
                    $stext = implode("\n", $table);
                }

                $s = $f->addSegment();

                $splitFlights = $transitName = null;

                if (preg_match("#" . $this->preg_implode($this->t("Stops")) . "\s*(\d)\s*\n#i", $stext, $m)) {
                    if ($m[1] == 1) {
                        $splitFlights = true;
                    } else {
                        $s->extra()->stops($m[1]);
                    }
                } elseif (preg_match("#" . $this->preg_implode($this->t("Stops")) . "\s*" . $this->t("Non-stop") . "#i", $stext)) {
                    $s->extra()->stops(0);
                } elseif (preg_match("#" . $this->preg_implode($this->t("Stops")) . "\s*" . $this->preg_implode($this->t("One stop")) . "#i", $stext)) {
                    $splitFlights = true;
                }

                // Airline
                if (preg_match("#" . $this->preg_implode($this->t("Flight")) . "/" . $this->preg_implode($this->t("Class")) . "\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d{1,5})\s+(.+)#", $stext, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);

                    $cabin = $m[3];
                } elseif (preg_match("#" . $this->preg_implode($this->t("Flight")) . "\s+([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d{1,5})\s+#u", $stext, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                } elseif (preg_match("#" . $this->preg_implode($this->t("Flight")) . "\s+(\d{1,5})\s+#u", $stext, $m)) {
                    $s->airline()
                        ->number($m[1]);

                    if (preg_match("#" . $this->preg_implode($this->t("Airline")) . "(?:\s*\n\s*|[ ]{2,})(.+)\s+#u", $stext, $m)) {
                        $s->airline()
                            ->name($m[1]);
                    }
                } elseif (preg_match("#{$this->preg_implode('Flight/Class')}\s*[*]([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d{1,4})#", $stext, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                if ($al !== $s->getAirlineName() && isset($this->commonProperty['tickets'][$s->getAirlineName()])) {
                    $tickets = array_merge($tickets, $this->commonProperty['tickets'][$s->getAirlineName()]);
                }

                if (preg_match("#" . $this->preg_implode($this->t('Airline Record Locator')) . " *\*?([A-Z\d]{5,7})\b#", $stext, $m)) {
                    $s->airline()->confirmation($m[1]);
                } elseif (!empty($s->getAirlineName()) && preg_match("#\n\s*" . $this->preg_implode($this->t('Airline Record Locators')) . "\n(?:.*\n+){0,8}\s*([A-Z\d]{5,6})[ ]{2,}" . $s->getAirlineName() . "\s*\n#", $this->emailText, $m)) {
                    $s->airline()->confirmation($m[1]);
                }

                if (isset($splitFlights) && !empty($transitName = $this->re("#{$this->preg_implode($this->t('Stop/Transit'))}\s*(.+)#i",
                        $stext))
                ) {
                    $s2 = $f->addSegment();

                    if ($s->getAirlineName()) {
                        $s2->airline()->name($s->getAirlineName());
                    } else {
                        $s2->airline()->noName();
                    }

                    if ($s->getFlightNumber()) {
                        $s2->airline()->number($s->getFlightNumber());
                    } else {
                        $s2->airline()->noNumber();
                    }
                }

                if (preg_match("#" . $this->preg_implode($this->t("Operated By")) . "\s*(.+?) ([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d+)\s+#", $stext, $m)) {
                    $s->airline()
                        ->operator($m[1])
                        ->carrierName($m[2])
                        ->carrierNumber($m[3])
                    ;
                } elseif (preg_match("#" . $this->preg_implode($this->t("Operated By")) . "\s*(.+)#iu", $stext, $m)) {
                    if (stripos($m[1], $this->t('For')) !== false) {
                        $m[1] = $this->re("/^(.+){$this->opt($this->t('For'))}/u", trim($m[1], '/'));
                    }

                    $s->airline()
                        ->operator($m[1]);
                }

                if (preg_match("#" . $this->opt($this->t("Flight Information")) . "[\s]+" . $this->preg_implode($this->t("Date")) . "\s+(\d+\s+\w+\.?\s+\d{4})#u", $stext, $m)) {
                    $date = $m[1];
                } elseif (preg_match("#" . $this->preg_implode($this->t("Date")) . "\s*(\d+\s+\w+\.?\s+\d{4})#u", $stext, $m)) {
                    $date = $m[1];
                }
                // Departure
                $depTerminal = $this->re("#" . $this->preg_implode($this->t('Departure Terminal')) . "(?:\s*\n\s*|[ ]{2}|\s*Terminal)(.+)#u", $stext);

                if (stripos($depTerminal, 'Number of Stop') !== false) {
                    $depTerminal = '';
                }

                $depAirport = $this->re("/{$this->preg_implode($this->t('Origin'))}\s*.*\,\s*(.+)/", $stext);
                $depCode = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Trip Details'))}]/following::text()[{$this->contains($depAirport)}][1]", null, true, "/\(([A-Z]{3})\)/");

                if (!empty($depCode)) {
                    $s->departure()
                        ->code($depCode);
                } else {
                    $s->departure()
                        ->noCode();
                }

                $s->departure()
                    ->name(preg_replace("#\s+#", ' ', $this->re("#" . $this->preg_implode($this->t('Origin')) . "\s*(.+)#u", $stext)))
                    ->terminal(trim(preg_replace("#\s*Terminal\s*#i", ' ', $depTerminal)), true, true)
                ;

                $time = $this->re("#" . $this->preg_implode($this->t('Departing')) . "\s*(.+)#u", $stext);

                if (empty($date)) {
                    $flightInfo = $this->re("/(Flight\s*Information\n(?:.+\n*){8,10}\s*Flight\n(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{2,4})/", $this->emailText);
                    $date = $this->re("/^(.+)\n{$flightInfo}/mu", $this->emailText);
                }

                if (!empty($date) & !empty($time)) {
                    $s->departure()
                        ->date($this->normalizeDate($date . ' ' . $time))
                        ->strict();
                }

                if (isset($s2) && !empty($transitName)) {
                    $s2->departure()
                        ->noDate()
                        ->name($transitName)
                        ->noCode();
                    $s->arrival()
                        ->noDate()
                        ->name($transitName)
                        ->noCode();
                    // Arrival
                    $s2->arrival()
                        ->noCode()
                        ->name(preg_replace("#\s+#", ' ',
                            $this->re("#" . $this->preg_implode($this->t('Destination')) . "\s+(.+)#u", $stext)))
                        ->terminal(trim(preg_replace("#\s*Terminal\s*#i", ' ',
                            $this->re("#" . $this->preg_implode($this->t('Arrival Terminal')) . "(?:\s*\n\s*|[ ]{2}|\s*Terminal)(.+)#u",
                                $stext))), true, true);
                    $time = $this->re("#" . $this->preg_implode($this->t('Arriving')) . "\s+(.+)#u", $stext);

                    if (!empty($date) & !empty($time)) {
                        $s2->arrival()->date($this->normalizeDate($date . ' ' . $time))->strict();
                    }
                } else {
                    $arrTerminal = $this->re("#" . $this->preg_implode($this->t('Arrival Terminal')) . "(?:\s*\n\s*|[ ]{2}|\s*Terminal)(.+)#u",
                        $stext);

                    if (stripos($arrTerminal, 'Class') !== false) {
                        $arrTerminal = '';
                    }

                    // Arrival

                    $arrAirport = $this->re("/{$this->preg_implode($this->t('Destination'))}\s*.*\,\s*(.+)/", $stext);
                    $arrCode = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Trip Details'))}]/following::text()[{$this->contains($arrAirport)}][1]", null, true, "/\(([A-Z]{3})\)/");

                    if (!empty($arrCode)) {
                        $s->arrival()
                            ->code($arrCode);
                    } else {
                        $s->arrival()
                            ->noCode();
                    }

                    $s->arrival()
                        ->noCode()
                        ->name(preg_replace("#\s+#", ' ',
                            $this->re("#" . $this->preg_implode($this->t('Destination')) . "\s*(.+)#u", $stext)))
                        ->terminal(trim(preg_replace("#\s*Terminal\s*#i", ' ',
                            $arrTerminal)), true, true);
                    $time = $this->re("#" . $this->preg_implode($this->t('Arriving')) . "\s*(.+)#u", $stext);

                    if (preg_match("/^([\d\:]+)\s*\/\s*(\d+\s\D+\s+\d{4})$/", $time, $m)) {
                        $date = $m[2];
                        $time = $m[1];
                    }

                    if (!empty($date) & !empty($time)) {
                        $s->arrival()->date($this->normalizeDate($date . ' ' . $time))->strict();
                    }
                }

                // Extra
                if (preg_match("#" . $this->preg_implode($this->t("Meal")) . "(?:\s*\n\s*|[ ]{2,})(.+(?:\n\w+\s+\n)?)#u", $stext, $m)) {
                    $s->extra()
                        ->meal(str_replace("\n", " ", $m[1]));
                }

                if (preg_match("#" . $this->preg_implode($this->t("Estimated Time")) . "\s+(.*)#i", $stext, $m) && !isset($s2)) {
                    $s->extra()->duration($m[1]);
                }

                if (preg_match("#" . $this->preg_implode($this->t("Aircraft")) . "\s+(.*)#", $stext, $m)) {
                    $s->extra()->aircraft($m[1]);
                }

                if (preg_match("#" . $this->preg_implode($this->t("Distance")) . "\s+(.*)#", $stext, $m) && !isset($s2)) {
                    $s->extra()->miles($m[1]);
                }

                if (empty($cabin)) {
                    $cabin = $this->re("#" . $this->preg_implode($this->t('Class')) . "\s+(.+)#", $stext);
                }

                if (!empty($cabin)) {
                    if (preg_match('/([A-Z])\s+(\w+)/', $cabin, $m)) {
                        $s->extra()
                            ->bookingCode($m[1])
                            ->cabin($m[2]);
                    } else {
                        $s->extra()->cabin($cabin);
                    }
                    unset($cabin);
                }

                if (preg_match("#" . $this->preg_implode($this->t("Seat")) . "\s*(\d{1,3}[A-Z](?:[ ,]+\d{1,3}[A-Z])*)\s+#", $stext, $m)) {
                    $s->extra()->seats(array_map('trim', preg_split("#[ ,]+#", $m[1])));
                }

                if (preg_match("#\n\n([A-Z].+)\n" . $this->preg_implode($this->t('Estimated Time')) . "#u", $stext, $m)) {
                    $s->extra()->status($m[1]);
                }
            }

            if (count($tickets) > 0) {
                $f->setTicketNumbers(array_unique($tickets), false);
            }
        }

        return $email;
    }

    /**
     * @param string $text
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseHotel(Email $email, array $segments): Email
    {
        foreach ($segments as $seg) {
            $date = $seg['date'];
            $stext = $seg['text'];

            $stext0 = $this->re("#(^.+?)\n{$this->preg_implode($this->t('Base Rate'))}#su", $stext);
            $stext1 = $this->re("#^.+?\n({$this->preg_implode($this->t('Base Rate'))}.+)#su", $stext);

            if (empty($stext0)) {
                $stext0 = $stext;
            }

            if (preg_match_all("#^(.{30,}[ ]{3,})" . $this->preg_implode(array_merge((array) $this->t('Telephone'), (array) $this->t('Fax'))) . "#mu", $stext0, $m)) {
                $pos = min(array_map(function ($v) {return strlen($v); }, $m[1]));
                $table = $this->SplitCols($stext0, [0, $pos]);

                $stext0 = implode("\n", $table);
            }
            $stext = $stext0 . "\n" . $stext1;

            $h = $email->add()->hotel();

            // General
            $conf = null;

            if (preg_match("#(" . $this->preg_implode($this->t("Reference Number")) . ")\s+([\w\-]+)\s+#u", $stext, $m)) {
                $conf = [$m[2], $m[1]];
            }

            if (empty($conf)) {
                if (preg_match("#\n *" . str_replace(' ', '(?: {2,}+([\w\-]+)(?: {5,}.*)?\n| )', $this->preg_implode($this->t("Reference Number"))) . "(?: {2,}+([\w\-]+)(?: {5,}.*)?)?\n#u", $stext, $m)) {
                    $all = trim($m[0]);
                    unset($m[0]);
                    $m = array_values(array_filter(array_map('trim', $m)));

                    if (count($m) == 1) {
                        $conf = [$m[0], preg_replace('/ {2,}+([\w\-]+)(?: {5,}.*)?\n\s*/', ' ', trim($all))];
                    }
                }
            }

            if (!empty($conf)) {
                $h->general()
                    ->confirmation($conf[0], $conf[1]);
            } else {
                $h->general()
                    ->noConfirmation();
            }

            if (!empty($this->commonProperty['travellers'])) {
                $h->general()->travellers(array_filter($this->commonProperty['travellers']));
            }

            if (preg_match("#" . $this->preg_implode($this->t("Cancellation Policy")) . "\s+(.+)#u", $seg['text'], $m)) {
                $h->general()->cancellation($m[1]);
            }

            // Program
            if (preg_match("#" . $this->preg_implode($this->t("Frequent Guest")) . "\s+(.+)#u", $stext, $m)) {
                $h->program()->account($m[1], false);
            }

            // Hotel
            if (preg_match("#\n\s*" . $this->preg_implode($this->t("Hotel")) . "\s+((?:.+\n+){1,3})" . $this->preg_implode($this->t("Address")) . "\s+#", $stext, $m)) {
                $hotelName = preg_replace("#\s+#", ' ', trim($m[1]));
            } elseif (preg_match("#\n\s*" . $this->preg_implode($this->t("Hotel")) . "\s+((?:.+\n+){1,3})" . $this->preg_implode($this->t("Reference Number")) . "\s+#", $stext, $m)) {
                $hotelName = preg_replace("#\s+#", ' ', trim($m[1]));
            }

            if (!empty($hotelName)) {
                $h->hotel()->name($hotelName);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Address")) . "\s+([\s\S]+?)\s+(?:" . $this->preg_implode($this->t("Telephone")) . "|" . $this->preg_implode($this->t("Reference Number")) . "|" . $this->preg_implode($this->t("Check In Date")) . ")#", $stext, $m)) {
                $h->hotel()->address(preg_replace("#\s+#", ' ', $m[1]));
            } elseif (empty($this->re("#" . $this->preg_implode($this->t("Address")) . "#iu", $stext))) {
                $h->hotel()->noAddress();
            }

            if (preg_match("#" . $this->preg_implode($this->t("Telephone")) . "\s+(.+)#", $stext, $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Fax")) . "(?:\s*\n\s*|[ ]{2,})(.+)#", $stext, $m)) {
                $h->hotel()->fax($m[1]);
            }

            // Booked
            if (preg_match("#" . $this->preg_implode($this->t("Check In Date")) . "\s+(.+)#iu", $stext, $m)) {
                $h->booked()->checkIn($this->normalizeDate($m[1]));
            }

            if (preg_match("#" . $this->preg_implode($this->t("Check Out Date")) . "\s+(.+)#iu", $stext, $m)) {
                $h->booked()->checkOut($this->normalizeDate($m[1]));
            }

            if (preg_match("#{$this->t('Rooms')}\s+(.+)#", $stext, $mm) && preg_match($this->t("RoomsReg"), $mm[1], $m)) {
                $h->booked()->rooms($m[1]);
            }
            $this->detectDeadLine($h);

            // Rooms
            $r = $h->addRoom();

            if (preg_match("#" . $this->preg_implode($this->t("Room Type")) . "\s+(.+)#u", $stext, $m)) {
                $r->setType($m[1]);
            }

            if (preg_match_all("#" . $this->preg_implode($this->t("Base Rate")) . "\s{2,}(.+)#u", $stext, $m)) {
                $r->setRate(implode(", ", $m[1]));
            } elseif (preg_match_all("#" . $this->preg_implode($this->t("Base Rate")) . "\n(.+{$this->preg_implode($this->t('per night'))})#u", $stext, $m)) {
                $r->setRate(implode(", ", $m[1]));
            }
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return false;
        }

        if (preg_match("#To Avoid Being Billed Cancel By (\d+)([ap]m) ([\d/]+)$#i", $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m[3] . ', ' . $m[1] . ':00' . $m[2]));

            return true;
        }

        if (preg_match("#CANCEL ON (\d+\s+\w+\s+\d{4}) BY ([\d\:]+) LT TO AVOID A CHARGE OF#i", $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m[1] . ', ' . $m[2]));

            return true;
        }
    }

    /**
     * @param string $text
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseCar(Email $email, array $segments): Email
    {
        foreach ($segments as $seg) {
            $date = $seg['date'];
            $stext = $seg['text'];

            if (preg_match_all("#^(.{30,}[ ]{3,})" . $this->preg_implode(array_merge((array) $this->t('Car Size'), (array) $this->t('Category'))) . "#m", $stext, $m)) {
                $pos = min(array_map(function ($v) {return strlen($v); }, $m[1]));
                $table = $this->SplitCols($stext, [0, $pos]);
                $stext = implode("\n", $table);
            }

            $r = $email->add()->rental();

            // General
            if (preg_match("#" . $this->preg_implode($this->t('Confirmation Number')) . "\s+([\w\-]+)\s+#i", $stext, $m)) {
                $r->general()->confirmation($m[1]);
            }

            // Pick Up
            if (preg_match("#\n\s*" . $this->preg_implode($this->t('Location')) . "\s+(.+)#i", $stext, $m)) {
                $r->pickup()->location($m[1]);
                $r->dropoff()->location($m[1]);
            }

            if (preg_match("#\n\s*" . $this->preg_implode($this->t('Location DropOff')) . "\s+(.+)#i", $stext, $m)) {
                $r->dropoff()->location($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Pick Up Date")) . "(?:\s*\n\s*|[ ]{2,})(.+)#iu", $stext, $m)) {
                if (preg_match("/^(.+)\s{$this->opt($this->t('Telephone'))}\s*([\d\s\+\-]+)/", $m[1], $match)) {
                    $r->pickup()
                        ->date($this->normalizeDate($match[1]))
                        ->phone($match[2]);
                } else {
                    $r->pickup()
                        ->date($this->normalizeDate($m[1]));
                }
            }

            if (preg_match("#" . $this->preg_implode($this->t("Drop Off Date")) . "(?:\s*\n\s*|[ ]{2,})(.+)#iu", $stext, $m)) {
                $r->dropoff()->date($this->normalizeDate($m[1]));
            }

            // Car
            $size = $this->re("#" . $this->preg_implode($this->t("Car Size")) . "\s+(.+)#u", $stext);
            $category = $this->re("#" . $this->preg_implode($this->t("Category")) . "\s+(.+)#u", $stext);

            if (!empty($size) || !empty($category)) {
                $r->car()->type(implode(", ", array_filter([$size, $category])));
            }

            // Extra
            if (preg_match("#" . $this->preg_implode($this->t('Agency')) . "\s+(.+)#i", $stext, $m)) {
                if (preg_match("/^(\D+)\s+({$this->opt($this->t('Confirmed'))})$/", $m[1], $match)) {
                    $r->extra()
                        ->company($match[1]);
                    $r->general()
                        ->status($match[2]);
                } else {
                    $r->extra()->company($m[1]);
                }
            }

            if (preg_match("/{$this->opt($this->t('CarTotal'))}\s*(?<currency>(?:[A-Z]{3}|\S))(?<total>[\d\,\.]+)$/mu", $stext, $m)
                || preg_match("/{$this->opt($this->t('CarTotal'))}\s+(?<total>[\d\,\.]+)\s+(?<currency>(?:[A-Z]{3}|\S))\s*$/um", $stext, $m)) {
                $currency = $this->normalizeCurrency($m['currency']);
                $r->price()
                    ->currency($currency)
                    ->total(PriceHelper::parse($m['total'], $currency));
            }
        }

        return $email;
    }

    /**
     * @param string $text
     * @param int $i
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseRail(Email $email, array $segments): Email
    {
        foreach ($segments as $seg) {
            $date = $seg['date'];

            $stext = $seg['text'];

            if (empty($date)) {
                $date = $this->re("/{$this->preg_implode($this->t('Date'))}\n(\w+\s*\w+\s*\d{4})\n/", $stext);
            }

            if (preg_match_all("#^(.{30,}[ ]{3,})" . $this->preg_implode(array_merge((array) $this->t('Seat'), (array) $this->t('Voiture'))) . "#m", $stext, $m)) {
                $pos = min(array_map(function ($v) {return strlen($v); }, $m[1]));
                $table = $this->SplitCols($stext, [0, $pos]);
                $stext = implode("\n", $table);
            }

            $t = $email->add()->train();

            // General
            if (preg_match("#" . $this->preg_implode($this->t("Rail Booking Ref")) . "\s+([\w\-]+)\s+#u", $stext, $m)) {
                $t->general()
                    ->confirmation($m[1]);
            }

            if (!empty($this->commonProperty['travellers'])) {
                $t->general()->travellers(array_filter($this->commonProperty['travellers']));
            }

            $s = $t->addSegment();

            // Departure
            $s->departure()
                ->name(preg_replace("#\s+#", ' ', $this->re("#" . $this->preg_implode($this->t('Origin')) . "\s+(.+)#u", $stext)))
            ;
            $time = $this->re("#" . $this->preg_implode($this->t('Departing')) . "\s+(.+)#u", $stext);

            if (!empty($date) & !empty($time)) {
                $s->departure()->date($this->normalizeDate($date . ' ' . $time));
            }

            // Arrival
            $s->arrival()
                ->name(preg_replace("#\s+#", ' ', $this->re("#" . $this->preg_implode($this->t('Destination')) . "\s+(.+)#u", $stext)))
            ;
            $time = $this->re("#" . $this->preg_implode($this->t('Arriving')) . "\s+(.+?)(?:/[^\d\W]+\.?)?\n#u", $stext);

            if (!empty($date) & !empty($time)) {
                $s->arrival()->date($this->normalizeDate($date . ' ' . $time));
            }

            // Extra
            if (preg_match("#" . $this->preg_implode($this->t("RailProvider")) . "\s+(.+)#u", $stext, $m)) {
                $s->extra()
                    ->service($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Train")) . "\s+(.+)#u", $stext, $m)) {
                $s->extra()
                    ->number($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("RailCabin")) . "\s+(.+)#u", $stext, $m)) {
                $s->extra()
                    ->cabin($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Voiture")) . "\s+(.+)#u", $stext, $m)) {
                $s->extra()
                    ->car($m[1]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Seat")) . "\s+([A-Z\d]{1,5})\s+#u", $stext, $m)) {
                $s->extra()
                    ->seat($m[1]);
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function striposAll(string $text, $needle): bool
    {
        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $translations) {
            foreach (['Flight Information', 'Car Information', 'Informations Rail', 'Reference Number', 'Departing'] as $info) {
                if (isset($translations[$info])) {
                    if (is_array($translations[$info])) {
                        foreach ($translations[$info] as $value) {
                            if ($value !== $info && $this->detectPhrase($value) === true) {
                                $this->lang = $lang;

                                return true;
                            }
                        }
                    } else {
                        if ($translations[$info] !== $info && $this->detectPhrase($translations[$info]) === true) {
                            $this->lang = $lang;

                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function detectPhrase($phrase)
    {
        if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$phrase}')]")->length > 0
            || stripos($this->emailText, $phrase) !== false) {
            return true;
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text, $returnEmpty = true)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            if ($returnEmpty === true) {
                $ret[] = reset($r);

                return $ret;
            } else {
                return [];
            }
        }

        return $ret;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), '{$s}')"; }, $field));
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s*(\d+)\s*(\w+)\s*(\d{2})\,\s*(\d{2})(\d{2}A?P?)$#iu", //Friday 25 Mar 22, 0630P
            "#^(\d+)\s*(\w+)\.?\s*(\d{4})\s*([\d\:]+)$#u", //26 Jun. 2022 05:25
            "#^\s*(?:[^\d\s]+\s+)?(\d{1,2})[ ]+([^\d\s]+)[ ]+(\d{4})\s*$#iu", // Son 21 Jan 2018

            "#^\s*(?:[^\d\s]+\s+)?(\d{1,2})[ ]+([^\d\s]+)[ ]+(\d{4})[ ]+(\d{2})(\d{2}(?:\s*[ap]m)?)\s*$#iu", // 10 Mar 2019 1310
            "#^\s*(?:[^\d\s]+\s+)?([^\d\s]+)[ ]+(\d{1,2})[, ]+(\d{4})[ ]+(\d{1,2})[:]?(\d{2}(?:\s*[ap]m)?)\s*$#iu", // Martes Marzo 19, 2019 2:20 PM
            "#^\s*(?:[^\d\s]+\s+)?\d{1,2}[ ]+[^\d\s]+[ ]+\d{2,4}[ ]+(\d{2})(\d{2}(?:\s*[ap]m)?)\s*/\s*(\d{1,2})[ ]+([^\d\s]+)[ ]+(\d{4})\s*$#iu", // 10 Mar 2019 0810 / 11 Mar 2019
            "#^\s*(\d{2})[:]?(\d{2}(?:\s*[ap]m)?)\s*/\s*(\d{1,2})[ ]+([^\d\s]+)[ ]+(\d{4})\s*$#iu", // 0810 / 11 Mar 2019

            "#^\s*(?:[^\d\s]+\s+)?(\d{1,2})[ ]+([^\d\s]+)[ ]+(\d{2})[ ]+(\d{2})[:]?(\d{2}(?:\s*[ap]m)?)\s*$#iu", // Tuesday 21 Nov 17 08:45 PM
            "#^\s*(?:[^\d\s]+\s+)?\d{1,2}[ ]+[^\d\s]+[ ]+\d{2}[ ]+(\d{2})[:]?(\d{2}(?:\s*[ap]m)?)\s*/\s*(\d{1,2})[ ]+([^\d\s]+)[ ]+(\d{4})\s*$#iu", // Tuesday 21 Nov 17 08:30 AM / 22 Nov 2017

            "#^\s*(\d{1,2}/\d{1,2}/\d{4})[ ]+at[ ]+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$#iu", // 11/26/2012 at 02:33 PM
            "#^\w+\s*\d+\s*\w+\s*(\d{4})\s*(\d{2})(\d{2})\s*\/\s*(\d+)(\w+)$#", //Wednesday 15 Sep 2021 0900 / 16SEP
            "#^\w+\s*(\d+)\s*(\w+)\.?\s*(\d{4})\s*([\d\:]+)$#u", //Jeudi 02 Sept. 2021 07:20
        ];
        $out = [
            '$1 $2 20$3, $4:$5M',
            '$1 $2 $3, $4',

            '$1 $2 $3',

            '$1 $2 $3, $4:$5',
            '$2 $1 $3, $4:$5',
            '$3 $4 $5, $1:$2',
            '$3 $4 $5, $1:$2',

            '$1 $2 20$3, $4:$5',
            '$3 $4 $5, $1:$2',

            '$1, $2',
            '$4 $5 $1, $2:$3',
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/[[:alpha:]]+/iu', $str, $m)) {
            if (($month = MonthTranslate::translate($m[0], $this->lang)) && false !== $month) {
                $str = preg_replace("/{$m[0]}/", $month, $str);
            }
        }

        return strtotime($str);
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
