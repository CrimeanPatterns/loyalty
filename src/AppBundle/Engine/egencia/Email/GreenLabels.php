<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Engine\MonthTranslate;

class GreenLabels extends \TAccountChecker
{
    public $mailFiles = "egencia/it-11619333.eml, egencia/it-12526318.eml, egencia/it-13172230.eml, egencia/it-13172233.eml, egencia/it-13172321.eml, egencia/it-29559110.eml, egencia/it-6049135.eml, egencia/it-6430450.eml, egencia/it-6522730.eml, egencia/it-6737438.eml, egencia/it-6902960.eml, egencia/it-8754798.eml, egencia/it-8818020.eml";
    public $reFrom = "@customercare.egencia.com";
    public $reSubject = [
        "en" => "Train - ",
        "fr" => "Avion - ",
        "fr2"=> "Hôtel - ",
        "de" => "Flug - ",
        "nl" => "Hotel -",
        "da" => "Fly -",
    ];
    public $reBody = 'Egencia';
    public $reBody2 = [
        "en"=> "TRIP STATUS",
        "fr"=> "STATUT DU VOYAGE",
        "de"=> "REISESTATUS",
        "sv"=> "RESANS STATUS",
        "nl"=> "REISSTATUS",
        "it"=> "STATO DELL'ITINERARIO",
        "da"=> "REJSENS STATUS",
        "fi"=> "MATKAN TILA",
        "no"=> "BESTILLINGSSTATUS",
    ];

