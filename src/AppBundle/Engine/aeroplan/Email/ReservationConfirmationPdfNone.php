<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmationPdfNone extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-105519681.eml, aeroplan/it-107883520.eml, aeroplan/it-11057835.eml, aeroplan/it-11168273.eml, aeroplan/it-219769655.eml, aeroplan/it-30851848.eml, aeroplan/it-31097445.eml, aeroplan/it-4351447.eml, aeroplan/it-49463710.eml, aeroplan/it-6019720.eml, aeroplan/it-713553421.eml, aeroplan/it-717128769.eml, aeroplan/it-8574549.eml, aeroplan/it-8820338.eml"; // +1 bcdtravel(html)[fr]

    public $reSubject = [
        'en' => ['Booking Reference:', 'flight itinerary has been shared with you'],
        'fr' => ['Numéro de réservation:', 'itinéraire de vol vous a été transmis'],
        'it' => ['Riferimento prenotazione:'],
        'es' => ['Código de reserva:', 'Air Canada - Un itinerario de vuelo se ha compartido con usted'],
        'de' => ['Buchungsreferenz:'],
        'ja' => [' (予約照会番号:'],
    ];

    public $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];
    public $subject;
    public $currentDate;
    public $travellers;

    public static $dictionary = [
        'en' => [ // it-105519681.eml, it-11057835.eml, it-11168273.eml, it-219769655.eml, it-30851848.eml, it-31097445.eml, it-4351447.eml
            // Header
            "Booking Reference:" => ["Booking Reference", "Booking Reference:", "Booking reference"],
            //            'Date of issue:' => '',
            //            'Your seats are confirmed.' => '',
            //            'Passengers' => '',
            "Infant (On lap)"    => ["Infant (On lap)", "Infant (In seat)"],
            "Ticket Number"      => ["Ticket Number", "Ticket number", "Ticket no.:", "Ticket #:", "common.ticket.cc.text #:", "Ticket#:"],
            "Aeroplan #:"        => ["Aeroplan #:", "Air Canada - Aeroplan"],
            //            'Seats' => '',

            // Segments
            "direction"             => ["Depart", "Return"],
            'Flight '               => ['Flight ', 'FLIGHT'],
            //            'Aircraft type:' => '',
            //            'Cabin' => '',
            //            'Operated by:' => '',
            //            'Terminal' => '',
            //            'day' => '',
            'For information' => ['For information', 'For important information'],

            //            'adult'              => ['adult', 'adults'],
            //            'Purchase summary' => '',
            'GRAND TOTAL'        => ['GRAND TOTAL', 'Air Canada - total', 'Grand total'],
            'airFareHeader'      => ['Air Transportation Charges', 'Air transportation charges'],
            'feesHeader'         => ['Taxes, Fees and Charges', 'Taxes, fees and charges'],
            'feesEnd'            => ['Total airfare and taxes before options (per passenger)', 'Airfare and taxes, per passenger', 'Air Canada - total'],
            'feesHeaderOptions'  => ['Seat selection'],
            'feesEndOptions'     => ['Total with options and seat selection fee:'],
            //            'Number of passengers' => '',
            //            'Subtotal' => '',
            //            'per passenger' => '',
        ],
        'fr' => [ // it-107883520.eml
            "direction"                    => ['Départ', 'Retour'],
            "Flight "                      => "Vol ",
            "Booking Reference:"           => "Numéro de réservation:",
            "Date of issue:"               => ["Date de délivrance:"],
            'Your seats are confirmed.'    => 'Vos places sont confirmées.',
            // 'adult' => '',
            "GRAND TOTAL"          => "TOTAL GÉNÉRAL",
            "Cabin"                => "Cabine",
            "Operated by:"         => "Exploité par",
            "Aircraft type:"       => "Type d'appareil:",
            'day'                  => 'jour',
            'For information'      => ['Ceci est un vol à code multiple assuré par'],
            "Ticket Number"        => ["Numéro de billet", "Nº de billet :", 'Billet #:'],
            "Passengers"           => "Passagers",
            // "Infant (On lap)" => "",
            "Terminal"             => 'Aérogare',
            "Seats"                => 'Places',
            'Purchase summary'     => "Sommaire de l'achat",
            'Aeroplan'             => 'Aéroplan',
            'airFareHeader'        => 'Frais de transport aérien',
            'Subtotal'             => 'Total partiel',
            'feesHeader'           => ['Taxes, frais et surtaxes', 'Taxes, frais et droits'],
            'feesEnd'              => 'Frais de transport aérien et taxes par passager',
            'feesHeaderOptions'    => ['Sélection des places'],
            'feesEndOptions'       => ['Total comprenant les options et les frais de sélection des places :'],
            'Aeroplan #:'          => ['Aéroplan #:', 'Aeroplan #:'],
            'Number of passengers' => 'Nombre de passagers',
            'per passenger'        => 'par passager',
        ],
        'it' => [ // it-6019720.eml, it-8820338.eml
            "direction"             => ["Parte", "Ritorno"],
            "Flight "               => "NOTTRANSLATED",
            "Booking Reference:"    => "Riferimento prenotazione:",
            "Date of issue:"        => "Data di emissione:",
            // 'Your seats are confirmed.' => '',
            // 'adult' => '',
            "GRAND TOTAL"          => ["TOTALE COMPLESSIVO", "Totale complessivo"],
            "Cabin"                => "Cabina",
            "Operated by:"         => "Operato da:",
            "Aircraft type:"       => "Tipo di aereo:",
            "Ticket Number"        => ["Numero del biglietto", 'Biglietto #:'],
            "Passengers"           => "Passeggeri",
            // "Infant (On lap)" => "",
            //            "Terminal" => '',
            'day'              => 'giorno',
            // 'For information' => '',
            'Purchase summary' => 'Riepilogo acquisto',
            //'Aeroplan',
            "Seats"                => "Posti",
            'airFareHeader'        => ['Addebiti per il trasporto aereo'],
            'Subtotal'             => 'Subtotale',
            'feesHeader'           => 'Tasse, supplementi e addebiti',
            'feesEnd'              => ['Totale tariffa aerea e tasse, tutti i passeggeri', 'Tariffa aerea e tasse, per ogni passeggero (prima delle opzioni di viaggio)'],
            'feesHeaderOptions'    => ['Opzioni di viaggio'],
            'feesEndOptions'       => ['Totale della tariffa aerea e delle tasse dopo le opzioni'],
            "Aeroplan #:"          => ["Aeroplan #:", "Air Canada - Aeroplan"],
            'Number of passengers' => 'Numero di passeggeri',
            'per passenger'        => 'per ogni passeggero',
        ],
        'es' => [ // it-8574549.eml
            "direction"                    => ["Salida", "Regreso"],
            "Flight "                      => "NOTTRANSLATED",
            "Booking Reference:"           => "Código de reserva:",
            "Date of issue:"               => "Fecha de emisión:",
            'Your seats are confirmed.'    => 'Sus asientos están confirmados.',
            // 'adult' => '',
            "GRAND TOTAL"          => ["TOTAL GENERAL", "Air Canada - total"],
            "Cabin"                => "Cabina",
            "Operated by:"         => "Operado por:",
            "Aircraft type:"       => "Tipo de aereo:",
            "Ticket Number"        => ["Número de boleto", "Boleto #:"],
            "Passengers"           => "Pasajeros",
            // "Infant (On lap)" => "",
            // 'day' => '',
            // 'For information' => '',
            'Purchase summary'     => 'Resumen de la compra',
            //'Aeroplan',
            //            "Terminal" => '',
            "Seats"         => "Asientos",
            'airFareHeader' => 'Cargos de transporte aéreo',
            'Subtotal'      => 'Subtotal',
            'feesHeader'    => 'Impuestos, tasas y cargos',
            'feesEnd'       => ['Total de tarifa aérea e impuestos, sin incluir opciones', 'Air Canada - total'],
            // 'Aeroplan #:' => '',
            'Number of passengers' => 'Número de pasajeros',
            'per passenger'        => 'por pasajero',
        ],
        'de' => [
            "direction"             => ["Abflug", "Rückflug"],
            "Flight "               => "Flug ",
            "Booking Reference:"    => "Buchungsreferenz:",
            "Date of issue:"        => "Ausstellungsdatum:",
            // 'Your seats are confirmed.' => '',
            // 'adult' => '',
            "GRAND TOTAL"      => "GESAMTSUMME",
            "Cabin"            => "Kabine",
            "Aircraft type:"   => "Flugzeugtyp:",
            "Operated by:"     => "Durchgeführt von:",
            "Ticket Number"    => ["Ticketnummer", "Ticket #:", "Ticket#:"],
            "Passengers"       => ["Passagiere", "Fluggäste"],
            // "Infant (On lap)" => "",
            'day'              => 'Tag',
            'For information'  => 'Wichtige Informationen',
            'Purchase summary' => 'Buchungsübersicht',
            //'Aeroplan',
            //            "Terminal" => '',
            "Seats"         => 'Sitzplätze',
            'airFareHeader' => 'Lufttransportgebühren',
            'Subtotal'      => 'Zwischensumme',
            'feesHeader'    => 'Steuern, Abgaben und Gebühren',
            'feesEnd'       => 'Gesamtflugpreis und Steuern vor Reiseoptionen ',
            // 'Aeroplan #:' => '',
            'Number of passengers' => 'Anzahl der Passagiere',
            'per passenger'        => 'vor Reiseoptionen ',
        ],
        'ja' => [ // it-49463710.eml
            "direction"             => ["出発", "戻る"],
            "Flight "               => "NOTTRANSLATED",
            "Booking Reference:"    => "予約照会番号:",
            "Date of issue:"        => "発行日:",
            // 'Your seats are confirmed.' => '',
            // 'adult' => '',
            "GRAND TOTAL"          => ["総額", "合計金額"],
            "Cabin"                => "キャビン :",
            "Operated by:"         => "運航:",
            "Aircraft type:"       => "機材タイプ:",
            "Ticket Number"        => ["航空券番号", '航空券 #:'],
            "Passengers"           => "搭乗者",
            "Infant (On lap)"      => "幼児 (座席なし)",
            "Terminal"             => 'ターミナル',
            // 'day' => '',
            // 'For information' => '',
            "Seats"                => '座席',
            'airFareHeader'        => '航空輸送料',
            'Subtotal'             => '小計',
            'feesHeader'           => ['諸税、各種手数料', '諸税、空港利用料、追加料金'],
            // 'feesEnd' => '',
            'Purchase summary' => 'ご購入内容',
            "Aeroplan #:"      => ["全日空-ANAマイレージクラブ:"],
            //            'Number of passengers' => '',
            //            'per passenger' => '',
        ],
    ];

    public $segmentType = '';
    public $lang = '';
    private $reBody2 = [
        'en' => [
            'Get your travel started',
            'In preparation for your trip', 'Take a look at this travel information',
            'your flight details and other useful information for your trip',
        ],
        'fr' => [
            'En vue de votre voyage', 'Confirmation de réservation', 'Vos places sont confirmées',
            'Commencez à préparer votre voyage', 'options de voyage a été effectué avec succès',
        ],
        'it' => ['In preparazione del suo viaggio', 'Conferma prenotazione', 'Inizi da qui il suo viaggio'],
        'es' => ['Confirmación de reserva', 'Sus asientos están confirmados', 'Prepárese para emprender su viaje'],
        'de' => ['Buchungsbestätigung', 'Buchungsreferenz'],
        'ja' => ['予約の確認'],
    ];

    public function parseHtml(Email $email): void
    {
        $this->patterns['terminal'] = '/' . $this->opt($this->t('Terminal')) . '\s*(.+)/';

        $seats = [];

        if (count($nodes = $this->http->FindNodes("//text()[" . $this->eq($this->t("Seats")) . "]/ancestor::tr[1]/..")) > 0) {
            foreach ($nodes as $node) {
                if (preg_match_all("#\w{2}(\d+)\s+(\d+\w)#", $node, $ms, PREG_SET_ORDER)) {
                    foreach ($ms as $m) {
                        $seats[$m[1]][] = $m[2];
                    }
                }
            }
        }

        $f = $email->add()->flight();

        // RecordLocator
        $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference:'))}]",
            null, false, "/{$this->opt($this->t('Booking Reference:'))}\s+([\w\-\s]+)/");

        if (!$conf) {
            $conf = $this->nextText($this->t("Booking Reference:"));
        }

        if (!$conf) {
            $conf = $this->re("/{$this->opt($this->t('Booking Reference:'))}\s*([A-Z\d]{4,})/", $this->subject);
        }

        if ($conf) {
            $f->general()->confirmation($conf);
        } elseif (!$conf && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking Reference:'))}]")->length == 0) {
            $f->general()->noConfirmation();
        }

        // ReservationDate
        $reservationDate = $this->http->FindSingleNode('//tr[not(.//tr) and ' . $this->contains($this->t("Booking Reference:")) . ']/descendant::text()[' . $this->contains($this->t("Date of issue:")) . ']', null, true, '/^[^:]+:\s*(.+)$/');

        if (empty($reservationDate)) {
            $reservationDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Date of issue:'))}]", null, true, "/{$this->opt($this->t('Date of issue:'))}\s*(.+)/");
        }

        if ($reservationDate) {
            $reservationDate = $this->normalizeDate($reservationDate);
        }

        if ($reservationDate) {
            $f->general()->date(strtotime($reservationDate));
        }

        // Passengers
        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t("Passengers"))}]/following::table[1]/descendant::tr[1]/../tr[{$this->contains($this->t("Seats"))}]/descendant::text()[normalize-space(.)!=''][not(contains(.,'image'))][2][not({$this->contains($this->t("Ticket Number"))})][not({$this->contains($this->t("Seats"))})][not({$this->contains($this->t("Aeroplan #:"))})][not({$this->contains($this->t("Infant (On lap)"))})]");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t("Passengers"))}]/ancestor::table[1]/descendant::text()[{$this->starts($this->t("Ticket Number"))}]/preceding::text()[normalize-space()][1]");
        }

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t("Passengers"))}]/following::table[1]/descendant::tr[1]/../tr/descendant::text()[normalize-space(.)][1][not({$this->contains($this->t("Ticket Number"))})][not({$this->contains($this->t("Seats"))})][not({$this->contains($this->t("Aeroplan #:"))})][not({$this->contains($this->t("Infant (On lap)"))})]");
        }

        if (!empty($travellers)) {
            $f->general()->travellers($this->travellers = $travellers);
        }

        $infants = $this->http->FindNodes("//text()[{$this->eq($this->t("Passengers"))}]/following::table[1]/descendant::td[not(.//td)][normalize-space()][1][descendant::text()[normalize-space(.)!=''][2][{$this->contains($this->t("Infant (On lap)"))}]]/descendant::text()[normalize-space(.)!=''][1]");

        if (!empty($infants)) {
            $f->general()
                ->infants($infants);
        }

        // TicketNumbers
        $tickets = array_unique(array_filter($this->http->FindNodes("//text()[{$this->contains($this->t("Ticket Number"))}]/following::text()[string-length(normalize-space(.))>1][1]", null, "#^[\d \-]{10,}$#")));

        if (count($tickets) == 0) {
            $tickets = array_unique(array_filter($this->http->FindNodes("//text()[{$this->contains($this->t("Ticket Number"))}]", null, "#\s([\d\-]{10,})\s*$#")));
        }

        foreach ($tickets as $ticket) {
            $pax = $this->http->FindSingleNode("//text()[{$this->contains($ticket)}]/ancestor::tr[1]/descendant::text()[normalize-space()][1][{$this->contains($travellers)}]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/");

            if (empty($pax)) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1]/descendant::text()[{$this->contains($ticket)}]/ancestor::tr[2]/descendant::text()[normalize-space()][1][{$this->contains($travellers)}]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/");
            }

            if (empty($pax)) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/ancestor::table[normalize-space()][4]/descendant::text()[normalize-space()][1][{$this->contains($travellers)}]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/");
            }

            if (empty($pax)) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/ancestor::table[1]/descendant::text()[{$this->contains($travellers)}]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/");
            }

            if (!empty($pax)) {
                $f->addTicketNumber($ticket, false, $pax);
            } else {
                $f->addTicketNumber($ticket, false);
            }
        }

        // AccountNumbers
        //	Air Canada - Aéroplan, Lufthansa - Miles & More
        $accounts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t("Ticket Number"))}]/following::text()[normalize-space()][position()>0 and position()<5 and contains(.,'-')]/following::text()[string-length(normalize-space())>1][1][not(preceding::text()[normalize-space()][position()<5][{$this->contains($this->t("Seats"))}])]", null, "/^[-A-Z\d ]{5,}$/"));

        if (count($accounts) === 0) {
            $accounts = array_filter(array_merge(
                $this->http->FindNodes("//text()[{$this->starts($this->t("Aeroplan #:"))}]", null, "/{$this->opt($this->t('Aeroplan #:'))}\s*([A-Z\d]{5,})$/u"),
                $this->http->FindNodes("//text()[{$this->eq($this->t("Aeroplan #:"))}]/following::text()[normalize-space()][1]", null, "/^[A-Z\d]{5,}$/")
            ));
        }

        if (count($accounts) > 0) {
            $accounts = array_filter($accounts, function ($v) {
                return preg_match('/\d/', $v) > 0;
            });

            foreach ($accounts as $account) {
                $pax = $this->http->FindSingleNode("//text()[{$this->contains($account)}]/preceding::text()[position() < 5][{$this->contains($travellers)} or {$this->contains($infants)}][1]");
                $name = $this->http->FindSingleNode("(//text()[{$this->contains($account)}])[1]", null, true, "/^({$this->opt($this->t('Aeroplan #:'))})\s*[A-Z\d]+/");

                if (!empty($name)) {
                    $name = trim($name, ':');
                }
                $f->addAccountNumber($account, false, $pax, $name);
            }
        }

        // it-219769655.eml, it-8574549.eml
        $bookingModified = $this->http->XPath->query("//text()[{$this->contains($this->t('Your seats are confirmed.'))}]")->length > 0;

        if (!$bookingModified) {
            $this->parsePrice($f, $email);
        }

        $ruleTime = "translate(normalize-space(.),'0123456789','dddddddddd--')='dd:dd' or translate(normalize-space(.),'0123456789','dddddddddd--')='d:dd'";
        $xpath = "//text()[{$ruleTime}]/ancestor::tr[count(..//text()[{$ruleTime}])=2][1]/ancestor::tr[1][count(./td)=2 or count(./td)=4]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//text()[(" . $this->starts($this->t("Flight ")) . " or " . $this->eq($this->t("direction")) . ") and string-length()<20]/following::table[1][contains(translate(., '0123456789', 'dddddddddd'), 'd:dd')]/descendant::tr[2]/../tr[position()>1][contains(translate(., '0123456789', 'dddddddddd'), 'd:dd')]";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length === 0) {
            $xpath = "//text()[(" . $this->starts($this->t("Flight ")) . " or " . $this->eq($this->t("direction")) . ") and string-length()<20]/following::table[1][contains(translate(., '0123456789', 'dddddddddd'), 'd:dd')]/descendant::tr[2]/../tr[position()>1][contains(translate(., '0123456789', 'dddddddddd'), 'd:dd')]";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length > 0) {
            $this->logger->debug("[XPath] Segments type 1:\n" . $xpath);
            $this->segmentType = '1';
            $this->parseSegment1($f, $email, $segments);
        } elseif ($segments->length == 0) {
            $xpath = "//text()[{$this->contains($this->t('Operated by:'))}]/ancestor::tr[1]";
            $segments = $this->http->XPath->query($xpath);
            $this->logger->debug("[XPath] Segments type 2:\n" . $xpath);

            if ($segments->length > 0) {
                $this->segmentType = '2';
                $this->parseSegment2($f, $email, $segments);
            }
        }
    }

    public function parsePrice(Flight $f, Email $email): void
    {
        // Type 1
        // Air transportation charges (in points)       71,500 pts
        // Air transportation charges                   CA $39.00*
        // Taxes, fees and charges                      CA $78.76*
        // Total airfare and taxes, all passengers      71,500 pts + CA $117.76
        // GRAND TOTAL                                  71,500 pts + CA $117.76
        $xpath1 = "//td[{$this->starts($this->t('airFareHeader'))}][following-sibling::*[normalize-space()]]"
            . "/following::td[{$this->starts($this->t('feesHeader'))}][following-sibling::*[normalize-space()]]"
            . "/following::*[{$this->starts($this->t('GRAND TOTAL'))}]";

        if ($this->http->XPath->query($xpath1)->length > 0
        ) {
            $totalPriceRows = $this->http->XPath->query("//text()[{$this->eq($this->t('GRAND TOTAL'))}]/ancestor::tr[count(*[normalize-space()])=2][1]");

            if ($totalPriceRows->length === 1) {
                $root = $totalPriceRows->item(0);

                $totalPriceStr = implode(' ',
                    $this->http->FindNodes("*[normalize-space()][2]/descendant::text()[normalize-space()]", $root));

                $currency = $this->normalizeCurrency($this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[string-length(normalize-space()) > 2][last()]", $root));

                if (preg_match("/^\s*(?<spent>\d[,.‘\'\d]*\s*(?:pts|points))\s*(?:[+](?<total>.*)?)\s*$/iu",
                    $totalPriceStr, $m)) {
                    // 42,600 pts + CA $260.84
                    $f->price()->spentAwards($m['spent']);
                    $totalPriceStr = $m['total'] ?? '';

                    $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Purchase summary'))}]/following::text()[normalize-space()][position() < 5][{$this->eq(['Aeroplan', 'Aéroplan'])}]/ancestor::td[1][following-sibling::*[{$this->contains(['pts', 'point'])}]]",
                        null, true, "/(?:Aeroplan|Aéroplan)\s*([^\w\s]{5,7}\d{3})\s*$/u");

                    if (!empty($account)) {
                        $f->program()
                            ->account($account, true, null, $this->http->FindSingleNode("//text()[{$this->eq($this->t('Purchase summary'))}]/following::text()[normalize-space()][position() < 5][{$this->eq(['Aeroplan', 'Aéroplan'])}]/ancestor::td[1][following-sibling::*[{$this->contains(['pts', 'point'])}]]",
                                null, true, "/(Aeroplan|Aéroplan)\s*[^\w\s]{5,7}\d{3}\s*$/u"));
                    }
                }
                $total = $this->getTotal($totalPriceStr, $currency);

                $f->price()
                    ->total($total['amount'])
                    ->currency($currency ?? $total['currency']);

                $taxStr = $this->http->FindSingleNode("//td[{$this->eq($this->t('feesHeader'))}]/following::*[normalize-space()][1]");

                if (!empty($taxStr) && $tax = $this->getTotal($taxStr, $currency)) {
                    $f->price()
                        ->tax($tax['amount']);
                }

                $costStr = $this->http->FindSingleNode("//td[{$this->eq($this->t('airFareHeader'))}]/following::*[normalize-space()][1]");

                if (!empty($costStr) && $cost = $this->getTotal($costStr, $currency)) {
                    $f->price()
                        ->cost($cost['amount']);
                }
            }

            return;
        }
        // Type 2
        // Air transportation charges
        // Base fare - Economy - Standard - Discount applied        CA $327.00
        //Surcharges.                                               CA $30.00
        //Subtotal                                                  CA $357.00
        //Taxes, fees and charges
        //Air Travellers Security Charge - Canada                   CA $7.12
        //Harmonized Sales Tax - Canada - 100092287 RT0001          CA $51.89
        //Airfare and taxes, per passenger (before travel options)  CA $451.01
        //Number of passengers                                      2
        //Total                                                     CA $902.02
        //GRAND TOTAL Canadian dollars (CAD)                        CA $902.02
        $xpath2 = "//tr[{$this->eq($this->t('airFareHeader'))}]"
            . "/following::tr[{$this->eq($this->t('feesHeader'))}]"
            . "/following::*[{$this->starts($this->t('GRAND TOTAL'))}]";

        if ($this->http->XPath->query($xpath2)->length > 0
        ) {
            $totalPriceRows = $this->http->XPath->query("//text()[{$this->eq($this->t('GRAND TOTAL'))}]/ancestor::tr[count(*[normalize-space()])=2][1]");

            if ($totalPriceRows->length === 1) {
                $root = $totalPriceRows->item(0);

                $totalPriceStr = implode(' ',
                    $this->http->FindNodes("*[normalize-space()][last()]/descendant::text()[normalize-space()]", $root));

                $currency = $this->normalizeCurrency($this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[string-length(normalize-space()) > 2][last()]", $root));

                if (preg_match("/^\s*(?<spent>\d[,.‘\'\d]*\s*(?:pts|points))\s*(?:[+](?<total>.*)?)\s*$/iu",
                    $totalPriceStr, $m)) {
                    // 42,600 pts + CA $260.84
                    $f->price()->spentAwards($m['spent']);
                    $totalPriceStr = $m['total'] ?? '';
                }
                $total = $this->getTotal($totalPriceStr);

                $f->price()
                    ->total($total['amount'])
                    ->currency($currency ?? $total['currency']);

                $passengersCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Purchase summary'))}]/following::tr[{$this->starts($this->t('Number of passengers'))}]/*[normalize-space()][2]", null, true, "/^[☓x\s]*(\d{1,3})$/iu");

                if (empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Purchase summary'))}]/following::node()[{$this->contains($this->t('Number of passengers'))}])[1]"))
                    && empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Purchase summary'))}]/following::node()[{$this->contains($this->t('per passenger'))}])[1]"))
                ) {
                    // price for all passengers, not per passenger
                    $passengersCount = 1;
                }

                $baseFareRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Purchase summary'))}]/following::tr[count(*[normalize-space()]) = 2]" .
                    "[preceding::tr[{$this->eq($this->t('airFareHeader'))}] and following::tr[{$this->eq($this->t('feesHeader'))}]]" .
                    "[not(contains(@style, 'font-weight: 700'))]", $root);

                $baseFare = [];

                foreach ($baseFareRows as $bfRow) {
                    $bfCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $bfRow);
                    $base = $this->getTotal($bfCharge, $currency);
                    $baseFare[] = $base['amount'];
                }

                if (count($baseFare) > 0 && !in_array(null, $baseFare, true)) {
                    $f->price()->cost($passengersCount * array_sum($baseFare));
                }

                $feesRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Purchase summary'))}]/following::tr[count(*[normalize-space()]) = 2]" .
                    "[preceding::tr[{$this->eq($this->t('feesHeader'))}] and following::tr[{$this->starts($this->t('feesEnd'))}]]" .
                    "[not(contains(@style, 'font-weight: 700'))]", $root);

                foreach ($feesRows as $fRow) {
                    $fName = $this->http->FindSingleNode('*[normalize-space()][1]', $fRow);
                    $fAmount = $this->getTotal($this->http->FindSingleNode('*[normalize-space()][2]', $fRow), $currency);
                    $f->price()
                        ->fee($fName, $passengersCount * $fAmount['amount']);
                }

                $feesRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Purchase summary'))}]/following::tr[count(*[normalize-space()]) = 2]" .
                    "[preceding::tr[{$this->eq($this->t('feesHeaderOptions'))}] and following::tr[{$this->starts($this->t('feesEndOptions'))}]]" .
                    "[not(contains(@style, 'font-weight: 700'))]", $root);

                foreach ($feesRows as $fRow) {
                    $fName = $this->http->FindSingleNode('*[normalize-space()][1]', $fRow);
                    $fAmount = $this->getTotal($this->http->FindSingleNode('*[normalize-space()][2]', $fRow), $currency);
                    $f->price()
                        ->fee($fName, $passengersCount * $fAmount['amount']);
                }
            }

            return;
        }

        return;
    }

    public function parseSegment1(Flight $f, Email $email, \DOMNodeList $segments): void
    {
        $this->logger->debug(__FUNCTION__);

        foreach ($segments as $root) {
            if (count($this->http->FindNodes("./td[3]//img[contains(@src, 'plane.png') or contains(@src, 'fare-rules')]", $root)) > 0) {
                $key = 0;
                $this->currentDate = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

                if (empty($this->currentDate)) {
                    $key = 1; // for forwarded emails
                    $this->currentDate = strtotime($this->normalizeDate($this->http->FindSingleNode("./ancestor::td[1]/preceding::td[1]", $root)));
                }

                $s = $f->addSegment();

                // AirlineName
                // FlightNumber
                $flight = implode("\n",
                    $this->http->FindNodes("(./following-sibling::tr[1]//td[.//img[@width='23'] and not(.//td)])[1]//text()[normalize-space(.)!='']",
                        $root));

                if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $flight, $matches)) {
                    $s->airline()->name($matches[1]);
                    $s->airline()->number($matches[2]);
                }

                // DepCode
                $s->departure()->code($this->http->FindSingleNode("./td[" . (2 - $key) . "]", $root, true, "#\(([A-Z]{3})\)#"));

                // DepName
                $name = $this->http->FindSingleNode("./td[" . (2 - $key) . "]/descendant::text()[contains(., '(') and contains(., ')')][1]",
                    $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                if ($name) {
                    $s->departure()->name($name);
                }

                // DepDate
                $time = $this->http->FindSingleNode("./td[" . (2 - $key) . "]/descendant::text()[normalize-space(.)][1]", $root, true, "/{$this->patterns['time']}/");

                if (!empty($this->currentDate)) {
                    if (empty($time)) {
                        $dop = $this->http->FindSingleNode("./td[" . (2 - $key) . "]/descendant::text()[normalize-space(.)][1]", $root);
                        $time = $this->http->FindSingleNode("./td[" . (2 - $key) . "]/descendant::text()[normalize-space(.)][2]", $root, true, "/{$this->patterns['time']}/");
                        $s->departure()->date(strtotime($dop, strtotime($time, $this->currentDate)));
                    } else {
                        $s->departure()->date(strtotime($time, $this->currentDate));
                    }
                }

                // DepartureTerminal
                $terminalDep = $this->http->FindSingleNode("./td[" . (2 - $key) . "]", $root, true, $this->patterns['terminal']);

                if ($terminalDep) {
                    $s->departure()->terminal($terminalDep);
                }

                // ArrCode
                $s->arrival()->code($this->http->FindSingleNode("./td[" . (4 - $key) . "]", $root, true, "#\(([A-Z]{3})\)#"));

                // ArrName
                $name = $this->http->FindSingleNode("./td[" . (4 - $key) . "]/descendant::text()[contains(., '(') and contains(., ')')][1]",
                    $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                if ($name) {
                    $s->arrival()->name($name);
                }

                // ArrDate
                $time = $this->http->FindSingleNode("./td[" . (4 - $key) . "]/descendant::text()[normalize-space(.)][1]", $root, true, "/{$this->patterns['time']}/");

                if (!empty($this->currentDate)) {
                    if (empty($time)) {
                        $dop = $this->http->FindSingleNode("./td[" . (4 - $key) . "]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\s*(\+\s*\d+)\D#");
                        $time = $this->http->FindSingleNode("./td[" . (4 - $key) . "]/descendant::text()[normalize-space(.)][2]", $root, true, "/{$this->patterns['time']}/");
                        $s->arrival()->date(strtotime($dop . ' days', strtotime($time, $this->currentDate)));
                    } else {
                        $s->arrival()->date(strtotime($time, $this->currentDate));
                        $dop = $this->http->FindSingleNode("./td[" . (4 - $key) . "]/descendant::text()[normalize-space(.)][2]", $root, true, "#^\s*(\+\s*\d+)\D#");

                        if (!empty($dop)) {
                            $s->arrival()->date(strtotime($dop . ' days', $s->getArrDate()));
                        }
                    }
                }

                // ArrivalTerminal
                $terminalArr = $this->http->FindSingleNode("./td[" . (4 - $key) . "]", $root, true, $this->patterns['terminal']);

                if ($terminalArr) {
                    $s->arrival()->terminal($terminalArr);
                }

                // AirlineName
                if (!empty($s->getFlightNumber())) {
                    $info = array_filter($this->http->FindNodes("(./following-sibling::tr[1]//td[.//img[@width='23'] and not(.//td)])[1]/following::td[1]//*[local-name()='td' or local-name()='div' and not(td)]", $root));

                    foreach ($info as $key => $value) {
                        // 1hr35, 5h55, 7Std.30
                        if (empty($s->getDuration()) && preg_match("#^\s*((?:\d{0,2}[a-z]{0,2})+)\s*$#", $value, $m) || preg_match("#^\s*(\d{0,2}Std\.\d+)\b\s*$#", $value, $m)) {
                            // Duration
                            $s->extra()->duration($m[1]);

                            continue;
                        }
                        // Aircraft
                        // Operator

                        if (empty($s->getOperatedBy()) && empty($s->getAircraft())
                            && preg_match("#" . $this->t('Operated by:') . "(?<operator>[^\|]*)\|\s*(?<aircraft>.*?)\s*(?:$|\|)#", $value, $m)
                        ) {
                            $s->airline()->operator(trim($m['operator']));
                            $s->extra()->aircraft(trim($m['aircraft']));

                            continue;
                        }
                        // Cabin
                        // BookingClass
                        if (!isset($itsegment['Cabin']) && !isset($itsegment['BookingClass']) && preg_match('/^\s*(?:' . $this->opt($this->t('Cabin')) . '\s*:\s*)?(\w+)[\s\(]*([A-Z]{1,2})[\s\)]*$/u', $value, $m)) {
                            $s->extra()->cabin($m[1]);
                            $s->extra()->bookingCode($m[2]);

                            continue;
                        }
                        // Meal
                        if (count($info) < 5) {
                            $s->extra()->meal($value);

                            continue;
                        }
                    }
                    // Seats
                    $seats = array_filter($this->http->FindNodes("//h2[contains(normalize-space(.),'" . $this->t("Passengers") . "')]/following-sibling::table[1]//*[contains(text(),'" . $s->getAirlineName() . $s->getFlightNumber() . "')]/following::text()[string-length(normalize-space(.))>1][1]", null, "#^\s*(\d{1,3}[A-Z])\s*$#"));
                    //$s->extra()->seats($seats);
                    foreach ($seats as $seat) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->contains($seat)}]/ancestor::table[3]/descendant::text()[normalize-space()][{$this->contains($this->travellers)}]");

                        if (!empty($pax)) {
                            $s->addSeat($seat, false, false, $pax);
                        } else {
                            $s->addSeat($seat);
                        }
                    }
                }
            } else {
                // TODO: think need to check (need examples) how collected a few flights on one trip (with layover)
                $this->currentDate = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

                $s = $f->addSegment();

                // AirlineName
                // FlightNumber
                $flight = implode("\n",
                    $this->http->FindNodes("./td[2]/descendant::tr[1]/../tr[2]//text()[normalize-space(.)!='']",
                        $root));

                if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $flight, $matches)) {
                    $s->airline()->name($matches[1]);
                    $s->airline()->number($matches[2]);
                }

                // DepCode
                $s->departure()->code($this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[1]", $root, true, "#\(([A-Z]{3})\)#"));

                // DepName
                $name = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[1]/descendant::text()[contains(., '(') and contains(., ')')][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                if ($name) {
                    $s->departure()->name($name);
                }

                // DepDate
                $time = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "/{$this->patterns['time']}/");

                if (!empty($this->currentDate)) {
                    if (empty($time)) {
                        $dop = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[1]/descendant::text()[normalize-space(.)][1]", $root);
                        $time = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "/{$this->patterns['time']}/");
                        $s->departure()->date(strtotime($dop, strtotime($time, $this->currentDate)));
                    } else {
                        $s->departure()->date(strtotime($time, $this->currentDate));
                    }
                }

                // DepartureTerminal
                $terminalDep = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[1]", $root, true, $this->patterns['terminal']);

                if ($terminalDep) {
                    $s->departure()->terminal($terminalDep);
                }

                // ArrCode
                $s->arrival()->code($this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[3]", $root, true, "#\(([A-Z]{3})\)#"));

                // ArrName
                $name = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[3]/descendant::text()[contains(., '(') and contains(., ')')][1]",
                    $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                if ($name) {
                    $s->arrival()->name($name);
                }

                // ArrDate
                $time = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "/{$this->patterns['time']}/");

                if (!empty($this->currentDate)) {
                    if (empty($time)) {
                        $dop = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\s*(\+\s*\d+)\D#");
                        $time = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[3]/descendant::text()[normalize-space(.)][2]", $root, true, "/{$this->patterns['time']}/");
                        $s->arrival()->date(strtotime($dop . ' days', strtotime($time, $this->currentDate)));
                    } else {
                        $s->arrival()->date(strtotime($time, $this->currentDate));
                        $dop = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#^\s*(\+\s*\d+)\D#");

                        if (!empty($dop)) {
                            $s->arrival()->date(strtotime($dop . ' days', $s->getArrDate()));
                        }
                    }
                }

                // ArrivalTerminal
                $terminalArr = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/td[3]", $root, true, $this->patterns['terminal']);

                if ($terminalArr) {
                    $s->arrival()->terminal($terminalArr);
                }

                // AirlineName
                // Duration
                $s->extra()->duration($this->http->FindSingleNode("./td[2]/descendant::tr[1]/../tr[2]/descendant::tr[1]/td[2]/descendant::text()[normalize-space(.)][1]", $root));

                // Aircraft
                $aircraft = $this->http->FindSingleNode("./td[2]/descendant::tr[1]/../tr[2]/descendant::tr[1]/td[2]/descendant::text()[" . $this->contains($this->t("Operated by:")) . "][1]/ancestor::td[1]", $root, true, "#" . $this->t("Operated by:") . "\s*.*?\s*\|\s*(.*?)\s*(?:\||$)#u");

                if (!empty($aircraft)) {
                    $s->extra()->aircraft($aircraft);
                }

                // Operator
                $s->airline()->operator($this->http->FindSingleNode("./td[2]/descendant::tr[1]/../tr[2]/descendant::tr[1]/td[2]/descendant::text()[" . $this->contains($this->t("Operated by:")) . "][1]/ancestor::td[1]", $root, true, "#" . $this->t("Operated by:") . "\s*(.*?)\s*\|#"));

                // Cabin
                $s->extra()->cabin($this->http->FindSingleNode("./td[2]/descendant::tr[1]/../tr[2]/descendant::tr[1]/td[2]/*[string-length(normalize-space(.))>1][2]", $root, true, '/^\s*(?:' . $this->opt($this->t('Cabin')) . '\s*:\s*)?([\w ]+) [A-Z]{1,2}$/u'), true, true);

                // BookingClass
                $s->extra()->bookingCode($this->http->FindSingleNode("./td[2]/descendant::tr[1]/../tr[2]/descendant::tr[1]/td[2]/*[string-length(normalize-space(.))>1][2]", $root, true, '/^[\w ]+ ([A-Z]{1,2})$/u'), true, true);

                // Seats
                $seats = [];

                if (!empty($seats[$s->getFlightNumber()])) {
                    $seats = $seats[$s->getFlightNumber()];
                } elseif (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                    $seats = array_filter($this->http->FindNodes("//h2[contains(normalize-space(.),'" . $this->t("Passengers") . "')]/following-sibling::table[1]//*[contains(text(),'" . $s->getAirlineName() . $s->getFlightNumber() . "')]/following::text()[string-length(normalize-space(.))>1][1]",
                        null, "#^\s*(\d{1,3}[A-Z])\s*$#"));
                }

                foreach ($seats as $seat) {
                    $pax = $this->http->FindSingleNode("//text()[{$this->contains($seat)}]/ancestor::tr[3]/descendant::text()[normalize-space()][1][{$this->contains($this->travellers)}]");

                    if (empty($pax)) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->contains($seat)}]/ancestor::table[3]/descendant::text()[normalize-space()][1][{$this->contains($this->travellers)}]");
                    }

                    if (!empty($pax)) {
                        $s->addSeat($seat, false, false, $pax);
                    } else {
                        $s->addSeat($seat);
                    }
                }
            }
        }
    }

    public function parseSegment2(Flight $f, Email $email, \DOMNodeList $segments): void
    {
        $this->logger->debug(__FUNCTION__);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $dateVal = $this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Flight '))} or {$this->starts($this->t('direction'))}][not({$this->contains($this->t('For information'))})][1]/ancestor::tr[1]", $root, true, "/[[:alpha:]]\.?\s*(\d{1,2}\s*[[:alpha:]]+\.?,\s*\d{4})(?:\b|\D)/u");
            $date = $this->normalizeDate($dateVal);

            if (empty($date) || empty($this->currentDate) || strtotime($date) > strtotime($this->currentDate)) {
                $this->currentDate = $date;
            }

            $info = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->contains($this->t('Operated by:'))}][1]/ancestor::td[1]/descendant::text()[normalize-space()]", $root));
            /*
             AC1291
            4hr 30m
            Business (I)
            Operated by: Air Canada
            A321-200 |
            Wi-Fi
            */

            // $this->logger->debug($info);
            // $this->logger->debug('----------------------------------');

            if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d+)[ ]*\n+(?<duration>\d.+)\n+(?<cabin>\D+)\s*\(\s*(?<bCode>[A-Z]{1,2})\s*\)[ ]*\n+{$this->opt($this->t('Operated by:'))}\s*:?\s*(?<operator>.+)(?:\n(?:{$this->opt($this->t('Aircraft type:'))}\s*)?(?<aircraft>.+\s*)|$)/u", $info, $m)
                || preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d+)[ ]*\n+(?<duration>\d.+)[ ]*\n+{$this->opt($this->t('Operated by:'))}\s*:?\s*(?<operator>.+)(?:\n(?<aircraft>.+\s*)|$)?/", $info, $m)
                || preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d+)[ ]*\n+NA\n+(?<cabin>\D+)\s*\(\s*(?<bCode>[A-Z]{1,2})\s*\)[ ]*\n+{$this->opt($this->t('Operated by:'))}\s*:?\s*(?<operator>.+)/", $info, $m)
            ) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number'])
                    ->operator($m['operator']);

                if (isset($m['cabin'])) {
                    $m['cabin'] = preg_replace("/^\s*{$this->opt($this->t('Cabin'))}\s*:\s*/", '', $m['cabin'] ?? '');
                    $s->extra()
                        ->cabin($m['cabin']);
                }

                if (isset($m['bCode'])) {
                    $s->extra()
                        ->bookingCode($m['bCode']);
                }

                if (isset($m['duration'])) {
                    $s->extra()
                        ->duration($m['duration']);
                }

                if (isset($m['aircraft']) && (stripos($m['aircraft'], 'Air Canada Bistro') === false && stripos($m['aircraft'], 'Snack') === false)) {
                    $s->extra()
                        ->aircraft($m['aircraft']);
                }

                if (isset($m['aircraft']) && (stripos($m['aircraft'], 'Air Canada Bistro') !== false || stripos($m['aircraft'], 'Snack') !== false)) {
                    $s->extra()
                        ->meal($m['aircraft']);
                }

                if (preg_match("/(?:Wi-Fi|WLAN)\n(.*(?:food|meal|snack).*)$/i", $info, $m)) {
                    $s->extra()
                        ->meal($m[1]);
                }
            }

            $depInfo = implode("\n", $this->http->FindNodes("./descendant::td[1]/descendant::text()[normalize-space()]", $root));
            $depInfo = str_replace(['&nbsp'], "", $depInfo);

            if (preg_match($pattern = "/^(?<city>.+)\n*(?<code>[A-Z]{3})\b\n?(?<time>{$this->patterns['time']}).*(?<date>\n.*\d{4}.*)?\n*(?:.*{$this->opt($this->t('Terminal'))}\s*(?<terminal>.+))?/u", $depInfo, $m)) {
                if (preg_match("/.*{$this->opt($this->t('Terminal'))}\s*(?<terminal>.+)/", $m['date'] ?? '', $mat)) {
                    $m['terminal'] = $mat['terminal'];
                    $m['date'] = null;
                }

                if (!empty($m['date'])) {
                    $date = $this->normalizeDate($m['date']);

                    if (!empty($date)) {
                        $this->currentDate = $date;
                    }
                }

                if (!empty($this->currentDate)) {
                    $s->departure()
                        ->name($m['city'])
                        ->date(strtotime($this->currentDate . ' ' . $m['time']))
                        ->code($m['code']);
                }

                if (isset($m['terminal'])) {
                    $s->departure()
                        ->terminal($m['terminal']);
                }
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./descendant::td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $arrInfo, $m)) {
                if (!empty($this->currentDate)) {
                    $s->arrival()
                        ->name($m['city'])
                        ->date(strtotime($this->currentDate . ' ' . $m['time']))
                        ->code($m['code']);

                    if (isset($m['terminal'])) {
                        $s->arrival()
                            ->terminal($m['terminal']);
                    }

                    if (preg_match("/[+]\s*(\d{1,3})\s*{$this->opt($this->t('day'))}/u", $arrInfo, $m2)) {
                        $s->arrival()
                            ->date(strtotime("+{$m2[1]} days", $s->getArrDate()));
                    }
                }
            }

            //if all depart and arrival date in row not td
            if (empty($s->getDepCode()) && empty($s->getArrCode())) {
                if (preg_match("/^(?<depCity>.+)\n*(?<depCode>[A-Z]{3})\b\n?(?<arrCity>.+)\n*(?<arrCode>[A-Z]{3})\b\n?(?<depTime>{$this->patterns['time']})\n?(?<arrTime>{$this->patterns['time']})\n/u", $depInfo, $m)) {
                    $s->departure()
                        ->name($m['depCity'])
                        ->code($m['depCode'])
                        ->date(strtotime($this->currentDate . ' ' . $m['depTime']));

                    $depTerminal = $this->re("/.*(?:{$this->opt($this->t('Terminal'))}\s*(?<depTerminal>.+))\n.*$/", $depInfo);

                    if (!empty($depTerminal)) {
                        $s->departure()
                            ->terminal($depTerminal);
                    }

                    $s->arrival()
                        ->name($m['arrCity'])
                        ->code($m['arrCode'])
                        ->date(strtotime($this->currentDate . ' ' . $m['arrTime']));

                    $arrTerminal = $this->re("/\n.*{$this->opt($this->t('Terminal'))}(?<arrTerminal>.+)$/", $depInfo);

                    if (!empty($arrTerminal)) {
                        $s->arrival()
                            ->terminal($arrTerminal);
                    }
                }
            }

            // Seats
            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seats = $this->http->FindNodes("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('Passengers'))}] ]/descendant::*[ count(node()[normalize-space()])=2 and node()[normalize-space()][1][{$this->eq($this->t('Seats'))}] ]/node()[normalize-space()][2]/descendant::text()[{$this->starts($s->getDepCode())} and {$this->contains($s->getArrCode())}]/following::text()[normalize-space()][1][not(contains(.,'-'))]");

                if (count($seats) === 0) {
                    $seats = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1]/descendant::text()[{$this->starts($s->getDepCode())} and {$this->contains($s->getArrCode())}]/following::text()[normalize-space()][1][not(contains(.,'-'))]");
                }

                if (count($seats) > 0) {
                    foreach ($seats as $seat) {
                        $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[1]/descendant::text()[{$this->contains($seat)}]/ancestor::table[2]/descendant::text()[normalize-space()][1][{$this->contains($this->travellers)}]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/");

                        if (!empty($pax)) {
                            $s->addSeat($seat, false, false, $pax);
                        } else {
                            $s->addSeat($seat);
                        }
                    }
                }
            }

            if (!empty($s->getAircraft()) && strcasecmp($s->getAircraft(), 'Train') === 0
                && (!empty($s->getDepName()) && strcasecmp($s->getDepName(), 'Rail&Fly') === 0 | !empty($s->getArrName()) && strcasecmp($s->getArrName(), 'Rail&Fly') === 0)
            ) {
                // aeroplan/it-670817261-fr.eml
                $f->removeSegment($s, 'Not flight segment!');
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Air Canada') !== false
            || stripos($from, '@aircanada.ca') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['subject'], 'Air Canada') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".aircanada.com/") or contains(@href,"www.aircanada.com") or contains(@href,"book.aircanada.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing GoToGate") or contains(.,"@gotogate.com")]')->length === 0
        ) {
            return false;
        }

        $body = $this->http->Response['body'];

        return $this->assignLang($body);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->http->FilterHTML = false;
        $body = $this->http->Response['body'];
        $this->http->SetEmailBody(str_replace(" ", " ", $body)); // bad fr char " :"

        $this->assignLang($body);

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHtml($email);

        $email->setType('ReservationConfirmationPdfNone' . $this->segmentType . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function getTotal($text, $currency = null)
    {
        $result = ['amount' => null, 'currency' => null];
        $text = trim($text, '*');
        // $260 84  ->  $260.84    |    $260. 84  ->  $260.84    |    $260 .84  ->  $260.84
        $text = preg_replace('/(\d) ?[ .] ?(\d{1,2})\s*$/', '$1.$2', $text);

        if (preg_match("#^\s*(?<currency>[^\d\s]\D{0,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]\D{0,5})\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->normalizeCurrency($m['currency'] ?? null);
            $m['amount'] = PriceHelper::parse($m['amount'], $m['currency'] ?? $currency);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function assignLang($body): bool
    {
        foreach ($this->reBody2 as $lang => $re) {
            foreach ((array) $re as $item) {
                if (stripos($body, $item) !== false
                    || $this->http->XPath->query("//*[{$this->contains($item)}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function nextText($field, $root = null, $n = 1): ?string
    {
        return $this->http->FindSingleNode("(.//text()[{$this->eq($field)}])[{$n}]/following::text()[normalize-space(.)][1]", $root);
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
        //$this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            '/.*?(\d{1,2})\s+([^\d\W]{3,})\.?[, ]+(\d{2,4}).*/su', // 11 nov., 2017
            '/^.*?(\d+) (\d+)月, (\d{4})/su', // 28 10月, 2019
            '/^(\d+)\s*(\w+)\s*(\d{4})/u', // 12 November 2023
        ];
        $out = [
            '$1 $2 $3',
            '$3-$2-$1',
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function normalizeCurrency($string): ?string
    {
        $string = trim($string, '+-');
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€', 'Euro', 'EU €'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
            'CAD' => ['CA $', 'Canadian dollars', 'CA $', 'Dollars canadiens'],
            'JPY' => ['円(日本)', 'JP ¥'],
            'USD' => ['US $', 'US dollars'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        if (preg_match("/\(([A-Z]{3})\)/", $string, $m)) {
            return $m[1];
        }

        if ($string === '(Canadian dollars)') {
            return 'CAD';
        }

        return null;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
