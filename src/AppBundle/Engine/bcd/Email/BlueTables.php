<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class BlueTables extends \TAccountChecker
{
    public $mailFiles = "bcd/it-1-booz.eml, bcd/it-10-booz.eml, bcd/it-10.eml, bcd/it-11-booz.eml, bcd/it-12-booz.eml, bcd/it-12238598.eml, bcd/it-12238599.eml, bcd/it-12434231.eml, bcd/it-12464487.eml, bcd/it-12626492.eml, bcd/it-1480376.eml, bcd/it-15.eml, bcd/it-1601669.eml, bcd/it-1632380.eml, bcd/it-1649583.eml, bcd/it-1702447.eml, bcd/it-1731651.eml, bcd/it-1804333.eml, bcd/it-1844839.eml, bcd/it-1915792.eml, bcd/it-1927580.eml, bcd/it-1997320.eml, bcd/it-2-booz.eml, bcd/it-2.eml, bcd/it-20.eml, bcd/it-2054516.eml, bcd/it-2078274.eml, bcd/it-2534195.eml, bcd/it-2609189.eml, bcd/it-2666194.eml, bcd/it-2668122.eml, bcd/it-2668133.eml, bcd/it-2765180.eml, bcd/it-2907821.eml, bcd/it-2908128.eml, bcd/it-3-booz.eml, bcd/it-3015700.eml, bcd/it-3093648.eml, bcd/it-3118241.eml, bcd/it-33928232.eml, bcd/it-3545413.eml, bcd/it-3545684.eml, bcd/it-3545686.eml, bcd/it-3545690-gant.eml, bcd/it-3548943.eml, bcd/it-3549329.eml, bcd/it-3549409.eml, bcd/it-3549451.eml, bcd/it-3549622.eml, bcd/it-3549635.eml, bcd/it-3550022.eml, bcd/it-3550098.eml, bcd/it-3554052-gant.eml, bcd/it-3554469-gant.eml, bcd/it-3562366.eml, bcd/it-3562829.eml, bcd/it-3563622.eml, bcd/it-3563883.eml, bcd/it-4-booz.eml, bcd/it-5-booz.eml, bcd/it-5101300.eml, bcd/it-6-booz.eml, bcd/it-6.eml, bcd/it-6054110.eml, bcd/it-6691315.eml, bcd/it-6710523.eml, bcd/it-6750420.eml, bcd/it-7-booz.eml, bcd/it-7036268-gant.eml, bcd/it-7063772-gant.eml, bcd/it-8-booz.eml, bcd/it-8.eml, bcd/it-8883774.eml, bcd/it-8883796.eml, bcd/it-9-booz.eml, bcd/it-9.eml";

    public $lang = "";
    private $reFrom = ["@bcdtravel.com", "online@us.fcm.travel"];
    private $reSubject = [
        "Booking Confirmation",
        "Travel Invoice for",
    ];
    private static $reBody = [
        'booz'    => ['The Booz'],
        'nexion'  => ['nexion.com', 'Nexion'],
        'virtuoso'=> ['air@virtuoso.com'],
        'bcd'     => [
            'BCD Travel', 'bcd.compleattrip.com', 'HDS-BCD.CompleatTrip.com', 'Protravel',
            'www.trueplacestravels.com', 'cadencetravel.com', 'WWW.TRAVEL.STATE.GOV',
            'BOEING TRAVEL',
        ],
        'tleaders'   => ['Travel Leaders'],
        'directravel'=> ['Direct Travel'],
        'transport'  => ['tandt.com'],
        'gant'       => ['Gant Travel'],
        'fcmtravel'  => ['fcmtravel.ca', '.fcm.travel'],
        'camelback'  => ['camelbacktravel.com'],
        'otg'        => ['ovationtravel.com'],
        'tcase'      => ['.tripcase.com'],
        'frosch'     => ['tangerinetravel.com'],
        'alaskaair'  => ['Alaska Airlines Confirmation'],
        'concur'     => ['.concurcompleat.com/protravel-int'], //???provider???
    ];
    private $reBody2 = [
        "de"=> ["Reiseplan"],
        "es"=> ["Resumen del Viaje", "Resumen de Viaje"],
        "en"=> ["Ticket Receipt", "Travel Summary", "Baggage Allowance Link", "Booking Ref.:", "AIR -", 'Rail -'],
        "fr"=> ["Itinéraire de voyage", "Réservation aérienne"],
    ];

    private static $dictionary = [
        "en" => [
            "Agency Record Locator" => ["Agency Record Locator", "- Record", "Agency Record Locator:", "Reference Number:", "Booking Ref.:", 'Reference:', "Agency Record Locator:", "Booking Reference #"],
            "Traveler"              => ["Traveler", "Traveller", "Passenger(s):", 'Traveller(s)'],

            /* flight / train */
            //            "From/To" => "",
            //            "Class/Type" => "",
            "AIR -"           => ["AIR -", "Air -"],
            "Record Locator:" => ["Record Locator:", "Booking Reference:", "Locator:"],
            //            "Flight" => "",
            //            "Depart:" => "",
            //            "Arrive:" => "",
            "Terminal" => ["Terminal", "TERMINAL"],
            //            "Operated By:"=>"",
            //            "Equipment:" => "",
            "Distance:" => ["Distance:", "Mileage:"],
            //            "Seat:" => "",
            //            "Duration:" => "",
            //            "Meal:" => "",
            //            "Remarks:" => "",
            "Non-stop-reg" => "(?:Non-stop|with (\d+) Stop)",
            'RAIL -'       => ['Rail -', 'RAIL -'],
            'Train Number' => ['Train Number', 'Train number'],

            "Ticket Number:" => ["E-Ticket Number:", "Ticket Number:", "Tkt Nbr:", "YOUR TICKET NUMBERS ARE"],
            //			"Ticket Amount:" => "",
            "FF Number:" => ["FF Number:", "Loyalty Number:"],

            /* hotel */
            "HOTEL -" => ["HOTEL -", "Hotel -"],
            //            "Confirmation:" => "",
            //            "Address:" => "",
            "Check In/Check Out:" => ["Check In/Check Out:", "Check In / Check Out:"],
            //            "Check Out:" => "",
            "Tel:" => ["Tel:", "Tel.:", "Tel"],
            "Fax:" => ["Fax:", "Fax"],
            //            "Number of Persons:" => "",
            "Number of Rooms:" => ["Number of Rooms:", "Number Of Rooms:"],
            //            "Room Type:" => "",
            "Rate per night:" => ["Rate per night:", "Rate:"],
            //            "Cancellation Policy:" => "",
            "Description:" => ["Description:", "Additional Information:"],
            //            "Frequent Guest ID:" => "",

            /* car */
            "CAR -" => ["CAR -", "Car -"],
            //			"Pick Up:" => "",
            //			"Drop Off:" => "",
            //			"Type:" => "",
            //			"Frequent Renter ID:" => "",
            "Estimated Total:" => ["Estimated Total:", "Total:", "Est. Total:"],

            /* transfer */
            //			"LIMO -" => "",
            //			"Confirmation Number:" => "",
            //			"Rate:" => "",
            //			"Pickup Location:" => "",
            //			"Pickup Date and Time:" => "",
            //			"Dropoff Location:" => "",

            "Status:" => "Status:",
            //			"Balance Due:" => "",
            //			"Total Amount" => "",
            //			"Total Amount Due" => "",
            //			"Total Invoice Amount" => "",
        ],
        "de" => [ // it-1649583.eml
            "Agency Record Locator" => ["- Buchungsnummer", "Amadeus Buchungscode"],
            "Traveler"              => ["Reisender/Reisende", "Reisende(r)"],

            /* flight / train */
            "From/To"         => ["Von/Bis", "Leistung"],
            "Class/Type"      => "Klasse/Leistungsart",
            "AIR -"           => ["FLUG -", "Flug -"],
            "Record Locator:" => ["Buchungsnummer:", "Buchungsreferenz:"],
            "Flight"          => "Flugnummer/Leistung",
            "Depart:"         => ["Von:", "Abreise:"],
            "Arrive:"         => ["Nach:", "Ankunft:"],
            //            "Terminal" => "",
            // "Operated By:" => "",
            "Equipment:" => "Fluggerät:",
            // "Distance:" => "",
            "Seat:"     => "Sitzplatz:",
            "Duration:" => "Dauer:",
            //            "Meal:" => "",
            //            "Remarks:" => "",
            "Non-stop-reg" => "(?:Non-stop)",

            //            'RAIL -' => "",
            //            'Train Number' => "",

            //            "Ticket Number:" => "",
            //            "Ticket Amount:" => "",
            //            "FF Number:" => "",

            /* hotel */
            "HOTEL -"             => "HOTEL -",
            "Confirmation:"       => "Bestätigungsnummer:",
            "Address:"            => "Adresse:",
            "Check In/Check Out:" => "Anreise/Abreise:",
            //            "Check Out:" => "",
            "Tel:" => "Telefonnummer:",
            "Fax:" => "Fax:",
            // "Number of Persons:" => "",
            // "Number of Rooms:" => "",
            // "Room Type:" => "",
            "Rate per night:"      => "Rate pro Nacht:",
            "Cancellation Policy:" => "Stornobedingungen:",
            "Description:"         => "Beschreibung:",
            "Frequent Guest ID:"   => "ID Nummer:",

            /* car */
            //			"CAR -" => "",
            // "Pick Up:" => "",
            // "Drop Off:" => "",
            // "Type:" => "",
            //            "Frequent Renter ID:" => "",
            // "Estimated Total:" => "",

            /* transfer */
            // "LIMO -" => "",
            // "Confirmation Number:" => "",
            // "Rate:" => "",
            // "Pickup Location:" => "",
            // "Pickup Date and Time:" => "",
            // "Dropoff Location:" => "",

            "Status:"              => "Status:",
            "Balance Due:"         => "",
            "Total Amount"         => "",
            "Total Amount Due"     => "",
            "Total Invoice Amount" => "",
        ],
        "es" => [ // it-6750420.eml
            "Agency Record Locator" => ["Localizador de la Agencia", "Localizador"],
            "Traveler"              => "Pasajero",

            /* flight / train */
            "From/To"         => ["Origen/Destino", "From/To"],
            "Class/Type"      => "Clase/Tipo",
            "AIR -"           => ["AÉREO -", "AÉREO -"],
            "Record Locator:" => "Código de Referencia:",
            "Flight"          => "Vuelo",
            "Depart:"         => ["Origen:", "Salida:"],
            "Arrive:"         => ["Destino:", "Llegada:"],
            "Terminal"        => ["Terminal", "TERMINAL"],
            "Operated By:"    => "Operado por:",
            "Equipment:"      => "Equipo:",
            "Distance:"       => "Distancia:",
            "Seat:"           => "Asiento:",
            "Duration:"       => "Duración:",
            "Meal:"           => "Comida:",
            //            "Remarks:" => "",
            "Non-stop-reg" => "(?:Directo)",
            //            'RAIL -' => "",
            //            'Train Number' => "",
            //
            "Ticket Number:" => ["Numero de Boleto (Electrónico):", "Número de billete:"],
            "Ticket Amount:" => "Monto del Boleto:",
            //            "FF Number:" => "",

            /* hotel */
            "HOTEL -"             => "HOTEL -",
            "Confirmation:"       => "Confirmación:",
            "Address:"            => "Dirección:",
            "Check In/Check Out:" => "Registro/Salida:",
            //            "Check Out:" => "",
            "Tel:"               => "Tel.:",
            "Fax:"               => "Fax:",
            "Number of Persons:" => "Núm. de personas:",
            // "Number of Rooms:" => "",
            //            "Room Type:" => "",
            "Rate per night:"      => "Tarifa por noche:",
            "Cancellation Policy:" => "Normativa de cancelación:",
            "Description:"         => "Descripción:",
            //            "Frequent Guest ID:" => "",

            /* car */
            // "CAR -" => "",
            // "Pick Up:" => "",
            // "Drop Off:" => "",
            // "Type:" => "",
            //            "Frequent Renter ID:" => "",
            // "Estimated Total:" => "",

            /* transfer */
            // "LIMO -" => "",
            // "Confirmation Number:" => "",
            // "Rate:" => "",
            // "Pickup Location:" => "",
            // "Pickup Date and Time:" => "",
            // "Dropoff Location:" => "",

            "Status:" => "Estado:",
            //            "Balance Due:" => "",
            "Total Amount" => "Total estimado del viaje",
            //            "Total Amount Due" => "",
            //            "Total Invoice Amount" => "",
        ],
        "fr" => [ // it-1702447.eml
            "Agency Record Locator" => "Numéro de dossier",
            "Traveler"              => "Voyageur",

            /* flight / train */
            "From/To"         => "Trajet",
            "Class/Type"      => "Classe/Catégorie",
            "AIR -"           => ["Réservation aérienne -"],
            "Record Locator:" => ["Numéro de dossier:", "Numéro de dossier SNCF:"],
            "Flight"          => "Vol",
            "Depart:"         => "Départ:",
            "Arrive:"         => "Arrivée:",
            "Terminal"        => ["Terminal", "TERMINAL"],
            "Operated By:"    => "Opéré par:",
            "Equipment:"      => "Appareil:",
            //            "Distance:" => "",
            "Seat:"     => ["Siège:", "Place:"],
            "Duration:" => "Temps de vol:",
            //            "Meal:" => "",
            //            "Remarks:" => "",
            "Non-stop-reg" => "(?:Direct)",
            'RAIL -'       => ['Réservation Train -'],
            'Train Number' => ['Numéro de train:'],

            //            "Ticket Number:"=> "",
            //            "Ticket Amount:" => "",
            "FF Number:" => "Carte de fidélité:",

            /* hotel */
            //            "HOTEL -" => "",
            //            "Confirmation:" => "",
            //            "Address:" => "",
            //            "Check In/Check Out:" => "",
            //            "Check Out:" => "",
            //            "Tel:" => "",
            //            "Fax:" => "",
            //            "Number of Persons:" => "",
            //            "Number of Rooms:" => "",
            //            "Room Type:" => "",
            //            "Rate per night:" => "",
            //            "Cancellation Policy:" => "",
            //            "Description:" => "",
            //            "Frequent Guest ID:" => "",

            /* car */
            //            "CAR -" => "",
            //            "Pick Up:" => "",
            //            "Drop Off:" => "",
            //            "Type:" => "",
            //            "Frequent Renter ID:" => "",
            //            "Estimated Total:" => "",

            /* transfer */
            //            "LIMO -" => "",
            //            "Confirmation Number:" => "",
            //            "Rate:" => "",
            //            "Pickup Location:" => "",
            //            "Pickup Date and Time:" => "",
            //            "Dropoff Location:" => "",

            "Status:" => "Statut de la réservation:",
            //            "Balance Due:" => "",
            //            "Total Amount" => "",
            //            "Total Amount Due" => "",
            //            "Total Invoice Amount" => "",
        ],
    ];
    private $date = null;

    public static function getEmailProviders()
    {
        return array_keys(self::$reBody);
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (strpos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"], $headers["subject"])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers["from"]) !== true) {
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

        if (!$this->getProvider($parser->getSubject() . "\n" . $body)) {
            return false;
        }

        foreach ($this->reBody2 as $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (($provider = $this->getProvider($parser->getSubject() . "\n" . $parser->getHTMLBody())) === null) {
            $this->logger->debug("provider not detected");
        }

        foreach ($this->reBody2 as $lang=>$re) {
            foreach ($re as $r) {
                if (strpos($this->http->Response["body"], $r) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Total Amount Due"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][last()]")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t("Total Invoice Amount"))}]/ancestor::td[1]")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t("Total Amount"))}]/ancestor::td[1]/following-sibling::td[1]")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t("Ticket Amount:"))}]", null, true, "/{$this->opt($this->t("Ticket Amount:"))}\s*(.+)/");

        $currency = $this->currency($totalPrice);
        $amount = $this->amount($totalPrice);

        if ($totalPrice === null) {
            $currencies = $amounts = [];
            $totalPriceValues = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t("Estimated Trip Total:"))}]", null, "/^{$this->opt($this->t("Estimated Trip Total:"))}[:\s]*(.*\d.*)$/"));

            foreach ($totalPriceValues as $tpVal) {
                if (preg_match('/^(?:(?<currency>[^\-\d)(]+?)[ ]*)?(?<amount>\d[,.‘\'\d ]*)$/u', $tpVal, $matches)) {
                    // CHF 6076.50
                    if (empty($matches['currency'])) {
                        $matches['currency'] = '';
                    }

                    if (!empty($matches['currency'])) {
                        $currencies[] = $matches['currency'];
                    }

                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                    $amounts[] = PriceHelper::parse($matches['amount'], $currencyCode);
                }
            }

            if (count(array_unique($currencies)) === 1) {
                $cur = array_shift($currencies);
                $currency = $this->currency($cur);
                $amount = array_sum($amounts);
            }
        }

        if ($currency === 'USD') {
            $due = $this->http->FindSingleNode(" //text()[{$this->starts($this->t("Balance Due:"))}]", null, false,
                "#{$this->t('Balance Due:')}\s*(.+)#");

            if (!empty($due) && $code = $this->currency($due)) {
                $currency = $code;
            }
        }
        $its = $this->parseHtml();

        if (count($its) === 1 && $its[0]["Kind"] == 'T') {
            $its[0]['TotalCharge'] = $amount;
            $its[0]['Currency'] = $currency;
        }
        $result = [
            'emailType'  => 'BlueTables' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
                'TotalCharge' => [
                    "Amount"   => $amount,
                    "Currency" => $currency,
                ],
            ],
        ];

        if (!empty($provider)) {
            $result['providerCode'] = $provider;
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

    private function parseHtml(): ?array
    {
        $patterns = [
            'time'           => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'phone'          => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName'  => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'travellerName2' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+[. ]{1,2})*[[:upper:]]+', // BROWNING / CHAD.R
            'eTicket'        => '(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]+)?\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004    |    AA 724-6338357762    |    LX - 724-6338357762
        ];

        $globalTravellers = $flightsPassengers = [];
        $travellerContainers = $this->http->XPath->query("//tr[ *[normalize-space()][1][{$this->eq($this->t("Traveler"))}] ][not(preceding::tr/*[{$this->eq($this->t("From/To"))}])]/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]");

        if ($travellerContainers->length === 1) {
            $globalTravellers = $this->http->FindNodes("following-sibling::tr[normalize-space()]/descendant-or-self::tr[not(.//tr) and normalize-space()][1]/*[normalize-space()][1]/descendant::text()[normalize-space()]", $travellerContainers->item(0), "/^{$patterns['travellerName2']}$/u");
            $globalTravellers = array_filter($globalTravellers);
        }

        if (count($globalTravellers) === 0) {
            // it-7036268-gant.eml, it-3554052-gant.eml, it-3554469-gant.eml
            $globalPassengersVal = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Passengers:"))}]", null, true, "/^{$this->opt($this->t("Passengers:"))}\s*({$patterns['travellerName']})/u");

            if ($globalPassengersVal) {
                $globalTravellers = [$globalPassengersVal];
            }
        }

        $globalTravellers = preg_replace(['/^(.{3,}?)\s+(?:MR|MS)$/i', '/^([^\/]+?)\s*\/\s*([^\/]+)$/'], ['$1', '$2 $1'], $globalTravellers);

        $itineraries = [];

        //##################
        //##   FLIGHTS   ###
        //##################
        $xpath = "//text()[" . $this->eq($this->t("From/To")) . "]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>1]";
        $nodes = $this->http->XPath->query($xpath);
        $codes = [];
        $cabin = [];
        // Date	From/To	Flight/Vendor	Status	Depart/Arrive	Class/Type
        // 06/24/2014	PVD-MDW	WN 281	Confirmed	11:45 AM/01:15 PM	Economy (Business Select)
        foreach ($nodes as $root) {
            if ($number = $this->http->FindSingleNode("./td[3]", $root, true, "#^\w{2}\s*(\d+)\*?$#")) {
                if (preg_match("#^([A-Z]{3})\s*-\s*([A-Z]{3})#", $this->http->FindSingleNode("./td[2]", $root), $m)) {
                    // day is for two flights on different directions FE: it-3118241.eml, it-3545684.eml
                    $day = date("Y-m-d", strtotime($this->http->FindSingleNode("./td[1]", $root)));
                    $codes[$number . '-' . $day] = [$m[1], $m[2]];
                    $codes[$number] = [$m[1], $m[2]];

                    if (($n = count($this->http->FindNodes("(./preceding-sibling::tr/td[" . $this->eq($this->t("Class/Type")) . "])[1]/preceding-sibling::td", $root))) > 0) {
                        $n++;
                        $cabin[$number] = $this->http->FindSingleNode("./td[{$n}]", $root);
                    }
                }
            }
        }

        $xpath = "//text()[" . $this->starts($this->t("AIR -")) . "]/ancestor::tr[./following-sibling::tr][1]/parent::*[" . $this->contains($this->t("Depart:")) . "]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH-FLIGHT]\n" . $xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Record Locator:")) . "]/ancestor::tr[1]", $root, true, "/" . $this->opt($this->t("Record Locator:")) . "\s*([A-Z\d]+)/")) {
                $airs[$rl][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Agency Record Locator")) . "]/ancestor::tr[1])[1]", null, true, "/" . $this->opt($this->t("Agency Record Locator")) . "\s*([A-Z\d]{5,7})/")) {
                $airs[$rl][] = $root;
            } else {
                $this->logger->info("RL not matched");

                return null;
            }
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Agency Record Locator")) . "])[1]/ancestor::tr[1]", $roots[0], true, "/" . $this->opt($this->t("Agency Record Locator")) . "\s*([A-Z\d]+)/");

            // Passengers
            $itPassengers = [];

            // TicketNumbers
            $tickets = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("Ticket Number:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, "/^{$patterns['eTicket']}$/"));

            if (count($tickets) === 0) {
                $tickets = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t("Ticket Number:"))}]", null, "/{$this->opt($this->t("Ticket Number:"))}[:\s]*({$patterns['eTicket']})$/"));
            }

            if (count($tickets) > 0) {
                $it['TicketNumbers'] = array_values(array_unique($tickets));
            }

            // AccountNumbers
            $accounts = [];

            foreach ($roots as $root) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("tr[string-length(normalize-space())>2][2]/descendant::text()[string-length(normalize-space())>2][1]", $root, true, "/(?:^|{$this->opt($this->t("Flight"))}\s+)(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/");

                // DepName
                // DepartureTerminal
                $depName = implode("\n", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space()]", $root));

                if (preg_match("#(.+), ([^,]*Terminal[^\n]*?)(?:\(.*)?(?:\s*\n\s*([\s\S]+))?$#is", $depName, $m)) {
                    $itsegment['DepName'] = implode(", ", array_filter([$m[1], $m[3] ?? null]));
                    $itsegment['DepartureTerminal'] = trim(preg_replace("#\s*Terminal\s*#i", ' ', $m[2]));
                } else {
                    $itsegment['DepName'] = preg_replace("#\s*\n\s*#", ', ', $depName);
                }

                $containsTime = "contains(translate(normalize-space(),'0123456789:','∆∆∆∆∆∆∆∆∆∆h'),'∆∆h∆∆')";

                // DepDate
                $depDateText1 = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Depart:"))}]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space())>1][position()<3][contains(.,':') or {$containsTime}][1]/td[contains(.,':') or {$containsTime}]", $root);
                $depDate = $this->normalizeDate($depDateText1);

                if (empty($depDate)) {// it-12238598.eml
                    $depDateText2 = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/following-sibling::tr[./td[2][" . $this->contains(":") . "]][1]/td[2]", $root);
                    $depDate = $this->normalizeDate($depDateText2);
                }

                if ($depDate > 1000000000) {
                    $itsegment['DepDate'] = $depDate;
                }
                $date = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][1]", $root, true, "/" . $this->opt($this->t("AIR -")) . "\s*(.+)$/"));

                if (empty($date)) {
                    $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space(.)][not(.//td)][1]", $root, true, "/" . $this->opt($this->t("AIR -")) . "\s*(.+)$/"));
                }

                if ($depDate < 1000000000 && preg_match("#^\D*\d+:\d+\D*$#", $depDateText1) && !empty($date)) {
                    $itsegment['DepDate'] = strtotime($depDateText1, $date);
                } elseif (isset($depDateText2) && $depDate < 1000000000 && preg_match("#^.*\d+:\d+\D*$#", $depDateText2) && !empty($date)) {
                    $itsegment['DepDate'] = strtotime($depDateText2, $date);
                }
                // DepCode
                $itsegment['DepCode'] = $this->http->FindPreg("#\(([A-Z]{3})\)#i", false, $depName);

                if (empty($itsegment['DepCode'])) {
                    if (isset($itsegment['DepDate'])
                        && !empty($day = date("Y-m-d", $itsegment['DepDate']))
                        && isset($codes[$itsegment['FlightNumber'] . '-' . $day])
                    ) {
                        $itsegment['DepCode'] = $codes[$itsegment['FlightNumber'] . '-' . $day][0];
                    } elseif (isset($codes[$itsegment['FlightNumber']])) {
                        $itsegment['DepCode'] = $codes[$itsegment['FlightNumber']][0];
                    }

                    if (!$itsegment['DepCode']) {
                        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                    }
                }

                // ArrName
                // ArrivalTerminal
                $arrName = implode("\n", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space()]", $root));

                if (preg_match("#(.+), ([^,]*Terminal[^\n]*?)(?:\(.*)?(?:\s*\n\s*([\s\S]+))?$#is", $arrName, $m)) {
                    $itsegment['ArrName'] = implode(", ", array_filter([$m[1], $m[3] ?? null]));
                    $itsegment['ArrivalTerminal'] = trim(preg_replace("#\s*Terminal\s*#i", ' ', $m[2]));
                } else {
                    $itsegment['ArrName'] = preg_replace("#\s*\n\s*#", ', ', $arrName);
                }

                // ArrDate
                $arrDateText1 = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Arrive:"))}]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space())>1][position()<3][contains(.,':') or {$containsTime}][1]/td[contains(.,':') or {$containsTime}]", $root);

                if (preg_match("/^{$patterns['time']}[,\s]+\d{1,2}\s+[[:alpha:]]+$/u", $arrDateText1) && !empty($itsegment['DepDate'])) {
                    // 15:55, 04 December
                    $arrDate = $this->normalizeDate($arrDateText1 . ' %Y%', $itsegment['DepDate']);
                } else {
                    $arrDate = $this->normalizeDate($arrDateText1);
                }

                if (empty($arrDate)) {
                    $arrDateText2 = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::tr[1]/following-sibling::tr[./td[2][" . $this->contains(":") . "]][1]/td[2]", $root);
                    $arrDate = $this->normalizeDate($arrDateText2);
                }

                if ($arrDate > 1000000000) {
                    $itsegment['ArrDate'] = $arrDate;
                }

                if ($arrDate < 1000000000 && preg_match("#^\D*\d+:\d+\D*$#", $arrDateText1) && !empty($date)) {
                    $itsegment['ArrDate'] = strtotime($arrDateText1, $date);
                } elseif (isset($arrDateText2) && $arrDate < 1000000000 && preg_match("#^.*\d+:\d+\D*$#", $arrDateText2) && !empty($date)) {
                    $itsegment['ArrDate'] = strtotime($arrDateText2, $date);
                }

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindPreg("#\(([A-Z]{3})\)#i", false, $arrName);

                if (empty($itsegment['ArrCode'])) {
                    if (isset($itsegment['ArrDate'])
                        && !empty($day = date("Y-m-d", $itsegment['ArrDate']))
                        && isset($codes[$itsegment['FlightNumber'] . '-' . $day])
                    ) {
                        $itsegment['ArrCode'] = $codes[$itsegment['FlightNumber'] . '-' . $day][1];
                    } elseif (isset($codes[$itsegment['FlightNumber']])) {
                        $itsegment['ArrCode'] = $codes[$itsegment['FlightNumber']][1];
                    }

                    if (!$itsegment['ArrCode']) {
                        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    }
                }

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("tr[string-length(normalize-space())>2][2]/descendant::text()[string-length(normalize-space())>2][1]", $root, true, "/(?:^|{$this->opt($this->t("Flight"))}\s+)([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+/");

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Operated By:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[string-length(normalize-space(.))>2][1]", $root);

                // Aircraft
                $itsegment['Aircraft'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Equipment:")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][1]", $root);

                // TraveledMiles
                $itsegment['TraveledMiles'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Distance:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

                if (isset($cabin[$itsegment['FlightNumber']])) {
                    // Cabin
                    if (!$itsegment['Cabin'] = trim(str_ireplace("Class", '', $this->re("#(.*?)\s*(?:/\s*[A-Z]|\(.*?\))$#u", $cabin[$itsegment['FlightNumber']])))) {
                        $itsegment['Cabin'] = trim(str_ireplace("Terminal", '', $this->re("#^([^\s\d]+)$#u", $cabin[$itsegment['FlightNumber']])));
                    }

                    // BookingClass
                    $itsegment['BookingClass'] = $this->re("#/\s*([A-Z])$#", $cabin[$itsegment['FlightNumber']]);
                } else {
                    $itsegment['Cabin'] = $this->http->FindSingleNode("tr[string-length(normalize-space())>2][2]/descendant::text()[string-length(normalize-space())>2][1]", $root, true, "/{$this->opt($this->t("Flight"))}\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+[ ]+(.+)/");
                }

                // Seats
                if (preg_match_all('/\b(\d{1,3}[A-Z])\b/', implode(' ', $this->http->FindNodes("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Seat:"))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]", $root)), $seatMatches)
                    || preg_match_all('/\b(\d{1,3}[A-Z])\b/', implode(' ', $this->http->FindNodes("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Remarks:"))}] ]/*[normalize-space()][2]/descendant::text()[contains(.,'SEAT')]", $root)), $seatMatches)
                ) {
                    $itsegment['Seats'] = $seatMatches[1];
                }

                // Duration
                $itsegment['Duration'] = trim($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Duration:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#(.*?)(?:{$this->t("Non-stop-reg")}|$)#i"));

                // Meal
                $itsegment['Meal'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Meal:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

                // Stops
                $itsegment['Stops'] = (int) ($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Duration:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#" . $this->t("Non-stop-reg") . "#"));

                $acc = array_filter($this->http->FindNodes("descendant::text()[{$this->eq($this->t("FF Number:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root, "/^([A-Z\d]{5,})\s*-\s*/"));

                if (count($acc) > 0) {
                    $accounts = array_merge($accounts, $acc);
                }

                $segPassengers = array_values(array_unique(array_filter(array_merge(
                    $this->http->FindNodes("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Seat:"))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]", $root, "/(?:\s+-\s+|Confirmed[-\s]+)(?-i)({$patterns['travellerName2']})$/iu"),
                    $this->http->FindNodes("descendant::text()[{$this->eq($this->t("Meal:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root, "/(?:\s+-\s+|Confirmed[-\s]+)(?-i)({$patterns['travellerName2']})$/iu"),
                    $this->http->FindNodes("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("FF Number:"))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]", $root, "/\s+-\s+({$patterns['travellerName2']})$/u")
                ))));
                $segPassengers = preg_replace(['/^(.{3,}?)\s+(?:MR|MS)$/i', '/^([^\/]+?)\s*\/\s*([^\/]+)$/'], ['$1', '$2 $1'], $segPassengers);

                if (count($segPassengers) > 0) {
                    $itPassengers = array_merge($itPassengers, $segPassengers);
                }

                $it['TripSegments'][] = $itsegment;
            }

            if (count($accounts) > 0) {
                $it['AccountNumbers'] = array_values(array_unique($accounts));
            }

            if (count($itPassengers) > 0) {
                $it['Passengers'] = array_values(array_unique($itPassengers));
                $flightsPassengers = array_merge($flightsPassengers, $it['Passengers']);
            } elseif (count($globalTravellers) > 0) {
                $it['Passengers'] = $globalTravellers;
            }

            $itineraries[] = $it;
        }

        $flightsPassengers = array_values(array_unique($flightsPassengers));

        //#################
        //##   HOTELS   ###
        //#################
        $xpath = "//text()[ {$this->starts($this->t("HOTEL -"))} and following::text()[{$this->eq($this->t("Address:"))}] ]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /*if ($this->http->XPath->query("./descendant::text()[normalize-space()='Reservation Name:']", $root)->length == 0) {
                $this->logger->error('NEXT');
                continue;
            }*/
            $it = [];
            $it['Kind'] = "R";

            // ConfirmationNumber
            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Confirmation:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Address:"))}]/ancestor::tr[1]/../tr[normalize-space() and not({$this->starts($this->t("HOTEL -"))})][1]/descendant-or-self::tr[normalize-space() and count(*)=3][1]/*[3]", $root, true, '/^[^:]+[:]+\s*([-A-Z\d\s]{2,}?)(?:\s*\(|$)/');
            $it['ConfirmationNumber'] = $confirmation;

            if ($acc = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("FF Number:")) . " or " . $this->eq($this->t("Frequent Guest ID:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root, true, "#^([A-Z\d]+)(?:\s*-\s*|$)#")) {
                $it['AccountNumbers'][] = $acc;
            }

            // HotelName
            $it['HotelName'] = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Address:"))}]/ancestor::tr[1]/../tr[normalize-space() and not({$this->starts($this->t("HOTEL -"))})][1]/descendant::*[not(.//tr) and normalize-space()][1]", $root);

            $dates = explode(" - ", $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Check In/Check Out:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root));

            if (count($dates) == 2) {
                // CheckInDate
                $it['CheckInDate'] = $this->normalizeDate($dates[0]);

                // CheckOutDate
                $it['CheckOutDate'] = $this->normalizeDate($dates[1]);
            } elseif (empty(array_filter($dates))) {
                $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Check Out:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root));

                $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][1]", $root, true, "/" . $this->opt($this->t("HOTEL -")) . "\s*(.+)$/"));

                if (empty($it['CheckInDate'])) {
                    $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space(.)!=''][not(.//td)][1]", $root, true, "/" . $this->opt($this->t("HOTEL -")) . "\s*(.+)$/"));
                }
            }

            // Address
            $address = implode(", ", $this->http->FindNodes("descendant::text()[{$this->eq($this->t("Address:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root));
            $addressNextFragments = [];
            $addressNextRows = $this->http->XPath->query("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t("Address:"))}] ]/following-sibling::tr[normalize-space()]", $root);

            foreach ($addressNextRows as $row) {
                $addressFragment = $this->http->FindSingleNode("self::tr[count(*)=2 and normalize-space(*[1])='']", $row);

                if ($addressFragment) {
                    $addressNextFragments[] = $addressFragment;
                } else {
                    break;
                }
            }

            if (count($addressNextFragments) > 0) {
                $address = implode(', ', array_filter([$address, implode(', ', $addressNextFragments)]));
            }

            $it['Address'] = $address;

            // Phone
            $it['Phone'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Tel:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root);

            // Fax
            $it['Fax'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Fax:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root);

            // GuestNames
            $reservationName = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Reservation Name:"))}] ]/*[normalize-space()][2]", $root, true, "/^{$patterns['travellerName2']}$/u")
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Name:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, "/^(?:{$patterns['travellerName']}|{$patterns['travellerName2']})$/u");

            if ($reservationName) {
                $it['GuestNames'] = [preg_replace(['/^(.{3,}?)\s(?:MR|MS)$/i', '/^([^\/]+?)\s*\/\s*([^\/]+)$/'], ['$1', '$2 $1'], $reservationName)];
            } else {
                $it['GuestNames'] = count($globalTravellers) > 0 ? $globalTravellers : $flightsPassengers;
            }

            if (empty($it['GuestNames'])) {
                $it['GuestNames'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Traveler")) . "]/ancestor::tr[1]/following-sibling::tr[count(.//text()[string-length(normalize-space(.))>2])=1]");
            }

            if (empty($it['GuestNames'])) {
                $it['GuestNames'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Traveler")) . "]/ancestor::td[1]/following-sibling::td[count(.//text()[string-length(normalize-space(.))>2])=1]",
                    null, "/^\s*[[:alpha:]][[:alpha:]\- ]+[[:alpha:]]\/[[:alpha:]][[:alpha:]\- ]+[[:alpha:]]\s*$/");
            }

            $it['GuestNames'] = preg_replace("/\*/", "", $it['GuestNames']);

            // Guests
            $it['Guests'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Number of Persons:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root);

            // Kids
            // Rooms
            $it['Rooms'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Number of Rooms:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root);

            // Rate
            $it['Rate'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Rate per night:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root);

            // RateType
            // CancellationPolicy
            if (!$it['CancellationPolicy'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Cancellation Policy:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root)) {
                $it['CancellationPolicy'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Remarks:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1][" . $this->contains("CXL") . "]", $root);
            }

            // RoomType
            $roomType = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Room Type:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Address:"))}]/ancestor::tr[1]/../tr[normalize-space() and not({$this->starts($this->t("HOTEL -"))})][1]/descendant-or-self::tr[normalize-space() and count(*)=3][1]/*[2]", $root);

            if (strlen($roomType) >= 250) {
                $roomType = $this->re("/$(.{2,}?)\s*\(/", $roomType);
            }

            $it['RoomType'] = $roomType;

            // RoomTypeDescription
            $it['RoomTypeDescription'] = $this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Description:")) . "])[1]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root);

            // Cost
            // Taxes
            // Total
            // Currency
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Status:")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]", $root);

            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //###############
        //##   CARS   ###
        //###############
        $xpath = "//text()[ {$this->starts($this->t("CAR -"))} and following::text()[{$this->eq($this->t("Pick Up:"))}] ]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];
            $it['Kind'] = "L";

            // Number
            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Confirmation:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, '/^([-A-Z\d\s]{2,}?)(?:\s*\(|$)/')
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::tr[1]/../tr[normalize-space() and not({$this->starts($this->t("CAR -"))})][1]/descendant-or-self::tr[normalize-space() and count(*)=3][1]/*[3]", $root, true, '/^[^:]+[:]+\s*([-A-Z\d\s]{2,}?)(?:\s*\(|$)/');
            $it['Number'] = preg_replace('/\s+/', '-', $confirmation);

            // AccountNumbers
            if ($acc = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Frequent Renter ID:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root, false, "#^¤?([A-Z\d]{5,})#u")
            ) {
                $it['AccountNumbers'][] = $acc;
            }

            // PickupDatetime
            $PickupDatetime = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space())>2 and not({$this->contains($this->t("Tel:"))}) and not({$this->contains($this->t("Fax:"))})][1]/td[2]", $root);
            $it['PickupDatetime'] = $this->normalizeDate($PickupDatetime);

            $date = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][1]", $root, true, "/" . $this->opt($this->t("CAR -")) . "\s*(.+)$/"));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space(.)][not(.//td)][1]", $root, true, "/" . $this->opt($this->t("CAR -")) . "\s*(.+)$/"));
            }

            if ($it['PickupDatetime'] < 1000000000 && preg_match("#^\D*\d+:\d+\D*$#", $PickupDatetime) && !empty($date)) {
                $it['PickupDatetime'] = strtotime($PickupDatetime, $date);
            }

            // PickupLocation
            $it['PickupLocation'] = $this->re("/^(.*?)\s*(?:[,;]+\s*{$this->opt($this->t("Tel:"))}|$)/i", implode(", ", $this->http->FindNodes("descendant::text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root)));

            // DropoffDatetime
            $it['DropoffDatetime'] = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space())>2 and not({$this->contains($this->t("Tel:"))}) and not({$this->contains($this->t("Fax:"))})][1]/td[2]", $root));

            // DropoffLocation
            $it['DropoffLocation'] = $this->re("/(.*?)(?:[,;]+\s*{$this->opt($this->t("Tel:"))}|$)/i", implode(", ", $this->http->FindNodes("descendant::text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root)));

            $patternPhone = "/{$this->opt($this->t("Tel:"))}\s*({$patterns['phone']})[,;\s]*(?:{$this->opt($this->t("Fax:"))}|$)/i";
            $patternFax = "/{$this->opt($this->t("Fax:"))}\s*({$patterns['phone']})/i";

            // PickupPhone
            $it['PickupPhone'] = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, $patternPhone)
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Drop Off:"))}]/preceding::text()[normalize-space()][position()<10][{$this->eq($this->t("Tel:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, "/^{$patterns['phone']}$/")
                ?? $this->http->FindSingleNode("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t("Pick Up:"))}] ]/following-sibling::tr[normalize-space()][1][count(*)=2 and normalize-space(*[1])='']/*[2]", $root, true, $patternPhone)
            ;

            // PickupFax
            $pickupFax = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, $patternFax)
                ?? $this->http->FindSingleNode("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t("Pick Up:"))}] ]/following-sibling::tr[normalize-space()][1][count(*)=2 and normalize-space(*[1])='']/*[2]", $root, true, $patternFax)
            ;

            if ($pickupFax) {
                $it['PickupFax'] = $pickupFax;
            }

            // DropoffPhone
            $it['DropoffPhone'] = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, $patternPhone)
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Drop Off:"))}]/following::text()[normalize-space()][position()<10][{$this->eq($this->t("Tel:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, "/^{$patterns['phone']}$/")
                ?? $this->http->FindSingleNode("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t("Drop Off:"))}] ]/following-sibling::tr[normalize-space()][1][count(*)=2 and normalize-space(*[1])='']/*[2]", $root, true, $patternPhone)
            ;

            // DropoffFax
            $dropoffFax = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, $patternFax)
                ?? $this->http->FindSingleNode("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t("Drop Off:"))}] ]/following-sibling::tr[normalize-space()][1][count(*)=2 and normalize-space(*[1])='']/*[2]", $root, true, $patternFax)
            ;

            if ($dropoffFax) {
                $it['DropoffFax'] = $dropoffFax;
            }

            // RentalCompany
            $it['RentalCompany'] = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::tr[1]/../tr[normalize-space() and not({$this->starts($this->t("CAR -"))})][1]/descendant::*[not(.//tr) and normalize-space()][1]", $root);

            // CarType
            // CarModel
            $carType = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Type:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root);
            $carModel = null;

            $carDetails = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::tr[1]/../tr[normalize-space() and not({$this->starts($this->t("CAR -"))})][1]/descendant-or-self::tr[normalize-space() and count(*)=3][1]/*[2]", $root);

            if (preg_match("/^(?<type>.{2,}?)\s*\(\s*(?:e\.g\.\s*[:]+\s*)?(?<model>.{2,}?(?:\s+OR SIMILAR)?)\s*\)$/i", $carDetails, $m)) {
                // Intermediate 2/4 Door, Automatic Transmission, Air Conditioning (e.g.: C MAZDA 3 4 DOOR OR SIMILAR)
                $carType = $m[1];
                $carModel = $m[2];
            } elseif (!$carType && $carDetails) {
                $carType = $carDetails;
            }

            $it['CarType'] = $carType;

            if ($carModel) {
                $it['CarModel'] = $carModel;
            }

            // RenterName
            $renterName = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Name:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, "/^(?:{$patterns['travellerName']}|{$patterns['travellerName2']})$/u");

            if ($renterName) {
                $it['RenterName'] = preg_replace(['/^(.{3,}?)\s+(?:MR|MS)$/i', '/^([^\/]+?)\s*\/\s*([^\/]+)$/'], ['$1', '$2 $1'], $renterName);
            }

            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Estimated Total:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root));

            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Estimated Total:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root));

            // Status
            $status = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t("Status:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root);

            if ($status) {
                $it['Status'] = $status;
            }

            $itineraries[] = $it;
        }

        //###################
        //##   TRANSFER   ###
        //###################
        $xpath = "//text()[" . $this->starts($this->t("LIMO -")) . "]/ancestor::tr[./following-sibling::tr][1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "T";
            // RecordLocator
            $it['RecordLocator'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Confirmation Number:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // TripNumber
            // Passengers
            $it['Passengers'] = count($globalTravellers) > 0 ? $globalTravellers : $flightsPassengers;

            // AccountNumbers
            // Cancelled
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Rate:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root));

            // BaseFare
            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Rate:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root));

            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            $it['Status'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Status:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // ReservationDate
            // NoItineraries
            // TripCategory
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

            $itsegment = [];

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Pickup Location:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // DepDate
            $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Pickup Date and Time:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Dropoff Location:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            $it['TripSegments'][] = $itsegment;

            $itineraries[] = $it;
        }

        //################
        //##   RAILS   ###
        //################
        $xpath = "//text()[" . $this->starts($this->t("RAIL -")) . "]/ancestor::tr[./following-sibling::tr][1]/parent::*[" . $this->contains($this->t("Depart:")) . "]";
        $this->logger->info("XPATH RAIL: {$xpath}");
        $nodes = $this->http->XPath->query($xpath);
        $rails = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Record Locator:")) . "]/ancestor::tr[1]", $root, true, "/" . $this->opt($this->t("Record Locator:")) . "\s*([A-Z\d]+)/")) {
                $rails[$rl][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Agency Record Locator")) . "])[1]/ancestor::tr[1]", $root, true, "/" . $this->opt($this->t("Agency Record Locator")) . "\s*([A-Z\d]+)/")) {
                $rails[$rl][] = $root;
            } else {
                $this->logger->info("RL not matched");

                return null;
            }
        }

        foreach ($rails as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Agency Record Locator")) . "])[1]/ancestor::tr[1]", $root, true, "/" . $this->opt($this->t("Agency Record Locator")) . "\s*([A-Z\d]+)/");

            // Passengers
            $it['Passengers'] = count($globalTravellers) > 0 ? $globalTravellers : $flightsPassengers;

            // TicketNumbers
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
            foreach ($roots as $root) {
                $itsegment = [];

                $class = $this->http->FindSingleNode("descendant::td[{$this->contains($this->t("Class:"))} and not(.//tr)]/following::td[1]", $root, true, '/\b[A-Z\d]{1,2}\b/');
                $itsegment['Cabin'] = $class;

                $date = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("RAIL -"))}]", $root, true, "/{$this->opt($this->t("RAIL -"))}\s*(.{6,})/i"));

                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[string-length(normalize-space(.))>2][2]/descendant::text()[string-length(normalize-space(.))>2][1]", $root, true, "/" . $this->opt($this->t("Train Number")) . " (\d+)/");

                if (!$itsegment['FlightNumber']) {
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Train Number"))}]", $root, true, '/\b(\d+)\b/');
                }

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = implode(", ", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space()]", $root));

                // DepDate
                $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>2][1]/td[2]", $root));

                if (!empty($itsegment['DepDate']) && is_numeric($itsegment['DepDate']) && $itsegment['DepDate'] < 1000000000) {
                    $itsegment['DepDate'] = null;
                }

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = implode(", ", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space()]", $root));

                // ArrDate
                $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>2][1]/td[2]", $root));

                if (!empty($itsegment['ArrDate']) && is_numeric($itsegment['ArrDate']) && $itsegment['ArrDate'] < 1000000000) {
                    $itsegment['ArrDate'] = null;
                }

                $times = array_values(array_filter($this->http->FindNodes(".//text()[{$this->eq($this->t("Depart:"))}]/ancestor::td[1]/following::td[normalize-space()][position()<10]", $root, '/^\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?$/')));

                if (!$itsegment['DepDate'] && !$itsegment['ArrDate'] && 2 === count($times)) {
                    $itsegment['DepDate'] = strtotime($times[0], $date);
                    $itsegment['ArrDate'] = strtotime($times[1], $date);
                }

                // Type
                // Vehicle
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                $seats = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Seat:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);
                // no examples for 2 or more seats
                if (preg_match_all("/(" . $this->opt($this->t("Voiture")) . " [A-Z\d]{1,5} ?\W? ?" . $this->opt($this->t("Place")) . " [A-Z\d]{1,5})(?:\s+|$|\W)/", $seats, $m)) {
                    $itsegment['Seats'] = $m[1];
                }

                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Duration:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#(.*?)(?:" . $this->t("Non-stop-reg") . "|$)#");

                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }

        return $itineraries;
    }

    private function getProvider($body): ?string
    {
        foreach (self::$reBody as $prov=>$reBody) {
            foreach ($reBody as $re) {
                if (strpos($body, $re) !== false) {
                    return $prov;
                }
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        $in = [
            "#^(\d+:\d+ [AP]M) [^\s\d]+, ([^\s\d]+) (\d+) (\d{4})$#", //9:54 AM Thursday, June 27 2013
            "#^[^\s\d]+, ([^\s\d]+) (\d+) (\d{4})$#", //Thursday, June 27 2013
            "#^(\d+)h(\d+) [^\s\d]+, (\d+)\. ([^\s\d]+) (\d{4})$#", //13h10 Montag, 30. Juli 2012
            "#^(\d+:\d+) [^\s\d]+[, ]+(\d+)\.? ([^\s\d]+?) (\d{4})$#", //18:00 Monday, 14 September 2015  //18:00  Dienstag, 17. Dezember 2019 // 16:55 jeudi 12 juin 2014
            "#^(\d+:\d+), [^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //18:00, Thursday 17 September 2015
            "#^[^\s\d]+, (\d+) de ([^\s\d]+) de (\d{4})$#", //miércoles, 14 de junio de 2017
            "#^(\d+:\d+) [^\s\d]+, (\d+) de ([^\s\d]+) de (\d{4})$#", //miércoles, 14 de junio de 2017
            "#^(\d+:\d+ [AP]M) [^\s\d]+ ([^\s\d]+) (\d+) (\d{4})$#", //3:45 PM Tuesday May 1 2018
            "#^[^\s\d]+, (\d+)\.? ([^\s\d]+?) (\d{4})$#", //Monday, 14 September 2015  //Dienstag, 17. Dezember 2019
        ];
        $out = [
            "$3 $2 $4, $1",
            "$2 $1 $3",
            "$3 $4 $5, $1:$2",
            "$2 $3 $4, $1",
            "$2, $1",
            "$1 $2 $3",
            "$2 $3 $4, $1",
            "$3 $2 $4, $1",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (preg_match("/^(.+) %Y%$/", $str, $m)) {
            return EmailDateHelper::parseDateRelative($m[1], $relDate, true, '%D% %Y%');
        }

        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
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

        return null;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
