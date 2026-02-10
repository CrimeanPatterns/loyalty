<?php

namespace AwardWallet\Engine\checkmytrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use Cassandra\Date;

// the same format in html: parse by amadeus/TravelDocument

// another format with the same blue strip in headers html: amadeus/It1640513, pdf: aviancataca/PDF

class PDF extends \TAccountChecker
{
    public $mailFiles = "checkmytrip/it-10050381.eml, checkmytrip/it-120101192.eml, checkmytrip/it-12287505.eml, checkmytrip/it-124667646.eml, checkmytrip/it-16480388.eml, checkmytrip/it-222745218.eml, checkmytrip/it-28958874.eml, checkmytrip/it-29623671.eml, checkmytrip/it-32631758.eml, checkmytrip/it-33535315.eml, checkmytrip/it-33535367.eml, checkmytrip/it-346847765.eml, checkmytrip/it-370514555.eml, checkmytrip/it-371594387.eml, checkmytrip/it-52408320.eml, checkmytrip/it-5993729.eml, checkmytrip/it-6766216.eml, checkmytrip/it-721294578.eml, checkmytrip/it-8601597.eml, checkmytrip/it-8748747.eml, checkmytrip/it-8749777.eml, checkmytrip/it-9760210.eml";

    public $reBody = [
        'zh'    => ['旅客', '出發'],
        'it'    => ['Traveler', 'Partenza'],
        'en'    => ['Traveler', 'Departure'],
        'en2'   => ['Traveller', 'Departure'],
        'en3'   => ['Traveler', 'Check-in'],
        'en4'   => ['Traveller', 'Check-in'],
        'en5'   => ['Traveler', 'Pick up'],
        'en6'   => ['Traveller', 'Pick up'],
        'es'    => ['Viajero', 'Salida'],
        'pt'    => ['Passageiro', 'Partida'],
        'fr'    => ['Voyageur', 'Départ'],
        'fr2'   => ['Voyageur', 'Retrait'],
        'fr3'   => ['Itinéraire', 'Départ'],
        'de'    => ['Reisender', 'Abholung'],
        'de2'   => ['Reisender', 'Abreise'],
    ];
    public $lang = '';
    public $providerCode;
    public $headers = [];
    public $allConfirmations = [];
    public $depDay;
    public $depMonth;
    public static $dict = [
        'en' => [
            //            'Booking ref:'    => '',
            'Issue date:'    => ['Issue date:', 'Issued date:'],
            'Traveler'       => ['Traveler', 'Traveller'],
            //            'Itinerary'       => '',
            //            'Agency'       => [''],

            //            'Booking status' => '',

            // FLIGHT + TRAIN, TRANSFER
            //            'Operated By' => '',
            'Airline Booking Reference(s)' => ['Airline Booking Reference(s)', 'Airline Booking Reference'],
            //            'Departure' => '', // + train
            //            'Arrival' => '', // + train
            'Duration'        => ['Duration', 'Total duration'], // + train
            //            'Distance' => '',
            //            'Class' => '', // + train
            //            'Seat'       => '', // + train
            'Equipment'       => ['Equipment', 'Aircraft'], // + train
            "Meal"            => ["Flight meal", "Meal"],
            //            'Frequent Flyer number' => '', // + train
            'E-ticket' => ['E-ticket number', 'E-ticket', 'Ticket number', 'Ticket details'], // + train
            //            'for' => '', // + train
            //            'Baggage allowance' => '', // + train
            //            'Check in completed by' => '', // + train

            // HOTEL + RENTAL
            //            "Confirmation number" => "", // + rental
            "Check-in"            => ["Check-in", "Check-In"],
            "Check-out"           => ["Check-out", "Check-Out"],
            //            "Location"            => "", // + rental
            //            "Tel"            => "", // + rental
            //            "Fax"            => "",
            //            "Name"                => "", // + rental
            //            "Rate"                => "",
            //            "per night"           => "",
            //            "for"                 => "",
            //            "night(s)"            => "",
            "Estimated total" => ["Estimated total", "Total -(May Not Incl Tax)", 'Estimated Total', 'Total Amount'], // + rental
            //            "Taxes" => '',
            //            "Room type"           => "",
            //            "Occupancy"           => "",
            //            "Cancellation policy" => "",

            // another format (it-33535315.eml)
            //            'Details' => '',
            //            'Hotel Name' => '',
            'Hotel Address' => ['Hotel Address', 'Address'],
            'Hotel Tel'     => ['Hotel Tel', 'Tel'],
            //            'Hotel Fax' => '',
            //            'Room' => '', // row with room type
            //            'Hotel Cost Per Night' => '',

            // RENTAL (+ see HOTEL)
            //            'Pick up'         => '',
            //            'Drop off'        => '',
            //            'Car Rental'      => '',
            //            'Car type'        => '',
            //            'Day'             => '', // part estimated total
            //            'Membership ID' => '',

            // TRAIN (+ see FLIGHT)
            //            "Train" => "",
            //            "Reference" => "",
            //            "Platform" => "", // to check
            //            "Coach" => "",
        ],
        'es' => [
            'Booking ref:'   => 'Localizador de',
            'Issue date:'    => 'Fecha de emisión',
            'Traveler'       => 'Viajero',
            'Ticket Number'  => 'Numero De Billete',
            'Telephone'      => 'Teléfono',
            'Agency'         => 'Agencia',
            'Operated By'    => 'Operado por',
            'Departure'      => 'Salida',
            'Arrival'        => 'Llegada',
            'Duration'       => 'Duración',
            'Booking status' => 'Estatus de la reserva',
            'Class'          => 'Clase',
            'Seat'           => 'Asiento',
            //			'Baggage allowance' => '',
            //			'Check in completed by' => '',
            'Equipment'                   => 'Equipo',
            'Frequent Flyer number'       => 'Número de viajero frecuente',
            'E-ticket'                    => 'Billete electrónico',
            'for'                         => ['para', 'por'],
            "Meal"                        => ["Comida", "Flight meal"],
            "Airline Booking Reference(s)"=> "Localizador(es) de reserva de la aerolínea",
            "Itinerary"                   => "Itinerario",
            "Total Amount"                => ["Importe Total"],
            // HOTEL
            "Confirmation number" => "Número de confirmación",
            "Check-in"            => "Entrada",
            "Check-out"           => "Salida",
            "Location"            => "Ubicación",
            "Cancellation policy" => "Condiciones de cancelación",
            "Occupancy"           => "Occupancy",
            "Room type"           => "Tipo de habitación",
            "Name"                => "Nombre",
            "Rate"                => "Tarifa",
            "Estimated total"     => ["Importe total estimado", 'Importe Total'],
            "per night"           => "por noche",
            //"for"                 => "para",
            "night(s)"            => "noche(s)",

            // TRAIN (+ see FLIGHT)
            "Train" => "Tren",
            //                        "Reference" => "",
            //                        "Platform" => "",
            //                        "Coach" => "Número de autobús",
        ],
        'pt' => [
            'Booking ref:'      => 'Ref. reserva:',
            'Issue date:'       => 'Data de emissão:',
            'Traveler'          => 'Passageiro',
            'Telephone'         => 'Telefone',
            'Agency'            => 'Agência',
            'Operated By'       => 'Operado por',
            'Departure'         => 'Partida',
            'Arrival'           => 'Chegada',
            'Duration'          => 'Duração',
            'Booking status'    => 'Estado da reserva',
            'Class'             => 'Classe',
            'Seat'              => 'Lugar',
            'Baggage allowance' => 'Bagagem incluída',
            //			'Check in completed by' => '',
            'Equipment' => 'Equipamento',
            //            'Frequent Flyer number' => 'Número de viajero frecuente',
            'E-ticket'                     => 'Bilhete eletrônico',
            'for'                          => ['para', 'por'],
            "Meal"                         => ['Flight meal'],
            "Airline Booking Reference(s)" => "Referência de reserva da",
            // HOTEL
            "Confirmation number" => "Número de confirmação",
            "Check-in"            => "Check-in",
            "Check-out"           => "Check-out",
            "Location"            => "Local",
            "Cancellation policy" => "Política de cancelamento",
            "Occupancy"           => "Occupancy",
            "Room type"           => "Tipo de quarto",
            "Name"                => "Nome",
            "Rate"                => "Tarifa",
            "Estimated total"     => "Total estimado",
            "per night"           => "para 1 noite",
        ],
        'fr' => [
            'Booking ref:'      => 'Ref. Dossier:',
            'Issue date:'       => ['Date d’émission:'],
            'Traveler'          => 'Voyageur',
            'Telephone'         => 'Téléphone',
            'Agency'            => 'Agence',
            'Operated By'       => 'Exploité par',
            'Departure'         => 'Départ',
            'Arrival'           => 'Arrivée',
            'Duration'          => 'Durée',
            'Booking status'    => 'Statut de la réservation',
            'Class'             => 'Classe',
            //            'Seat'              => 'Lugar',
            'Baggage allowance' => 'Bagages autorisés',
            //			'Check in completed by' => '',
            'Equipment'                    => ['Équipement', 'Equipement'],
            'Frequent Flyer number'        => ['Numéro de carte de fidélité', 'Nº de carte fidélité'],
            'E-ticket'                     => ['Numéro de billet', 'Numéro de billet électronique:'],
            'for'                          => ['pour'],
            //            "Meal"                         => ['Flight meal'],
            "Airline Booking Reference(s)" => "Référence dossier compagnie",
            // HOTEL
            //            "Confirmation number" => "Número de confirmación",
            "Check-in"  => "Arrivée",
            "Check-out" => "Départ",
            //            "Location" => "Ubicación",
            "Cancellation policy" => "Conditions d'annulation",
            //            "Occupancy" => "Occupancy",
            //            "Room type" => "Tipo de habitación",
            //            "Name" => "Nombre",
            //            "Rate" => "Tarifa",
            //            "Estimated total" => "Importe total estimado",
            //            "per night" => "por noche",
            'charge for the first night' => ['cancel fee per room', 'night', ' to avoid', 'one nite stay', 'Cancel on '],

            // Rental
            'Confirmation number' => ['Numéro de confirmation'],
            'Name'                => 'Nom',
            //            'Membership ID' => '',
            'Location'        => 'Emplacement',
            'Pick up'         => 'Retrait',
            'Drop off'        => 'Restitution',
            'Tel'             => 'Tél.',
            'Car Rental'      => 'Location de véhicules',
            'Car type'        => 'Type de véhicule',
            'Estimated total' => 'Total estimé',
            'Day'             => 'Jour',
        ],
        'de' => [
            'Booking ref:'      => 'Buchungsreferenz:',
            'Issue date:'       => 'Ausstellungsdatum:',
            'Traveler'          => 'Reisender',
            //            'Itinerary'       => '',
            'Agency'            => 'Reisebüro',

            //            'Booking status' => 'Status',

            // FLIGHT + TRAIN, TRANSFER
            'Operated By'                  => 'Durchgeführt von',
            'Airline Booking Reference(s)' => 'Buchungsreferenz(en)',
            'Departure'                    => 'Abreise', // + train
            'Arrival'                      => 'Ankunft', // + train
            'Duration'                     => 'Dauer', // + train
            //            'Distance' => '',
            'Class'                 => 'Klasse', // + train
            'Seat'                  => 'Sitzplatz', // + train
            'Equipment'             => 'Ausstattung', // + train
            "Meal"                  => 'Mahlzeit',
            'Frequent Flyer number' => 'Vielfliegernummer', // + train
            'E-ticket'              => 'Ticketnummer', // + train
            'for'                   => 'für', // + train
            'Baggage allowance'     => 'Zulässiges Gepäck', // + train
            //            'Check in completed by' => '', // + train

            // HOTEL + RENTAL
            //            "Confirmation number" => "", // + rental
            //            "Check-in"            => '',
            //            "Check-out"           => '',
            //            "Location"            => "", // + rental
            //            "Tel"            => "", // + rental
            //            "Fax"            => "",
            //            "Name"                => "", // + rental
            //            "Rate"                => "",
            //            "per night"           => "",
            //            "for"                 => "",
            //            "night(s)"            => "",
            "Estimated total" => 'Geschätzter Endpreis', // + rental
            //            "Taxes" => '',
            //            "Room type"           => "",
            //            "Occupancy"           => "",
            //            "Cancellation policy" => "",

            // another format (it-33535315.eml)
            //            'Details' => '',
            //            'Hotel Name' => '',
            //            'Hotel Address' => '',
            //            'Hotel Tel' => '',
            //            'Hotel Fax' => '',
            //            'Room' => '', // row with room type
            //            'Hotel Cost Per Night' => '',

            // RENTAL (+ see HOTEL)
            'Pick up'         => 'Abholung',
            'Drop off'        => 'Rückgabe',
            'Car Rental'      => 'Mietwagenanbieter',
            'Car type'        => 'Fahrzeugtyp',
            'Day'             => 'Tag', // part estimated total
            //            'Membership ID' => '',

            // TRAIN (+ see FLIGHT)
            "Train"     => "Zug",
            "Reference" => "Bestätigungsnummer",
            "Platform"  => "Gleis",
            "Coach"     => "Waggonnummer",
        ],
        'it' => [
            'Booking ref:'      => 'Riferimento',
            'Issue date:'       => 'Data di emissione',
            'Traveler'          => 'Traveler',
            //'Telephone'         => '',
            //'Agency'            => '',
            'Operated By'       => 'Operato Da',
            'Departure'         => 'Partenza',
            'Arrival'           => 'Arrivo',
            //'Duration'          => '',
            'Itinerary'         => 'Itinerario',
            'Terminal'          => 'Terminale',
            'Booking status'    => 'Booking Status',
            'Class'             => 'Classe',
            //'Seat'              => '',
            'Baggage allowance' => 'Bagaglio ammesso',
            //			'Check in completed by' => '',
            'Equipment' => 'Equipment',
            //            'Frequent Flyer number' => 'Número de viajero frecuente',
            //'E-ticket'                     => '',
            'Ticket Number'                => 'Numero Biglietto',
            'for'                          => ['for'],
            "Meal"                         => ['Flight Meal'],
            "Airline Booking Reference(s)" => "Compagnia Aerea Emittente",
            // HOTEL
            //            "Confirmation number" => "Número de confirmación",
            //            "Check-in" => "Entrada",
            //            "Check-out" => "Salida",
            //            "Location" => "Ubicación",
            //            "Cancellation policy" => "Condiciones de cancelación",
            //            "Occupancy" => "Occupancy",
            //            "Room type" => "Tipo de habitación",
            //            "Name" => "Nombre",
            //            "Rate" => "Tarifa",
            //            "Estimated total" => "Importe total estimado",
            //            "per night" => "por noche",
        ],
        'zh' => [
            'Booking ref:'      => '訂位代號',
            'Issue date:'       => '開票日期',
            'Traveler'          => '旅客',
            //'Telephone'         => '',
            //'Agency'            => '',
            //'Operated By'       => '',
            'Departure'         => '出發',
            'Arrival'           => '抵達',
            'Duration'          => '歷時',
            //'Itinerary'         => '',
            'Terminal'          => '航站',
            'Booking status'    => '預訂狀態',
            'Class'             => '艙等',
            'Seat'              => '座位',
            'Baggage allowance' => '行李重量限度',
            //			'Check in completed by' => '',
            'Equipment'                    => '機型',
            'Frequent Flyer number'        => '航空公司會員編號',
            'E-ticket'                     => '電子機票',
            //'Ticket Number'                => '',
            'for'                          => ['對於'],
            "Meal"                         => ['機上餐點'],
            //"Airline Booking Reference(s)" => "",
            // HOTEL
            //            "Confirmation number" => "Número de confirmación",
            //            "Check-in" => "Entrada",
            //            "Check-out" => "Salida",
            //            "Location" => "Ubicación",
            //            "Cancellation policy" => "Condiciones de cancelación",
            //            "Occupancy" => "Occupancy",
            //            "Room type" => "Tipo de habitación",
            //            "Name" => "Nombre",
            //            "Rate" => "Tarifa",
            //            "Estimated total" => "Importe total estimado",
            //            "per night" => "por noche",
        ],
    ];