    public static $dictionary = [
        "en" => [
            "Itinerary #"=> ["Itinerary #", "Egencia Reference #"],
            "Pickup"     => ["Pick Up", "Pickup"],
            "Dropoff"    => ["Drop Off", "Dropoff"],
        ],
        "fr" => [
            "Traveler Details"=> "Informations voyageur(s)",

            "Total Price"   => "Prix total",
            "BOOKING STATUS"=> "STATUT DE LA RÉSERVATION",
            //			"Not Booked" => "",
            //
            // FLIGHT/RAIL
            "DEPARTURE"=> "DÉPART",
            "ARRIVAL"  => "ARRIVÉE",
            "Ticket#"  => "Billet no.",
            "Terminal" => "Terminal",
            //			"Operated by"=>"NOTTRANSLATED",
            "Seat "               => "Siège(s) ",
            "Total Duration"      => "Durée du trajet",
            "Leg Duration"        => "Delsträckans restid",
            "Supplier reference #"=> "Référence fournisseur #",
            "Itinerary #"         => "Référence Egencia #",
            //			"Train #" => "",
            //			"Class #" => "",

            //HOTEL
            "Check in"       => ["Enregistrement"],
            "Check out"      => ["Paiement"],
            "Adults Per Room"=> "Nombre d'adultes par chambre ",
            "Room reference:"=> ["Confirmation::"],

            //CARS
            "Pickup" => "Prise en charge",
            "Dropoff"=> "Restitution",
        ],
        "de" => [
            "Traveler Details"=> "Angaben zum Reisenden",

            "Total Price"   => "Gesamtpreis",
            "BOOKING STATUS"=> "BUCHUNGSSTATUS",
            //			"Not Booked" => "",
            //
            // FLIGHT/RAIL
            "DEPARTURE"=> "ABFLUG",
            "ARRIVAL"  => "ANKUNFT",
            //			"Ticket#"=>"NOTTRANSLATED",
            "Terminal"   => "Terminal",
            "Operated by"=> "Betrieben von",
            //			"Seat "=>"NOTTRANSLATED",
            "Total Duration"      => "Reisedauer",
            "Leg Duration"        => "Flugdauer",
            "Supplier reference #"=> "Buchungsnr #",
            "Itinerary #"         => "Egencia Buchungsnr #",
            //			"Train #" => "",
            //			"Class #" => "",

            //HOTEL
            "Check in"       => "Check-in",
            "Check out"      => "Check-out",
            "Adults Per Room"=> "Erwachsene pro Zimmer",
            "Room reference:"=> ["Bestätigung:", "Zimmerreferenz:"],

            //CARS
            //			"Pickup"=>"",
            //			"Dropoff"=>"",
        ],
        "sv" => [
            "Traveler Details"=> "Uppgifter om resenären",

            "Total Price"   => "Totalt pris",
            "BOOKING STATUS"=> "BOKNINGSSTATUS",
            //			"Not Booked" => "",

            // FLIGHT/RAIL
            "DEPARTURE"  => "AVGÅNG",
            "ARRIVAL"    => "ANKOMST",
            "Ticket#"    => "Biljettnr",
            "Terminal"   => "Terminal",
            "Operated by"=> "Trafikeras av",
            //			"Seat "=>"NOTTRANSLATED",
            "Total Duration"      => "Total restid",
            "Leg Duration"        => "Delsträckans restid",
            "Supplier reference #"=> "Leverantörsreferens #",
            "Itinerary #"         => "Egencia referens #",
            //			"Train #" => "",
            //			"Class #" => "",

            //HOTEL
            "Check in"       => "Incheckning",
            "Check out"      => "Utcheckning",
            "Adults Per Room"=> "Vuxna per rum",
            "Room reference:"=> "Rumsreferens:",

            //CARS
            "Pickup" => "Upphämtning",
            "Dropoff"=> "Avlämning",
        ],
        "nl" => [
            "Traveler Details"=> "Reizigersdetails",

            "Total Price"   => "Totaalprijs",
            "BOOKING STATUS"=> "BOEKINGSSTATUS",
            //			"Not Booked" => "",

            //			// FLIGHT/RAIL
            //			"DEPARTURE"=>"",
            //			"ARRIVAL"=>"",
            //			"Ticket#"=>"",
            //			"Terminal"=>"Terminal",
            //			"Operated by"=>"",
            //			"Seat "=>"",
            //			"Total Duration"=>"",
            //			"Leg Duration"=>"",
            //			"Supplier reference #"=>"",
            "Itinerary #"=> "Ref. agentschap #",
            //			"Train #" => "",
            //			"Class #" => "",

            //HOTEL
            "Check in"       => "Check-in",
            "Check out"      => "Check-out",
            "Room reference:"=> "Bevestiging:",
            "Adults Per Room"=> "Volwassenen per kamer",

            //CARS
            //			"Pickup"=>"Upphämtning",
            //			"Dropoff"=>"Avlämning",
        ],
        "it" => [
            "Traveler Details"=> "Dettagli del viaggiatore",

            "Total Price"   => "Prezzo totale",
            "BOOKING STATUS"=> "STATO DELLA PRENOTAZIONE",
            //			"Not Booked" => "",

            // FLIGHT/RAIL
            "DEPARTURE"=> "PARTENZA",
            "ARRIVAL"  => "ARRIVO",
            //"Ticket#"=>"NOTTRANSLATED",
            "Terminal"=> "Terminal",
            //"Operated by"=>"NOTTRANSLATED",
            "Seat "               => "NOTTRANSLATED",
            "Total Duration"      => "Durata totale",
            "Leg Duration"        => "Durata della tratta",
            "Supplier reference #"=> "Riferimento del fornitore #",
            "Itinerary #"         => "Rif. agenzia #",
            //			"Train #" => "",
            //			"Class #" => "",

            //HOTEL
            //			"Check in"=>"NOTTRANSLATED",
            //			"Check out"=>"NOTTRANSLATED",
            //			"Room reference:"=>"NOTTRANSLATED",
            //			"Adults Per Room"=>"NOTTRANSLATED",

            //CARS
            //			"Pickup"=>"NOTTRANSLATED",
            //			"Dropoff"=>"NOTTRANSLATED",
        ],
        "da" => [
            "Traveler Details"=> "Rejsende:",

            "Total Price"   => "Samlet pris",
            "BOOKING STATUS"=> "RESERVATIONSSTATUS",
            //			"Not Booked" => "",

            // FLIGHT/RAIL
            "DEPARTURE"           => "AFREJSE",
            "ARRIVAL"             => "ANKOMST",
            "Ticket#"             => "Billetnummer",
            "Terminal"            => "Terminal",
            "Operated by"         => "Betjenes af",
            "Seat "               => "Sæde ",
            "Total Duration"      => "Samlet rejsetid",
            "Leg Duration"        => "Rejsetid for strækning",
            "Supplier reference #"=> "Leverandørreference #",
            "Itinerary #"         => "Egencia-reference #",
            //			"Train #" => "",
            //			"Class #" => "",

            //HOTEL
            "Check in"       => ["CHECK-IN", "Check-in"],
            "Check out"      => ["UDTJEKNING", "Udtjekning"],
            "Adults Per Room"=> "Voksne pr. værelse",
            "Room reference:"=> "Bekræftelse:",

            //CARS
            "Pickup" => "Afhentning",
            "Dropoff"=> "Aflevering",
        ],
        "fi" => [
            "Traveler Details"=> "Matkustajan tiedot",

            "Total Price"   => "Kokonaishinta",
            "BOOKING STATUS"=> "VARAUKSEN TILA",
            //			"Not Booked" => "",

            // FLIGHT/RAIL
            "DEPARTURE"           => "LÄHTÖ",
            "ARRIVAL"             => "SAAPUMINEN",
            "Ticket#"             => "Lippunumero",
            "Terminal"            => "Terminaali",
            "Operated by"         => "NOTTRANSLATED",
            "Seat "               => "NOTTRANSLATED",
            "Total Duration"      => "Kokonaiskesto",
            "Leg Duration"        => "Osuuden pituus",
            "Supplier reference #"=> "Myyjän viitenumero #",
            "Itinerary #"         => ["Egencian varaustunnus #", "Egencia Reference #"],
            //			"Train #" => "",
            //			"Class #" => "",

            //HOTEL
            "Check in"       => "CHECK IN",
            "Check out"      => "CHECK OUT",
            "Adults Per Room"=> "Adults Per Room",
            "Room reference:"=> "Vahvistus:",

            //CARS
            "Pickup" => "NOTTRANSLATED",
            "Dropoff"=> "NOTTRANSLATED",
        ],
        "no" => [
            "Traveler Details"=> "Reisendes detaljer",

            "Total Price"   => "Totalpris",
            "BOOKING STATUS"=> "BESTILLINGSSTATUS",
            //			"Not Booked" => "",

            // FLIGHT/RAIL
            "DEPARTURE"=> "AVREISE",
            "ARRIVAL"  => "ANKOMST",
            //			"Ticket#"=>"",
            //			"Terminal"=>"",
            "Operated by"         => "NOTTRANSLATED",
            "Seat "               => "NOTTRANSLATED",
            "Total Duration"      => "Total varighet",
            "Leg Duration"        => "Reisetid for strekningen",
            "Supplier reference #"=> "Leverandørens referanse #",
            "Itinerary #"         => ["Egencias referansenr. #"],
            //			"Train #" => "",
            //			"Class #" => "",

            //HOTEL
            //			"Check in"=>"CHECK IN",
            //			"Check out"=>"CHECK OUT",
            //			"Adults Per Room"=>"Adults Per Room",
            //			"Room reference:"=>"Vahvistus:",

            //CARS
            //			"Pickup"=>"NOTTRANSLATED",
            //			"Dropoff"=>"NOTTRANSLATED",
        ],
    ];

