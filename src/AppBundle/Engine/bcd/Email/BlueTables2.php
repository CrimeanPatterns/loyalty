<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class BlueTables2 extends \TAccountChecker
{
    public $mailFiles = "bcd/it-1740221.eml, bcd/it-1794782.eml, bcd/it-1803266.eml, bcd/it-1901175.eml, bcd/it-2633998.eml, bcd/it-3023342.eml, bcd/it-5337720.eml, bcd/it-5422822.eml, bcd/it-119924517.eml";

    public $lang = "en";
    private $reFrom = "@bcdtravel.com";
    private $reSubject = [
        "en" => "Booking Confirmation",
        "de" => "Reiseplan für",
        "fr" => "Billet électronique pour",
        "sv" => "Resplan för",
    ];
    private $reBody = [
        'bcd' => ['BCD Travel', 'bcd.compleattrip.com'],
    ];
    private $reBody2 = [
        "en" => ["Itinerary"],
        "de" => ["Reiseplan"],
        "fr" => ["Reçu de billet électronique", "Itinéraire"],
        "sv" => ["Resplan"],
    ];

    private static $dictionary = [
        "en" => [
            "Air -" => ["Air -", "AIR -"],
            "Tel.:" => ["Tel.:", "Tel:"],

            //			"Bus -" => "",
            //			"Rail -" => "",
            "Train Number" => ["Train Number", "Train number"],
        ],
        "de" => [ // it-1740221.eml, it-1794782.eml, it-1901175.eml, it-2633998.eml
            "Flight"                    => "Flug",
            "Please print this receipt" => "Bitte drucken Sie sich diesen Itinerary Receipt",
            "E-Ticket Number"           => "E-Ticket-Nummer",
            "Traveller(s)"              => "Reisende(r)",
            "Fare and Ticket Details"   => ["Tarif- und Ticketdetails", "Tarif und Ticketübersicht"],
            "Itinerary Details"         => "Leistung",
            "Class"                     => "Klasse",
            "Air -"                     => "Flug -",
            "Reference:"                => "Buchungsreferenz:",
            "Depart:"                   => "Abreise:",
            "Arrive:"                   => "Ankunft:",
            "Equipment:"                => "Fluggerät:",
            // "Distance:"=>"",
            "Seat:"     => "Sitzplatz:",
            "Duration:" => "Dauer:",
            // "Non-stop"=>"",
            "Operated by:"          => "Durchgeführt von:",
            "Loyalty Number:"       => "Mitgliedsnummer:",
            "Hotel -"               => "Hotel -",
            "Check In / Check Out:" => "Anreise / Abreise:",
            "Address:"              => "Adresse:",
            "Tel.:"                 => ["Tel.:", "Tel:"],
            "Fax:"                  => "Fax:",
            "Rate per night:"       => "Preis pro Nacht:",
            "Cancellation Policy:"  => "Stornobedingung:",
            "Description:"          => "Beschreibung:",
            "Total:"                => ["Gesamtpreis:", "Gesamt:"],
            "Car -"                 => "Mietwagen -",
            "Pick Up:"              => ["Anmietung:", "Zustellung:"],
            "Drop Off:"             => ["Abgabe:", "Abholung:"],
            "Rail -"                => "Bahn -",
            "Train Number"          => "Zugnummer",
            "Estimated Trip Total:" => "Voraussichtlicher Gesamtreisepreis:",
            "Ticket Number:"        => "Ticketnummer:",
        ],
        "fr" => [
            "Flight"                  => "Vol",
            // "Please print this receipt" => "",
            // "E-Ticket Number" => "",
            // "Traveller(s)" => "",
            "Fare and Ticket Details" => "Détails des prix et billets",
            "Itinerary Details"       => "Itinéraire détaillé",
            "Class"                   => "Classe",
            "Air -"                   => "AIR -",
            "Reference:"              => "Référence:",
            "Depart:"                 => ["Départ :", "Départ:"],
            "Arrive:"                 => "Arrivée:",
            "Equipment:"              => "Appareil:",
            // "Distance:"=>"",
            "Seat:"                 => "Siège:",
            "Duration:"             => "Temps de trajet :",
            "Non-stop"              => "non-stop",
            "Operated by:"          => "Opéré par:",
            "Loyalty Number:"       => "Numéro de fidélité:",
            "Hotel -"               => "HÔTEL -",
            "Check In / Check Out:" => "Arrivée / Départ:",
            "Address:"              => "Adresse:",
            "Tel.:"                 => "Tel.:",
            "Fax:"                  => "Fax:",
            "Rate per night:"       => "Tarif par nuit :",
            "Cancellation Policy:"  => "Politique d’annulation:",
            "Description:"          => "Descriptif:",
            "Total:"                => "Total:",
            "Car -"                 => "VOITURE -",
            // "Pick Up:"=>"",
            // "Drop Off:"=>"",
            "Rail -"                => ["RAIL -", "RAIL - "],
            "Train Number"          => "Train Numéro",
            "Estimated Trip Total:" => "Estimation du prix total du trajet:",
            //            "Ticket Number:" => "",
        ],
        "sv" => [
            "Flight"                  => "Flyg",
            // "Traveller(s)" => "",
            "Fare and Ticket Details" => "Pris och biljettinformation",
            "Itinerary Details"       => "Resplan detaljer",
            "Class"                   => "Klass",
            "Air -"                   => "FLYG -",
            "Reference:"              => "Referens:",
            "Depart:"                 => "Avresa:",
            "Arrive:"                 => "Ankomst:",
            "Equipment:"              => "Utrustning:",
            // "Distance:"=>"",
            "Seat:"     => "Plats:",
            "Duration:" => "Varaktighet:",
            // "Non-stop"=>"",
            // "Operated by:"          => "",
            // "Loyalty Number:"       => "",
            "Hotel -"               => "HOTELL -",
            // "Check In / Check Out:" => "",
            // "Address:"              => "",
            "Tel.:"                 => "Telefon:",
            // "Fax:"                  => "",
            // "Rate per night:"       => "",
            // "Cancellation Policy:"  => "",
            // "Description:"          => "",
            "Total:"                => "Totalt:",
            "Car -"                 => "BIL -",
            // "Pick Up:"              => "",
            // "Drop Off:"             => "",
            "Rail -"                => "Rail -",
            "Train Number"          => "Tågnummer",
            "Estimated Trip Total:" => "Beräknat totalpris:",
            "Ticket Number:"        => "Biljettnummer:",
        ],
    ];
    private $date = null;

    private $namePrefixes = ['MISS', 'MRS', 'MR', 'MS', 'DR'];
    private $travellers = [];

    private $patterns = [
        // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992    |    8/ 47 30 470
        'phone' => '[+(\d][-+. \/\d)(]{5,}[\d)]',
        // KOH / KIM LENG MR
        'travellerName' => '[[:upper:]]+(?: [[:upper:]]+)*[ ]*\/[ ]*(?:[[:upper:]]+ )*[[:upper:]]+',
        // 075-2345005149-02    |    0167544038003-004
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}',
    ];

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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
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

        foreach ($this->reBody2 as $lang => $reBody) {
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
        $this->http->FilterHTML = true; // fixing damaged flight segments
        $this->http->SetEmailBody($this->http->Response['body']);

        if (($provider = $this->getProvider($parser->getHTMLBody())) === false) {
            $this->logger->debug("provider not detected");

            return null;
        }

        foreach ($this->reBody2 as $lang => $re1) {
            foreach ($re1 as $re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $itineraries = $this->parseHtml();

        $result = [
            'emailType'  => 'BlueTables2' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
            'providerCode' => $provider,
        ];

        $totalCurrency = $totalAmount = [];
        $totalPrice = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t("Estimated Trip Total:"))}]", null, "/{$this->opt($this->t("Estimated Trip Total:"))}\s*(.+)$/"));

        foreach ($totalPrice as $tP) {
            if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/', $tP, $matches)
                || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $tP, $matches)
            ) {
                // SEK 3980.00
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $totalCurrency[] = $matches['currency'];
                $totalAmount[] = PriceHelper::parse($matches['amount'], $currencyCode);
            }
        }

        if (count($totalAmount) > 0 && count($totalAmount) === count($this->travellers) && count(array_unique($totalCurrency)) === 1) {
            $result['parsedData']['TotalCharge']['Amount'] = array_sum($totalAmount);
            $result['parsedData']['TotalCharge']['Currency'] = $totalCurrency[0];
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
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $itineraries = [];

        $eTickets = [];
        $eTicketsText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t("Please print this receipt"))}]/ancestor::td[count(descendant::text()[normalize-space()])>1][1]"));
        // E-Ticket Number: TF - 276-3854369685
        preg_match_all("/{$this->opt($this->t("E-Ticket Number"))}[: ]+(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+-[ ]+(?<ticket>{$this->patterns['eTicket']})$/m", $eTicketsText, $ticketMatches, PREG_SET_ORDER);

        foreach ($ticketMatches as $m) {
            if (empty($eTickets[$m['airline']])) {
                $eTickets[$m['airline']] = [$m['ticket']];
            } else {
                $eTickets[$m['airline']][] = $m['ticket'];
            }
        }

        $this->travellers = [];
        $travellersText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[normalize-space()][2][{$this->eq($this->t("Fare and Ticket Details"))}] ]/*[normalize-space()][1]"));

        if (empty($travellersText)) {
            // it-3023342.eml
            $travellersText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->eq($this->t("Traveller(s)"))}]/following::tr[normalize-space()][1][not(descendant::text()[{$this->eq($this->t('Flight'))}])]/ancestor-or-self::tr[1]/*[normalize-space()][1]"));
        }

        foreach (preg_split("/[ ]*\n+[ ]*/", $travellersText) as $tName) {
            if (preg_match("/^{$this->patterns['travellerName']}$/u", $tName)) {
                $this->travellers[] = preg_replace("/^(.{2,}?)\s+{$this->opt($this->namePrefixes)}$/", '$1', $tName);
            } else {
                $this->travellers = [];

                break;
            }
        }
        $this->travellers = array_unique($this->travellers);

        //##################
        //##   FLIGHTS   ###
        //##################

        $xpath = "//text()[{$this->eq($this->t("Itinerary Details"))}]/ancestor::tr[1]/following-sibling::tr[{$xpathNoEmpty}]";
        $nodes = $this->http->XPath->query($xpath);
        $codes = [];
        $cabin = [];

        //Vendor    Itinerary Details
        //CA936     Frankfurt (FRA)
        //          Shanghai (PVG)
        foreach ($nodes as $root) {
            $vendorText = $this->htmlToText($this->http->FindHTMLByXpath('*[2]', null, $root));

            if (preg_match('/^[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)[* ]*$/m', $vendorText, $m)) {
                // DY3088    |    EW4600*
                if (($dep = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root,
                        true, "#\(([A-Z]{3})\)#"))
                    && ($arr = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root,
                        true, "#\(([A-Z]{3})\)#"))) {
                    $codes[$m['number']] = [$dep, $arr];
                }

                if (($n = count($this->http->FindNodes("(./preceding-sibling::tr/td[" . $this->contains($this->t("Class")) . "])[1]/preceding-sibling::td",
                        $root))) > 0) {
                    $n++;
                    $cabin[$m['number']] = $this->http->FindSingleNode("./td[{$n}]", $root);
                }
            }
        }

        if (0 === count($codes)) {
            $nodes = $this->http->FindNodes("//td[contains(., '(') and contains(., ')') and not(.//td)]", null,
                '/(\([A-Z]{3}\)\s*.+\s*\([A-Z]{3}\))/');
            $nodes = array_values(array_unique(array_filter($nodes)));
            $n = $this->http->FindNodes("//td[contains(., '(') and contains(., ')') and not(.//td)]/preceding::td[normalize-space(.)]",
                null, '/^(?:[A-Z]\d{1,3}|[A-Z]{2})\s*(\d+)$/');
            $n = array_values(array_unique(array_filter($n)));
            $c = $this->http->FindNodes("//td[contains(., '(') and contains(., ')') and not(.//td)]/following::td[normalize-space(.)]",
                null, '/^((?:[Ee]conomy|[Bb]usiness|[Ff]irst [Cc]lass)\s*[A-Z])$/');
            $c = array_values(array_filter($c));

            foreach ($nodes as $i => $node) {
                if (isset($n[$i]) && preg_match('/\(([A-Z]{3})\)\s*.+\s*\(([A-Z]{3})\)/', $node, $m)) {
                    $codes[$n[$i]] = [$m[1], $m[2]];
                }
            }

            foreach ($c as $i => $cab) {
                if (isset($n[$i])) {
                    $cabin[$n[$i]] = $cab;
                }
            }
        }

        $xpath = "//text()[" . $this->starts($this->t("Air -")) . "]/ancestor::tr[./following-sibling::tr][1]/ancestor::table[" . $this->contains($this->t("Depart:")) . "][1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                $root, true, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#")) {
                $airs[$rl][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("//text()[" . $this->contains("Buchungscode") . "]", null, true,
                "#Buchungscode ([A-Z\d]+)#")) {
                $airs[$rl][] = $root;
            } elseif ($this->http->XPath->query("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Air -")) . ") and not(.//tr)][1]/td",
                    $root)->length === 3) {
                $airs[CONFNO_UNKNOWN][] = $root;
            } else {
                $this->logger->debug("RL not matched");

                return null;
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = $this->travellers;

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
            $ticketNumbers = [];
            $accounts = [];

            foreach ($roots as $root) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][1]",
                    $root, true, "#" . $this->opt($this->t("Air -")) . "\s*(.+)$#"));

                if (!$date) {
                    $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space(.)][not(.//td)][1]",
                        $root, true, "#" . $this->opt($this->t("Air -")) . "\s*(.+)$#"));
                }

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Air -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1]",
                    $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)[*\s]*$/");

                // DepCode
                if (isset($codes[$itsegment['FlightNumber']])) {
                    $itsegment['DepCode'] = $codes[$itsegment['FlightNumber']][0];
                }

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root, true, "#(.*?)(?:, Terminal|$)#i");

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root, true, "#Terminal (.+)#i");

                // DepDate
                $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][2]/td[2]",
                    $root), $date);

                // ArrCode
                if (isset($codes[$itsegment['FlightNumber']])) {
                    $itsegment['ArrCode'] = $codes[$itsegment['FlightNumber']][1];
                }

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root, true, "#(.*?)(?:, Terminal|$)#i");

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root, true, "#Terminal (.+)#i");

                // ArrDate
                $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][2]/td[2]",
                    $root), $date);

                if (!$itsegment['ArrDate']) {
                    $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("descendant::td[" . $this->eq($this->t("Arrive:")) . " and not(.//td)]/following::td[normalize-space(.)][3]",
                        $root), $date);
                }

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Air -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1]",
                    $root, true, "/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+[*\s]*$/");

                if (!empty($itsegment['AirlineName']) && !empty($eTickets[$itsegment['AirlineName']])) {
                    foreach ($eTickets[$itsegment['AirlineName']] as $eT) {
                        $ticketNumbers[] = $eT;
                    }
                }

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Equipment:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root);

                // TraveledMiles
                $itsegment['TraveledMiles'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Distance:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root);

                // AwardMiles
                if (isset($cabin[$itsegment['FlightNumber']])) {
                    // Cabin
                    $itsegment['Cabin'] = $this->re("#(.*?)\s*[A-Z]$#", $cabin[$itsegment['FlightNumber']]);

                    // BookingClass
                    $itsegment['BookingClass'] = $this->re("#\s*([A-Z])$#", $cabin[$itsegment['FlightNumber']]);
                }
                // PendingUpgradeTo
                // Seats
                if (preg_match_all("#\b(\d{1,2}[A-Z])\b#",
                    $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Seat:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                        $root), $m)) {
                    $itsegment['Seats'] = $m[1];
                }

                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Duration:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root, true, "#(.*?)(?: " . $this->opt($this->t("Non-stop")) . "|$)#i");

                // Stops
                $stopsValue = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Duration:"))}]/ancestor::td[1]/following-sibling::td[1]", $root);

                if (preg_match("/{$this->opt($this->t("Non-stop"))}/i", $stopsValue)) {
                    $itsegment['Stops'] = 0;
                }

                $operator = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Operated by:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root);

                if (!empty($operator)) {
                    $itsegment['Operator'] = $operator;
                }

                $loyaltyNumber = $this->htmlToText($this->http->FindHTMLByXpath("descendant::text()[{$this->eq($this->t("Loyalty Number:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, $root));

                foreach (preg_split('/[ ]*\n+[ ]*/', $loyaltyNumber) as $lNumber) {
                    if (preg_match("/^([-A-Z\d]{5,})\s+-\s+{$this->patterns['travellerName']}$/u", $lNumber, $m)) {
                        // SKEB21XXXX4336 - FRANSSON/SVEN TOMAS MR
                        $accounts[] = $m[1];
                    } elseif (preg_match("/^[-A-Z\d]{5,}$/", $lNumber)) {
                        // SKEB21XXXX4336
                        $accounts[] = $lNumber;
                    }
                }

                $fl = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Air -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1]",
                    $root, true, "/^((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+)[*\s]*$/");

                if (!empty($fl)
                    && count($tckts = array_filter($this->http->FindNodes("//td[not(.//tr) and {$this->starts($this->t("Air -"))} and {$this->contains($fl)}][not(preceding-sibling::td)]/following-sibling::td[{$this->contains($this->t("Ticket Number:"))}]", null, "/:\s*({$this->patterns['eTicket']})\s*(\D|$)/")))
                ) {
                    // it-1901175.eml
                    $ticketNumbers = array_merge($ticketNumbers, $tckts);
                }

                $it['TripSegments'][] = $itsegment;
            }

            $ticketNumbers = array_values(array_unique($ticketNumbers));

            if (count($ticketNumbers) > 0) {
                $it['TicketNumbers'] = $ticketNumbers;
            }

            $accounts = array_unique(array_filter($accounts));

            if (!empty($accounts)) {
                $it['AccountNumbers'] = $accounts;
            }

            $itineraries[] = $it;
        }

        //#################
        //##   HOTELS   ###
        //#################
        $xpath = "//text()[" . $this->starts($this->t("Hotel -")) . "]/ancestor::tr[./following-sibling::tr][1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                $root, true, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#");

            // TripNumber

            // HotelName
            $it['HotelName'] = $this->http->FindSingleNode("./tr[normalize-space(.)][2]/td[1]", $root);

            // 2ChainName
            $dates = explode(" - ",
                $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Check In / Check Out:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root));

            if (count($dates) == 2) {
                // CheckInDate
                $it['CheckInDate'] = $this->normalizeDate($dates[0]);

                // CheckOutDate
                $it['CheckOutDate'] = $this->normalizeDate($dates[1]);
            }
            // Address
            if (($adr = $this->http->XPath->query(".//text()[{$this->eq($this->t("Address:"))}]/ancestor::tr[1]", $root))->length !== 0) {
                $it['Address'] = $this->http->FindSingleNode("td[2]", $adr->item(0));

                while (($adr = $this->http->XPath->query("following-sibling::tr[normalize-space()][1][normalize-space(td[1])='']", $adr->item(0)))->length !== 0) {
                    $it['Address'] .= ' ' . $this->http->FindSingleNode("td[2]", $adr->item(0));
                }
            }

            // Phone
            $it['Phone'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Tel.:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root);

            // Fax
            $it['Fax'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Fax:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root);

            // GuestNames
            $it['GuestNames'] = $this->travellers;

            // Guests
            // Kids
            // Rooms
            // Rate
            $it['Rate'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Rate per night:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root);

            // RateType
            // CancellationPolicy
            $it['CancellationPolicy'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Cancellation Policy:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root);

            // RoomType
            // RoomTypeDescription
            $it['RoomTypeDescription'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Description:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root);

            // Cost
            // Taxes
            // Total
            $it['Total'] = $this->amount($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Total:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root));

            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Total:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root));

            $itineraries[] = $it;
        }

        //###############
        //##   CARS   ###
        //###############
        $xpath = "//text()[" . $this->starts($this->t("Car -")) . "]/ancestor::tr[./following-sibling::tr[" . $this->contains($this->t("Pick Up:")) . "]][1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "L";

            // RenterName
            if (count($this->travellers) === 1) {
                $it['RenterName'] = $this->travellers;
            }

            // Number
            $it['Number'] = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                $root, true, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#");

            // TripNumber
            // PickupDatetime
            $it['PickupDatetime'] = $this->normalizeDate($this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Pick Up:")) . "]/ancestor::tr[1]/following-sibling::tr/td[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd') and contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')])[1]",
                $root));

            // PickupLocation
            $it['PickupLocation'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Pick Up:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root);

            // DropoffDatetime
            $it['DropoffDatetime'] = $this->normalizeDate($this->http->FindSingleNode("(.//text()[" . $this->eq($this->t("Drop Off:")) . "]/ancestor::tr[1]/following-sibling::tr/td[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd') and contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')])[1]",
                $root));

            // DropoffLocation
            $it['DropoffLocation'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Drop Off:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root);

            // PickupPhone
            $it['PickupPhone'] = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                $root, true, "/{$this->opt($this->t("Tel.:"))}\s*({$this->patterns['phone']})[;,\s]*(?:{$this->opt($this->t("Fax:"))}|$)/i");

            // PickupFax
            $it['PickupFax'] = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                $root, true, "/{$this->opt($this->t("Fax:"))}\s*({$this->patterns['phone']})[;,\s]*$/i");

            // DropoffPhone
            $it['DropoffPhone'] = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                $root, true, "/{$this->opt($this->t("Tel.:"))}\s*({$this->patterns['phone']})[;,\s]*(?:{$this->opt($this->t("Fax:"))}|$)/i");

            // DropoffFax
            $it['DropoffFax'] = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2]",
                $root, true, "/{$this->opt($this->t("Fax:"))}\s*({$this->patterns['phone']})[;,\s]*$/i");

            // RentalCompany
            $it['RentalCompany'] = $this->http->FindSingleNode("./tr[normalize-space(.)][2]/td[1]", $root);

            // CarType
            $it['CarType'] = $this->http->FindSingleNode("./tr[normalize-space(.)][2]/td[2]", $root);

            if (preg_match("#(.+)\(\s*[\w\. ]{1,5}:\s*(.+)\s*\)\s*$#", $it['CarType'], $m)) {
                $it['CarType'] = trim($m[1]);
                $it['CarModel'] = $m[2];
            }

            // CarModel
            // CarImageUrl
            // PromoCode
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Total:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root));

            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Total:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                $root));

            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ServiceLevel
            // PricedEquips
            // Discount
            // Discounts
            // Fees
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //##################
        //##     BUS     ###
        //##################
        $xpath = "//text()[" . $this->starts($this->t("Bus -")) . "]/ancestor::tr[./following-sibling::tr][1]/ancestor::table[" . $this->contains($this->t("Depart:")) . "][1]";