    private $detectBody = [
        'zh'   => '訂位代號:',
        'it'   => 'Ricevuta del biglietto elettronico',
        'es'   => 'El cálculo de la emisión promedio',
        'es2'  => 'Calculadora de emisiones de carbono',
        'es3'  => 'Localizador(es) de reserva de la aerolínea',
        'es4'  => 'Aviso de protección de datos:',
        'en'   => 'Electronic Ticket Receipt',
        'en2'  => 'Airline Booking Reference',
        'en3'  => 'Your trip',
        'en4'  => 'Check My Trip',
        'pt'   => 'A sua viagem',
        'fr'   => 'Avis de protection des données :',
        'fr2'  => 'Votre voyage',
        'de'   => 'Allgemeine Informationen oder Weitere Informationen',
        'de2'  => 'Ihre Reise',
        'de3'  => 'Falls Sie diese Buchung ändern oder stornieren möchten, setzen Sie sich bitte direkt',
    ];

    private $providers = ['checkmytrip.com', 'amadeus.com', 'atpi.com', 'flyingcolours', 'ctraveller', 'bcdtravel.',
        '@travel-experts.be', ]; //!!! don't include mta until it's ignoreTraxo

    private $pdf;
    private $pdfText;
    private $emailDate = 0;

    public static function getEmailProviders()
    {
        return ['amadeus', 'atpi', 'fct', 'checkmytrip', 'ctraveller', 'bcd', 'fcmtravel', 'flightcentre', 'travexp'];
    }

