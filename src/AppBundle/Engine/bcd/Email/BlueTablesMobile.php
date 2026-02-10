<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BlueTablesMobile extends \TAccountChecker
{
    public $mailFiles = "bcd/it-12911301.eml, bcd/it-12923439.eml, bcd/it-13092960.eml, bcd/it-89556193.eml, bcd/it-91260210.eml"; // +1 bcdtravel(html)[de]

    public $lang = "en";
    public $noPdf;
    private $enDatesInverted = false;
    private $reFrom = "@bcdtravel.com";
    private $reSubject = [
        "en"  => "TRAVEL ITINERARY FOR",
        "en2" => "Travel Receipt for",
        "en3" => "Reminder:  Complimentary Hotel Internet Access Included - Log on to Deloitte VPN Before Using Public Internet.",
        "es"  => "Resumen de viaje para",
        "fr"  => "Reçu pour voyage de",
        "fr2" => "Itinéraire pour",
        "pt"  => "Recibo de viagem para",
        "it"  => "Ricevuta di viaggio per",
        "de"  => "Reisebestätigung für",
    ];
    private $reBody = [
        'bcd' => ['BCD Travel', 'bcd.compleattrip.com', 'HDS-BCD.CompleatTrip.com', 'bcdtravel.com', 'BCD TRAVEL'],
    ];
    private $reBody2 = [
        "en" => ["Travel Summary", "The Deloitte Travel Services Team"],
        "es" => ["Resumen de Viaje"],
        "fr" => ["Récapitulatif", "récapitulatif"],
        "pt" => ["Resumo de Viagem"],
        "it" => ["Riepilogo Viaggio"],
        "de" => ["Reiseübersicht", 'Flugnummer/Leistung'],
    ];

    private static $dictionary = [
        "en" => [
            //			'Passenger' => '',
            //			'Agency Record Locator' => '',
            //			'Ticket Receipt' => '',
            //			'Total Amount:' => '',
            //			'Ticket Number' => '',
            //			'From/To' => '',
            //			'Class/Type' => '',

            //			//flight
            //			'Flight' => '',
            //			'Layover' => '',
            //			'Airline Record Locator' => '',
            //			'Loyalty Number' => '',
            //			'Class' => '',
            //			'Terminal' => '',
            //			'Operated By:' => '',
            //
            //
            //			'Confirmation' => '',
            //			//hotel
            //			'Hotel' => '',
            //			'Address' => '',
            //			'Fax' => '',
            //			'Number of Rooms' => '',
            'Cancellation Policy' => ['CANCEL', 'Cancellation Policy', 'CANCELLATION'],
            //
            //			//car
            //			'Car' => '',
            //			'Tel:' => '',
            //			'Fax:' => '',
            //			'Type:' => '',
            //			//rail
            //			'Rail' => '',
            //
            //			'Estimated trip total' => '',
            //			'Air' => '',
        ],
        "es" => [
            'Passenger'             => 'Pasajero',
            'Agency Record Locator' => 'Localizador del PNR de la agencia',
            'Ticket Receipt'        => 'Recibo del billete',
            'Total Amount:'         => 'Importe total:',
            'Ticket Number'         => 'Numero de Boleto',
            'From/To'               => 'Origen/Destino',
            'Class/Type'            => 'Clase/Tipo',

            //flight
            'Flight' => 'Vuelo',
            // 'Layover' => '',
            'Airline Record Locator' => 'Localizador de registros de aerolíneas',
            'Loyalty Number'         => 'Número de lealtad',
            //			'Class' => '',
            'Terminal'     => 'Terminal',
            'Operated By:' => ['Operado por:', 'Operated By'],
            //*Operado por: Avianca Costa Rica S.A. Operated By /Avianca Costa Rica S.A.

            'Confirmation' => 'Confirmación',
            //hotel
            'Hotel'   => 'Hotel',
            'Address' => 'Dirección',
            //			'Fax' => '',
            'Number of Rooms'     => ['N° de Habitaciones', 'Número de habitaciones'],
            'Cancellation Policy' => ['Política de cancelación', 'Normativa de cancelación'],

            //car
            'Car'   => ['Car', 'Coche'],
            'Tel:'  => ['Teléfono:', 'Tel.:'],
            'Fax:'  => 'Fax:',
            'Type:' => 'Tipo:',

            //rail
            //			'Rail' => '',

            //			'Estimated trip total' => '',
            //			'Air' => '',
        ],
        "fr" => [
            'Passenger'             => 'Voyageur',
            'Agency Record Locator' => 'N° de réservation BCD Travel',
            'Ticket Receipt'        => 'Reçu pour billet',
            'Total Amount:'         => 'Montant Total:',
            'Ticket Number'         => 'Billet électronique n°',
            'From/To'               => 'Trajet',
            'Class/Type'            => 'Classe/Catégorie',

            //flight
            'Flight' => ['Flight', 'Vol'],
            // 'Layover' => '',
            'Airline Record Locator' => 'Référence dossier de la compagnie aérienne',
            'Loyalty Number'         => 'Numéro de fidélité',
            'Terminal'               => 'Terminal',
            'Operated By:'           => ['Opéré par:', 'Operated By'],

            'Confirmation' => 'Numéro de Confirmation',
            //hotel
            'Hotel'   => ['Hôtel', 'HÔTEL'],
            'Address' => 'Adresse',
            //			'Fax' => '',
            'Number of Rooms' => 'Nombre de chambres',
            //			'Cancellation Policy' => '',

            //car
            'Car'   => 'Car',
            'Tel:'  => 'Téléphone:',
            'Fax:'  => 'Fax:',
            'Type:' => 'Type:',

            //rail
            'Rail'                 => 'Train',
            'Estimated trip total' => 'Estimation du prix total du trajet',
            'Class'                => 'Classe',
            //			'Air' => '',

            //Segment type
            'TypeFlight' => 'Avion',
            'TypeRails'  => 'Train',
        ],
        "pt" => [
            'Passenger'             => 'Passageiro',
            'Agency Record Locator' => 'Localizador do PNR da agência',
            'Ticket Receipt'        => 'Recibo do bilhete',
            'Total Amount:'         => 'Valor Total:',
            'Ticket Number'         => 'Número do Bilhete',
            'From/To'               => 'Voo/Fornecedor',
            'Class/Type'            => 'Classe/Tipo',

            //flight
            'Flight' => 'Voo',
            // 'Layover' => '',
            'Airline Record Locator' => 'Localizador de registro da companhia aérea',
            //			'Loyalty Number' => '',
            //			'Class' => '',
            'Terminal'     => 'Terminal',
            'Operated By:' => ['Operado por:', 'Operated By'],

            'Confirmation' => ['Numéro de Confirmation', 'Confirmação'],
            //hotel
            'Hotel'   => 'Hotel',
            'Address' => 'Endereço',
            //			'Fax' => '',
            'Number of Rooms'     => 'Número de quartos',
            'Cancellation Policy' => 'Condições de Cancelamento',

            //car
            'Car'   => 'Car',
            'Tel:'  => ['Tel:', 'Telefone:'],
            'Fax:'  => 'Fax:',
            'Type:' => 'Tipo:',

            //rail
            //			'Rail' => '',

            //			'Estimated trip total' => '',
            //			'Air' => '',
        ],
        "it" => [
            'Passenger'             => 'Passeggero',
            'Agency Record Locator' => 'Codice Identificativo Agenzia',
            'Ticket Receipt'        => 'Ricevuta del Biglietto',
            'Total Amount:'         => 'Totale Importo:',
            'Ticket Number'         => 'Numero Biglietto Elettronico',
            'From/To'               => 'Da/A',
            'Class/Type'            => 'Classe',

            //flight
            'Flight' => ['Volo', 'Scalo'],
            // 'Layover' => '',
            'Airline Record Locator' => 'Localizzatore record compagnie aeree',
            'Loyalty Number'         => 'Numero carta fedeltà',
            //			'Class' => '',
            'Terminal'     => 'Terminal',
            'Operated By:' => ['Operato da:', 'Operated By'],

            'Confirmation' => 'Conferma',
            //hotel
            'Hotel'   => 'Hotel',
            'Address' => 'Indirizzo',
            //			'Fax' => '',
            'Number of Rooms'     => 'Numero di camere',
            'Cancellation Policy' => ['Politica di Cancellazione', 'CANCELLATION'],

            //car
            'Car' => 'Car',
            //			'Tel:' => '',
            //			'Fax:' => '',
            //			'Type:' => '',

            //rail
            //			'Rail' => '',

            //			'Estimated trip total' => '',
            //			'Air' => '',
        ],
        "de" => [
            'Passenger'             => 'Reisender/Reisende',
            'Agency Record Locator' => 'Agenturbuchungscode',
            'Ticket Receipt'        => 'Ticketbeleg',
            // 'Total Amount:' => '',
            'Ticket Number' => 'Nummer des elektronischen Tickets',
            'From/To'       => 'Von/nach',
            'Class/Type'    => 'Klasse/Leistungsart',

            // FLIGHT
            'Flight'                 => 'Flugnummer/Leistung',
            'Layover'                => 'Aufenthalt',
            'Airline Record Locator' => 'Buchungscode der Fluggesellschaft',
            'Loyalty Number'         => 'Nummer Ihres Treueprogramms',
            // 'Class' => '',
            // 'Terminal' => '',
            // 'Operated By:' => '',

            // HOTEL
            'Confirmation' => ['Bestätigungsnummer', 'Confirmation'],
            // 'Hotel' => '',
            'Address'         => ['Adresse', 'Address'],
            "Number of Rooms" => 'Anzahl der Zimmer',
            'guests'          => 'Personenanzahl',
            // 'Fax' => '',
            'Cancellation Policy' => 'Stornobedingungen',

            // CAR
            'Car'  => ['Mietwagen', 'Car'],
            'Tel:' => ['Tel:', 'Telefonnummer:'],
            //			'Fax:' => '',
            'Type:' => 'Fahrzeuggruppe:',

            // RAIL
            'Rail' => 'Bahn',

            'Estimated trip total' => 'Geschätzte Gesamtreisekosten',
            'Air'                  => 'Flug',
        ],
    ];

    private $patterns = [
        'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*noon)?', // 4:19PM    |    2:00 p. m.    |    3pm    |    12 noon
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (($provider = $this->getProvider($parser->getHTMLBody())) === null) {
            $this->logger->debug("Provider not detected");

            return $email;
        }

        foreach ($this->reBody2 as $lang => $reBody2) {
            foreach ($reBody2 as $re) {
                if (stripos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->http->SetEmailBody(str_replace("\xE2\x80\x8B", '', $this->http->Response['body'])); //zerowidth space

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if ($provider !== 'bcd') {
            $email->setProviderCode($provider);
        }

        if (is_array($this->t("Agency Record Locator"))) {
            foreach ($this->t("Agency Record Locator") as $agencyRL) {
                $rl = $this->http->FindSingleNode("(//text()[" . $this->eq($agencyRL) . "])[1]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]",
                    null, true, "#^\s*([A-Z\d]+)\s*$#");

                if (!empty($rl)) {
                    $tripNumber = [
                        "Name"   => $agencyRL,
                        "Number" => $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Agency Record Locator")) . "])[1]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]",
                            null, true, "#^\s*([A-Z\d]+)\s*$#"),
                    ];

                    break;
                }
            }
        } else {
            $num = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Agency Record Locator")) . "])[1]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]",
                null, true, "#^\s*([A-Z\d]+)\s*$#");

            if (!empty($num)) {
                $tripNumber = [
                    "Name"   => $this->t("Agency Record Locator"),
                    "Number" => $num,
                ];
            }
        }
        $email->ota();

        if (!empty($tripNumber)) {
            $email->ota()->confirmation($tripNumber['Number'], $tripNumber['Name']);

            $pdfs = $parser->searchAttachmentByName('.*\.pdf');
            $foundPdf = false;

            foreach ($pdfs as $i => $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (strpos($text, ' ' . $tripNumber['Number'])) {
                        $foundPdf = true;

                        break;
                    }
                }
            }

            if ($foundPdf == false) {
                $this->noPdf = true;
            }
        }
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Estimated trip total")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if ($total === null) {
            $total = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Ticket Receipt")) . "][not(preceding-sibling::td[normalize-space()])]/following-sibling::td[" . $this->starts($this->t("Total Amount:")) . "]",
                null, true, "/:\s*(.+)/");
        }

        if ($total !== null) {
            $email->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        $this->parseSegments($email);

        return $email;
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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ($this->getProvider($body) === null) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $reBody) {
            foreach ($reBody as $re) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$re}')]")->length > 0) {//stripos($body, $re) !== false
                    return true;
                }
            }
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

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    public static function normalizeProvider(?string $string): ?string
    {
        // used in parser bcd/BlueTablesMobilePdf

        $string = trim($string);
        $providers = [
            'rentacar' => ['Enterprise Rent A Car'],
            'avis'     => ['Avis Rent A Car'],
            'hertz'    => ['Hertz Rent-A-Car'],
            'national' => ['National Rent A Car'],
            'localiza' => ['Localiza Rent a Car'],
            'sixt'     => ['Sixt Rent a Car'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function parseSegments(Email $email): void
    {
        $passengers = str_replace(' (Child)', '', array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]")));
        $passengers = preg_replace("/\s(?:MRS|MR|Ms)$/", "", $passengers);

        //##################
        //##   FLIGHTS   ###
        //##################

        $xpath = "//img[(contains(@src, 'flight_duration_icon') or (@width=32 and @height=32)) and count(ancestor::td[1]/preceding-sibling::td)=1  and count(ancestor::td[1]/following-sibling::td)=1]/ancestor::table[1]";

        if (0 === $this->http->XPath->query($xpath)->length) {
            $xpath = "//tr[ *[1][normalize-space()=''] and *[2][{$this->eq($this->t('Flight'))} or {$this->eq($this->t('Layover'))}] and *[3][normalize-space()] and (not(*[position()>3][normalize-space()]) or *[position()>2]//a[contains(@href,'MessageHistoryAttachmentDownload')]) ]/ancestor::table[1]/following::table[normalize-space()][2]";
        }

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//*[" . $this->starts($this->t('TypeFlight')) . "]/ancestor::table[1]/following::tr[1]/descendant::img[contains(@id,'_x0000_i1031')]/ancestor::table[1]";
        }
        $nodes = $this->http->XPath->query($xpath);

        $airs = [];

        $isDeletedSegment = false;
        $flightsSummary = [];

        if ($nodes->length > 0) {
            $xpathC = "//text()[" . $this->eq($this->t("From/To")) . "]/ancestor::tr[1][" . $this->contains($this->t("Class/Type")) . "]/following-sibling::tr[normalize-space(.)]";
            $nodesC = $this->http->XPath->query($xpathC);
            $codes = [];
            $cabins = [];

            foreach ($nodesC as $root) {
                if ($number = $this->http->FindSingleNode("./td[3]", $root, true, "#^(\w{2}\s*\d+)\*?$#")) {
                    if (preg_match("#^([A-Z]{3})\s*-\s*([A-Z]{3})#", $this->http->FindSingleNode("./td[2]", $root),
                        $m)) {
                        $flNumber = preg_replace("#^\w{2}\s*#", '', $number);
                        $codes[$flNumber] = [$m[1], $m[2]];
                        $fl['depCode'] = $m[1];
                        $fl['arrCode'] = $m[2];

                        if (preg_match("#^\s*(\w{2})\s*(\d+)\*?$#", $number, $mat)) {
                            $fl['airlineCode'] = $mat[1];
                            $fl['flightNumber'] = $mat[2];
                        }

                        if (($n = count($this->http->FindNodes("(./preceding-sibling::tr/td[" . $this->eq($this->t("Class/Type")) . "])[1]/preceding-sibling::td",
                                $root))) > 0) {
                            $n++;

                            if (!isset($cabins[$flNumber])) {
                                $cabins[$flNumber] = $this->http->FindSingleNode("./td[{$n}]", $root);
                            } else {
                                unset($cabins[$flNumber]);
                            }

                            $cabinText = $this->http->FindSingleNode("./td[{$n}]", $root);
                            $cabin = trim(str_ireplace("Class", '',
                                $this->re("#(.*?)\s*(?:/\s*[A-Z]|\(.*?\))$#", $cabinText)));

                            if (empty($cabin)) {
                                $cabin = trim(str_ireplace("Class", '',
                                    $this->re("#^([^\s\d]+)$#", $cabinText)));
                            }

                            // BookingClass
                            $class = $this->re("#/\s*([A-Z])$#", $cabinText);

                            $fl['cabin'] = $cabin;
                            $fl['class'] = $class;
                        }

                        if (($n = count($this->http->FindNodes("(./preceding-sibling::tr/td[" . $this->eq($this->t("Status")) . "])[1]/preceding-sibling::td",
                                $root))) > 0) {
                            $n++;
                            $status = $this->http->FindSingleNode("./td[{$n}]", $root);

                            if (in_array($status, ["Waitlisted", "SC", "Cancelled", "Unable"])) {
                                $fl['isDelete'] = true;
                                $isDeletedSegment = true;
                            } else {
                                unset($fl['isDelete']);
                            }
                        }

                        if (($n = count($this->http->FindNodes("(./preceding-sibling::tr/td[" . $this->eq($this->t("Depart/Arrive")) . "])[1]/preceding-sibling::td",
                                $root))) > 0) {
                            $n++;
                            $times = explode("/", $this->http->FindSingleNode("./td[{$n}]", $root));
                            $dateStr = $this->http->FindSingleNode("./td[1]", $root);

                            if (!empty($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Passenger")) . "])[1]/preceding::td[normalize-space() and not(.//td)][1][contains(., 'United States')]"))) {
                                $date = strtotime($dateStr);
                            } else {
                                $date = strtotime(str_replace('/', '.', $dateStr));
                            }

                            if (count($times) == 2 && !empty($date)) {
                                $fl['depDate'] = strtotime($times[0], $date);

                                if (preg_match("#(.+?)\s*([+\-]\d+)\s*$#", $times[1], $mat)) {
                                    $fl['arrDate'] = strtotime($mat[2] . " day", strtotime($mat[1], $date));
                                } else {
                                    $fl['arrDate'] = strtotime($times[1], $date);
                                }
                            }
                        }
                        $flightsSummary[] = $fl;
                    }
                }
            }

            $prices = [];
            $xpathT = "//text()[" . $this->eq($this->t("From/To")) . " and (" . $this->eq($this->t("Ticket Receipt"),
                    './ancestor::table[2]/preceding::tr[1]//text()[normalize-space()][1]') . ")]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]";
            $nodesT = $this->http->XPath->query($xpathT);

            if ($nodesT->length == 0) {
                $airs['default'] = $nodes;
            } else {
                $tickets = [];

                foreach ($nodesT as $rootT) {
                    $route = $this->http->FindSingleNode("./td[2]", $rootT, true, "#\s*[A-Z]{3}-[A-Z]{3}\s*#");

                    if (empty($route)) {
                        continue;
                    }
                    $ticketNumber = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[" . $this->contains($this->t("Ticket Number")) . "][1]/descendant::tr[1]/following-sibling::tr[1]/td[1]",
                        $rootT, true, "#^\s*(\d{3}[\-]?\d{9,})#");
                    $tickets[$route] = $ticketNumber;
                    $prices[$route] = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[" . $this->contains($this->t("Ticket Number")) . "][1]/descendant::tr[1]/following-sibling::tr[1]/td[last()]",
                        $rootT);
                }
                $tickets = array_filter($tickets);

                foreach ($nodes as $root) {
                    $dCode = $this->http->FindSingleNode("./descendant::tr[1]/td[1]", $root, true,
                        "#^\s*([A-Z]{3})\s*$#");
                    $aCode = $this->http->FindSingleNode("./descendant::tr[1]/td[3]", $root, true,
                        "#^\s*([A-Z]{3})\s*$#");

                    if (isset($tickets[$dCode . '-' . $aCode])) {
                        $airs[$tickets[$dCode . '-' . $aCode]][] = $root;
                    } else {
                        $airs['default'][] = $root;
                    }
                }
            }
        }

        foreach ($airs as $ticket => $roots) {
            $f = $email->add()->flight();

            if (!empty($passengers)) {
                $f->general()->travellers($passengers, true);
            }

            if ($ticket !== 'default') {
                $f->issued()->ticket($ticket, false);
            }
            $rl = [];
            $accNums = [];

            foreach ($roots as $root) {
                $acc = $this->http->FindSingleNode("./preceding-sibling::table[normalize-space()][1]//text()[" . $this->contains($this->t("Loyalty Number")) . "]/ancestor::tr[1]",
                    $root, true, "#" . $this->preg_implode($this->t("Loyalty Number")) . "\s*([A-Z\d]+)\b#");

                if (!empty($acc) && !in_array($acc, $accNums)) {
                    $accNums[] = $acc;
                    $f->program()
                        ->account($acc, !empty($this->re("#^(?:[A-Z\d]{2})?XXXX[A-Z\d]+$#", $acc)));
                }

                $rl[] = $this->http->FindSingleNode("./preceding-sibling::table[normalize-space()][1]//text()[" . $this->contains($this->t("Airline Record Locator")) . "]/ancestor::tr[1]",
                    $root, true, "#" . $this->preg_implode($this->t("Airline Record Locator")) . "\s*([A-Z\d]+)\b#");

                $s = $f->addSegment();

                $flight = $this->http->FindSingleNode("./preceding::table[normalize-space()][1]/descendant::text()[normalize-space()][1]",
                    $root);

                if (preg_match("/\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s*$/", $flight, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                } else {
                    $flight = $this->http->FindSingleNode("./preceding::table[normalize-space()][1]/descendant::text()[normalize-space()][2]",
                        $root);

                    if (preg_match("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s*$/", $flight, $m)) {
                        $s->airline()
                            ->name($m[1])
                            ->number($m[2]);
                    }
                }

                // Operated By: Air Canada Express - Sky Regional Operated By /Air Canada Express - Sky Regional
                // Operated By: Operated By /Latam Airlines Brasil For Latam Airlines
                // Operated By: Latam Airlines
                $node = $this->http->FindSingleNode("./following-sibling::table[normalize-space()][2]//tr[" . $this->contains($this->t("Operated By:")) . "]",
                    $root);
                $regOp = array_map(function ($s) {
                    return trim($s, " :");
                }, (array) $this->t('Operated By:'));

                if (preg_match("#{$this->preg_implode($this->t('Operated By:'))}\s*(?:{$this->preg_implode($regOp)}[ \/]*)?(.+?)(?:{$this->preg_implode($regOp)}.*|$)#",
                    $node, $op)) {
                    $operator = $op[1];
                }

                if (isset($operator) && !empty($operator)) {
                    $s->airline()->operator($operator);
                }

                // departure
                $code = $this->http->FindSingleNode("./descendant::tr[1]/td[1]", $root, true, "#^\s*([A-Z]{3})\s*$#");

                if (empty($code) && isset($codes[$s->getFlightNumber()])) {
                    $code = $codes[$s->getFlightNumber()][0];
                }
                $s->departure()
                    ->code($code)
                    ->name(implode(", ",
                        $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[1]/td[1]//text()[normalize-space()]",
                            $root)))
                    ->terminal(trim(str_ireplace("Terminal", '',
                        $this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[3]/td[1]//text()[normalize-space() and not(ancestor::a)][1]",
                            $root, true, "#.*terminal.*#i"))), true)
                    ->date($this->normalizeDate(
                        trim(implode(' ',
                            $this->http->FindNodes("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space()!=''][position()<=2]",
                                $root))) . ', ' .
                        trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[1]",
                            $root))
                    ));

                // arrival
                $code = $this->http->FindSingleNode("./descendant::tr[1]/td[3]", $root, true, "#^\s*([A-Z]{3})\W*$#");

                if (empty($code) && isset($codes[$s->getFlightNumber()])) {
                    $code = $codes[$s->getFlightNumber()][1];
                }
                $s->arrival()
                    ->code($code)
                    ->name(implode(", ",
                        $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[1]/td[2]//text()[normalize-space()]",
                            $root)))
                    ->terminal(trim(str_ireplace("Terminal", '',
                        $this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[3]/td[3]//text()[normalize-space() and not(ancestor::a)][1]",
                            $root, true, "#.*terminal.*#i"))), true)
                    ->date($this->normalizeDate(
                        implode(' ',
                            $this->http->FindNodes("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[1]/td[3]/descendant::text()[normalize-space()!=''][position()<=2]",
                                $root)) . ', ' .
                        $this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[3]",
                            $root)
                    ));

                // Extra
                $miles = $this->http->FindSingleNode("./descendant::tr[1]/td[2]/descendant::text()[normalize-space()][2]",
                    $root, true, "#^\s*\d+.*$#");
                $km = $this->http->FindSingleNode("./descendant::tr[1]/td[2]/descendant::text()[normalize-space()][3]",
                    $root, true, "#^\s*\d+.*$#");

                if (!empty($miles) && !empty($km)) {
                    $s->extra()
                        ->miles($miles . ' (' . $km . ')');
                } else {
                    $s->extra()
                        ->miles(trim($miles . $km), true);
                }
                $s->extra()
                    ->duration($this->http->FindSingleNode("./descendant::tr[1]/td[2]/descendant::text()[normalize-space()][1]",
                        $root, true, "#^\s*\d+.*$#"), true)
                    ->seats(array_filter($this->http->FindNodes("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[1]/td[2]//text()[normalize-space()]",
                        $root, "#^\s*(\d{1,3}[A-Z])(?:\s+|$)#")));

                // Cabin
                if (!empty($s->getFlightNumber()) && isset($cabins[$s->getFlightNumber()])) {
                    $cabinText = $cabins[$s->getFlightNumber()];
                } else {
                    $cabinText = $this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[2]",
                        $root);
                }

                if (!empty($cabinText)) {
                    $cabin = trim(str_ireplace("Class", '',
                        $this->re("#(.*?)\s*(?:/\s*[A-Z]|\(.*?\))$#", $cabinText)));

                    if (empty($cabin)) {
                        $cabin = trim(str_ireplace("Class", '',
                            $this->re("#^([^\s\d]+)$#", $cabinText)));
                    }

                    if (!empty($cabin)) {
                        $s->extra()->cabin($cabin);
                    }
                    // BookingClass
                    $s->extra()->bookingCode($this->re("#/\s*([A-Z])\s*$#", $cabinText), true, true);
                }

                if (!empty($s->getDepCode()) && !empty($s->getArrCode()) && !empty($prices[$s->getDepCode() . '-' . $s->getArrCode()])) {
                    $f->price()
                        ->total($this->amount($prices[$s->getDepCode() . '-' . $s->getArrCode()]))
                        ->currency($this->currency($prices[$s->getDepCode() . '-' . $s->getArrCode()]));
                }

                if ($isDeletedSegment == true && !empty($flightsSummary)) {
                    foreach ($flightsSummary as $fl) {
                        if (
                            (isset($fl['isDelete']) && $fl['isDelete'] == true)
                            && (!empty($s->getAirlineName()) && !empty($fl['airlineCode']) && $s->getAirlineName() == $fl['airlineCode'])
                            && (!empty($s->getFlightNumber()) && !empty($fl['flightNumber']) && $s->getFlightNumber() == $fl['flightNumber'])
                            && (!empty($s->getDepCode()) && !empty($fl['depCode']) && $s->getDepCode() == $fl['depCode'])
                            && (!empty($s->getDepDate()) && !empty($fl['depDate']) && $s->getDepDate() == $fl['depDate'])
                            && (!empty($s->getArrCode()) && !empty($fl['arrCode']) && $s->getArrCode() == $fl['arrCode'])
                            && (!empty($s->getArrDate()) && !empty($fl['arrDate']) && $s->getArrDate() == $fl['arrDate'])
                            && ((!empty($s->getCabin()) && !empty($fl['cabin']) && $s->getCabin() == $fl['cabin'])
                                || (!empty($s->getBookingCode()) && !empty($fl['class']) && $s->getBookingCode() == $fl['class']))
                        ) {
                            $f->removeSegment($s);

                            continue 2;
                        }
                    }
                }
            }

            $rl = array_unique(array_filter($rl));

            foreach ($rl as $value) {
                $f->general()->confirmation($value);
            }

            if (0 === count($rl)) {
                $f->general()->noConfirmation();
            }
        }

        if (count($airs) == 1 && isset($f) && empty($f->obtainPrice()->getTotal())) {
            $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Estimated trip total")) . "]/ancestor::tr[1]/following::text()[" . $this->eq($this->t("Air")) . "][1]/ancestor::tr[1]/following-sibling::tr[1]/td[position() = 1+count(ancestor::tr[1]/preceding-sibling::tr[1]/td[" . $this->eq($this->t("Air")) . "]/preceding-sibling::td)]");

            if (!empty($total) && 'Unavailable' !== $total) {
                $f->price()
                    ->total($this->amount($total))
                    ->currency($this->currency($total));
            }
        }

        //#################
        //##   HOTELS   ###
        //#################

        $xpath = "//table[(" . $this->eq($this->t("Hotel"),
                'descendant::text()[normalize-space()][1]') . ") and not(.//table)]/following::table[1]";

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            if (!empty($passengers)) {
                $h->general()->travellers($passengers, true);
            }

            $conf = $this->http->FindSingleNode("descendant::td[({$this->starts($this->t("Confirmation"))}) and not(.//td)][1]",
                $root, true, "#{$this->preg_implode($this->t("Confirmation"))}\s*([-\dA-Z ]{5,})$#");

            if ($conf) {
                $h->general()->confirmation(str_replace(' ', '', $conf));
            } else {
                $h->general()
                    ->confirmation(CONFNO_UNKNOWN);
            }

            $h->general()
                ->status($this->http->FindSingleNode("./following-sibling::table[normalize-space()][2]/descendant::tr[4]/td[2]",
                    $root));

            $acc = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Loyalty Number")) . "]/ancestor::tr[1]",
                $root, true, "#" . $this->preg_implode($this->t("Loyalty Number")) . "\s*([A-Z\d]+)\b#");

            if (!empty($acc)) {
                $h->program()
                    ->account($acc, !empty($this->re("#^\s*[A-Z\d]*(XXXX[A-Z\d]+)\s*$#", $acc)));
            }

            $address = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::td[1][" . $this->eq($this->t("Address")) . "]/following-sibling::td[1]/descendant::text()[normalize-space()][1]",
                $root);

            if (empty($address)) {// bcd
                $h->hotel()
                    ->noAddress()
                    ->phone($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/following::text()[normalize-space()!=''][1]/ancestor::a[contains(@href,'tel:')]",
                        $root));
            } else {
                $h->hotel()
                    ->address($address)
                    ->phone($this->http->FindSingleNode("./following-sibling::table[1]/descendant::td[1][" . $this->eq($this->t("Address")) . "]/following-sibling::td[1]/descendant::text()[normalize-space()][2]",
                        $root), false, true)
                    ->fax($this->http->FindSingleNode("following-sibling::table[normalize-space()][3]//tr[{$this->starts($this->t("Fax"))}][1]", $root, true, "#{$this->preg_implode($this->t("Fax"))}[:\s]*({$this->patterns['phone']})\s*$#"), false, true);
            }

            $h->hotel()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root));

            $h->booked()
                ->checkIn($this->normalizeDate(implode(' ',
                    $this->http->FindNodes("./following-sibling::table[normalize-space()][2]/descendant::tr[1]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space()!=''][position()<=2]",
                        $root))))
                ->checkOut($this->normalizeDate(implode(' ',
                    $this->http->FindNodes("./following-sibling::table[normalize-space()][2]/descendant::tr[1]/following-sibling::tr[1]/td[3]/descendant::text()[normalize-space()!=''][position()<=2]",
                        $root))))
                ->rooms($this->http->FindSingleNode("./following-sibling::table[normalize-space()][3]//tr[" . $this->starts($this->t("Number of Rooms")) . "][1]",
                    $root, true, "#" . $this->preg_implode($this->t("Number of Rooms")) . "\s*(\d+)#"), true, true);

            $guests = $this->http->FindSingleNode("//text()[{$this->starts($this->t('guests'))}]", null, true, "/{$this->opt($this->t('guests'))}\s*(\d+)/");

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }

            $cancellation = $this->http->FindSingleNode("./following-sibling::table[normalize-space()][3]//tr[" . $this->starts($this->t("Cancellation Policy")) . "][1]",
                $root, true, "#" . $this->preg_implode($this->t("Cancellation Policy")) . "\s*(.+)#");

            if (empty($cancellation)) {
                $cancellation = $this->http->FindSingleNode("./following::table[contains(normalize-space(), 'Note')][1]/descendant::text()[" . $this->starts($this->t("Cancellation Policy")) . "][1]",
                    $root, true, "#" . $this->preg_implode($this->t("Cancellation Policy")) . "\s*(.+)#");
            }

            $h->general()
                ->cancellation($cancellation, true, true);

            $this->detectDeadLine($h);

            $rateVal = $this->http->FindSingleNode("following-sibling::table[normalize-space()][2]/descendant::tr[3]/*[2]", $root, true, '/^.*\d.*$/');

            if ($rateVal !== null) {
                $r = $h->addRoom();
                $r->setRate($rateVal);
            }
        }

        if ($nodes->length === 1) {
            $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Estimated trip total")) . "]/ancestor::tr[1]/following::text()[" . $this->eq($this->t("Hotel")) . "][1]/ancestor::tr[1]/following-sibling::tr[1]/td[position() = 1+count(ancestor::tr[1]/preceding-sibling::tr[1]/td[" . $this->eq($this->t("Hotel")) . "]/preceding-sibling::td)]");

            if (!empty($total) && isset($h)) {
                $h->price()
                    ->total($this->amount($total))
                    ->currency($this->currency($total));
            }
        }

        //###############
        //##   CARS   ###
        //###############

        $xpath = "//table[(" . $this->eq($this->t("Car"),
                'descendant::text()[normalize-space()][1]') . ") and not(.//table)]/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            if (!empty($passengers)) {
                $r->general()->travellers($passengers, true);
            }

            $r->general()
                ->status($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[4]/td[2]",
                    $root));

            $confirmation = $this->http->FindSingleNode(".//td[(" . $this->starts($this->t("Confirmation")) . ") and not(.//td)][1]",
                $root, true, "#" . $this->preg_implode($this->t("Confirmation")) . "\s*([\dA-Z]+)\b#");

            if (!empty($confirmation)) {
                $r->general()
                    ->confirmation($confirmation);
            } else {
                $r->general()
                    ->noConfirmation();
            }

            $acc = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Loyalty Number")) . "]/ancestor::tr[1]",
                $root, true, "#" . $this->preg_implode($this->t("Loyalty Number")) . "\s*([A-Z\d]+)\b#");

            if (!empty($acc)) {
                $r->program()
                    ->account($acc, !empty($this->re("#^\s*[A-Z\d]*(XXXX[A-Z\d]+)\s*$#", $acc)));
            }

            $r->pickup()
                ->location(trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[3]/td[1]",
                    $root, true, "#(.*?)(?:" . $this->preg_implode($this->t("Tel:")) . "|$)#")))
                ->date($this->normalizeDate(
                    implode(' ',
                        $this->http->FindNodes("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space()!=''][position()<=2]",
                            $root)) . ', ' .
                    $this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[1]",
                        $root)
                ));

            $pickUpPhone = trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[3]/td[1]",
                $root, true, "#" . $this->preg_implode($this->t("Tel:")) . "\s*([\d\+\-\(\) \/]{5,})#"));

            if (!empty($pickUpPhone)) {
                $r->pickup()
                    ->phone($pickUpPhone);
            }

            $pickUpFax = trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[3]/td[1]",
                $root, true, "#" . $this->preg_implode($this->t("Fax:")) . "\s*([\d\+\-\(\) \/]{5,})#"));

            if (!empty($pickUpFax)) {
                $r->pickup()
                    ->fax($pickUpFax, true,
                        true);
            }

            $r->dropoff()
                ->location(trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[3]/td[3]",
                    $root, true, "#(.*?)(?:" . $this->preg_implode($this->t("Tel:")) . "|$)#")))
                ->date($this->normalizeDate(
                    implode(' ',
                        $this->http->FindNodes("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[1]/td[3]/descendant::text()[normalize-space()!=''][position()<=2]",
                            $root)) . ', ' .
                    $this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[3]",
                        $root)
                ));

            $dropOffPhone = trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[3]/td[3]",
                $root, true, "#" . $this->preg_implode($this->t("Tel:")) . "\s*([\d\+\-\(\) ]{5,})#"));

            if (!empty($dropOffPhone)) {
                $r->dropoff()
                    ->phone($dropOffPhone, true);
            }

            $dropOffFax = trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[3]/td[3]",
                $root, true, "#" . $this->preg_implode($this->t("Fax:")) . "\s*([\d\+\-\(\) ]{5,})#"));

            if (!empty($dropOffFax)) {
                $r->dropoff()
                    ->fax($dropOffFax, true, true);
            }

            $r->car()
                ->type($this->http->FindSingleNode("./following-sibling::table[normalize-space()][2]//tr[" . $this->starts($this->t("Type:")) . "][1]",
                    $root, true, "#" . $this->preg_implode($this->t("Type:")) . "\s*(.+)#"), true, true);

            $company = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root);

            if (($code = $this->normalizeProvider($company))) {
                $r->program()->code($code);
            } else {
                $r->extra()->company($company);
            }
        }

        if ($nodes->length === 1) {
            $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Estimated trip total")) . "]/ancestor::tr[1]/following::text()[" . $this->eq($this->t("Car")) . "][1]/ancestor::tr[1]/following-sibling::tr[1]/td[position() = 1+count(ancestor::tr[1]/preceding-sibling::tr[1]/td[" . $this->eq($this->t("Car")) . "]/preceding-sibling::td)]");

            if (!empty($total)) {
                $r->price()
                    ->total($this->amount($total))
                    ->currency($this->currency($total));
            }
        }

        //################
        //##   RAILS   ###
        //################

        $xpath = "//img[contains(@src, 'rail_duration_icon')]/ancestor::table[1]";

        if (0 === $this->http->XPath->query($xpath)->length) {
            $xpath = "//*[" . $this->starts($this->t("TypeRails")) . "]/ancestor::table[1]/following::tr[1]/descendant::img[contains(@id,'_x0000_i1031')]/ancestor::table[1]";
        }
        $nodes = $this->http->XPath->query($xpath);
        $rails = [];

        $prices = [];

        if ($nodes->length > 0) {
            $xpathT = "//text()[" . $this->eq($this->t("From/To")) . " and (" . $this->eq($this->t("Ticket Receipt"),
                    './ancestor::table[2]/preceding::tr[1]//text()[normalize-space()][1]') . ")]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]";
            $nodesT = $this->http->XPath->query($xpathT);

            if ($nodesT->length == 0) {
                $rails['default'] = $nodes;
            } else {
                $tickets = [];

                foreach ($nodesT as $rootT) {
                    $route = str_replace('/', '-',
                        $this->http->FindSingleNode("./td[2]", $rootT, true, "#\s*[A-Z]{3}[-/][A-Z]{3}\s*#"));

                    if (empty($route)) {
                        continue;
                    }
                    $ticketNumber = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[" . $this->contains($this->t("Ticket Number")) . "][1]/descendant::tr[1]/following-sibling::tr[1]/td[1]",
                        $rootT, true, "#^\s*(\d{10,})#");
                    $tickets[$route] = $ticketNumber;
                    $prices[$route] = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[" . $this->contains($this->t("Ticket Number")) . "][1]/descendant::tr[1]/following-sibling::tr[1]/td[last()]",
                        $rootT);
                }
                $tickets = array_filter($tickets);

                foreach ($nodes as $root) {
                    $dCode = $this->http->FindSingleNode("./descendant::tr[1]/td[1]", $root, true,
                        "#^\s*([A-Z]{3})\s*$#");
                    $aCode = $this->http->FindSingleNode("./descendant::tr[1]/td[3]", $root, true,
                        "#^\s*([A-Z]{3})\s*$#");

                    if (isset($tickets[$dCode . '-' . $aCode])) {
                        $rails[$tickets[$dCode . '-' . $aCode]][] = $root;
                    } else {
                        $rails['default'][] = $root;
                    }
                }
            }
        }

        $cntRails = count($rails);

        foreach ($rails as $ticket => $roots) {
            $t = $email->add()->train();

            if (!empty($passengers)) {
                $t->general()->travellers($passengers, true);
            }

            if ($ticket !== 'default') {
                $t->setTicketNumbers([$ticket], false);
            }

            $rl = [];

            foreach ($roots as $root) {
                $rl[] = $this->http->FindSingleNode("./preceding-sibling::table[normalize-space()][1]//text()[" . $this->contains($this->t("Confirmation")) . "]/ancestor::tr[1]",
                    $root, true, "#" . $this->preg_implode($this->t("Confirmation")) . "\s*([A-Z\d]+)\b#");
                $s = $t->addSegment();

                $node = $this->http->FindSingleNode("./preceding::table[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                    $root);

                if (preg_match("/\s*(.+?)\s+(\d{1,5}|E|N|S)\s*$/", $node, $m)) {
                    $s->extra()
                        ->service($m[1])
                        ->number($m[2]);
                }

                // departure, it's not a station code
//                if ($code = $this->http->FindSingleNode("./descendant::tr[1]/td[1]", $root, true,
//                    "#^\s*([A-Z]{3})\s*$#")
//                ) {
//                    $s->departure()->code($code);
//                }
                $s->departure()
                    ->name(implode(", ",
                        $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[1]/td[1]//text()[normalize-space()]",
                            $root)))
                    ->date($this->normalizeDate(
                        implode(' ',
                            $this->http->FindNodes("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space()!=''][position()<=2]",
                                $root)) . ', ' .
                        $this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[1]",
                            $root)
                    ));

                // arrival, it's not a station code
//                if ($code = $this->http->FindSingleNode("./descendant::tr[1]/td[3]", $root, true,
//                    "#^\s*([A-Z]{3})\s*$#")
//                ) {
//                    $s->arrival()->code($code);
//                }
                $s->arrival()
                    ->name(implode(", ",
                        $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[1]/td[2]//text()[normalize-space()]",
                            $root)))
                    ->date($this->normalizeDate(
                        implode(' ',
                            $this->http->FindNodes("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[1]/td[3]/descendant::text()[normalize-space()!=''][position()<=2]",
                                $root)) . ', ' .
                        $this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[3]",
                            $root)
                    ));

                if (($d = $s->getDepDate()) && ($a = $s->getArrDate()) && $a === $d && date("H:i", $d) === "00:00") {
                    // open ticket
                    $t->removeSegment($s);

                    continue;
                }

                $s->extra()
                    ->duration($this->http->FindSingleNode("./descendant::tr[1]/td[2]//text()[normalize-space()][1]",
                        $root, true, "#^\s*\d+.*$#"), false, true);

                $cabin = $this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[2]",
                    $root);

                if (!empty($cabin) && preg_match("#^(.+)/([A-Z]{1,2})\s*$#", $cabin, $m)) {
                    $s->extra()
                        ->cabin($m[1])
                        ->bookingCode($m[2]);
                } elseif (!empty($cabin) && preg_match("/^({$this->opt($this->t("Class"))}.+)/", $cabin, $m)) {
                    $s->extra()
                        ->cabin($m[1]);
                }

                if (!empty($s->getDepCode()) && !empty($s->getArrCode()) && !empty($prices[$s->getDepCode() . '-' . $s->getArrCode()])) {
                    $t->price()
                        ->cost($this->amount($prices[$s->getDepCode() . '-' . $s->getArrCode()]))
                        ->currency($this->currency($prices[$s->getDepCode() . '-' . $s->getArrCode()]));
                }
            }
            $rl = array_unique(array_filter($rl));

            if (count($rl) == 0) {
                $t->general()
                    ->noConfirmation();
            }

            foreach ($rl as $value) {
                $t->general()->confirmation($value);
            }

            if (count($t->getSegments()) === 0 && count($roots) > 0) {
                $email->removeItinerary($t);
                $cntRails--;
            }
        }

        if ($cntRails == 1 && isset($t) && empty($t->obtainPrice()->getTotal())) {
            $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Estimated trip total")) . "]/ancestor::tr[1]/following::text()[" . $this->eq($this->t("Rail")) . "][1]/ancestor::tr[1]/following-sibling::tr[1]/td[position() = 1+count(ancestor::tr[1]/preceding-sibling::tr[1]/td[" . $this->eq($this->t("Rail")) . "]/preceding-sibling::td)]");

            if (!empty($total)) {
                $t->price()
                    ->total($this->amount($total))
                    ->currency($this->currency($total));
            }
        }

        //##################
        //##  TRANSFERS  ###
        //##################

        $xpath = "//table[(" . $this->eq($this->t("Limo"),
                'descendant::text()[normalize-space()][1]') . ") and not(.//table)]/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $t = $email->add()->transfer();

            if (!empty($passengers)) {
                $t->general()->travellers($passengers, true);
            }

            $t->general()
                ->confirmation(str_replace('*', '-',
                    $this->http->FindSingleNode(".//td[(" . $this->starts($this->t("Confirmation")) . ") and not(.//td)][1]",
                        $root, true, "#" . $this->preg_implode($this->t("Confirmation")) . "\s*([\dA-Z*]+)\b#")))
                ->status($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[4]/td[2]",
                    $root));

            $s = $t->addSegment();

            // departure
            $s->departure()
                ->name(trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[3]/td[1]/descendant::text()[normalize-space()][1]",
                    $root)))
                ->date($this->normalizeDate(
                    trim(implode(' ',
                        $this->http->FindNodes("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space()!=''][position()<=2]",
                            $root))) . ', ' .
                    trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[1]",
                        $root))
                ));

            // arrival
            $s->arrival()
                ->name(trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[3]/td[3]/descendant::text()[normalize-space()][1]",
                    $root)))
            ;
            $date = trim(implode(' ', $this->http->FindNodes("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[1]/td[3]/descendant::text()[normalize-space()!=''][position()<=2]",
                    $root)));
            $time = trim($this->http->FindSingleNode("./following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[3]",
                $root));

            if (!empty($s->getDepDate()) && empty($time)) {
                $s->arrival()->noDate();
            } else {
                $s->arrival()->date($this->normalizeDate($date . ', ' . $time));
            }

            $total = $this->http->FindSingleNode(".//following-sibling::table[normalize-space()][1]/descendant::tr[1]/following-sibling::tr[2]/td[2]",
                $root);

            if (!empty($total)) {
                $t->price()
                    ->total($this->amount($total))
                    ->currency($this->currency($total));
            }
        }

        if ($this->http->XPath->query("descendant::text()[{$this->eq($this->t("Passenger"))}][1]/preceding::td[normalize-space() and not(.//tr)][1][{$this->contains(['Deutschland'])}]")->length > 0) {
            $this->enDatesInverted = true;
        }

        // Travel Summary (it-89556193.eml)
        if (count($email->getItineraries()) == 0 && $this->noPdf === true) {
            $xpathC = "//text()[" . $this->eq($this->t("From/To")) . "]/ancestor::tr[1][" . $this->contains($this->t("Class/Type")) . "]/following-sibling::tr[normalize-space(.)]";
            $nodesC = $this->http->XPath->query($xpathC);
            $xpathCF = "//text()[" . $this->eq($this->t("From/To")) . "]/ancestor::tr[1][" . $this->contains($this->t("Class/Type")) . "]/following-sibling::tr[normalize-space(.)][./td[2][contains(.,'-')]]";
            $nodesCF = $this->http->XPath->query($xpathCF);

            $colDateText = implode("\n", $this->http->FindNodes($xpathC . "/*[1]"));

            if (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $colDateText, $dateMatches)) {
                foreach ($dateMatches[1] as $simpleDate) {
                    if ($simpleDate > 12) {
                        $this->enDatesInverted = true;

                        break;
                    }
                }
            }

            if ($nodesCF->length > 0) {
                $f = $email->add()->flight();

                $f->general()->noConfirmation();

                if (!empty($passengers)) {
                    $f->general()->travellers($passengers, true);
                }
            }

            foreach ($nodesC as $root) {
                $dateStr = $this->http->FindSingleNode("*[1]", $root);

                if ($this->enDatesInverted) {
                    $date = strtotime(str_replace('/', '.', $dateStr));
                } else {
                    $date = strtotime($dateStr);
                }

                if ($this->http->XPath->query("*[2][contains(.,'-')]", $root)->length > 0) {
                    // FLIGHT

                    if (($n = count($this->http->FindNodes("(./preceding-sibling::tr/td[" . $this->eq($this->t("Status")) . "])[1]/preceding-sibling::td", $root))) > 0) {
                        $n++;
                        $status = $this->http->FindSingleNode("./td[{$n}]", $root);

                        if (in_array($status, ["Waitlisted", "SC", "Cancelled", "Unable"])) {
                            continue;
                        }
                    }

                    $s = $f->addSegment();

                    if (preg_match("/^([A-Z]{3})\s*-\s*([A-Z]{3})/", $this->http->FindSingleNode("*[2]", $root), $m)) {
                        $s->departure()->code($m[1]);
                        $s->arrival()->code($m[2]);
                    }

                    if (preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)[*\s]*$/", $this->http->FindSingleNode("*[3]", $root), $m)) {
                        $s->airline()->name($m[1])->number($m[2]);
                    }

                    if (($n = count($this->http->FindNodes("(./preceding-sibling::tr/td[" . $this->eq($this->t("Class/Type")) . "])[1]/preceding-sibling::td", $root))) > 0) {
                        $n++;

                        $cabinText = $this->http->FindSingleNode("./td[{$n}]", $root);
                        $cabin = trim(str_ireplace("Class", '',
                            $this->re("#(.*?)\s*(?:/\s*[A-Z]|\(.*?\))$#", $cabinText)));

                        if (empty($cabin)) {
                            $cabin = trim(str_ireplace("Class", '',
                                $this->re("#^([^\s\d]+)$#", $cabinText)));
                        }

                        if (!empty($cabin)) {
                            $s->extra()->cabin($cabin);
                        }

                        // BookingClass
                        $s->extra()->bookingCode($this->re("#/\s*([A-Z])$#", $cabinText), true, true);
                    }

                    if (($n = count($this->http->FindNodes("(./preceding-sibling::tr/td[" . $this->eq($this->t("Depart/Arrive")) . "])[1]/preceding-sibling::td",
                            $root))) > 0) {
                        $n++;
                        $times = explode("/", $this->http->FindSingleNode("./td[{$n}]", $root));

                        if (count($times) == 2 && !empty($date)) {
                            $s->departure()
                                ->date(strtotime($times[0], $date));

                            if (preg_match("#(.+?)\s*([+\-]\d+)\s*$#", $times[1], $mat)) {
                                $s->arrival()
                                    ->date(strtotime($mat[2] . " day", strtotime($mat[1], $date)));
                            } else {
                                $s->arrival()
                                    ->date(strtotime($times[1], $date));
                            }
                        }
                    }
                } elseif ($this->http->XPath->query("*[1]/descendant::a[contains(@href,'hotel')]", $root)->length > 0) {
                    // HOTEL

                    $h = $email->add()->hotel();

                    $h->general()->noConfirmation();

                    if (!empty($passengers)) {
                        $h->general()->travellers($passengers, true);
                    }

                    $hotelName = $this->http->FindSingleNode("*[3]", $root);

                    if ($hotelName) {
                        $h->hotel()->name($hotelName)->noAddress();
                    }

                    $status = $this->http->FindSingleNode("*[4]", $root, true, "/^(Confirmed)$/i");

                    if ($status) {
                        $h->general()->status($status);
                    }

                    $dates = preg_split('/\s*-\s*/', $this->http->FindSingleNode("*[5]", $root));

                    if (count($dates) === 2 && $date) {
                        $h->booked()
                            ->checkIn(EmailDateHelper::parseDateRelative($dates[0], $date, true, '%D%/%Y%'))
                            ->checkOut(EmailDateHelper::parseDateRelative($dates[1], $date, true, '%D%/%Y%'))
                        ;
                    }
                } elseif ($this->http->XPath->query("*[1]/descendant::a[contains(@href,'rail')]", $root)->length > 0) {
                    // TRAIN

                    $train = $email->add()->train();
                // parsing...
                } elseif ($this->http->XPath->query("*[1]/descendant::a[contains(@href,'car')]", $root)->length > 0) {
                    // CAR

                    $car = $email->add()->rental();
                // parsing...
                } else {
                    $email->add()->flight(); // for 100% fail
                }
            }
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): bool
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return false;
        }

        if (preg_match("#Cancel (?<day>\d+) days? prior to arrival local hotel time to avoid any charges\.#ui", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['day'] . ' day');

            return true;
        }

        if (preg_match("/CANCELL? ON (?<d>\d{1,2})[- ]*(?<m>[[:alpha:]]+)[- ]*(?<y>\d{2,4}) BY (?<time>{$this->patterns['time']}) LT TO AVOID 31 N IGHT/u", $cancellationText, $m)
            || preg_match("/^CANCELL? BY (?<time>{$this->patterns['time']}) ON (?<date>\d{1,2}\/\d{1,2}\/\d{2,4})/i", $cancellationText, $m)
        ) {
            if (array_key_exists('d', $m) && array_key_exists('m', $m) && array_key_exists('y', $m)) {
                $m['date'] = $m['d'] . ' ' . $m['m'] . ' ' . $m['y'];
            }

            $h->booked()->deadline(strtotime($m['date'] . ', ' . $m['time']));

            return true;
        }

        if (preg_match("#CXL PRIOR TO (?<hour>\d{1,2})(?<apm>[AP]M) ON DAY OF ARR TO AVO ?ID CHG OF#ui", $cancellationText, $m)
            || preg_match("#Cancel by (?<hour>\d{1,2})(?<min>\d{2})(?<apm>[AP]M) day of arrival local hotel time to avoid any charges\.#ui", $cancellationText, $m)
            /*|| preg_match("#(?<hour>\d{1,2}) HOURS PRIOR TO ARRIVAL#ui", $cancellationText, $m)*/) {
            $h->booked()->deadlineRelative('0 day', trim($m['hour'] . ':' . (!empty($m['min']) ? $m['min'] : '00') . ' ' . ($m['apm'] ?? '')));

            return true;
        }

        if (preg_match("#(?<hour>\d{1,2}) HOURS PRIOR TO ARRIVAL#ui", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['hour'] . ' hours');

            return true;
        }

        if (preg_match("#BY (?<hour>\d+\s*A?P?M) DAY OF ARRIVAL#ui", $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m['hour'], $h->getCheckInDate()));

            return true;
        }

        if (preg_match("#FREE OF CHARGE WHEN CANCELLING BEFORE (\d+\/\d+\/\d+)#ui", $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m[1]));

            return true;
        }

        return false;
    }

    private function getProvider($body): ?string
    {
        foreach ($this->reBody as $prov => $reBody) {
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

    private function normalizeDate($str)
    {
        //$this->logger->error($str);

        $in = [
            "#^\s*[^\s\d]+,\s*([^\s\d]+) (\d+) (\d{4}), (\d+:\d+\s*([AP]M)?)\s*$#",
            // Thursday, June 27 2013, 09:30
            "/^\s*[-[:alpha:]]+\s*(\d+)(?:\s+de)?\s+([[:alpha:]]+)(?:\s+de)?\s+(\d{4})\s*$/iu",
            // Viernes 4 De Mayo De 2018
            "/^\s*[-[:alpha:]]+\s*(\d+)(?:\s+de)?\s+([[:alpha:]]+)(?:\s+de)?\s+(\d{4})\s*,\s*(\d+:\d+)\s*([ap])[ .]*(m)[.]*\s*$/iu",
            // Viernes 4 De Mayo De 2018 03:15 p. m.
            "/^\s*[-[:alpha:]]+\s*(\d+)(?:[.\s]+de)?[.\s]+([[:alpha:]]+)(?:\s+de)?\s+(\d{4})\s*,\s*(\d+:\d+(?:\s*[ap]m)?)\s*$/iu",
            // Viernes 4 De Mayo De 2018 03:15  |  Samedi 8 Septembre 2018 09:30  |  Sonntag 2. Mai 2021, 10:15
            "#^\s*[^\s\d]+\s*(\d+) ([^\s\d]+) (\d{4})\s*$#",
            // Samedi 8 Septembre 2018
            "#^\s*\w+\s*(\d+)\.\s*(\w+)\s*(\d{4})\s*$#ui",
            //Montag 21. Juni 2021
        ];
        $out = [
            "$2 $1 $3 $4",
            "$1 $2 $3",
            "$1 $2 $3 $4$5$6",
            "$1 $2 $3 $4",
            "$1 $2 $3",
            "$1 $2 $3",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->error($str);

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s): ?float
    {
        if (empty($s)) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {
            return preg_quote($v, '#');
        }, $field)) . ')';
    }
}