    public $lang = "en";

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

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'GreenLabels' . ucfirst($this->lang),
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

    private function parseHtml(&$itineraries)
    {
        //###############
        //##   RAIL   ###
        //###############
        //region rail
        $xpath = "//text()[" . $this->eq($this->t("DEPARTURE")) . "]/ancestor::tr[" . $this->contains($this->t("ARRIVAL")) . "][1][./preceding::img[contains(@src, '-filled.png')][1][contains(@src, 'white-rail-filled.png')]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && $this->http->XPath->query("//img[contains(@src, 'rail-filled.png')] | //img[contains(@alt, 'train_icon') or contains(@alt, 'Train_icon')]")->length > 0) {
            $this->logger->debug('train not found');
            $itineraries = ['Kind' => "T"];

            return false;
        }
        $trains = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]//text()[" . $this->eq($this->t("Supplier reference #")) . "]/following::text()[normalize-space(.)][1]", $root)) {
                $trains[$rl][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]//text()[" . $this->eq($this->t("Itinerary #")) . "]/following::text()[normalize-space(.)][1]", $root)) {
                $trains[$rl][] = $root;
            } else {
                $this->http->log("locator not found");

                return false;
            }
        }

        foreach ($trains as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Traveler Details")) . "]/ancestor::tr[1]/following-sibling::tr//tr", null, "#\d+\)\s+(.+)#"));

            // TicketNumbers
            // AccountNumbers
            $it['AccountNumbers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Traveler Details")) . "]/ancestor::tr[1]/following-sibling::tr", null, "/.+\)\s*.+? #\s+([\d-]+)$/"));
            // Cancelled
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("./ancestor::tr[1]/..//text()[" . $this->contains($this->t("Total Price")) . "]", $roots[0], true, "#" . $this->preg_implode($this->t("Total Price")) . "[\s-:]+(.+)#"));

            // BaseFare
            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode("./ancestor::tr[1]/..//text()[" . $this->contains($this->t("Total Price")) . "]", $roots[0], true, "#" . $this->preg_implode($this->t("Total Price")) . "[\s-:]+(.+)#"));

            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            $xpath = "//text()[" . $this->eq($this->t("DEPARTURE")) . "]/ancestor::tr[{$this->contains($this->t('DEPARTURE'))} and {$this->contains($this->t('ARRIVAL'))}][1][./preceding::img[contains(@src, '-filled.png')][1][contains(@src, 'white-rail-filled.png')]]";
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length == 0) {
                $this->logger->info("segments root not found: {$xpath}");
            }
            //			$this->logger->info("Segments: {$xpath}");

            foreach ($nodes as $root) {
                $head = $this->http->XPath->query("./ancestor::tr[1]/preceding-sibling::tr[1]", $root)->item(0);
                $itsegment = [];
                // FlightNumber
                if ($fn = $this->nextText($this->t("Train #"), $head)) {
                    $itsegment['FlightNumber'] = $fn;
                } else {
                    $itsegment["FlightNumber"] = FLIGHT_NUMBER_UNKNOWN;
                }

                // DepCode
                $itsegment["DepCode"] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment["DepName"] = $this->http->FindSingleNode("./*[name() = 'th' or name() = 'td'][1]/descendant::tr[1]/../tr[4]", $root);

                // DepAddress
                // DepDate
                $itsegment["DepDate"] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./*[name() = 'th' or name() = 'td'][1]/descendant::tr[1]/../tr[position()=2 or position()=3]", $root))));

                // ArrCode
                $itsegment["ArrCode"] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment["ArrName"] = $this->http->FindSingleNode("./*[name() = 'th' or name() = 'td'][3]/descendant::tr[1]/../tr[4]", $root);

                // ArrAddress
                // ArrDate
                $itsegment["ArrDate"] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./*[name() = 'th' or name() = 'td'][3]/descendant::tr[1]/../tr[position()=2 or position()=3]", $root))));

                // Type
                $itsegment["Type"] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $head) . " " . $this->nextText("Train #", $head);

                // Vehicle
                // TraveledMiles
                // Cabin
                $itsegment["Cabin"] = $this->nextText($this->t("Class #"), $head);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                $itsegment["Duration"] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Leg Duration")) . "]", $root, true, "#" . $this->t("Leg Duration") . "\s+(.+)#");

                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
        //endregion

        //##################
        //##   FLIGHTS   ###
        //##################
        //region flight
        $xpath = "//text()[" . $this->eq($this->t("DEPARTURE")) . "]/ancestor::tr[" . $this->contains($this->t("ARRIVAL")) . "][1][./preceding::img[contains(@src, '-filled.png') or contains(@alt, 'Flight_icon')][1][contains(@src, 'white-flight-filled.png') or contains(@alt, 'Flight_icon')]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && $this->http->XPath->query("//img[contains(@src, 'flight-filled.png')] | //img[contains(@alt, 'flight_icon') or contains(@alt, 'Flight_icon')]")->length > 0) {
            $this->logger->debug('flight not found');
            $itineraries = ['Kind' => "T"];

            return false;
        }
        $airs = [];

        foreach ($nodes as $root) {
            $segmentsDirection = $this->http->FindSIngleNode("./ancestor::tr[./preceding-sibling::tr and count(./../tr)=2][1]/../tr[1]", $root);

            if ($rl = $this->http->FindSingleNode("./preceding::tr[1]//text()[" . $this->eq($this->t("Supplier reference #")) . "]/following::text()[normalize-space(.)][1]", $root)) {
                $airs[$rl][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]//text()[" . $this->eq($this->t("Itinerary #")) . "]/following::text()[normalize-space(.)][1]", $root)) {
                $airs[$rl][] = $root;
            } elseif ($this->http->FindSingleNode("//text()[" . $this->eq($segmentsDirection) . "]/ancestor::tr[1]/..//text()[" . $this->eq($this->t("BOOKING STATUS")) . "]/following::text()[normalize-space(.)][1][" . $this->eq($this->t("Not Booked")) . "]", $root)) {
                $airs[CONFNO_UNKNOWN][] = $root;
            } else {
                $this->http->log("locator not found");

                return false;
            }
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Traveler Details")) . "]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space(.)][1]", null, "#\d+\)\s+(.+)#"));

            // TicketNumbers
            $it['TicketNumbers'] = [];
            $items = $this->http->FindNodes("//text()[" . $this->eq($this->t("Traveler Details")) . "]/ancestor::tr[1]/following-sibling::tr", null, "@" . $this->t("Ticket#") . "\s+([\d-,]+)@");

            foreach ($items as $item) {
                $tn = explode(",", $item);
                $it['TicketNumbers'] = array_merge($it['TicketNumbers'], $tn);
            }
            $it['TicketNumbers'] = array_filter($it['TicketNumbers']);
            //$it['TicketNumbers'] = array_filter(array_merge(array_map(function($s){ return explode(",", $s); }, $this->http->FindNodes("//text()[".$this->eq($this->t("Traveler Details"))."]/ancestor::tr[1]/following-sibling::tr", null, "@".$this->t("Ticket#")."\s+([\d-,]+)@"))));

            // AccountNumbers
            $programs = [
                'Delta SkyMiles',
                'Eurobonus',
            ];
            $it['AccountNumbers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Traveler Details")) . "]/ancestor::tr[1]/following-sibling::tr", null, "/(?:{$this->preg_implode($programs)})\s*#\s+([\d-]+)/"));

            // Cancelled
            if (count($airs) == 1) {
                // TotalCharge
                $it['TotalCharge'] = 0.0;

                // BaseFare
                // Currency
                $it['Currency'] = $this->currency($this->http->FindSingleNode("./ancestor::tr[1]/..//text()[" . $this->contains($this->t("Total Price")) . "]", end($roots), true, "#" . $this->t("Total Price") . "[\s-:]+(.+)#"));
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            if ($rl == CONFNO_UNKNOWN) {
                $it['Status'] = $this->t("Not Booked");
            } else {
                $it['Status'] = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("BOOKING STATUS")) . "][./preceding::img[contains(@src, '-filled.png')][1][contains(@src, 'white-flight-filled')]])[1]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
            }

            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($roots as $root) {
                $headroot = $this->http->XPath->query("./ancestor::tr[1]/preceding-sibling::tr[1]/descendant::tr[1]", $root)->item(0);

                $itsegment = [];
                // FlightNumber
                if (!$itsegment['FlightNumber'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][2]", $headroot, true, "#^\w{2}(\d+)$#")) {
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][1]", $headroot, true, "#^\w{2}(\d+)$#");
                }

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][3]", $root);

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][5]", $root);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][6]", $root, true, "#" . $this->t("Terminal") . "\s+(.*?),#");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./*[1]/descendant::text()[normalize-space(.)!=''][position()=2 or position()=4]", $root))));

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)!=''][3]", $root);

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)!=''][5]", $root);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)!=''][6]", $root, true, "#" . $this->t("Terminal") . "\s+(.*?),#");

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./*[3]/descendant::text()[normalize-space(.)!=''][position()=2 or position()=4]", $root))));

                // AirlineName
                if (!$itsegment['AirlineName'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][2]", $headroot, true, "#^(\w{2})\d+$#")) {
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][1]", $headroot, true, "#^(\w{2})\d+$#");
                }

                // Operator
                $itsegment['Operator'] = $this->nextText($this->t("Operated by"), $headroot);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Supplier reference #")) . " or " . $this->eq($this->t("Total Duration")) . "])[1]/preceding::text()[normalize-space(.)][1]", $headroot, true, "#(.*?)(?:\s*\(\w\)|$)#");

                // BookingClass
                $itsegment['BookingClass'] = $this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Supplier reference #")) . " or " . $this->eq($this->t("Total Duration")) . "])[1]/preceding::text()[normalize-space(.)][1]", $headroot, true, "#\((\w)\)#");

                // Aircraft
                $itsegment['Aircraft'] = $this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)][" . ($itsegment['Operator'] ? 3 : 1) . "][not(" . $this->contains($itsegment['Cabin']) . ")]", $headroot);

                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Seat ")) . "]", $root, true, "#" . $this->t("Seat ") . "(.+)#");

                // Duration
                $itsegment["Duration"] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Leg Duration")) . "]", $root, true, "#" . $this->t("Leg Duration") . "\s+(.+)#");

                // Meal
                // Smoking
                // Stops

                if (isset($it['TotalCharge'])) {
                    $total = $this->amount($this->http->FindSingleNode("./ancestor::tr[1]/..//text()[" . $this->contains($this->t("Total Price")) . "]", $root, true, "#" . $this->t("Total Price") . "[\s-:]+(.+)#"));

                    if (!empty($total)) {
                        $it['TotalCharge'] += $total;
                    }
                }

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }
        //endregion

        //#################
        //##   HOTELS   ###
        //#################
        //region hotel
        $xpath = "//text()[" . $this->eq($this->wordCases($this->t("Check in"))) . "]/ancestor::tr[" . $this->contains($this->wordCases($this->t("Check out"))) . "][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && $this->http->XPath->query("//img[contains(@src, 'hotel-filled.png')] | //img[contains(@alt, 'hotel_icon') or contains(@alt, 'Hotel_icon')]")->length > 0) {
            $this->logger->debug('hotel not found');
            $itineraries = ['Kind' => "R"];

            return false;
        }

        foreach ($nodes as $root) {
            $headroot = $this->http->XPath->query("./ancestor::tr[1]/preceding-sibling::tr[1]/descendant::tr[1]", $root)->item(0);
            $it = [];

            $it['Kind'] = "R";

            // TripNumber
            $it['TripNumber'] = $this->nextText($this->t("Itinerary #"), $headroot);
            // ConfirmationNumbers

            // HotelName
            $it['HotelName'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][1]", $headroot);

            // ConfirmationNumber
            if (!$it['ConfirmationNumber'] = implode("/", $this->http->FindNodes("//text()[" . $this->contains($this->t("Room reference:")) . "][./preceding::text()[normalize-space(.)][1][" . $this->contains($it['HotelName']) . "]]", null, "#(?:" . $this->preg_implode($this->t("Room reference:")) . ")\s*(\d+)#"))) {
                $it['ConfirmationNumber'] = implode("/", $this->http->FindNodes("//text()[" . $this->contains($this->t("Room reference:")) . "][./preceding::text()[normalize-space(.)][position()<5][" . $this->contains($it['HotelName']) . "]]/following::text()[1]", null, "#(\d+)#"));
            }

            if (!$it['ConfirmationNumber']) {
                $it['ConfirmationNumber'] = $it['TripNumber'];
            }

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][2]", $root)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)!=''][2]", $root)));

            // Address
            $it['Address'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][2]", $headroot);

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][3]", $headroot);

            // Fax
            // GuestNames
            $it['GuestNames'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Traveler Details")) . "]/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space(.)][1]", null, "#\d+\)\s+(.+)#"));

            // Guests
            $it['Guests'] = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Adults Per Room")) . "]", $headroot, true, "#" . $this->t("Adults Per Room") . "[\s-:]+(\d+)#");

            // Kids
            // Rooms
            $it['Rooms'] = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Adults Per Room")) . "]", $headroot, true, "#^\d+#");

            // Rate
            // RateType

            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Adults Per Room")) . "]", $headroot, true, "#^[^,]+,\s*(.*?)\s*,#");

            if (strlen($it['RoomType']) >= 50) {
                $it['RoomTypeDescription'] = $it['RoomType'];
                unset($it['RoomType']);
            }

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            $it['Total'] = $this->amount($this->http->FindSingleNode("./ancestor::tr[1]/..//text()[" . $this->contains($this->t("Total Price")) . "]", $root, true, "#" . $this->t("Total Price") . "[\s-:]+(.+)#"));

            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode("./ancestor::tr[1]/..//text()[" . $this->contains($this->t("Total Price")) . "]", $root, true, "#" . $this->t("Total Price") . "[\s-:]+(.+)#"));

            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("BOOKING STATUS")) . "][./preceding::img[contains(@src, '-filled.png')][1][contains(@src, 'white-hotel-filled')]]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }
        //endregion

        //#################
        //##    CARS    ###
        //#################
        //region cars
        $xpath = "//text()[" . $this->eq($this->wordCases($this->t("Pickup"))) . "]/ancestor::tr[" . $this->contains($this->wordCases($this->t("Dropoff"))) . "][1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug($xpath);

        if ($nodes->length == 0 && $this->http->XPath->query("//img[contains(@src, 'white-car.png')] | //img[contains(@alt, 'car_icon') or contains(@alt, 'Car_icon')]")->length > 0) {
            $this->logger->debug('car not found');
            $itineraries[] = ['Kind' => "L"];

            return false;
        }

        foreach ($nodes as $root) {
            $headroot = $this->http->XPath->query("./ancestor::tr[1]/preceding-sibling::tr[1]/descendant::tr[1]", $root)->item(0);
            $it = [];

            $it['Kind'] = "L";

            $it['CarType'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][1]", $headroot);

            $it['RentalCompany'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][2]", $headroot);

            if ($rl = $this->http->FindSingleNode("./*[normalize-space(.)][2]//text()[" . $this->eq($this->t("Supplier reference #")) . "]/following::text()[normalize-space(.)][1]", $headroot)) {
                $it['Number'] = $rl;
            } elseif ($rl = $this->http->FindSingleNode("./*[normalize-space(.)][2]//text()[" . $this->eq($this->t("Itinerary #")) . "]/following::text()[normalize-space(.)][1]", $headroot)) {
                $it['Number'] = $rl;
            }

            $it['PickupDatetime'] = strtotime($this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][3]", $root), strtotime($this->normalizeDate($this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][2]", $root))));

            $it['DropoffDatetime'] = strtotime($this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)!=''][3]", $root), strtotime($this->normalizeDate($this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)!=''][2]", $root))));

            $it['PickupLocation'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][4]", $root);

            $it['DropoffLocation'] = $this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)!=''][4]", $root);

            $it['PickupPhone'] = $this->http->FindSingleNode("./*[1]/descendant::text()[normalize-space(.)!=''][5]", $root);

            $it['DropoffPhone'] = $this->http->FindSingleNode("./*[3]/descendant::text()[normalize-space(.)!=''][5]", $root);

            $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("./ancestor::tr[1]/..//text()[" . $this->contains($this->t("Total Price")) . "]", $root, true, "#" . $this->t("Total Price") . "[\s-:]+(.+)#"));

            $it['Currency'] = $this->currency($this->http->FindSingleNode("./ancestor::tr[1]/..//text()[" . $this->contains($this->t("Total Price")) . "]", $root, true, "#" . $this->t("Total Price") . "[\s-:]+(.+)#"));

            $it['Status'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("BOOKING STATUS")) . "][./preceding::img[contains(@src, '-filled.png')][1][contains(@src, 'white-car-filled')]]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

            $itineraries[] = $it;
        }
        //endregion
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][{$n}]", $root);
    }

    private function t($word)
    {
        //$this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        //$year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+[^\s\d]+\s+\d{4},\s+\d+:\d+\s+[AP]M)$#", //22 June 2017, 01:08 PM
            "#^(\d+)\.\s+([^\s\d]+)\s+(\d{4}),\s+(\d+:\d+)$#", //29. Juni 2017, 07:10
            "#^(\w+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+)$#", //oktober 7, 2017, 12:00
            "#^(\w+)\s+(\d+),\s+(\d{4})$#", //oktober 7, 2017
            "#^(\d+)\.\s+(\w+)\s+(\d{4})$#", //10. Dezember 2017
        ];
        $out = [
            "$1",
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
            "$2 $1 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->http->log($str);
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            'kr'=> 'SEK',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return preg_quote($s, '#'); }, $field));
    }

    private function wordCases($words)
    {
        $result = [];

        if (!is_array($words)) {
            $words = [$words];
        }

        foreach ($words as $word) {
            $result[] = $word;
            $result[] = mb_strtoupper($word);
            $result[] = mb_convert_case(mb_strtolower($word), MB_CASE_TITLE);
        }

        return array_unique($result);
    }
}