    public function ParsePlanEmailLocal(\PlancakeEmailParser $parser, Email $email): ?string
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) < 1) {
            return null;
        }

        foreach ($pdfs as $pdf) {
            $pdfBody = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $pdfHtml = null;

            if (!$this->assignLang($pdfBody)) {
                continue;
            }

            if (empty($this->providerCode)) {
                $this->providerCode = $this->getProviderPDF($pdfBody);
            }

            if (
                !empty($str = $this->findСutSection($pdfBody, null, [
                    'Data Protection Notice:',
                    'Información ecológica',
                    'Aviso de protección de datos:',
                    'General Information',
                ]))
            ) {
                $pdfBody = $str;
            }

            if (strpos($pdfBody, 'Page 1 of') !== false) {// del garbage - separate pages
                $pdfBody = preg_replace("#^[ ]*Page \d+ of \d+[ ]*$#m", '', $pdfBody);
            }

            if (mb_strpos($pdfBody, 'Página 1 de') !== false) {// del garbage - separate pages
                $pdfBody = preg_replace("#^[ ]*Página \d+ de \d+[ ]*$#um", '', $pdfBody);
            }

            if (mb_strpos($pdfBody, 'Seite 1 von') !== false) {// del garbage - separate pages
                $pdfBody = preg_replace("#^[ ]*Seite \d+ von \d+[ ]*$#um", '', $pdfBody);
            }

            if (mb_strpos($pdfBody, 'Page 1 de') !== false) {// del garbage - separate pages
                $pdfBody = preg_replace("#\n+[ ]*Page \d+ de \d+[ ]*$#um", '', $pdfBody);
            }

            if (mb_strpos($pdfBody, 'Scan for check-in. Not to be used as boarding pass.') !== false) {
                $pdfBody = preg_replace("#\n+[ ]*Scan for check-in. Not to be used as boarding pass.#s", '', $pdfBody);
            }

            //Scan for check-in. Not to be used as boarding pass.

            $this->pdfText = $pdfBody;

            $this->parseHeader($pdfBody);

            unset($airs);
            $segments = $this->split("/^[ ]*([-[:alpha:]]+ \d{1,2} [[:alpha:]]+ \d{4}\n)/mu", $this->pdfText);

            if ($this->lang === 'zh') {
                $segments = $this->split("/^[ ]*([-[:alpha:]]+ \d{4}\D\d{1,2}\D\d{1,2}\D)/mu", $this->pdfText);
            }

            foreach ($segments as $i => $sText) {
                $sTextPrev = array_key_exists($i - 1, $segments) ? $segments[$i - 1] : null;
                $sTextNext = array_key_exists($i + 1, $segments) ? $segments[$i + 1] : null;

                if (strpos($sText, $this->t('Chauffeur driven car')) !== false) {
                    $this->parseEmailTransfer($email, $sText, $sTextPrev, $sTextNext);

                    continue;
                }

                if (strpos($sText, $this->t('Departure')) !== false && strpos($sText, $this->t('Arrival')) !== false && $this->strposAll($sText, $this->t('charge for the first night')) == false
                    && strpos($sText, $this->t('Room type')) === false && strpos($sText, $this->t('Location')) === false
                ) {
                    if (preg_match("/(?:.*\n){0,3}\s*" . $this->opt($this->t("Train")) . "/", $sText)) {
                        $trains[] = $sText;

                        continue;
                    }

                    if (empty($pdfHtml)) {
                        $pdfHtml = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE);
                        $this->pdf = clone $this->http;
                        $this->pdf->SetEmailBody(str_replace(['&#160;', '  '], ' ', $pdfHtml));
                    }
                    $airs[] = $sText;

                    continue;
                }

                if (strpos($sText, $this->t('Pick up')) !== false) {
                    $this->parseEmailRental($email, $sText);

                    continue;
                }

                if ($this->strposAll($sText, $this->t('Check-in')) !== false || $this->strposAll($sText, $this->t('charge for the first night')) !== false) {
                    $this->parseEmailHotel($email, $sText);

                    continue;
                }
            }

            if (!empty($airs)) {
                $this->parseEmailFlight($email, $airs);
            }

            if (!empty($trains)) {
                $this->parseEmailTrain($email, $trains);
            }

            $total = $this->re("/Invoice Total +(.+)/u", $this->pdfText);

            if (!empty($total) && $total != '0') {
                $email->price()
                    ->total($this->re("/^\s*[A-Z]{3}\s*([\d\.]+)(?:\s+|$)/u", $total))
                    ->currency($this->re("/^\s*([A-Z]{3})/u", $total));

                $tax = $this->re("/Taxes And Airline Imposed Fees\s*[A-Z]{3}\s*([\d\,\.]+)/", $this->pdfText);

                if (!empty($tax)) {
                    $email->price()
                        ->tax($tax);
                }

                $cost = $this->re("/Air Fare\s*[A-Z]{3}\s*([\d\,\.]+)/", $this->pdfText);

                if (!empty($cost)) {
                    $email->price()
                        ->cost($cost);
                }
            }
        }

        if (!empty($this->headers['confirmation']) && !in_array($this->headers['confirmation'], $this->allConfirmations)
        ) {
            $email->ota()->confirmation($this->headers['confirmation']);
        }

        if (!empty($this->headers['account'])) {
            $email->ota()->account($this->headers['account'], false);
        }

        return null;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // that construction because of  mta ignoreTraxo
        if (strpos(implode(" ", $parser->getFrom()), 'mtatravel.com.au') !== false) {
            $this->logger->debug('goto parse by daughter-parser');

            return null;
        } //goto parse by daughter-parser

        $this->emailDate = EmailDateHelper::calculateOriginalDate($this, $parser);

        if (empty($this->emailDate)) {
            $this->emailDate = strtotime($parser->getDate());
        }

        $email->obtainTravelAgency();

        $this->ParsePlanEmailLocal($parser, $email);

        if (empty($this->providerCode)) {
            $this->providerCode = $this->getProvider(implode(" ", $parser->getFrom()));
        }

        if (empty($this->providerCode)) {
            $this->providerCode = 'checkmytrip';
        }

        $email->setProviderCode($this->providerCode);
        $email->setType('PDF' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!preg_match('/.+\.pdf\b/i', $headers['subject'])) {
            return false;
        }

        foreach ($this->providers as $provider) {
            if (stripos($headers['from'], $provider) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $cnt = count(self::$dict);

        return $cnt;
    }

    public function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    protected function detectBody(\PlancakeEmailParser $parser): bool
    {
        $flag = false;
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $pdfBody = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detectBody as $detect) {
                if (stripos($pdfBody, $detect) !== false) {
                    $flag = true;

                    break 2;
                }
            }
        }

        if ($flag && isset($pdfBody)) {
            /*if (strpos($pdfBody, 'mtatravel.com.au') !== false) { // don't delete until mta is ignoreTraxo.
                return false;
            }*/

            if (strpos($pdfBody, 'Direct Travel') !== false && strpos($pdfBody, 'Pick up') !== false) {
                return false;
            }

            return $this->assignLang($pdfBody);
        }

        return false;
    }

    private function getProvider($from)
    {
        if (
            0 < $this->http->XPath->query("//td[contains(., 'Email') and not(.//td)]/following-sibling::td[contains(., 'flyingcolours')]")->length
            && 0 < $this->http->XPath->query("//td[contains(., 'Agency') and not(.//td)]/following-sibling::td[contains(., 'FLYING COLOURS TRAVEL')]")->length
        ) {
            return 'fct';
        }

        if (stripos($from, 'amadeus') !== false) {
            return 'amadeus';
        }

        if (
            0 < $this->http->XPath->query("//td[contains(., 'Email') and not(.//td)]/following-sibling::td[contains(., 'atpi.com')]")->length
            && 0 < $this->http->XPath->query("//td[contains(., 'Agency') and not(.//td)]/following-sibling::td[contains(., 'ATP INSTONE')]")->length
        ) {
            return 'atpi';
        }

        if (
            0 < $this->http->XPath->query("//*[contains(., 'Corporate Traveller ')]")->length
        ) {
            return 'ctraveller';
        }

        if (
            0 < $this->http->XPath->query("//*[contains(., 'BCD TRAVEL')]")->length
        ) {
            return 'bcd';
        }

        if (
            0 < $this->http->XPath->query("//*[contains(., 'ORBIT WORLD TRAVEL')]")->length
        ) {
            return 'orbitwt';
        }

        if (
            0 < $this->http->XPath->query("//*[contains(., 'FCM TRAVEL SOLUTIONS')]")->length
        ) {
            return 'fcmtravel';
        }

        if (
            0 < $this->http->XPath->query("//*[contains(., 'BTS - TRAVEL EXPERT')]")->length
        ) {
            return 'travexp';
        }

        return null;
    }

    private function getProviderPDF($text)
    {
        if (preg_match("/\b" . $this->opt($this->t('Agency')) . "((?:.*\n+){7})/u", $text, $m)
            || preg_match("/\b(?:" . $this->opt($this->t('Booking ref:')) . '|' . $this->opt($this->t('Issue date:')) . ")((?:.*\n+){7})/u", $text, $m)
        ) {
            $agency = $m[1];

            if (
                preg_match("/\bFLYING COLOURS TRAVEL\b/", $agency)
            ) {
                return 'fct';
            }

            if (
                preg_match("/\b(Amadeus|@amadeus.com)\b/", $agency)
            ) {
                return 'amadeus';
            }

            if (
                preg_match("/\bATP INSTONE\b/", $agency)
            ) {
                return 'atpi';
            }

            if (
                preg_match("/\bCorporate Traveller\b/i", $agency)
            ) {
                return 'ctraveller';
            }

            if (
                preg_match("/(?:\bBCD TRAVEL\b|noreply@bcdtravel\.)/", $agency)
            ) {
                return 'bcd';
            }

            if (
                preg_match("/(\.fcm\.travel\b|FCM +TRAVEL +SOLUTIONS)/", $agency)
            ) {
                return 'fcmtravel';
            }

            if (
                preg_match("/(?:\bORBIT +WORLD +TRAVEL\b|\.orbitworldtravel\.com)/", $agency)
            ) {
                return 'orbitwt';
            }

            if (
                preg_match("/(?:\bFCBT PENDORING\b|www\.flightcentre\.co)/", $agency)
            ) {
                return 'flightcentre';
            }
        }

        if (
            $this->striposAll($text, "Thank you for booking with Corporate Traveller") == true
        ) {
            return 'ctraveller';
        }

        if (
            $this->striposAll($text, "atpi.com") == true
        ) {
            return 'atpi';
        }

        if ($this->striposAll($text, 'amadeus') !== false) {
            return 'amadeus';
        }

        if ($this->striposAll($text, 'Thank you for booking with FCM Travel') !== false) {
            return 'fcmtravel';
        }

        if ($this->striposAll($text, 'www.flightcentre.co') !== false) {
            return 'flightcentre';
        }

        return null;
    }

    private function parseHeader($text): void
    {
        $this->headers = [];
        $this->allConfirmations = [];
        $issueDateTitle = preg_replace('/[\s:]+$/', '', $this->t('Issue date:'));

        $recLoc = $this->findСutSection($text, $this->t('Booking ref:'), $issueDateTitle);

        if (empty($recLoc)) {
            $recLoc = substr($text, stripos($text, trim($this->t('Booking ref:'), ":")), 100);
        }

        if (empty(preg_replace('/^(\:\s+)$/su', '', $recLoc))) {
            $recLoc = $this->findСutSection($text, $this->t('Issue date:'), $this->t('Traveler'));
        }

        if (preg_match('/\b([A-Z\d]{5,6})\b/u', $recLoc, $m)) {
            $this->headers['confirmation'] = $m[1];
        }

        $issueDate = null;
        $issueDateValue = $this->findСutSection($text, $this->t('Issue date:'), $this->t('Traveler'));

        if (empty($issueDateValue)) {
            $issueDateValue = $this->findСutSection($text, $issueDateTitle, $this->t('Traveler'));
        }

        if (empty($issueDateValue)) {
            $issueDateValue = $this->findСutSection($text, 'Document Issue', $this->t('Traveler'));
        }

        $issueDateValue = str_replace('Baggage', '', $issueDateValue);

        if (preg_match('/^[>\s:]*(?:Date *)?(\d{1,2}\s+[[:alpha:]]+\s+\d{2,4})\b/u', preg_replace('/\s+/', ' ', $issueDateValue), $m)
        || preg_match('/\s*(\d{4}\D+\d{1,2}\D+\d{1,2}\D)\s*\n/u', $issueDateValue, $m)) {
            $issueDate = strtotime($this->normalizeDate($m[1]));
        }

        $this->headers['issueDate'] = $issueDate;

        $tickets = [];
        $pax = [];

        $travellersText = $this->findСutSection($text, $this->t('Traveler'), "\n\n");

        if (empty($travellersText)) {
            $travellersText = $this->findСutSection($text, $this->t('Traveler'), $this->t('Airline Booking Reference(s)'));
        }

        if (!empty($travellersText) && preg_match("/^((?:.*\n+){1,15}) {0,5}\w+( \w+){0,5} \d{4}( \w+){0,5}\n/", $travellersText, $m)) {
            $travellersText = $m[1];
        }

        if (empty($travellersText)) {
            $travellersText = $this->findСutSection($text, $this->t('Traveler'), "\n\n");
        }

        if (!empty($travellersText) && stripos($travellersText, "{$this->t('Departure')}") !== false) {
            $travellersText = $this->re("/^(.+)Accounting/su", $travellersText);
        }

        $travellersText = preg_replace("/\n {0,15}Date[\s\S]*/", '', $travellersText);

        if (stripos($travellersText, 'Account Number:') !== false) {
            $account = $this->re("/Account Number\s*\:?\s*(\d+)/", $travellersText);
            $this->headers['account'] = $account;
            $travellersText = str_replace(['Accounting', 'Information'], '', $travellersText);
        }

        if (stripos($travellersText, $this->t('Ticket Number')) === false
            && preg_match_all('/^[ ]{0,40}(?:' . $this->opt($this->t('Traveler')) . ' {1,15})?([[:alpha:] \/]+?)(?: {3,}|$)/mu', $travellersText, $travellerMatches)
        ) {
            $pax = array_filter(array_map("trim", $travellerMatches[1]));
        }

        if (empty($pax)) {
            $travellersText = $this->findСutSection($text, $this->t('Traveler'), $this->t('Itinerary'));
            //$this->logger->error();
            if (preg_match_all('/^\s*-?\s*([A-Za-z].+?\/.+)/m', $travellersText, $travellerMatches)) {
                foreach ($travellerMatches[1] as $key => $value) {
                    if (stripos($value, $this->t('Data')) === false) {
                        $pax[] = explode('   ', $travellerMatches[1][$key])[0];
                    }
                }

                if ((strpos($travellersText, $this->t('Ticket Number')) !== false) && preg_match_all('/^.*\b(\d{3}[- ]?\d{10})\b/m',
                        $travellersText, $m)
                ) {
                    $tickets = $m[1];
                }
            } elseif (preg_match_all('/^ *-? *([A-Z].+?)\s{3,}(\d[\d\-]+)/m', $travellersText, $m, PREG_SET_ORDER)) {
                $tickets = [];
                $pax = [];

                foreach ($m as $value) {
                    $pax[] = $value[1];
                    $tickets[] = $value[2];
                }
            }
        }

        if (empty($tickets)) {
            if (preg_match("#" . $this->opt($this->t("E-ticket")) . "( {3,}.*\d{5,}.*(?:\n.*)?\s+{$this->opt($this->t('for'))}\s+.*\n(?:(?: {50,}| +Check-in {3,}).+\n+)+)#",
                $text, $m)) {
                if (preg_match_all("/ {3,}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?([\d\-]{10,})\s*{$this->opt($this->t('for'))}\s+/",
                    $m[1], $mat)) {
                    $tickets = $mat[1];
                }
            } elseif (preg_match_all("#" . $this->opt($this->t("E-ticket")) . "\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*([\d\-]{5,})\s*{$this->opt($this->t('for'))} +([[:alpha:] ]+)\n#",
                $text,
                $m)) {
                $tickets = $m[1];

                if (count($m[1]) == 1 && preg_match_all("/^\s+(\d{13})\s*{$this->opt($this->t('for'))}/mu", $text, $match)) {
                    $tickets = $match[1];
                }

                if (empty($pax)) {
                    $pax = $m[2];
                }
            } elseif (preg_match_all("#^ *" . $this->opt($this->t("E-ticket")) . "\s*:\s*(\d{3}[ \-]\d+[\-\d]*)\s+#m", $text, $m)) {
                $tickets = $m[1];
            } elseif (preg_match_all("/^[ ]*{$this->opt($this->t("E-ticket"))}[ ]+(\d{3}[ \-]\d+[-\d]*)[ ]*\n/m", $text, $ticketMatches)) {
                $tickets = array_values(array_unique($ticketMatches[1]));
            }
        }

        $this->headers['travellers'] = preg_replace("/(\s(?:MRS|MR|MISS|MS|DR|Sr))$/i", "",
                                       preg_replace("/^(\s*(?:MRS|MR|MISS|MS|DR|Sr))\s/i", "", $pax));

        //it-346847765.eml //look Bosch
        if (count($this->headers['travellers']) > 0) {
            foreach ($this->headers['travellers'] as $key => $traveller) {
                if (preg_match("/^\w+$/", $traveller) && $key > 0) {
                    $newKey = $key - 1;
                    $previousTraveller = $this->headers['travellers'][$newKey];
                    $this->headers['travellers'][$newKey] = $previousTraveller . ' ' . $traveller;
                    unset($this->headers['travellers'][$key]);
                }
            }
        }
        $this->headers['tickets'] = $tickets;

        $airlineReferenceNumbersText = preg_match_all("/(?:^|\n)[ ]*{$this->opt($this->t('Airline Booking Reference(s)'))}([\s\S]*?)(?:\n\n|$)/u", $text, $matches) ? implode("\n", $matches[1]) : '';

        if (!empty(trim($airlineReferenceNumbersText))) {
            $this->headers['airlineReferenceNumbersText'] = $airlineReferenceNumbersText;
        }

        if (preg_match_all("#\n *{$this->opt($this->t('Total Amount'))} +: *([A-Z]{3}) *(\d+[\d\. ,]+)#", $text, $m, PREG_SET_ORDER)) {
            if (count($m) === 1) {
                $this->headers['flightTotal'] = PriceHelper::parse($m[0][2], $m[0][1]);
                $this->headers['flightCurrency'] = $m[0][1];
            }
        }
    }

    private function parseEmailFlight(Email $email, $segments): void
    {
        $this->depDay = $this->depMonth = null;

        $this->logger->debug(__FUNCTION__);
        // $this->logger->debug('Air Segments: ' . "\n" . print_r($segments, true));

        $f = $email->add()->flight();

        $f->general()->noConfirmation();

        if (!empty($this->headers['travellers'])) {
            $f->general()->travellers($this->headers['travellers']);
        }

        if (!empty($this->headers['issueDate'])) {
            $f->general()->date($this->headers['issueDate']);
        }

        if (!empty($this->headers['tickets'])) {
            $f->issued()->tickets($this->headers['tickets'], false);
        }

        $airlineReferenceNumbers = [];
        $accounts = [];

        foreach ($segments as $i => $info) {
            $s = $f->addSegment();

            $confno = $this->re("#{$this->opt($this->t('Airline Booking Reference(s)'))}[ ]+([A-Z\d]{5,})\b#", $info);

            if (!empty($confno)) {
                $s->airline()->confirmation($confno);
                $airlineReferenceNumbers[] = $confno;
                $this->allConfirmations[] = $confno;
            }

            // Airline
            if (preg_match('/^ *(?:[\w\-]+ (\d+ \w+ \d{4})\s*\n\s*|\D+\s*(\d{4}\D\d{1,2}\D\d{1,2}\D)\n\s*)?.*?\b(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\n*[ ]*(?<flightNumber>\d{1,5})\b/u', $info, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);

                if (empty($confno)) {
                    if (
                        // LH (Lufthansa): PJ6GY9
                        preg_match("/^[ ]*(?<title>{$m['airline']}\b[^:\n]*?)[ ]*:[ ]*(?<pnr>[A-Z\d]{6})(?:[ ]{2}|$)/m",
                        $this->headers['airlineReferenceNumbersText'] ?? '', $pnrMatches)
                        // LH/PJ6GY9
                        || preg_match("/(?:^|\D[ ]+)(?<title>{$m['airline']}) ?\/ ?(?<pnr>[A-Z\d]{6})(?:[ ]{2}|$)/m",
                            $this->headers['airlineReferenceNumbersText'] ?? '', $pnrMatches)
                    ) {
                        $airlineReferenceNumbers[] = $pnrMatches['pnr'];
                        $this->allConfirmations[] = $pnrMatches['pnr'];
                        $s->airline()->confirmation($pnrMatches['pnr']);
                    }
                }
            }

            // Operator
            if (preg_match("/\({$this->opt($this->t('Operated By'))}\s*(.{2,}?(?:\n.*)?)[ ]*(?:\)|Departure)/im", $info, $m)
                || preg_match("/\n[ ]*{$this->opt($this->t('Operated By'))}[ ]*(.+)/im", $info, $m)) {
                $m[1] = preg_replace("#{$this->opt($this->t('Airline Booking Reference(s)'))}[ ]+([A-Z\d]{5,}).*#", '', $m[1]);
                $m[1] = preg_replace("#\s+#", ' ', $m[1]);

                if (preg_match("#^(?<airline>[\w\s]*?)\.?[, ]+(?:(?<airlineIATA>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<flightNumber>\d{1,5}))\b#u", $m[1], $mat)) {
                    $s->airline()
                        ->operator($mat['airline'])
                        ->carrierName($mat['airlineIATA'])
                        ->carrierNumber($mat['flightNumber']);
                } else {
                    $s->airline()->operator($m[1]);
                }
            }

            $dep = $this->findСutSection($info, $this->t('Departure'), $this->t('Arrival'));

            $arr = $this->findСutSection($info, $this->t('Arrival'), $this->t('Duration'));

            if (empty($arr)) {
                $arr = $this->findСutSection($info, $this->t('Arrival'), $this->t('Class'));
            }
            $segmentDate = null;

            if (preg_match("/^ *[\w\-]+ (\d+ \w+ \d{4}) *\n/u", $info, $m)
            || preg_match("/^ *[\w\-]+ (\d{4}\D\d{1,2}\D\d{1,2}\D)\n\s*/u", $info, $m)) {
                $segmentDate = strtotime($this->normalizeDate($m[1]));
            }

            $segmentsInfo = ['Dep' => $dep, 'Arr' => $arr];

            // Departure and Arrival
            $patterns['dayMonth'] = '(?<Day>\d{1,2})[ ]+(?<Month>[^\d\W]{3,})'; // 27 November
            $patterns['dayMonth2'] = '(?<Month>\d+)[月](?<Day>\d+)日'; // 05月12日
            $patterns['time'] = '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?'; // 02:15 PM
            $patterns['name'] = '[\s\S]{3,}?'; // TOKYO INTL HANEDA TOKYO
            $patterns['terminal'] = "(?:\s+{$this->t('Terminal')}\s*:\s*(?<Terminal>[\s\S]+?))?"; // Terminal : D2 - Domestic Terminal 2
            $patterns['backend'] = '(?:Check-in|Stop|\n|$)';

            $re1 = "/(?<Date>{$patterns['dayMonth']}[ ]+(?<Time>{$patterns['time']}))\s+(?<Name>{$patterns['name']}){$patterns['terminal']}\s*{$patterns['backend']}/iu";
            $re2 = "/(?<Date>{$patterns['dayMonth']})[ ]+(?<Name>{$patterns['name']}){$patterns['terminal']}\s+(?<Time>{$patterns['time']})\s*{$patterns['backend']}/iu"; // it-32631758.eml
            $re3 = "/(?<Date>{$patterns['dayMonth2']}[ ]+(?<Time>{$patterns['time']}))\s+(?<Name>{$patterns['name']}){$patterns['terminal']}\s*{$patterns['backend']}/iu"; //it-370514555.eml

            foreach ($segmentsInfo as $key => $str) {
                // TODO: refactoring from `preg_match` to `$this->splitCols` (for fields: Date, Name, Terminal)
                if (preg_match($re1, $str, $m)
                    || preg_match($re2, $str, $m)
                    || preg_match($re3, $str, $m)
                ) {
                    $m['Name'] = preg_replace('/^(.{3,}?)\s*\([ ]*[+]+[ ]*\).*$/s', '$1', $m['Name']);

                    if ($key === 'Dep') {
                        $point = $s->departure();
                        $this->depDay = $m['Day'];
                        $this->depMonth = $m['Month'];
                    } else {
                        $point = $s->arrival();
                    }

                    $m['Code'] = $this->pdf->FindSingleNode("//b[normalize-space()=\"{$m['Date']}\"]/following::b[normalize-space()][1][normalize-space()=\"{$m['Name']}\"]/following::text()[normalize-space()][position()<3][contains(.,'+')]/ancestor::a[1]/@href", null, true, '/.+\/([A-Z]{3})\b/');

                    if (preg_match("/^(?<name>.+)\s+\((?<code>[A-Z]{3})\)$/u", $m['Name'], $match)) {
                        $m['Name'] = $match['name'];
                        $m['Code'] = $match['code'];
                    }

                    $point->name(trim(str_replace(['(', ')'], '', $m['Name']), ' +'));

                    if ($m['Code']) {
                        $point->code($m['Code']);
                    } else {
                        $point->noCode();
                    }

                    $lang = $this->lang;
                    //it-12287505.eml - fr + en //it-222745218.eml - it + en
                    if (preg_match("/^\s*Check\-in\s*as boarding pass/mus", $info) || preg_match("/^\s*Check\-in\s*Bagaglio ammesso/mus", $info)) {
                        $lang = 'en';
                    }

                    if ($segmentDate) {
                        // $this->logger->debug($key . '-' . $m['Day']);
                        if ($lang !== 'zh') {
                            $after = true;

                            if ($key === 'Arr' && (intval($this->depDay) > intval($m['Day'])) && $this->depMonth === $m['Month']) {
                                $after = false;
                            }
                            $month = MonthTranslate::translate($m['Month'], $lang);

                            if (empty($month)) {
                                $month = MonthTranslate::translate($m['Month'], 'en');
                            }

                            $point->date(strtotime($m['Time'], EmailDateHelper::parseDateRelative(
                                $m['Day'] . ' ' . $month, $segmentDate, $after
                            )));
                        } else {
                            $year = date("Y", $segmentDate);
                            $point->date(strtotime($m['Day'] . '.' . $m['Month'] . '.' . $year . ', ' . $m['Time']));
                        }
                    }

                    if (isset($m['Terminal']) && trim($m['Terminal']) !== null && strlen(trim($m['Terminal'])) > 0) {
                        $terminal = trim(preg_replace(["#\s+#", '/Check[-\s]{1}in/i'], ' ', $m['Terminal']));

                        if (preg_match("/(\d+)[\s\-]+\w+\s*\d+\s*\(\D+\)/", $terminal, $m)
                        || preg_match("/^(\D)[\s\-]+\w+\s*Terminal/", $terminal, $m)) {
                            $point->terminal($m[1]);
                        } else {
                            $point->terminal($terminal);
                        }
                    }
                } else {
                    $f->removeSegment($s);
                }
            }

            // Extra
            if (preg_match('/' . $this->opt($this->t('Duration')) . '\s+(\d{1,2}:\d{2})/u', $info, $m)) {
                $s->extra()->duration($m[1]);
            }

            if (preg_match('/' . $this->opt($this->t('Duration')) . '\s+\d{1,2}:\d{2}h?\s*\((.+)\)/u', $info, $m)) {
                if (preg_match("#(?:non[\s\-]*stop|sin\s+paradas|Sans\s+escale|ohne +Stopp|直飛無停點)#i", $m[1])) {
                    $s->extra()->stops(0);
                } elseif (preg_match("#(\d+)\s+(?:stop|paradas|Stopp)#i", $m[1], $v)) {
                    $s->extra()->stops((int) $v[1]);
                }
            }

            if (preg_match('/' . $this->t('Distance') . '[ ]+(\d[,.\'\d]*[ ]*(?:Miles|Kilometres))/i', $info, $m)) {
                // Distance    1,058 Miles | Distance 3605 Kilometres
                $s->extra()->miles($m[1]);
            }

            if (preg_match('/' . $this->t('Booking status') . '\s+((?:Confirmado|Confirmed|Confirmé|已確認)).*/ui', $info, $m)) {
                $s->extra()->status($m[1]);
            }

            if (preg_match('/' . $this->t('Class') . '[ ]+(.+?)[ ]*(?:\(|\n|$)/ui', $info, $m)) {
                $s->extra()->cabin($m[1]);
            }

            if (preg_match('/' . $this->t('Class') . '[ ]+.+?\s*\(([A-Z]{1,2})\)/ui', $info, $m)) {
                $s->extra()->bookingCode($m[1]);
            }

            if (preg_match('/' . $this->opt($this->t('Equipment')) . '[ ]+(.+)/', $info, $m)) {
                $s->extra()->aircraft($m[1]);
            }

            if (preg_match('/' . $this->opt($this->t('Meal')) . '[ ]+(.+)/', $info, $m)) {
                $s->extra()->meal($m[1]);
            }

            $seatInfo = $this->re("/{$this->opt($this->t('Seat'))}[ ]+(.+?)\s+(?:{$this->opt($this->t('Baggage allowance'))}|{$this->opt($this->t('Check in completed by'))}|{$this->opt($this->t('Equipment'))})/si",
                $info);

            if (empty($seatInfo)) {
                $seatInfo = $this->re("/\n.{40,} {3,}{$this->opt($this->t('Seat'))}[ ]+(.+?)\n/i",
                    $info);
            }

            if (preg_match_all('/^ *(\d{1,3}[A-Z])\b/m', $seatInfo, $m)) {
                $s->extra()->seats($m[1]);
            }

            if ($s->getFlightNumber() == 9331 && $s->getAirlineName() == 'QF' && preg_match('/Mystery\s*Flight/is', $s->getArrName())) {
                $f->removeSegment($s);

                continue;
            }

            $ffText = $this->re("#{$this->opt($this->t('Frequent Flyer number'))}[ ]+(.+?)(?:\n\n|$)#is", $info);
            // example 1: SQ8791827497 for CHUA/WAH CHOON MR
            // example 2: NH SQ8791827497
            if (preg_match_all("#^[ ]*(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) )?([A-Z\d]{7,}?)(?:\s+{$this->opt($this->t('for'))}\b|$)#mu", $ffText, $m)) {
                $accounts = array_merge($accounts, $m[1]);
            }

            if ($s->getStops() === 1) {
                $s->extra()->transit();

                // it-33535367.eml
                if (empty($s->getAircraft())) {
                    $equip = $this->re("/Stop 1 {5,}.+\s+.+? *(?:\(\+|\n).*\s+{$this->opt($this->t('Equipment'))}[ ]+(.+)/", $info);

                    if (empty($equip)) {
                        $equip = $this->re("/Stop 1 .+Stop 2.+Aircraft\s*(.+)\n\s*Details/s", $info);
                    }

                    if (!empty($equip)) {
                        $s->extra()->aircraft($equip);
                    }
                }
            }
        }

        if (!empty($accounts) > 0) {
            $f->program()
                ->accounts(array_values(array_filter(array_unique($accounts))), false);
        }

        // Price
        if (!empty($this->headers['flightTotal']) || !empty($this->headers['flightCurrency'])) {
            $f->price()
                    ->currency($this->headers['flightCurrency'])
                    ->total($this->headers['flightTotal']);
        }
    }

    private function parseEmailHotel(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);
        // $this->logger->debug('Hotel Segment: ' . "\n" . print_r($text, true));

        $r = $email->add()->hotel();

        // General
        $conf = $this->re("#{$this->opt($this->t('Confirmation number'))}:? +((?-i)[A-Z\d\-]{3,})#i", $text);

        if (!empty($conf)) {
            $this->allConfirmations[] = $conf;
            $r->general()
                ->confirmation($conf);
        }

        if (!empty($this->headers['travellers'])) {
            $r->general()->travellers($this->headers['travellers']);
        } elseif (preg_match("#\n *{$this->opt($this->t('Name'))} +(.+)#", $text, $m)) {
            $r->general()->traveller(trim($m[1]));
        }
        $segmentDate = null;

        if (preg_match("/^ *[\w\-]+ (\d+ \w+ \d{4}) *\n/u", $text, $m)) {
            $segmentDate = strtotime($this->normalizeDate($m[1]));
        }

        if (!empty($this->headers['issueDate'])) {
            $r->general()->date($this->headers['issueDate']);
        }
        $r->general()
            ->status($this->re("#{$this->opt($this->t('Booking status'))} +(.+)#", $text));

        if (preg_match("#{$this->opt($this->t('Details'))}[ ]+({$this->opt($this->t('Hotel Name'))}:.+)#s", $text, $m)) {
            $details = $m[1];

            $r->hotel()
                ->name(trim($this->re("#{$this->opt($this->t('Hotel Name'))}: +(.+)#", $details)))
                ->address(preg_replace("#\s+#", ' ',
                    $this->re("#{$this->opt($this->t('Hotel Address'))}:\s+(.+?)\s+(?:{$this->opt($this->t('Hotel Tel'))}|{$this->opt($this->t('Hotel Fax'))})#s",
                        $details)))
                ->phone($this->re("#{$this->opt($this->t('Hotel Tel'))}: +([\d\-\(\)\+ ]+)#", $details), true, true)
                ->fax(trim($this->re("#{$this->opt($this->t('Hotel Fax'))}: +([\d\-\(\)\+ ]+)#", $details)), true, true);

            $roomType = $this->re("#{$this->opt($this->t('Room type'))}: +(.+)#i", $details);

            if (!empty($roomType)) {
                $room = $r->addRoom();
                $room->setType($roomType);
            }

            if ($rate = $this->re("#{$this->opt($this->t('Hotel Cost Per Night'))}:[ ]+(\w+\s*[\d\.]+)#", $details)
            ) {
                $room->setRate($rate);
            }

            $r->general()
                ->cancellation(preg_replace("/\s+/", ' ', $this->re("#{$this->opt($this->t('Cancellation policy'))} +(.+)#i", $text)), true, true);
        } else {
            $name = '';

            if (preg_match("/(?:^|\n)(.+?) +{$this->opt($this->t('Confirmation number'))} .+((?:\n.*)*)?\n *{$this->opt($this->t('Check-in'))}/", $text, $m)) {
                $name = trim(preg_replace("/\s+/", ' ', $m[1] . ($m[2] ?? '')));

                if (preg_match("/^(.+)\s*{$this->opt($this->t('Check-in'))}/", $name, $m)) {
                    $name = $m[1];
                }
            }

            if (empty($name)) {
                $name = trim(preg_replace("/^(\S.+?) {2,}.*/", '$1', $this->re("#(?:^|\n) {0,40}(\S.+)\n\s*{$this->opt($this->t('Check-in'))} +#", $text)));
            }
            $r->hotel()
                ->name($name)
                ->address(preg_replace(["#\s*\n\s*#", "#\s+#"], [', ', ' '], trim($this->re("#{$this->opt($this->t('Location'))} +((.+\n){1,4})\s*{$this->opt($this->t('Check-out'))}#", $text))))
                ->phone($this->re("#{$this->opt($this->t('Tel'))}\.? +([\d\-\(\)\+ /]{5,})#", $text), true, true)
                ->fax(trim($this->re("#{$this->opt($this->t('Fax'))} +([\d\-\(\)\+ /]{5,})#", $text)), true, true);

            if ($name == 'Hotel' && preg_match("/^\D{0,15}$/", $r->getAddress())) {
                if (preg_match("/\n\s*{$this->opt($this->t('Details'))} {2,}(?<name>.+ Airport)\n(?<address>(?:.+\n){1,3})\s*{$this->opt($this->t('Tel'))}: *(?<phone>[\d \-\+\(\)\.]+)(?:\/.*)?\n(?<type>.*{$this->opt($this->t('Room'))}.*)/", $text, $m)) {
//                   Details                  Ibis Brisbane Airport
//                                            2 Dryandra Drive Brisbane Airport
//                                            Tel:07 3139 8100/Cf-9546Vkg670
//                                            Standard Queen Room
//                                            Breakfast
//                                            Free Cancellation Till 6.00Pm 17 N
                    $r->hotel()
                        ->name($m['name'])
                        ->address($m['address'])
                        ->phone($m['phone'], true, true)
                    ;
                    $room = $r->addRoom();
                    $room
                        ->setType($m['type'], true, true);
                } elseif (preg_match("/\s+{$this->opt($this->t('Htl:'))}[\s\S]+\s+{$this->opt($this->t('Adr:'))}[\s\S]+\s+{$this->opt($this->t('City:'))}/", $text, $m)) {
                    //  Details               Htl:Hotel Mulia Senayan
                    //                        Adr:Jl. Asia Afrika
                    //                        Senayan
                    //                        City:Jakarta
                    //                        Tel:+62 21 5747777
                    //                        Price:10164000.50Idr
                    //                        Services:Breakfast
                    //                        Cnx:No Xxl
                    //                        Fop:By Gap
                    //                        Conf:4421699051P5413

                    $r->general()
                        ->confirmation($this->re("/ {2}{$this->opt($this->t('Conf:'))} *(.+)/", $text))
                        ->cancellation($this->re("/ {2}{$this->opt($this->t('Cnx:'))} *(.+)/", $text))
                    ;

                    $r->hotel()
                        ->name(preg_replace('/\s+/', ' ', $this->re("/ {2}{$this->opt($this->t('Htl:'))}([\s\S]+)\n +{$this->opt($this->t('Adr:'))}/", $text)))
                        ->address(preg_replace('/\s+/', ' ', $this->re("/ {2}{$this->opt($this->t('Adr:'))}([\s\S]+)\n +{$this->opt($this->t('City:'))}/", $text) .
                            ', ' . $this->re("/ {2}{$this->opt($this->t('City:'))} *(.+)/", $text)))
                        ->phone($this->re("/ {2}{$this->opt($this->t('Tel:'))} *(.+)/", $text), true, true)
                    ;

                    $total = $this->re("/ {2}{$this->opt($this->t('Price:'))} *(.+)/", $text);

                    if (preg_match("/^\s*(?<currency>[A-Z]{3}) *(?<total>\d[\d\., ]*)\s*$/i", $total, $m)
                        || preg_match("/^\s*(?<total>\d[\d\., ]*) *(?<currency>[A-Z]{3})\s*$/i", $total, $m)
                    ) {
                        $r->price()
                            ->total($m['total'])
                            ->currency(strtoupper($m['currency']));
                    }
                } else {
                    $email->removeItinerary($r);
                    $this->logger->debug("not enough info for hotel");

                    return;
                }
            }

            $type = $this->re("#{$this->opt($this->t('Room type'))} +(.+)#", $text);
            $desc = preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t('Room type'))} +[^\n]+\s+(.*?)\s+(?:{$this->opt($this->t('Occupancy'))}|{$this->opt($this->t('Cancellation policy'))})#s",
                    $text));

            if (!empty($type) || !empty($desc)) {
                $room = $r->addRoom();
                $room
                    ->setType($type, true, true)
                    ->setDescription($desc, true, true);
            }

            if ($rate = $this->re("#\n *{$this->opt($this->t('Rate'))}[ ]{2,}(.+(\n {40,}\S.+)*)#", $text)
            ) {
                $rows = array_filter(explode("\n", $rate));
                $ratesValues = [];

                foreach ($rows as $row) {
                    if (preg_match("/^[ ]*(?<rate>\w+ [\d\.]+|[\d\.]+ \w+) {$this->opt($this->t('per night'))}.*? {$this->opt($this->t('for'))} 0?(?<count>\d+) {$this->opt($this->t('night(s)'))}/", $row, $mat)) {
                        $ratesValues = array_merge($ratesValues, array_fill(0, $mat['count'], $mat['rate']));
                    }
                }
            }
            $r->general()
                ->cancellation(preg_replace("/\s+/", ' ', $this->re("#{$this->opt($this->t('Cancellation policy'))} +(.+(\n {50,}\S.*){0,5})#i", $text)), true, true);
        }

        // Booked
        $checkInDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Check-in'))}\s+(.+?)\s{3,}#", $text), $segmentDate));
        $checkOutDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Check-out'))}\s+(.+?)\s{3,}#", $text), $segmentDate));
        $time = $this->re("#In\-(\d+:\d+) *lt#i", $text) ?? $this->re("#In (\d+:\d+ (?:[AP]M)?)\b.*out \d+:\d+#i", $text);

        if (!empty($time) && !empty($checkInDate)) {
            $checkInDate = strtotime($time, $checkInDate);
        }

        $time = $this->re("#out\-(\d+:\d+) *lt#i", $text) ?? $this->re("#In (?:\d+:\d+ (?:[AP]M)?)\b.*out (\d+:\d+ (?:[AP]M)?)\b#i", $text);

        if (!empty($time) && !empty($checkOutDate)) {
            $checkOutDate = strtotime($time, $checkOutDate);
        }
        $r->booked()
            ->checkIn($checkInDate)
            ->checkOut($checkOutDate);

        if ($checkOutDate < $checkInDate && !empty($checkInDate) && !empty($checkOutDate)) {
            $checkOutDate2 = strtotime("+ 1 year", $checkOutDate);

            if ($checkOutDate2 - $checkInDate > 0 && $checkOutDate2 - $checkInDate < 60 * 60 * 24 * 45) {
                $r->booked()
                    ->checkOut($checkOutDate2);
            }
        }

        if ($guest = $this->re("#{$this->opt($this->t('Occupancy'))}[ ]+(\d{1,2}) (?:adult|guest)#i", $text)) {
            $r->booked()->guests($guest);
        }

        // Rooms
        if (!empty($ratesValues) && !empty($r->getCheckInDate()) && !empty($r->getCheckOutDate())) {
            $day = date_diff(
                date_create('@' . strtotime('00:00', $r->getCheckOutDate())),
                date_create('@' . strtotime('00:00', $r->getCheckInDate()))
            )->format('%a');

            if (count($ratesValues) == $day) {
                if (isset($room)) {
                    $room->setRates($ratesValues);
                } else {
                    $r->addRoom()->setRates($ratesValues);
                }
            }
        }

        if (preg_match("/{$this->opt($this->t('Estimated total'))}[ \:]+(?<currency>[A-Z]{3}) (?<total>[\d\.]+)\s/", $text, $m)
            || preg_match("/{$this->opt($this->t('Estimated total'))}[ \:]+(?<total>[\d\.]+) (?<currency>[A-Z]{3})\s/", $text, $m)
        ) {
            $r->price()
                ->total($m['total'])
                ->currency($m['currency']);
        }

        $taxes = $this->re("/\n *{$this->opt($this->t('Taxes'))} +(.+)/", $text);
        $total = $this->getTotalCurrency($taxes);

        if ($total['Total'] !== '') {
            $r->price()
                ->tax($total['Total']);
        }

        if (empty($r->getConfirmationNumbers()) && !empty($this->re("#^.+\s*(\S+(?: \S)*)\s+{$this->opt($this->t('Check-in'))}#i", $text))) {
            $r->general()->noConfirmation();
        }

        foreach ($email->getItineraries() as $it) {
            if ($it->getId() === $r->getId() || $it->getType() !== 'hotel') {
                continue;
            }

            if (serialize(array_diff_key($it->toArray(), ['travellers' => []])) === serialize(array_diff_key($r->toArray(), ['travellers' => []]))) {
                $travellers = array_diff(array_column($r->getTravellers(), 0), array_column($it->getTravellers(), 0));

                foreach ($travellers as $tr) {
                    $it->general()
                        ->traveller($tr);
                }
                $email->removeItinerary($r);

                break;
            }
        }

        $this->detectDeadLine($r);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("#No cancellation charge applies prior to (\d{1,2}:\d{2})\(local time\), up to (\d+) day prior to$#i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m[2] . ' day', $m[1]);
        } elseif (preg_match("#Cancel on (\d{1,2})([^\W\d]+)(\d{4}) by (\d{1,2}:\d{2}) gmt to avoid \d+ night\(s\) charge#i", $cancellationText, $m)
            || preg_match("#Cancel on (\d{1,2})([^\W\d]+)(\d{4}) by (\d{1,2}:\d{2}) lt\.no charge for cancellation#i", $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime(implode(' ', [$m[1], $m[2], $m[3]]) . ', ' . $m[4]));
        } elseif (preg_match("/Cxl fee if cxld less than (\d+) days before arrv/", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1] . ' days');
        }
    }

    private function parseEmailRental(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);
        //$this->logger->debug('Rental Segment: ' . "\n" . print_r($text, true));

        $r = $email->add()->rental();

        // General
        $confirmation = $this->re("#{$this->opt($this->t('Confirmation number'))}\s+(\w+)#", $text);

        if (!empty($confirmation)) {
            $this->allConfirmations[] = $confirmation;
            $r->general()
                ->confirmation($confirmation);
        }

        if (!empty($this->headers['travellers'])) {
            $r->general()->travellers($this->headers['travellers']);
        } elseif (preg_match("#\n *{$this->opt($this->t('Name'))} +(.+)#", $text, $m)) {
            $r->general()->traveller(trim($m[1]));
        }
        $segmentDate = null;

        if (preg_match("/^ *[\w\-]+ (\d+ \w+ \d{4}) *\n/u", $text, $m)) {
            $segmentDate = strtotime($this->normalizeDate($m[1]));
        }

        if (!empty($this->headers['issueDate'])) {
            $r->general()->date($this->headers['issueDate']);
            $this->emailDate = $this->headers['issueDate'];
        }
        $status = $this->re("#{$this->opt($this->t('Booking status'))} +(.+)#", $text);

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        // Program
        $accountNumbers = array_filter([
            preg_replace("#\s+#", " ", $this->re("#{$this->opt($this->t("Membership ID"))}\s+(.+)#", $text)),
        ]);

        if (!empty($accountNumbers)) {
            $r->program()
                ->accounts($accountNumbers, false);
        }

        // Pick Up
        $puDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Pick up'))}\s+(.*?)\s+{$this->opt($this->t('Location'))}\s+(.+)#", $text)));
        $r->pickup()
            ->date($puDate)
            ->location($this->re("#{$this->opt($this->t('Pick up'))}[^\n]+{$this->opt($this->t('Location'))}\s+(.+)#", $text));

        $phone = $this->re("#{$this->opt($this->t('Pick up'))}[^\n]+\s+[^\n]*?Tel\s+([\d- ]+)#", $text);

        if (!empty($phone)) {
            $r->pickup()
                ->phone($phone);
        }

        $doDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Drop off'))}\s+(.*?)\s+{$this->opt($this->t('Location'))}\s+(.+)#", $text)));

        if (!empty($doDate)) {
            $r->dropoff()
                ->date($doDate)
                ->location($this->re("#{$this->opt($this->t('Drop off'))}[^\n]+{$this->opt($this->t('Location'))}\s+(.+)#", $text));
        }

        $phone = $this->re("#{$this->opt($this->t('Drop off'))}[^\n]+\s+[^\n]*?{$this->opt($this->t('Tel'))}\s+([\d- ]+)#", $text);

        if (!empty($phone)) {
            $r->dropoff()
                ->phone($phone);
        }

        if ($doDate < $puDate && !empty($puDate) && !empty($doDate)) {
            $doDate2 = strtotime("+ 1 year", $doDate);

            if ($doDate2 - $puDate > 0 && $doDate2 - $puDate < 60 * 60 * 24 * 45) {
                $r->dropoff()
                    ->date($doDate2);
            }
        }

        $company = $this->re("/{$this->opt($this->t('Car Rental'))}\s*(\w+)/", $text);

        if (empty($company)) {
            $company = $this->re("#\n\s*(.*?)\s+{$this->opt($this->t('Confirmation number'))}#", $text);
        }

        $r->extra()->company($company);

        $r->car()
            ->type(preg_replace("/\s+/", ' ', $this->re("#{$this->opt($this->t('Car type'))}\s+(.+(?:\n {50,}.+){0,2})#", $text)));

        $payment = $this->re("/{$this->opt($this->t('Estimated total'))}\s+(.+)/", $text);
        // example: NZD 383.47, 3 Day(s)    |    USD 677.95, 14 Day(s)
        $paymentNormal = preg_replace("/^(.*),\s*\d+\s*{$this->opt($this->t('Day'))}.*$/", '$1', $payment);
        $total = $this->getTotalCurrency($paymentNormal);

        if ($total['Total'] !== '') {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        if (preg_match("/\s*{$this->opt($this->t('Drop off'))}\s*\d+\s*\w+\n\s*Name/", $text)) {
            $email->removeItinerary($r);
        }
    }

    private function parseEmailTransfer(Email $email, string $text, ?string $textPrev, ?string $textNext): void
    {
        // examples: it-166653513.eml

        $this->logger->debug(__FUNCTION__);
        // $this->logger->debug('Transfer Segment: ' . "\n" . print_r($text, true));

        $r = $email->add()->transfer();

        // General
        $r->general()
            ->noConfirmation();

        if (!empty($this->headers['travellers'])) {
            $r->general()->travellers($this->headers['travellers']);
        } elseif (preg_match("#\n *{$this->opt($this->t('Name'))} +(.+)#", $text, $m)) {
            $r->general()->traveller(trim($m[1]));
        }

        $segmentDate = preg_match("/^[ ]*[-[:alpha:]]+[ ]+(\d{1,2}[ ]+[[:alpha:]]+[ ]+\d{4})[ ]*\n/u", $text, $m) > 0
            ? strtotime($this->normalizeDate($m[1])) : null;

        if (!empty($this->headers['issueDate'])) {
            $r->general()->date($this->headers['issueDate']);
        }

        if (preg_match("/{$this->opt($this->t('Booking status'))} +(.+)/", $text, $m)) {
            $r->general()->status($m[1]);
        }

        $s = $r->addSegment();

        $dateDepVal = $this->re("/\n[ ]*{$this->opt($this->t('Departure'))}[ ]+(.{3,}?)[ ]+00[ ]*:[ ]*00\s/", $text);
        $dateDep = EmailDateHelper::parseDateRelative($dateDepVal, $segmentDate);

        $nameDep = $this->re("/\n[ ]*{$this->opt($this->t('Departure'))} .{3,} 00[ ]*:[ ]*00[ ]+(.{3,})/", $text);
        $nameArr = $this->re("/\n[ ]*{$this->opt($this->t('Arrival'))}[ ]+(.{3,})/", $text);

        $misc = $this->re("/\n *{$this->opt($this->t('Miscellaneous'))} +(.+(?:\n {50,}\S.+)*)/", $text);
        $miscName = preg_replace(["/ \d{6,}(?: +(?:Pu|Do) ?\d{2}:\d{2})?\s*(?:\*{2}[\s\S]+)?$/i", "/\s+/", "/([\/\.\w]+\s*[ ]*\d{2}\:?\d{2}Hrs\s*)/", "/(Tel\s*\d*)/", "/^([\/\.\w]+)/"], ['', ' ', '', '', ''], $misc);

        $timeDep = $this->re("/\bPu[ ]*(\d{2}:\d{2})\s*(?:\*{2}[\s\S]+)?$/i", $misc);

        if (empty($timeDep) && preg_match("/P\/U\s*[ ]*(\d{2})\:?(\d{2})Hrs/u", $misc, $m)) {
            $timeDep = $m[1] . ':' . $m[2];
        }
        $timeArr = $this->re("/\bDo[ ]*(\d{2}:\d{2})\s*$/i", $misc);

        $dateTimeDep = $timeDep !== null ? strtotime($timeDep, $dateDep) : null;
        $dateTimeArr = $timeArr !== null ? strtotime($timeArr, $dateDep) : null;

        if (preg_match("/\bDeparture[ ]+.{3,}\n+(?:.+\n+){0,1}.*\bArrival[ ]+(?<date>.{3,} \d{1,2}:\d{2})[ ]+(?<airport>\D+[ ]*,.+\))\s*\(/", $textPrev, $m)) {
            $dateTimePrev = strtotime($this->normalizeDate($m['date'], strtotime($this->re("/^([-[:alpha:]]+\s*\d{1,2}\s*[[:alpha:]]+\s*\d{4})\n/u", $text))));
            $namePrev = $m['airport'];
        } else {
            $dateTimePrev = $namePrev = null;
        }

        if (preg_match("/\bDeparture[ ]+(?<date>.{3,} \d{1,2}:\d{2})[ ]+(?<airport>\D+[ ]*,.+\))\s*\(.+\n+(?:.+\n+){0,1}.*\bArrival[ ]+.{3,}[ ]+\D+[ ]*,.+\)\s*\(/", $textNext, $m)) {
            $dateTimeNext = strtotime($this->normalizeDate($m['date'], strtotime($this->re("/^([-[:alpha:]]+\s*\d{1,2}\s*[[:alpha:]]+\s*\d{4})\n/u", $text))));
            $nameNext = $m['airport'];
        } else {
            $dateTimeNext = $nameNext = null;
        }

        if (stripos($text, 'Chauffeur driven car For Flight Departure') !== false) {
            if (!$dateTimeArr && $dateTimeNext) {
                $dateTimeArr = strtotime('-3 hours', $dateTimeNext);
            }

            if (!$nameArr && $nameNext) {
                $nameArr = $nameNext;
            }

            if (!$nameDep && $miscName) {
                $nameDep = $miscName;
            }
        } elseif (stripos($text, 'Chauffeur driven car For Flight Arrival') !== false) {
            if (!$dateTimeDep && $dateTimePrev) {
                $dateTimeDep = strtotime('+30 minutes', $dateTimePrev);
            }

            if (!$nameDep && $namePrev) {
                $nameDep = $namePrev;
            }

            if (!$nameArr && $miscName) {
                $nameArr = $miscName;
            }
        }

        if ($dateTimeDep) {
            $s->departure()->date($dateTimeDep);
        }

        if ($dateTimeArr) {
            //$s->arrival()->date($dateTimeArr <= $dateTimeDep ? strtotime('+1 hours', $dateTimeArr) : $dateTimeArr);
            // mta/it-220443362.eml
            $dateTimeArr <= $dateTimeDep ? $s->arrival()->noDate() : $s->arrival()->date($dateTimeArr);
        } else {
            $s->arrival()->noDate();
        }

        $pattern = "/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)$/"; // Sydney NS (SYD)

        if (preg_match($pattern, $nameDep, $m)) {
            $nameDep = $m['name'];
            $codeDep = $m['code'];
        } else {
            $codeDep = null;
        }

        if (preg_match($pattern, $nameArr, $m)) {
            $nameArr = $m['name'];
            $codeArr = $m['code'];
        } else {
            $codeArr = null;
        }

        $s->departure()->name($nameDep);
        $s->arrival()->name($nameArr);

        if ($codeDep) {
            $s->departure()->code($codeDep);
        }

        if ($codeArr) {
            $s->arrival()->code($codeArr);
        }
    }

    private function parseEmailTrain(Email $email, $segments): void
    {
        $this->logger->debug(__FUNCTION__);
//         $this->logger->debug('Train Segments: ' . "\n" . print_r($segments, true));

        $f = $email->add()->train();

        if (!empty($this->headers['travellers'])) {
            $f->general()->travellers($this->headers['travellers']);
        }

        if (!empty($this->headers['issueDate'])) {
            $f->general()->date($this->headers['issueDate']);
        }

        if (!empty($this->headers['tickets'])) {
            $f->setTicketNumbers($this->headers['tickets'], false);
        }

        $confirmations = [];

        $accounts = [];

        foreach ($segments as $i => $info) {
            $s = $f->addSegment();
            // Extra
            if (
                preg_match('/^(?:.*\n){0,3}\s*' . $this->opt($this->t("Train")) . ' (?<service>.+)[ ]+(?<number>\d{1,6})(?:\s*\n| {3,})/', $info, $m)
                || preg_match('/^(?:.*\n){0,3}\s*' . $this->opt($this->t("Train")) . ' (?<service>[A-Z]+)(?<number>\d{1,5})(?:\s*\n| {3,})/', $info, $m)
            ) {
                $s->extra()
                    ->service($m['service'])
                    ->number($m['number']);

                if (preg_match("/sncf/i", $s->getServiceName())) {
                    $s->setDepGeoTip('Europe');
                    $s->setArrGeoTip('Europe');
                }

                if (
                    // Eurostar Reference: QK4PD3
                    preg_match("/\s{2,}" . $m['service'] . " +" . $this->opt($this->t("Reference")) . "[ ]*:[ ]*(?<pnr>[A-Z\d]{6})\s*$/m", $info, $m)
                    || preg_match("/ {2,}" . $this->opt($this->t("Reference")) . "[ ]*:?[ ]*(?<pnr>[A-Z\d]{6,})\s*$/m", $info, $m)
                ) {
                    $confirmations[] = $m['pnr'];
                }
            }

            $dep = $this->findСutSection($info, $this->t('Departure'), $this->t('Arrival'));
            $arr = $this->findСutSection($info, $this->t('Arrival'), $this->t('Duration'));

            if (empty($arr)) {
                $arr = $this->findСutSection($info, $this->t('Arrival'), $this->t('Class'));
            }

            $segmentDate = null;

            if (preg_match("/^ *[\w\-]+ (\d+ \w+ \d{4}) *\n/u", $info, $m)) {
                $segmentDate = strtotime($this->normalizeDate($m[1]));
            }

            $segmentsInfo = ['Dep' => $dep, 'Arr' => $arr];

            // Departure and Arrival
            $patterns['dayMonth'] = '(?<Day>\d{1,2})[ ]+(?<Month>[^\d\W]{3,})'; // 27 November
            $patterns['time'] = '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?'; // 02:15 PM
            $patterns['name'] = '.+(?:\n {40,}.+){0,2}'; // TOKYO INTL HANEDA TOKYO

            $re1 = "/(?<Date>{$patterns['dayMonth']}[ ]+(?<Time>{$patterns['time']}))\s+(?<Name>{$patterns['name']})/iu";

            foreach ($segmentsInfo as $key => $str) {
                if (preg_match($re1, $str, $m)
                ) {
                    $m['Name'] = preg_replace("/ {3,}{$this->opt($this->t("Platform"))}[ :]+[\s\S]+/", '', $m['Name']);

                    if ($key === 'Dep') {
                        $point = $s->departure();
                    } else {
                        $point = $s->arrival();
                    }
                    $point->name(str_replace(['(', ')'], '', $m['Name']));

                    if ($segmentDate) {
                        $point->date(strtotime($m['Time'], EmailDateHelper::parseDateRelative(
                            $m['Day'] . ' ' . MonthTranslate::translate($m['Month'], $this->lang), $segmentDate
                        )));
                    }
                }
            }

            // Extra
            if (preg_match('/' . $this->opt($this->t('Duration')) . '\s+(\d{1,2}:\d{2})/u', $info, $m)) {
                $s->extra()->duration($m[1]);
            }

            if (preg_match('/' . $this->opt($this->t('Duration')) . '\s+\d{1,2}:\d{2}h?\s*\((.+)\)/u', $info, $m)) {
                if (preg_match("#(?:non[\s\-]*stop|sin\s+paradas|Sans\s+escale)#i", $m[1])) {
                    $s->extra()->stops(0);
                } elseif (preg_match("#(\d+)\s+(?:stop|paradas)#i", $m[1], $v)) {
                    $s->extra()->stops((int) $v[1]);
                }
            }

            if (preg_match('/' . $this->t('Booking status') . '\s+((?:Confirmado|Confirmed|Confirmé)).*/ui', $info, $m)) {
                $s->extra()->status($m[1]);
            }

            if (preg_match('/' . $this->t('Class') . '[ ]+(.+?)[ ]*(?:\(|\n|$)/ui', $info, $m)) {
                $s->extra()->cabin($m[1]);
            }

            if (preg_match('/' . $this->t('Class') . '[ ]+.+?\s*\(([A-Z]{1,2})\)/ui', $info, $m)) {
                $s->extra()->bookingCode($m[1]);
            }

            if (preg_match('/' . $this->opt($this->t('Equipment')) . '[ ]+(.+)/', $info, $m)) {
                $s->extra()
                    ->type($m[1]);
            }

            $seatInfo = $this->re("/\n *{$this->opt($this->t('Seat'))}[ ]+(.+?)\s*\n\s*(?:{$this->opt($this->t('Baggage allowance'))}|{$this->opt($this->t('Check in completed by'))}|{$this->opt($this->t('Equipment'))}|Details)/si",
                $info);

            if (preg_match_all('/(?:^ *| {6,})' . $this->opt($this->t("Coach")) . ' ([A-Z\d]+) +' . $this->opt($this->t("Seat")) . ' ([A-Z\d]+)\b/m', $seatInfo, $m)) {
                $s->extra()->seats($m[2]);
                $s->extra()->car($m[1][0]);
            }

            $ffText = $this->re("#{$this->opt($this->t('Frequent Flyer number'))}[ ]+(.+?)(?:\n\n|$)#is", $info);
            // example 1: SQ8791827497 for CHUA/WAH CHOON MR
            // example 2: NH SQ8791827497
            if (preg_match_all("#^[ ]*(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) )?([A-Z\d]{7,}?)(?:\s+{$this->opt($this->t('for'))}\b|$)#m", $ffText, $m)) {
                $accounts = array_merge($accounts, $m[1]);
            }
        }

        if (!empty($accounts) > 0) {
            $f->program()
                ->accounts(array_values(array_filter(array_unique($accounts))), false);
        }

        $confirmations = array_unique($confirmations);

        foreach ($confirmations as $conf) {
            $this->allConfirmations[] = $conf;
            $f->general()
                ->confirmation($conf);
        }

        if (empty($confirmations)) {
            $f->general()
                ->noConfirmation();
        }

        // Price

        if (!empty($this->headers['flightTotal']) || !empty($this->headers['flightCurrency'])) {
            $f->price()
                ->currency($this->headers['flightCurrency'])
                ->total($this->headers['flightTotal']);
        }
    }

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (
            preg_match("#(?<c>[A-Z]{3})\s+(?<t>[\d\.\,]+)\s#", $node, $m)
            || preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function opt($field)
    {
        $field = (array) $field;
        $field = array_map(function ($v) {return preg_quote($v, '/'); }, $field);

        return '(?:' . implode("|", $field) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date, $relativeDate = null): string
    {
        if (!empty($relativeDate)) {
            $year = date("Y", $relativeDate);
        } else {
            $year = date("Y", $this->emailDate);
        }

        $in = [
            '#^\s*\w+ +(\d+) +(\w+) +(\d+)\s*$#u',
            '#^\s*(\d+)\s*(\D+?)\s*$#',
            '#^\s*(\d+)\s*(\D+?)\s*(\d+:\d+\s*(?:[ap]m)?)\s*$#i',
            '#^(\d+)\s*(\w+)\s*(\d+\:\d+)$#', //17 August 12:35
            '#^(\d{4})\D+(\d{1,2})\D+(\d{1,2})\D+$#u', //2023年05月11日
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 ' . $year,
            '$1 $2 ' . $year . ' $3',
            '$1 $2 ' . $year . ', $3',
            '$3.$2.$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function dateStringToEnglish($date): string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if (($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang))) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function findСutSection($input, $searchStart, $searchFinish): ?string
    {
        $searchStarts = [$searchStart];

        foreach ($searchStarts as $searchStart) {
            $inputResult = null;

            if (empty($searchStart)) {
                $left = $input;
            } elseif (is_array($searchStart)) {
                foreach ($searchStart as $ss) {
                    $left = mb_strstr($input, $ss);

                    if (!empty($left)) {
                        $left = mb_substr($left, mb_strlen($ss));

                        break;
                    }
                }
            } else {
                $left = mb_strstr($input, $searchStart);
                $left = mb_substr($left, mb_strlen($searchStart));
            }

            if (is_array($searchFinish)) {
                foreach ($searchFinish as $sf) {
                    $ir = mb_strstr($left, $sf, true);

                    if (!empty($ir)) {
                        $inputResult = $ir;

                        break;
                    }
                }
            } else {
                $inputResult = mb_strstr($left, $searchFinish, true);
            }
        }

        return $inputResult;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

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

    private function strposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
