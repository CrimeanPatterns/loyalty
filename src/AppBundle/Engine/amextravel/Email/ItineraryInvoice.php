<?php

namespace AwardWallet\Engine\amextravel\Email;

class ItineraryInvoice extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-10008641.eml, amextravel/it-10024772.eml, amextravel/it-15.eml, amextravel/it-1630541.eml, amextravel/it-1630542.eml, amextravel/it-1630834.eml, amextravel/it-1631146.eml, amextravel/it-1998942.eml, amextravel/it-2418068.eml, amextravel/it-2621650.eml, amextravel/it-3879765.eml, amextravel/it-677129167.eml, amextravel/it-69560376.eml, amextravel/it-9863770.eml, amextravel/it-9865511.eml, amextravel/it-9865541.eml, amextravel/it-9914703.eml, amextravel/it-9921062.eml";

    public $reFrom = ["mytrips.amexgbt.com", "@welcome.aexp.com"];

    public $reBodyHTML = [
        'es'  => ['Gracias por elegir American Express Global Business Travel', 'Estamos encantados de servirle'],
        'en'  => ['Please check your attached travel itinerary to confirm', 'Your detailed travel itinerary document is attached'],
        'en2' => ['Please find attached your invoice', 'Your detailed travel invoice document is attached'],
        'en3' => ['Please find your invoice attached', 'Your detailed travel invoice document is attached'],
        'en4' => ['American Express Global Business Travel', 'Your American Express Global Business Travel Record Locator is'],
        'en5' => ['Please find the attached Travel documents for your', 'Attachment'],
    ];
    public $reBodyPDF = [
        'es'  => ['Itinerario Clave de reservación', 'American Express Global Business Travel'],
        'fr'  => ["code de réservation d'itinéraire", 'American Express Global Business Travel'],
        'en'  => ['Itinerary Booking Reference', 'American Express Global Business Travel'],
        'en2' => ['Invoice Booking Reference', 'American Express Global Business Travel'],
        'en3' => ['Invoice Booking Reference', 'by American Express Travel'],
        'en4' => ['Travel Arrangements for', 'American Express Global Business Travel'],
    ];

    public $reSubject = [
        '/ITINERARIO de Viaje para\s+.+?\s+Ref\s+[A-Z\d]{5,}/', // es
        '#ITINERARY\s+for\s+.+?\s+Ref\s+[A-Z\d]{5,}#',
        '#INVOICE\s+\d+\s+for\s+.+?\s+Ref\s+[A-Z\d]{5,}#',
    ];

    public static $dict = [
        'es' => [
            'Itinerary Booking Reference' => 'Itinerario Clave de reservación',
            'Travel Arrangements for'     => 'Arreglos de viaje para',
            'Generated'                   => 'Generado',
            'Not Assigned'                => ['No aplica', 'No asignado'],
            'Status'                      => 'Estatus',

            // FLIGHT
            'to'                    => 'a',
            'Airline Booking Ref'   => 'Código de referencia de la aerolínea',
            'Carrier'               => 'Aerolínea',
            'Flight'                => 'Vuelo',
            'Operated by'           => 'Operado por',
            'Origin'                => 'Origen',
            'Departing'             => 'Saliendo',
            'Departure Terminal'    => 'Terminal de Salida',
            'Destination'           => 'Destino',
            'Arriving'              => 'Llegando',
            'Arrival Terminal'      => 'Terminal de Llegada',
            'Class'                 => 'Clase',
            'Aircraft Type'         => 'Tipo de Aeronave',
            'Meal Service'          => 'Servicio de Alimentos',
            'Frequent Flyer Number' => 'Número de Viajero Frecuente',
            'Distance'              => 'Distancia',
            'Seat'                  => 'Asiento',
            'Estimated Time'        => 'Tiempo Estimado',
            'Number of Stops'       => 'Número de Paradas',

            // HOTEL
            'Address'                => 'Dirección',
            'Phone'                  => ['TEL', 'Teléfono'],
            'Fax'                    => 'Fax',
            'Check In Date'          => 'Fecha de Check in',
            'Check Out Date'         => 'Fecha de Check out',
            'Reference Number'       => 'Número de Referencia',
            'Additional Information' => 'Información Adicional',
            //			'Special Information' => '',
            'Number Of Rooms' => 'Número de Habitaciones',
            'Guaranteed'      => 'Garantizado',
            'Rate'            => 'Tarifa',
            'Membership ID'   => 'Número de Membresía',

            // CAR
            //			'Pickup' => '',
            //			'Location' => '',
            //			'Date and Time' => '',
            //			'Drop Off' => '',
            //			'Car Type' => '',
            //			'Approximate Total Rate' => '',

            // TRAIN
            //			'Train' => '',
        ],
        'fr' => [
            "Itinerary Booking Reference" => "code de réservation d'itinéraire",
            "Travel Arrangements for"     => "Réservations de voyage pour",
            "Generated"                   => "Généré",
            //			"Not Assigned" => "",
            "Status" => "Statut",

            // FLIGHT
            //			"to" => "",
            //			"Airline Booking Ref" => "",
            //			"Carrier" => "",
            //			"Flight" => "",
            //			"Operated by" => "",
            //			"Origin" => "",
            //			"Departing" => "",
            //			"Departure Terminal" => "",
            //			"Destination" => "",
            //			"Arriving" => "",
            //			"Arrival Terminal" => "",
            //			"Class" => "",
            //			"Aircraft Type" => "",
            //			"Meal Service" => "",
            //			"Frequent Flyer Number" => "",
            //			"Distance" => "",
            //			"Seat" => "",
            //			"Estimated Time" => "",
            //			"Number of Stops" => "",

            // HOTEL
            "Address"                => "Adresse",
            "Phone"                  => "Téléphone",
            "Fax"                    => "Télécopieur",
            "Check In Date"          => "Date d'arrivée",
            "Check Out Date"         => "Date de départ",
            "Reference Number"       => "Numéro de référence",
            "Additional Information" => "Renseignements supplémentaires",
            "Special Information"    => "Renseignements spéciaux",
            "Number Of Rooms"        => "Nombre de chambres",
            "Guaranteed"             => "Garanti",
            "Rate"                   => "Prix total approximatif",
            "Membership ID"          => "Numéro de fidélité",

            // CAR
            "Pickup"                 => "Prise en charge",
            "Location"               => "Emplacement",
            "Date and Time"          => "Date et heure",
            "Drop Off"               => "Lieu de restitution",
            "Car Type"               => "Type de véhicule",
            "Approximate Total Rate" => "Prix total approximatif",

            // TRAIN
            //			"Train" => "",
        ],
        'en' => [
            'Travel Arrangements for'     => ['Passenger Name(s)', 'Travel Arrangements for'],
            'Itinerary Booking Reference' => ['Itinerary Booking Reference', 'Invoice Booking Reference', 'Booking Reference'],
            'Operated by'                 => ['Operated by', 'Operated By'],
            'Not Assigned'                => ['Not Assigned', 'Not Applicable'],
            'Flight'                      => ['Flight', 'Flugnummer'],
            'viarailVariants'             => ['VIA RAIL', 'VIARAIL'],
        ],
    ];

    private $lang;
    private $pdf;
    private $pdfNamePattern = '.*pdf';
    private $patterns = [
        'namePrefixes' => '(?:MISS|MRS|MR|MS|DR|MSTR)',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $type = [];

        $textPDF = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($htm = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $html .= $htm;
                }
            }

            if (!empty($html)) {
                $type[] = 'Pdf';
                $textPDF = $html;
                $this->pdf = clone $this->http;
                $NBSP = chr(194) . chr(160);
                $this->pdf->SetEmailBody(str_replace($NBSP, ' ', html_entity_decode($html)));
            }
        }
        $textPDF = preg_replace('/([^:\s])[ ]*[:]+\n+ *([-[:alpha:]]+[ ]+\d{1,2}[ ]+[[:alpha:]]{3,}[ ]+\d{4}\b)/u', '$1: $2', $textPDF);

        $body = $this->http->Response['body'];
        $this->assignLang($body);
        $res = $this->parseEmailFlight($textPDF);

        foreach ($res as $r) {
            if (count($r) > 0) {
                $its[] = $r;
            }
        }
        $res = $this->parseEmailHotel($textPDF);

        foreach ($res as $r) {
            if (count($r) > 0) {
                $its[] = $r;
            }
        }
        $res = $this->parseEmailCar($textPDF);

        foreach ($res as $r) {
            if (count($r) > 0) {
                $its[] = $r;
            }
        }
        $res = $this->parseEmailRail($textPDF);

        foreach ($res as $r) {
            if (count($r) > 0) {
                $its[] = $r;
            }
        }
        $res = $this->parseEmailOtherCancellation();

        foreach ($res as $r) {
            if (count($r) > 0) {
                $its[] = $r;
            }
        }

        //if just attachment
        if ((count($its) == 0) && !empty($textPDF)) {
            $this->assignLang($textPDF, true);
            $segments = $this->splitSegmentPdf($textPDF);

            $res = $this->parseEmailFlightPDF($textPDF, $segments);

            foreach ($res as $r) {
                if (count($r) > 0) {
                    $its[] = $r;
                }
            }
            $res = $this->parseEmailHotelPDF($textPDF, $segments);

            foreach ($res as $r) {
                if (count($r) > 0) {
                    $its[] = $r;
                }
            }
            $res = $this->parseEmailCarPDF($textPDF, $segments);

            foreach ($res as $r) {
                if (count($r) > 0) {
                    $its[] = $r;
                }
            }
            $res = $this->parseEmailRailPDF($textPDF, $segments);

            foreach ($res as $r) {
                if (count($r) > 0) {
                    $its[] = $r;
                }
            }
        } else {
            $type[] = 'Html';
        }

        $a = explode('\\', __CLASS__);
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . implode('', array_unique($type)) . ucfirst($this->lang),
        ];

        if (!empty($textPDF)) {
            $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Total Invoice Charge'))}\s+([A-Z]{3}\s+[\d\.]+)#", $textPDF));

            if ($tot['Total'] !== null) {
                $result['parsedData']['TotalCharge'] = ['Amount' => $tot['Total'], 'Currency' => $tot['Currency']];
            }
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'amexgbt.com') or contains(@href,'axexplore.com')] | //img[contains(@src,'amexgbt.com') or contains(@src,'americanexpress.com') or alt='GBT Logo']")->length > 0) {
            $body = $this->http->Response['body'];

            return $this->assignLang($body);
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs[0])) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            return $this->assignLang($textPdf, true);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $prov = false;

        if (isset($headers['from'])) {
            if ($this->detectEmailFromProvider($headers['from']) === true) {
                $prov = true;
            }
        }

        if ($prov && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        if (strpos($from, 'American Express Global Business Travel') !== false) {
            return true;
        }

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
        $types = 9;
        $cnt = count(self::$dict) * $types;

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

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                    foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                        if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])
                            && (isset($tsJ['Seats']) || isset($tsI['Seats']))
                        ) {
                            $new = "";

                            if (isset($tsJ['Seats'])) {
                                $new .= "," . $tsJ['Seats'];
                            }

                            if (isset($tsI['Seats'])) {
                                $new .= "," . $tsI['Seats'];
                            }
                            $new = implode(",", array_filter(array_unique(array_map("trim", explode(",", $new)))));
                            $its[$j]['TripSegments'][$flJ]['Seats'] = $new;
                            $its[$i]['TripSegments'][$flI]['Seats'] = $new;
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));

                if (isset($its[$j]['Passengers']) || isset($its[$i]['Passengers'])) {
                    $new = "";

                    if (isset($its[$j]['Passengers'])) {
                        $new .= "," . implode(",", $its[$j]['Passengers']);
                    }

                    if (isset($its[$i]['Passengers'])) {
                        $new .= "," . implode(",", $its[$i]['Passengers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['Passengers'] = $new;
                }

                if (isset($its[$j]['AccountNumbers']) || isset($its[$i]['AccountNumbers'])) {
                    $new = "";

                    if (isset($its[$j]['AccountNumbers'])) {
                        $new .= "," . implode(",", $its[$j]['AccountNumbers']);
                    }

                    if (isset($its[$i]['AccountNumbers'])) {
                        $new .= "," . implode(",", $its[$i]['AccountNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['AccountNumbers'] = $new;
                }

                if (isset($its[$j]['TicketNumbers']) || isset($its[$i]['TicketNumbers'])) {
                    $new = "";

                    if (isset($its[$j]['TicketNumbers'])) {
                        $new .= "," . implode(",", $its[$j]['TicketNumbers']);
                    }

                    if (isset($its[$i]['TicketNumbers'])) {
                        $new .= "," . implode(",", $its[$i]['TicketNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['TicketNumbers'] = $new;
                }

                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function splitSegmentPdf($textPDF): array
    {
        $segments = [];
        $detectFlight = ".+\([A-Z]{3}\) {$this->opt($this->t('to'))} .+ \([A-Z]{3}\)";
        $detectCar = "[ ]*\d{1,2}:\d{2} (?:.*\n){1,3}\s*{$this->opt($this->t('Pickup'))}:\s*\n";
        $detectHotel = ".+\n\s*{$this->opt($this->t('Address'))}:";
        $detectRail = "[ ]*\d{1,2}:\d{2}(?:[ ]*[AaPp]\.?[Mm]\.?)?\s+(?:.*\n){1,3}^[ ]*{$this->opt($this->t('Train'))}:";
        $nodes = $this->splitter('/^( *[-[:alpha:]]+[ ]+\d{1,2}[ ]+[[:alpha:]]{3,}[ ]+\d{4}\b).*/mu', $textPDF);

        foreach ($nodes as $value) {
            $segments = array_merge($segments, $this->splitter("#^(" . $detectFlight . "|" . $detectCar . "|" . $detectHotel . "|" . $detectRail . ")#m", $value));
        }

        return $segments;
    }

    private function parseEmailFlightPDF($textPDF, $segments): array
    {
        $its = [];

        $resDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Generated'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $textPDF)));
        $pax = $this->re("#{$this->opt($this->t('Travel Arrangements for'))}[:\s]+([[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+\b)(?: [[:upper:]][[:lower:]]|[ ]{2}|$)#mu", $textPDF);
        $tripNumber = $this->re("#{$this->opt($this->t('Itinerary Booking Reference'))}[: ]+([A-Z\d]{5,})(?:[ ]{2}|$)#m", $textPDF);

        foreach ($segments as $text) {
            if (!empty($this->re("#({$this->opt($this->t('Flight'))}[ ]*[:]+[ ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+\d+)#", $text))) {
                $it = ['Kind' => 'T', 'TripSegments' => []];
                // Status
                $it['Status'] = $this->re("#{$this->opt($this->t('Status'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text);
                // Passengers
                $it['Passengers'][] = $pax;
                // TripNumber
                if ($tripNumber) {
                    $it['TripNumber'] = $tripNumber;
                }
                // ReservationDate
                $it['ReservationDate'] = $resDate;
                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_AIR;

                $seg = [];

                // AirlineName
                // FlightNumber
                if (preg_match('/' . $this->opt($this->t('Flight')) . '[ ]*[:]+[ ]*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?[ ]*(?<flightNumber>\d+)/', $text, $matches)) {
                    if (!empty($matches['airline'])) {
                        $seg['AirlineName'] = $matches['airline'];
                    }
                    $seg['FlightNumber'] = $matches['flightNumber'];
                }

                // RecordLocator
                $it['RecordLocator'] = $this->re("#{$this->opt($this->t('Airline Booking Ref'))}[ ]*[:]+[ ]*([A-Z\d]{5,})(?:[ ]{2}|$)#m", $text);

                if (empty($it['RecordLocator']) && !empty($this->re("#{$this->opt($this->t('Airline Booking Ref'))}[ ]*[:]+[ ]*({$this->opt($this->t('Not Assigned'))})#", $text))) {
                    $it['RecordLocator'] = CONFNO_UNKNOWN;
                }

                // Operator
                $seg['Operator'] = $this->re("#{$this->opt($this->t('Operated by'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text);
                // DepName
                // DepCode
                $node = $this->re("#{$this->opt($this->t('Origin'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text);

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                } else {
                    $seg['DepName'] = $node;
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
                // ArrName
                // ArrCode
                $node = $this->re("#{$this->opt($this->t('Destination'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text);

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                } else {
                    $seg['ArrName'] = $node;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
                // DepartureTerminal
                $node = $this->re("#{$this->opt($this->t('Departure Terminal'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text);

                if (preg_match("/TERMINAL[ ]+(\w[\w ]*)/i", $node, $m)) {
                    $seg['DepartureTerminal'] = $m[1];
                } elseif (!preg_match("/{$this->opt($this->t('Not Assigned'))}/i", $node)) {
                    $seg['DepartureTerminal'] = $node;
                }
                // ArrivalTerminal
                $node = $this->re("#{$this->opt($this->t('Arrival Terminal'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text);

                if (preg_match("/TERMINAL[ ]+(\w[\w ]*)/i", $node, $m)) {
                    $seg['ArrivalTerminal'] = $m[1];
                } elseif (!preg_match("/{$this->opt($this->t('Not Assigned'))}/i", $node)) {
                    $seg['ArrivalTerminal'] = $node;
                }
                // DepDate
                $seg['DepDate'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Departing'))}[\s:]+(.+?)\s*(?:{$this->opt($this->t('Departure Terminal'))}|$)#m", $text)));
                // ArrDate
                $seg['ArrDate'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Arriving'))}[\s:]+(.+?)\s*(?:{$this->opt($this->t('Arrival Terminal'))}|$)#m", $text)));

                // Cabin
                if (!empty($str = $this->re("#{$this->opt($this->t('Class'))}[ ]*[:]+[ ]*(?i)(Economy|Business|First Class)(?-i)(?: {$this->opt($this->t('Distance'))}|[ ]{2}|$)#m", $text))) {
                    $seg['Cabin'] = $str;
                }
                // Duration
                if (!empty($str = $this->re("#{$this->opt($this->t('Estimated Time'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text))) {
                    $seg['Duration'] = $str;
                }
                // TraveledMiles
                if (!empty($str = $this->re("#{$this->opt($this->t('Distance'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text))) {
                    $seg['TraveledMiles'] = $str;
                }
                // Aircraft
                if (!empty($str = $this->re("#{$this->opt($this->t('Aircraft Type'))}[ ]*[:]+[ ]*(.+?)(?: {$this->opt($this->t('Seat'))}|[ ]{2}|$)#m", $text))) {
                    $seg['Aircraft'] = $str;
                }
                // Meal
                if (!empty($str = $this->re("#{$this->opt($this->t('Meal Service'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text))
                    && !preg_match("/{$this->opt($this->t('Not Assigned'))}/i", $str)
                ) {
                    $seg['Meal'] = $str;
                }
                // Stops
                if (($str = $this->re("/{$this->opt($this->t('Number of Stops'))}[ ]*[:]+[ ]*(\d{1,3})(?:[ ]{2}|$)/m", $text)) !== null) {
                    $seg['Stops'] = $str;
                }
                // AccountNumbers
                if (!empty($str = $this->re("#{$this->opt($this->t('Frequent Flyer Number'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text))
                    && !preg_match("/{$this->opt($this->t('Not Assigned'))}/i", $str)
                ) {
                    $it['AccountNumbers'][] = $str;
                }
                // Seats
                if (!empty($str = $this->re("#{$this->opt($this->t('Seat'))}[ ]*[:]+[ ]*(\d[,; A-Z\d]*?[A-Z])(?:[ ]{2}|$)#m", $text))) {
                    $seg['Seats'] = preg_split('/\s*[,;]+\s*/', $str);
                    $seg['Seats'] = array_filter($seg['Seats'], function ($v) {return (preg_match('/^\s*\d{1,3}[A-Z]\s*$/', $v)) ? true : false; });
                }

                if (isset($seg['FlightNumber'])) {
                    // BookingClass
                    if (!empty($str = $this->re("#^ *{$seg['FlightNumber']}\s+([A-Z]{1,2})\s+{$this->opt($this->t('Class'))}#m", $textPDF))) {
                        $seg['BookingClass'] = $str;
                    }
                    // TicketNumbers
                    if (!empty($str = $this->re("#{$this->opt($this->t('Ticket Number'))}[ ]+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,2})(?:.+\n+){3,9}[ 0]*{$seg['FlightNumber']}[ ]+[A-Z]{1,2}[ ]+{$this->opt($this->t('Class'))}#", $textPDF))) {
                        $it['TicketNumbers'][] = $str;
                    }
                }

                $it['TripSegments'][] = $seg;

                $its[] = $it;
            }
        }

        $its = $this->mergeItineraries($its);

        $this->addFlightSumsFromPDF($its, $textPDF);

        return $its;
    }

    private function parseEmailFlight($textPDF): array
    {
        $its = [];

        $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler:'))}]/following::text()[normalize-space()][1]");
        $pax = preg_split('/\s*,\s*/', $pax);
        $pax = preg_replace("/\s+{$this->patterns['namePrefixes']}\s*$/", '', $pax);
        $pax = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1', $pax);
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space(.)!=''][1]");

        $xpath = "//text()[{$this->eq($this->t('Flight Information'))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if (0 === $nodes->length) {
            $this->logger->info("Segments didn't found by xpath: {$xpath}");
        }

        foreach ($nodes as $root) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            // RecordLocator
            $it['RecordLocator'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Airline Booking Ref'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, '/^[:\s]*([A-Z\d]{5,})$/');

            if (empty($it['RecordLocator']) && !empty($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Airline Booking Ref'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#({$this->opt($this->t('Not Assigned'))})#"))) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }
            // Status
            $it['Status'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Status'))}]/following::text()[normalize-space(.)!=''][1]", $root);

            if (empty($it['Status'])) {
                $it['Status'] = $this->http->FindSingleNode("./following::table[1][not({$this->contains($this->t('Information'))})]/descendant::text()[{$this->starts($this->t('Status'))}]/following::text()[normalize-space(.)!=''][1]", $root);
            }
            // Passengers
            $it['Passengers'] = $pax;
            // TripNumber
            $it['TripNumber'] = $tripNumber;
            // TripCategory
            $it['TripCategory'] = TRIP_CATEGORY_AIR;

            $seg = [];
            // FlightNumber
            // AirlineName
            $node = $this->http->FindSingleNode("descendant::text()[({$this->starts($this->t('Flight'))}) and not({$this->contains($this->t('Flight Information'))})]/following::text()[string-length(normalize-space(.)) > 2][1]", $root);

            if (preg_match('/^[:\s]*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $carrier = strtoupper($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Carrier'))}]/following::text()[normalize-space(.)!=''][1]", $root));
            $it['TicketNumbers'][] = str_replace(" ", "", $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ticket Number'))}]/following::text()[normalize-space(.)!=''][1][{$this->contains($carrier)}]/following::text()[normalize-space(.)!=''][1]", null, true, "#^(?::\s*)?\s*([\d ]+)\s*$#"));
            $it['TotalCharge'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Ticket Number'))}]/following::text()[normalize-space(.)!=''][1][{$this->contains($carrier)}]/following::text()[normalize-space(.)!=''][2]", null, true, "#^(?::\s*)?\s*([\d\.]+)\s*$#");

            // Operator
            $seg['Operator'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Operated by'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // Cabin
            $seg['Cabin'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Class'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // DepName
            // DepCode
            $node = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Origin'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");

            if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
            } else {
                $seg['DepName'] = $node;
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }
            // DepartureTerminal
            $node = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure Terminal'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");

            if (preg_match("/TERMINAL[ ]+(\w[\w ]*)/i", $node, $m)) {
                $seg['DepartureTerminal'] = $m[1];
            } elseif (!preg_match("/{$this->opt($this->t('Not Assigned'))}/i", $node)) {
                $seg['DepartureTerminal'] = $node;
            }
            // ArrName
            // ArrCode
            $node = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Destination'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");

            if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
            } else {
                $seg['ArrName'] = $node;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            // ArrivalTerminal
            $node = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrival Terminal'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");

            if (preg_match("/TERMINAL[ ]+(\w[\w ]*)/i", $node, $m)) {
                $seg['ArrivalTerminal'] = $m[1];
            } elseif (!preg_match("/{$this->opt($this->t('Not Assigned'))}/i", $node)) {
                $seg['ArrivalTerminal'] = $node;
            }
            // DepDate
            $seg['DepDate'] = strtotime($this->normalizeDate(trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departing'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ")));
            // ArrDate
            $seg['ArrDate'] = strtotime($this->normalizeDate(trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arriving'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ")));
            // Duration
            $seg['Duration'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Estimated Time'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // Stops
            $seg['Stops'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Number of Stops'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // Seats
            $seg['Seats'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Seat'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");

            if (empty($seg['Seats'])) {
                $seg['Seats'] = trim($this->http->FindSingleNode("./following::table[1][not({$this->contains($this->t('Information'))})]/descendant::text()[{$this->starts($this->t('Seat'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            }

            if (!preg_match("/^\s*(\d{1,3}[A-Z])\s*$/", $seg['Seats'])) {
                unset($seg['Seats']);
            }

            //try add info from pdf
            if (!empty($textPDF)) {
                $memLang = $this->lang;
                $this->assignLang($textPDF, true);

                // ReservationDate
                $resDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Generated'))}[\s:]+(.+)#", $textPDF)));

                if ($resDate !== false) {
                    $it['ReservationDate'] = $resDate;
                }

                if (isset($seg['AirlineName'], $seg['FlightNumber'])) {
                    // BookingClass
                    if (!empty($str = $this->re("#^ *{$seg['FlightNumber']}\s+([A-Z]{1,2})\s+{$this->opt($this->t('Class'))}#m", $textPDF))) {
                        $seg['BookingClass'] = $str;
                    }
                    // TicketNumbers
                    if (!empty($str = $this->re("#{$this->opt($this->t('Ticket Number'))}[ ]+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,2})(?:.+\n+){3,9}[ 0]*{$seg['FlightNumber']}[ ]+[A-Z]{1,2}[ ]+{$this->opt($this->t('Class'))}#", $textPDF))) {
                        $it['TicketNumbers'][] = $str;
                    }
                    $text = $this->re("#({$this->opt($this->t('Flight'))}[\s:]+{$seg['AirlineName']}\s+{$seg['FlightNumber']}(?:.+\n+)+? *{$this->opt($this->t('Number of Stops'))}.+)#", $textPDF);
                    // Cabin
                    if (!empty($str = $this->re("#{$this->opt($this->t('Class'))}[\s:]+(Economy|Business|First Class)(?: {$this->opt($this->t('Distance'))}|[ ]{2}|$)#m", $text))) {
                        $seg['Cabin'] = $str;
                    }
                    // TraveledMiles
                    if (!empty($str = $this->re("#{$this->opt($this->t('Distance'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text))) {
                        $seg['TraveledMiles'] = $str;
                    }
                    // Aircraft
                    if (!empty($str = $this->re("#{$this->opt($this->t('Aircraft Type'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text))) {
                        $seg['Aircraft'] = $str;
                    }
                    // Meal
                    if (!empty($str = $this->re("#{$this->opt($this->t('Meal Service'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text))
                        && !preg_match("/{$this->opt($this->t('Not Assigned'))}/i", $str)
                    ) {
                        $seg['Meal'] = $str;
                    }
                    // AccountNumbers
                    if (!empty($str = $this->re("#{$this->opt($this->t('Frequent Flyer Number'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text))
                        && !preg_match("/{$this->opt($this->t('Not Assigned'))}/i", $str)
                    ) {
                        $it['AccountNumbers'][] = $str;
                    }
                    // Seats
                    if (!empty($str = $this->re("#{$this->opt($this->t('Seat'))}[ ]*[:]+[ ]*(\d[,; A-Z\d]*?[A-Z])(?:[ ]{2}|$)#m", $text))) {
                        $seg['Seats'] = preg_split('/\s*[,;]+\s*/', $str);
                    }
                }
                $this->lang = $memLang;
            }
            $it['TripSegments'][] = $seg;
            $it['TicketNumbers'] = array_values(array_unique($it['TicketNumbers']));
            $its[] = $it;
        }

        if (count($its) > 0) {
            $its = $this->mergeItineraries($its);
        }

        if (!empty($textPDF)) {
            $this->addFlightSumsFromPDF($its, $textPDF);
        }

        return $its;
    }

    private function addFlightSumsFromPDF(&$its, $textPDF): void
    {
        foreach ($its as &$it) {
            if (isset($it['TicketNumbers'])) {
                foreach ($it['TicketNumbers'] as $tn) {
                    $text = $this->re("#{$this->opt($this->t('Ticket Number'))}\s+{$tn}[ ]+((?:.+\n+){1,8}.+{$this->opt($this->t('Total'))}.+)#", $textPDF);
                    $it['BaseFare'] = $this->re("#{$this->opt($this->t('Ticket Base Fare'))}\s+(\-?[\d\.]+)#", $text);
                    $it['Tax'] = $this->re("#{$this->opt($this->t('Ticket Tax Fare'))}\s+(\-?[\d\.]+)#", $text);
                    $it['Currency'] = $this->re("#{$this->opt($this->t('Total'))}\s+\(([A-Z]{3})\)\s+{$this->opt($this->t('Ticket Amount'))}#", $text);
                    $it['TotalCharge'] = $this->re("#{$this->opt($this->t('Total'))}\s+(\-?[\d\.]+)#", $text);

                    if (empty($it['TotalCharge'])) {
                        $it['TotalCharge'] = $this->re("#{$this->opt($this->t('Total'))}\s+\([A-Z]{3}\)\s+{$this->opt($this->t('Ticket Amount'))}\s+(\-?[\d\.]+)#s", $text);
                    }

                    if (!empty($str = $this->re("#({$this->opt($this->t('Online Ticket Fee'))})\s+\-?[\d\.]+#s", $text))) {
                        $it['Fees'][] = [
                            'Name'   => $str,
                            'Charge' => $this->re("#{$this->opt($this->t('Online Ticket Fee'))}\s+(\-?[\d\.]+)#s", $text),
                        ];
                    }
                }
            }
        }
    }

    private function parseEmailHotelPDF($textPDF, $segments): array
    {
        $its = [];

        $resDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Generated'))}[\s:]+(.+)#", $textPDF)));
        $pax = $this->re("#{$this->opt($this->t('Travel Arrangements for'))}[:\s]+([[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+\b)(?: [[:upper:]][[:lower:]]|[ ]{2}|$)#mu", $textPDF);
        $tripNumber = $this->re("#{$this->opt($this->t('Itinerary Booking Reference'))}[\s:]+([A-Z\d]{5,})#", $textPDF);

        foreach ($segments as $text) {
            if (!empty($this->re("#({$this->opt($this->t('Number Of Rooms'))})#", $text))) {
                $it = ['Kind' => 'R'];
                // GuestNames
                $it['GuestNames'][] = $pax;
                // TripNumber
                $it['TripNumber'] = $tripNumber;
                // ReservationDate
                $it['ReservationDate'] = $resDate;
                // ConfirmationNumber
                $it['ConfirmationNumber'] = '';

                if (preg_match("#{$this->opt($this->t('Guaranteed'))}[^\n]+\n([^\n]*?)\s*{$this->opt($this->t('Reference Number'))}[\s:]+([^\n]*?)\n *{$this->opt($this->t('Status'))}[^\n]+\n *{$this->opt($this->t('Number Of Rooms'))}[^\n]+\n([^\n]*?)\s*{$this->opt($this->t('Additional Information'))}#", $text, $m)) {
                    $str = '';

                    for ($i = 1; $i <= 3; $i++) {
                        if (isset($m[$i]) && !empty($m[$i])) {
                            $str .= trim($m[$i]);
                        }
                    }
                    $it['ConfirmationNumber'] = $str;
                }

                if (empty($it['ConfirmationNumber'])) {
                    $it['ConfirmationNumber'] = $this->re("#{$this->opt($this->t('Reference Number'))}[\s:]+([A-Z\d]{5,}\b)#", $text);
                }
                // HotelName
                $it['HotelName'] = trim($this->re("#(.+)\n+ *{$this->opt($this->t('Address'))}[\s:]+#", $text));
                // Address
                $it['Address'] = $this->re("#{$this->opt($this->t('Address'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);
                // Phone
                if (($phone = $this->re("#{$this->opt($this->t('Phone'))}[\s:]+([+(\d][-. \d)(]{5,}[\d)])(?:[ ]{2}|$)#m", $text))) {
                    $it['Phone'] = $phone;
                }
                // Fax
                if (($fax = $this->re("#{$this->opt($this->t('Fax'))}[\s:]+([+(\d][-. \d)(]{5,}[\d)])(?:[ ]{2}|$)#m", $text))) {
                    $it['Fax'] = $fax;
                }
                // CheckInDate
                $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Check In Date'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text)));
                // CheckOutDate
                $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Check Out Date'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text)));
                // Rooms
                $it['Rooms'] = $this->re("#{$this->opt($this->t('Number Of Rooms'))}[ :]+(\d+)#", $text);
                // Status
                $it['Status'] = $this->re("#{$this->opt($this->t('Status'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);
                // Rate
                $it['Rate'] = preg_replace('/\s+/', ' ', $this->re("#{$this->opt($this->t('Rate'))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|$)#m", $text));
                // AccountNumbers
                if (!empty($str = $this->re("#{$this->opt($this->t('Membership ID'))}[\s:]+([A-Z\d][A-Z\d \-]{2,}?)(?:[ ]{2}|$)#m", $text))) {
                    $it['AccountNumbers'][] = $str;
                }
                // CancellationPolicy
                if (!empty($str = $this->re("#(CANCEL.+)#", $text))) {
                    $it['CancellationPolicy'] = $str;
                }

                $its[] = $it;
            }
        }

        return $its;
    }

    private function parseEmailHotel($textPDF): array
    {
        $its = [];

        $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler:'))}]/following::text()[normalize-space()][1]", null, true, "/^(.{2,}?)(?:\s+{$this->patterns['namePrefixes']})?$/");
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space(.)!=''][1]");

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Hotel Information'))}]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $it = ['Kind' => 'R'];
            // GuestNames
            $it['GuestNames'][] = $pax;
            // TripNumber
            $it['TripNumber'] = $tripNumber;
            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Reference Number'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#([A-Z\d\-]{5,})#");

            if (empty($it['ConfirmationNumber']) && !empty($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Reference Number'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#({$this->opt($this->t('Not Assigned'))})#"))) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }
            // HotelName
            $it['HotelName'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Hotel Name'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // Address
            $it['Address'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Address'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // Phone
            if (($phone = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Phone'))}]/following::text()[normalize-space()][1]", $root, true, '/^[: ]*([+(\d][-. \d)(]{5,}[\d)])[: ]*$/'))) {
                $it['Phone'] = $phone;
            }
            // Fax
            if (($fax = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Fax'))}]/following::text()[normalize-space()][1]", $root, true, '/^[: ]*([+(\d][-. \d)(]{5,}[\d)])[: ]*$/'))) {
                $it['Fax'] = $fax;
            }
            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate(trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Check In Date'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ")));
            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate(trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Check Out Date'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ")));
            // Status
            $it['Status'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Status'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // Rate
            $it['Rate'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Rate'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");

            //try add info from pdf
            if (!empty($textPDF)) {
                $memLang = $this->lang;
                $this->assignLang($textPDF, true);

                // ReservationDate
                $resDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Generated'))}[\s:]+(.+)#", $textPDF)));

                if ($resDate !== false) {
                    $it['ReservationDate'] = $resDate;
                }

                $text = $this->re("#{$it['HotelName']}\s+{$this->opt($this->t('Address'))}[\s:]+((?:.+\n+){15})#", $textPDF);

                // AccountNumbers
                if (!empty($str = $this->re("#{$this->opt($this->t('Membership ID'))}[\s:]+([A-Z\d][A-Z\d \-]{2,}?)(?:[ ]{2}|$)#m", $text))) {
                    $it['AccountNumbers'][] = $str;
                }

                // CancellationPolicy
                if (!empty($str = $this->re("#(CANCEL.+)#", $text))) {
                    $it['CancellationPolicy'] = $str;
                }

                $this->lang = $memLang;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailCarPDF($textPDF, $segments): array
    {
        $its = [];

        $resDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Generated'))}[\s:]+(.+)#", $textPDF)));
        $pax = $this->re("#{$this->opt($this->t('Travel Arrangements for'))}[:\s]+([[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+\b)(?: [[:upper:]][[:lower:]]|[ ]{2}|$)#mu", $textPDF);
        $tripNumber = $this->re("#{$this->opt($this->t('Itinerary Booking Reference'))}[\s:]+([A-Z\d]{5,}\b)#", $textPDF);

        foreach ($segments as $text) {
            if (!empty($this->re("#({$this->opt($this->t('Pickup'))}[\s:]+{$this->opt($this->t('Location'))}[\s:]+)#", $text))) {
                //		if (preg_match_all("#\n *\d+:\d+.*\n(?:[^\n]+\n){1,2} *{$this->opt($this->t('Pickup'))}[\s:]+{$this->opt($this->t('Location'))}[\s:]+(?:[^\n]*\n){15,25}\s+{$this->opt($this->t('Special Information'))}(?:[^\n]*\n){2,4}#", $textPDF, $m, PREG_PATTERN_ORDER)) {
                //			foreach ($m[0] as $text) {
                $it = ['Kind' => 'L'];
                // RenterName
                $it['RenterName'] = $pax;
                // TripNumber
                $it['TripNumber'] = $tripNumber;
                // ReservationDate
                $it['ReservationDate'] = $resDate;
                // Number
                $it['Number'] = $this->re("#{$this->opt($this->t('Reference Number'))}[\s:]+([A-Z\d]{5,}\b)#", $text);
                // RentalCompany
                $it['RentalCompany'] = trim($this->re("#(?:\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?[ ]+)?(\b.+)\n+[ ]*{$this->opt($this->t('Pickup'))}[\s:]+#", $text));
                // CarType
                $it['CarType'] = $this->re("#{$this->opt($this->t('Car Type'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);

                // PickupPhone
                if (($phonePickup = $this->re("#{$this->opt($this->t('Pickup'))}[\s:]+.+?\s+{$this->opt($this->t('Phone'))}[\s:]+([+(\d][-. \d)(]{5,}[\d)])$#ms", $text))) {
                    $it['PickupPhone'] = $phonePickup;
                }
                // DropoffPhone
                if (($phoneDropoff = $this->re("#{$this->opt($this->t('Drop Off'))}[\s:]+.+?\s+{$this->opt($this->t('Phone'))}[\s:]+([+(\d][-. \d)(]{5,}[\d)])$#ms", $text))) {
                    $it['DropoffPhone'] = $phoneDropoff;
                }

                // PickupLocation
                $it['PickupLocation'] = $this->re("#{$this->opt($this->t('Pickup'))}[\s:]+{$this->opt($this->t('Location'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);
                // DropoffLocation
                $it['DropoffLocation'] = $this->re("#{$this->opt($this->t('Drop Off'))}[\s:]+{$this->opt($this->t('Location'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);
                // PickupDatetime
                $it['PickupDatetime'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Pickup'))}[\s:]+.+?\s+{$this->opt($this->t('Date and Time'))}[\s:]+([^\n]+)#s", $text)));
                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Drop Off'))}[\s:]+.+?\s+{$this->opt($this->t('Date and Time'))}[\s:]+([^\n]+)#s", $text)));
                // Status
                $it['Status'] = $this->re("#{$this->opt($this->t('Status'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);
                // Rate to BaseFare - rate is not basefare
                //				$tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Rate'))}[\s:]+(.+)#", $text));
                //				if (!empty($tot['Total'])) {
                //					$it['BaseFare'] = $tot['Total'];
                //					$it['Currency'] = $tot['Currency'];
                //				}
                // AccountNumbers
                if (!empty($str = $this->re("#{$this->opt($this->t('Membership ID'))}[\s:]+([A-Z\d][A-Z\d \-]{2,}?)(?:[ ]{2}|$)#m", $text))) {
                    $it['AccountNumbers'][] = $str;
                }
                // TotalCharge
                // Currency
                $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Approximate Total Rate'))}.+?{$this->opt($this->t('Approximate Total Rate'))}[\s:]+(.+)#s", $text));

                if ($tot['Total'] !== null) {
                    $it['TotalCharge'] = $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                } else {
                    $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Approximate Total Rate'))}[\s:]+(.+)#", $text));

                    if ($tot['Total'] !== null) {
                        $it['TotalCharge'] = $tot['Total'];
                        $it['Currency'] = $tot['Currency'];
                    }
                }

                $its[] = $it;
            }
        }

        return $its;
    }

    private function parseEmailCar($textPDF): array
    {
        $its = [];

        $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler:'))}]/following::text()[normalize-space()][1]", null, true, "/^(.{2,}?)(?:\s+{$this->patterns['namePrefixes']})?$/");
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space(.)!=''][1]");

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Car Information'))}]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $it = ['Kind' => 'L'];
            // RenterName
            $it['RenterName'] = $pax;
            // TripNumber
            $it['TripNumber'] = $tripNumber;
            // Number
            $it['Number'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Reference Number'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#([A-Z\d\-]{5,})#");

            if (empty($it['Number']) && !empty($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Reference Number'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#({$this->opt($this->t('Not Assigned'))})#"))) {
                $it['Number'] = CONFNO_UNKNOWN;
            }
            // RentalCompany
            $it['RentalCompany'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Car Company'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // CarType
            $it['CarType'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Car Type'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // PickupPhone
            // DropoffPhone
            if (($phone = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Phone'))}]/following::text()[normalize-space()][1]", $root, true, '/^[: ]*([+(\d][-. \d)(]{5,}[\d)])[: ]*$/'))) {
                $it['DropoffPhone'] = $it['PickupPhone'] = $phone;
            }
            // PickupLocation
            $it['PickupLocation'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick Up Location'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // DropoffLocation
            $it['DropoffLocation'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Drop Off Location'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // PickupDatetime
            $it['PickupDatetime'] = strtotime($this->normalizeDate(trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Pick Up Date/Time'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ")));
            // DropoffDatetime
            $it['DropoffDatetime'] = strtotime($this->normalizeDate(trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Drop Off Date/Time'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ")));
            // Status
            $it['Status'] = trim($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Status'))}]/following::text()[normalize-space(.)!=''][1]", $root), ": ");
            // Rate to BaseFare
//            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Rate'))}]/following::text()[normalize-space(.)!=''][1]", $root));
//
//            if ($tot['Total'] !== null) {
//                $it['BaseFare'] = $tot['Total'];
//                $it['Currency'] = $tot['Currency'];
//            }

            //try add info from pdf
            if (!empty($textPDF)) {
                $memLang = $this->lang;
                $this->assignLang($textPDF, true);

                // ReservationDate
                $resDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Generated'))}[\s:]+(.+)#", $textPDF)));

                if ($resDate !== false) {
                    $it['ReservationDate'] = $resDate;
                }

                $text = $this->re("#{$it['RentalCompany']}[\s:]+({$this->opt($this->t('Pickup'))}[\s:]+{$this->opt($this->t('Location'))}[\s:]+(?:.+\n+){22})#", $textPDF);

                // AccountNumbers
                if (!empty($str = $this->re("#{$this->opt($this->t('Membership ID'))}[\s:]+([A-Z\d][A-Z\d \-]{2,}?)(?:[ ]{2}|$)#m", $text))) {
                    $it['AccountNumbers'][] = $str;
                }

                // Status
                if (!empty($str = $this->re("#{$this->opt($this->t('Status'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text))) {
                    $it['Status'] = $str;
                }

                // TotalCharge
                // Currency
                $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Approximate Total Rate'))}.+?{$this->opt($this->t('Approximate Total Rate'))}[\s:]+(.+)#s", $text));

                if ($tot['Total'] !== null) {
                    $it['TotalCharge'] = $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                } else {
                    $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Approximate Total Rate'))}[\s:]+(.+)#", $text));

                    if ($tot['Total'] !== null) {
                        $it['TotalCharge'] = $tot['Total'];
                        $it['Currency'] = $tot['Currency'];
                    }
                }

                $this->lang = $memLang;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailRailPDF($textPDF, $segments): array
    {
        $its = [];

        $resDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Generated'))}[\s:]+(.+)#", $textPDF)));
        $pax = $this->re("#{$this->opt($this->t('Travel Arrangements for'))}[:\s]+([[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+\b)(?: [[:upper:]][[:lower:]]|[ ]{2}|$)#mu", $textPDF);
        $tripNumber = $this->re("#{$this->opt($this->t('Itinerary Booking Reference'))}[\s:]+([A-Z\d]{5,}\b)#", $textPDF);

        foreach ($segments as $text) {
            if (!empty($this->re("#({$this->opt($this->t('Rail Carrier'))})#", $text))) {
                $it = ['Kind' => 'T', 'TripSegments' => []];
                // Status
                $it['Status'] = $this->re("#{$this->opt($this->t('Status'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);
                // Passengers
                $it['Passengers'][] = $pax;
                // TripNumber
                $it['TripNumber'] = $tripNumber;
                // TripNumber
                $it['ReservationDate'] = $resDate;
                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

                $seg = [];
                // FlightNumber
                $seg['FlightNumber'] = $this->re("#{$this->opt($this->t('Train'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);

                // RecordLocator
                $railCarrier = $this->re("#{$this->opt($this->t('Rail Carrier'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);

                if (preg_match('/^VIA\s*RAIL/i', $railCarrier)) {
                    $railCarrierRule = $this->opt($this->t('viarailVariants'));
                } elseif ($railCarrier !== null) {
                    $railCarrierRule = $this->opt($railCarrier);
                } else {
                    $railCarrierRule = 'carrier_not_found';
                }
                $it['RecordLocator'] = $this->re("#OTHER\s+.*?{$railCarrierRule}\s+(?:RESERVATION|LOCATOR)[-\s]+([A-Z\d]{5,})#s", $textPDF);

                if (empty($it['RecordLocator'])) {
                    $it['RecordLocator'] = $this->re("#{$this->opt($this->t('Reference Number'))}[\s:]+([A-Z\d]{5,}\b)#", $text);
                }

                if (empty($it['RecordLocator']) && !empty($this->re("#{$this->opt($this->t('Reference Number'))}[\s:]+({$this->opt($this->t('Not Assigned'))})#", $text))) {
                    $it['RecordLocator'] = CONFNO_UNKNOWN;
                }

                // Cabin
                $bookingClass = $this->re("#{$this->opt($this->t('Booking Class'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);

                if ($bookingClass !== null && !preg_match("#^{$this->opt($this->t('Not Assigned'))}#i", $bookingClass)) {
                    $seg['Cabin'] = $bookingClass;
                }
                // DepName
                $seg['DepName'] = $this->re("#{$this->opt($this->t('Origin'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);
                // ArrName
                $seg['ArrName'] = $this->re("#{$this->opt($this->t('Destination'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);
                // DepDate
                $seg['DepDate'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Departing'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text)));
                // ArrDate
                $dateArr = $this->re("#{$this->opt($this->t('Arriving'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);

                if (preg_match("#^{$this->opt($this->t('Not Assigned'))}#i", $dateArr)) {
                    $seg['ArrDate'] = MISSING_DATE;
                } else {
                    $seg['ArrDate'] = strtotime($this->normalizeDate($dateArr));
                }
                // DepCode
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                // ArrCode
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                // Seats
                $seat = $this->re("#{$this->opt($this->t('Seat'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text);

                if ($seat !== null && !preg_match("#^{$this->opt($this->t('Not Assigned'))}#i", $seat)) {
                    $seg['Seats'] = [$seat];
                }

                $it['TripSegments'][] = $seg;

                $its[] = $it;
            }
        }
        $its = $this->mergeItineraries($its);

        $this->addRailSumsFromPDF($its, $textPDF);

        return $its;
    }

    private function parseEmailRail($textPDF): array
    {
        $its = [];

        $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler:'))}]/following::text()[normalize-space()][1]", null, true, "/^(.{2,}?)(?:\s+{$this->patterns['namePrefixes']})?$/");
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space(.)!=''][1]");

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Rail Information'))}]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            // Status
            $it['Status'] = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.),'Status')]/following::text()[normalize-space(.)!=''][1]", $root);
            // Passengers
            $it['Passengers'][] = $pax;
            // TripNumber
            $it['TripNumber'] = $tripNumber;
            // TripCategory
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            $seg = [];
            // FlightNumber
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            // RecordLocator
            $railCarrier = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Rail Carrier'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match('/^VIA\s*RAIL/i', $railCarrier)) {
                $railCarrierRule = $this->starts($this->t('viarailVariants'));
            } elseif ($railCarrier !== null) {
                $railCarrierRule = $this->starts($railCarrier);
            } else {
                $railCarrierRule = 'false()';
            }
            $referenceNumber = $this->http->FindSingleNode("preceding::text()[normalize-space()='Other:']/following::text()[normalize-space()][1][{$railCarrierRule}]", $root, true, "/(?:RESERVATION|LOCATOR)[-\s]+([A-Z\d]{5,})$/");

            if (empty($referenceNumber)) {
                $referenceNumber = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Reference Number')]/following::text()[normalize-space()][1]", $root, true, '/^[:\s]*([A-Z\d]{5,})$/');
            }

            if (!empty($referenceNumber)) {
                $it['RecordLocator'] = $referenceNumber;
            } else {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }

            // Cabin
            $bookingClass = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Booking Class'))}]/following::text()[normalize-space()][1]", $root);

            if ($bookingClass !== null && !preg_match("#^{$this->opt($this->t('Not Assigned'))}#i", $bookingClass)) {
                $seg['Cabin'] = $bookingClass;
            }
            // DepName
            $seg['DepName'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Origin'))}]/following::text()[normalize-space(.)!=''][1]", $root);
            // ArrName
            $seg['ArrName'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Destination'))}]/following::text()[normalize-space(.)!=''][1]", $root);
            // DepDate
            $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departing'))}]/following::text()[normalize-space(.)!=''][1]", $root)));
            // ArrDate
            $dateArr = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Arriving'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("#^[:\s]*{$this->opt($this->t('Not Assigned'))}#i", $dateArr)) {
                $seg['ArrDate'] = MISSING_DATE;
            } else {
                $seg['ArrDate'] = strtotime($this->normalizeDate($dateArr));
            }
            $seg['DatesAreStrict'] = true;
            // DepCode
            // ArrCode
            if (!empty($seg['DepName']) && !empty($seg['ArrName']) && !empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            //try add info from pdf
            if (!empty($textPDF)) {
                $memLang = $this->lang;
                $this->assignLang($textPDF, true);

                // ReservationDate
                $resDate = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Generated'))}[\s:]+(.+)#", $textPDF)));

                if ($resDate !== false) {
                    $it['ReservationDate'] = $resDate;
                }

                $text = $this->re("#({$this->opt($this->t('Reference Number'))}.+\n+(?:.+\n+){1,3}[ ]*{$this->opt($this->t('Origin'))}[:\s]+{$this->opt($seg['DepName'])}\n.+\n[ ]*{$this->opt($this->t('Destination'))}[:\s]+{$this->opt($seg['ArrName'])}.*\n+(?:.+\n+){1,3}[ ]*{$this->opt($this->t('Special Information'))})#", $textPDF);
                // FlightNumber
                // Type
                if (!empty($str = $this->re("#{$this->opt($this->t('Train'))}[\s:]+(.+?)(?:[ ]{2}|$)#m", $text))) {
                    $seg['FlightNumber'] = $str;
                }
                // Seats
                $seat = $this->re("#{$this->opt($this->t('Seat'))}[\s:]+(\d{1,3}[A-Z])(?:[ ]{2}|$)#m", $text);

                if (!empty($seat)) {
                    $seg['Seats'] = [$seat];
                }

                $this->lang = $memLang;
            }
            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        if (count($its) > 0) {
            $its = $this->mergeItineraries($its);
        }

        if (!empty($textPDF)) {
            $this->addRailSumsFromPDF($its, $textPDF);
        }

        return $its;
    }

    private function addRailSumsFromPDF(&$its, $textPDF): void
    {
        if (count($its) == 1) {
            if (!empty($str = $this->re("#{$this->opt($this->t('The Price For Your Rail Ticket Is'))}[\s:]+(.+)#", $textPDF))) {
                $tot = $this->getTotalCurrency($str);
            }

            if ($tot['Total'] !== null) {
                $its[0]['BaseFare'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            } else {
                $its[0]['BaseFare'] = $this->re("#{$this->opt($this->t('Ticket Base Fare'))}\s+(\-?[\d\.]+)#", $textPDF);
            }
            $its[0]['Currency'] = $this->re("#{$this->opt($this->t('Total'))}\s+\(([A-Z]{3})\)\s+{$this->opt($this->t('Ticket Amount'))}#", $textPDF);
            $its[0]['TotalCharge'] = $this->re("#{$this->opt($this->t('Total'))}\s+(\-?[\d\.]+)#", $textPDF);

            if (empty($its[0]['TotalCharge'])) {
                $its[0]['TotalCharge'] = $this->re("#{$this->opt($this->t('Total'))}\s+\([A-Z]{3}\)\s+{$this->opt($this->t('Ticket Amount'))}\s+(\-?[\d\.]+)#s", $textPDF);
            }

            if (!empty($str = $this->re("#({$this->opt($this->t('Online Ticket Fee'))})\s+\-?[\d\.]+#s", $textPDF))) {
                $its[0]['Fees'][] = [
                    'Name'   => $str,
                    'Charge' => $this->re("#{$this->opt($this->t('Online Ticket Fee'))}\s+(\-?[\d\.]+)#s", $textPDF),
                ];
            }
        }
    }

    private function parseEmailOtherCancellation(): array
    {
        $its = [];

        $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler:'))}]/following::text()[normalize-space()][1]", null, true, "/^(.{2,}?)(?:\s+{$this->patterns['namePrefixes']})?$/");
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space(.)!=''][1]");

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Other Information'))}]/ancestor::table[1][{$this->contains($this->t('COURTESY CANCELLATION'))}]")->length > 0) {
            $it = ['Kind' => 'T'];
            // Status
            $it['Status'] = $this->t('COURTESY CANCELLATION');
            // Passengers
            $it['Passengers'][] = $pax;
            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip ID:'))}]/following::text()[normalize-space(.)!=''][1]");
            // RecordLocator
            $it['RecordLocator'] = $tripNumber;
            // Cancelled
            $it['Cancelled'] = true;

            $its[] = $it;
        }

        return $its;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body, $isPdf = false): bool
    {
        $this->lang = null;
        $reBody = $isPdf ? $this->reBodyPDF : $this->reBodyHTML;

        foreach ($reBody as $lang => $re) {
            if (stripos($body, $re[0]) !== false && stripos($body, $re[1]) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Sunday 12 November 2017 at 7:45PM
            '#^\w+\s+(\d+)\s+(\w+)\s+(\d+)\s+at\s+(\d+:\d+(?:\s*[ap]m)?)$#iu',
            //Sunday 12 November 2017
            '#^\w+\s+(\d+)\s+(\w+)\s+(\d+)$#',
            //09 November 2017 17:20 GMT
            '#^(\d+)\s+(\w+)\s+(\d+)\s+(\d+:\d+(?:\s*[ap]m)?)\s+GMT$#ui',
        ];
        $out = [
            '$1 $2 $3 $4',
            '$1 $2 $3',
            '$1 $2 $3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s);
        }, $field)) . ')';
    }
}