//        $this->logger->debug("XPATH: {$xpath}");
        $nodes = $this->http->XPath->query($xpath);
        $bus = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                $root, true, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#")) {
                $bus[$rl][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("//text()[" . $this->contains("Buchungscode") . "]", null, true,
                "#Buchungscode ([A-Z\d]+)#")) {
                $bus[$rl][] = $root;
            } else {
                $this->logger->debug("RL not matched");

                return null;
            }
        }

        foreach ($bus as $rl => $roots) {
            $it = [];

            $it['Kind'] = "T";

            $it['TripCategory'] = TRIP_CATEGORY_BUS;

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = $this->travellers;

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
                $date = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][1]",
                    $root, true, "#" . $this->opt($this->t("Bus -")) . "\s*(.+)$#"));

                if (!$date) {
                    $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space(.)][not(.//td)][1]",
                        $root, true, "#" . $this->opt($this->t("Bus -")) . "\s*(.+)$#"));
                }

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Bus -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1]",
                    $root, true, "/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)[*\s]*$/");

                // DepName
                // DepCode
                $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root, true, "#(.*?)(?:, Terminal|$)#i");

                if (!empty($itsegment['DepName'])) {
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                // DepDate
                $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2]",
                    $root), $date);

                // ArrName
                // ArrCode
                $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root, true, "#(.*?)(?:, Terminal|$)#i");

                if (!empty($itsegment['ArrName'])) {
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                // ArrDate
                $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2]",
                    $root), $date);

                if (!$itsegment['ArrDate']) {
                    $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("descendant::td[" . $this->eq($this->t("Arrive:")) . " and not(.//td)]/following::td[normalize-space(.)][2]",
                        $root), $date);
                }

                // AirlineName
                $itsegment['Type'] = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Bus -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1]",
                    $root, true, "/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+[*\s]*$/");

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }

        //##################
        //##    TRAIN    ###
        //##################
        $ruleTimeNotOpen = ".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2][not({$this->eq('open')})]";
        $xpath = "//text()[" . $this->starts($this->t("Rail -")) . "]/ancestor::tr[./following-sibling::tr][1]/ancestor::table[" . $this->contains($this->t("Depart:")) . "][1][{$ruleTimeNotOpen}]";
        $this->logger->debug("XPATH: {$xpath}");
        $nodes = $this->http->XPath->query($xpath);
        $rail = [];

        foreach ($nodes as $root) {
            if ($rl = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Reference:")) . "]/ancestor::tr[1]",
                $root, true, "#" . $this->opt($this->t("Reference:")) . "\s*([A-Z\d]+)#")) {
                $rail[$rl][] = $root;
            } elseif ($rl = $this->http->FindSingleNode("//text()[" . $this->contains("Buchungscode") . "]", null, true,
                "#Buchungscode ([A-Z\d]+)#")) {
                $rail[$rl][] = $root;
            } else {
                $this->logger->debug("RL not matched");

                return null;
            }
        }

        foreach ($rail as $rl => $roots) {
            $it = [];

            $it['Kind'] = "T";

            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = $this->travellers;

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

            $ticketNumbers = [];

            foreach ($roots as $root) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][1]",
                    $root, true, "#" . $this->opt($this->t("Rail -")) . "\s*(.+)$#"));

                if (!$date) {
                    $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space(.)][not(.//td)][1]",
                        $root, true, "#" . $this->opt($this->t("Rail -")) . "\s*(.+)$#"));
                }

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Rail -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][2][{$this->starts($this->t('Train Number'))}]",
                    $root, true, "/:\s*(\d+)(?:-|[*]*$)/");

                // DepName
                // DepCode
                $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root, true, "#(.*?)(?:, Terminal|$)#i");

                if (!empty($itsegment['DepName'])) {
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                // DepDate
                $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Depart:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2]",
                    $root), $date);

                // ArrName
                // ArrCode
                $nameArr = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Arrive:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, "/(.*?)(?:, Terminal|$)/i");

                if ($nameArr) {
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    if (preg_match("/{$this->opt($this->t("Pick up/Drop off Location"))}/i", $nameArr)) {
                        continue;
                    } else {
                        $itsegment['ArrName'] = $nameArr;
                    }
                }

                if (empty($itsegment['FlightNumber']) && !empty($itsegment['DepName']) && !empty($itsegment['ArrName'])) {
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("//text()[{$this->eq($itsegment['DepName'])}]/ancestor::td[1][./descendant::text()[normalize-space()!=''][last()][{$this->eq($itsegment['ArrName'])}]]/preceding-sibling::td[1]",
                        null, false, "/^\d+/");
                }

                // ArrDate
                $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrive:")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2]",
                    $root), $date);

                if (!$itsegment['ArrDate']) {
                    $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("descendant::td[" . $this->eq($this->t("Arrive:")) . " and not(.//td)]/following::td[normalize-space(.)][2]",
                        $root), $date);
                }

                // AirlineName
                $itsegment['Type'] = $this->http->FindSingleNode("descendant::tr[normalize-space(.)][not(" . $this->contains($this->t("Rail -")) . ") and not(.//tr)][1]/descendant::text()[normalize-space(.)][1][./following::text()[{$this->starts($this->t('Train Number'))}]]",
                    $root);

                $seat = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Seat:"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root);

                if (preg_match("/^(?<car>{$this->opt($this->t("Coach"))}[:\s]+[A-z\d]+)[,\s]+(?<seat>{$this->opt($this->t("Seat"))}[:\s]+[A-z\d]+)(?:\s+-\s+{$this->patterns['travellerName']})?$/u", $seat, $m)) {
                    // Coach: 2 Seat: 18 - DANIELSSON/CHARLOTTA MRS
                    $itsegment['Seats'] = $m['car'] . ', ' . $m['seat'];
                }

                $itsegment['Cabin'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Class:")) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root);

                if (!empty($itsegment['FlightNumber'])
                    && ($tckts = array_filter($this->http->FindNodes("//td[not(.//tr) and {$this->starts($this->t("Rail -"))} and {$this->contains($itsegment['FlightNumber'])}][not(preceding-sibling::td)]/following-sibling::td[{$this->contains($this->t("Ticket Number:"))}]", null, "/:\s*({$this->patterns['eTicket']}|[A-Z\d]{5,}\d)\s*(\D|$)/")))
                ) {
                    // ZAJ6174O0001
                    $ticketNumbers = array_merge($ticketNumbers, $tckts);
                }

                $it['TripSegments'][] = $itsegment;
            }

            $ticketNumbers = array_values(array_unique($ticketNumbers));

            if (count($ticketNumbers) > 0) {
                $it['TicketNumbers'] = $ticketNumbers;
            }

            $itineraries[] = $it;
        }

        //////////////////////
        ///    TRANSFER    ///    example: it-119924517.eml
        //////////////////////
        $xpath = "//text()[{$this->starts($this->t("Taxi -"))}]/ancestor::tr[following-sibling::tr][1]/ancestor::table[{$this->contains($this->t("Pick Up:"))}][1]";
        $this->logger->debug("XPATH: {$xpath}");
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = 'T';
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;
            $it['RecordLocator'] = $this->http->FindSingleNode(".//text()[{$this->contains($this->t("Reference:"))}]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t("Reference:"))}\s*([-A-Z\d]{5,})$/");
            $it['Passengers'] = $this->travellers;

            $date = $this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space()][1]/td[normalize-space()][1]", $root, true, "/{$this->opt($this->t("Taxi -"))}\s*(.+)$/"));

            if (!$date) {
                $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[normalize-space()][not(.//td)][1]", $root, true, "/{$this->opt($this->t("Taxi -"))}\s*(.+)$/"));
            }

            $itsegment = [];

            $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::td[1]/following-sibling::td[1]", $root);

            $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Pick Up:"))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1][normalize-space(td[1])='']/td[2]", $root), $date);

            $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::td[1]/following-sibling::td[1]", $root);

            $xpathTaxiTimeArr = ".//text()[{$this->eq($this->t("Drop Off:"))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1][normalize-space(td[1])='']/td[2]";

            if ($this->http->XPath->query($xpathTaxiTimeArr, $root)->length > 0) {
                $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode($xpathTaxiTimeArr, $root), $date);
            } else {
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            if (!empty($itsegment['DepName']) && !empty($itsegment['ArrName'])) {
                $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $additionalInformation = $this->htmlToText($this->http->FindHTMLByXpath(".//text()[{$this->eq($this->t("Additional Information:"))}]/ancestor::td[1]/following-sibling::td[1]", null, $root));

            if (preg_match("/^[ ]*{$this->opt($this->t("Taxi Type:"))}[ ]*(.{2,}?)[ ]*$/m", $additionalInformation, $m)) {
                $itsegment['Type'] = $m[1];
            }

            $it['TripSegments'][] = $itsegment;

            $totalPrice = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Total:"))}]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $it['Currency'] = $matches['currency'];
                $it['TotalCharge'] = PriceHelper::parse($matches['amount'], $currencyCode);
            }

            $itineraries[] = $it;
        }

        return $itineraries;
    }

    private function getProvider($body)
    {
        foreach ($this->reBody as $prov => $reBody) {
            foreach ($reBody as $re) {
                if (strpos($body, $re) !== false) {
                    return $prov;
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

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        //		$this->logger->debug($instr);
        $in = [
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4})$#", //Thursday, 31 July 2014
            "#^(\d+:\d+), (\d+) ([^\s\d]+)$#", //12:55, 20 July
            "#^(\d+:\d+) [^\s\d]+, (\d+ [^\s\d]+ \d{4})$#", //07:30 Tuesday, 01 December 2015
            "#^(\d+:\d+), [^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //07:30 Tuesday, 01 December 2015
        ];
        $out = [
            "$1",
            "$2 $3 %Y%, $1",
            "$2 $1",
            "$2 $1",
        ];

        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d',
                strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative($str, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
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
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
            '₹' => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
