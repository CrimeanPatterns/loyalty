<?php

namespace AwardWallet\Engine\amextravel\Email;

// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers mileageplus/CarHotel (in favor of amextravel/Reservation)

class Reservation extends \TAccountCheckerExtended
{
    public $mailFiles = "amextravel/it-11559812.eml, amextravel/it-11565934.eml, amextravel/it-11571911.eml, amextravel/it-116798418.eml, amextravel/it-125335866.eml, amextravel/it-125978845.eml, amextravel/it-126722047.eml, amextravel/it-127551498.eml, amextravel/it-132238389.eml, amextravel/it-134055432.eml, amextravel/it-134362571.eml, amextravel/it-136421613.eml, amextravel/it-1687807.eml, amextravel/it-1691451.eml, amextravel/it-1694545.eml, amextravel/it-1694609.eml, amextravel/it-1732740.eml, amextravel/it-1830763.eml, amextravel/it-1832581.eml, amextravel/it-2073944.eml, amextravel/it-2366032.eml, amextravel/it-2439628.eml, amextravel/it-451266763.eml, amextravel/it-451282963.eml, amextravel/it-453620282.eml, amextravel/it-6674549.eml, amextravel/it-6710074.eml";
    public static $dictionary = [
        "en" => [
            //            "Booking Number" => "",
            "Your Itinerary"                 => ["Your Itinerary", "YOUR ITINERARY"],
            "Lead Traveller"                 => ["Lead Traveller", "Lead Traveler", 'LEAD TRAVELER'],
            "Main Contact Information"       => ["Agent", "Main Contact Information"],
            "Payments Received"              => ["Payments Received", "Direct air total", "PAYMENT RECEIVED", 'Direct hotel total', 'Balance Due', 'Direct Hotel Total'],
            "Taxes and Carrier Imposed Fees" => ["Taxes and Carrier Imposed Fees", "Tax", 'Sales Tax'],
            "feesNames"                      => ["GOODS AND SERVICES TAX GST", "PASSENGER SERVICE CHARGE DOMES", "SAFETY AND SECURITY CHARGE DE"],
            "discount"                       => ["Acc Adj", "discount", 'Discount'],
            "Package Price"                  => ["Package Price", "Total Price", "Price"],

            // Flight
            "Flights" => ["Flights", "FLIGHT"],
            //            "Depart" => "",
            //            "Agency Confirmation" => "",
            "Operated By" => ["Operated By", "operated by"],
            //            "Plane:" => "",
            //            "Airline Confirmation" => "",
            //            "Arrive" => "",
            //            "Duration" => "",

            // Hotel
            "Confirmation" => ["Confirmation", "Confirmation:"],
            //            "Room type" => "",
            "Checkin"  => ["Checkin", "Check-In:", 'Check-in:'],
            "Checkout" => ["Checkout", "Check-Out:", 'Check-out:'],
            //            "Occupants:" => "",
            //            "Room description" => "",
            //            "Adult" => "",
            "Child" => ["Child", "Infant"],

            // Car
            // "Supplier Confirmation" => "",
            //            "Car type:" => "",
            //            "Pickup:" => "",
            //            "Pickup location:" => "",
            "Dropoff:"       => ["Dropoff:", "Drop-Off:"],
            //            "Drop off Location:" => "",
            "Rate includes:" => ["Rate includes:", "Rate Includes:"],
        ],
        "de" => [ // it-2366032.eml, it-2439628.eml
            "Booking Number" => ["Buchung Nummer", "Ihre Buchungsnummer:", "Buchungsnummer:"],
            "Your Itinerary" => ['Ihr Reiseplan', 'IHR REISEPLAN'],
            "Lead Traveller" => ["Reisender 1"],
            //            "Main Contact Information" => "",
            "Payments Received"              => ["Gesamtbetrag erhalten", 'Zahlung zum Zeitpunkt der Buchung:'],
            "Taxes and Carrier Imposed Fees" => ["Steuern"],
            "feesNames"                      => "Gebühren für Unterkunftsbuchungen",
            "discount"                       => "Reiseguthaben",
            "Package Price"                  => "Tarif / Rate",

            // Flight
            "Flights"              => ["Flugreise", "FLUGREISE"],
            "Depart"               => "Abflug",
            "Agency Confirmation"  => "Agency Confirmation",
            "Operated By"          => ["durchgeführt von", "Durchgeführt von"],
            "Plane:"               => "Fluggerät:",
            "Airline Confirmation" => "Airline Confirmation",
            "Arrive"               => "Ankunft",
            "Duration"             => "Flugdauer",

            // Hotel
            "Confirmation"       => "Bestätigung:",
            "Room type"          => "Zimmerkategorie/-typ:",
            "Checkin"            => "Anreise:",
            "Checkout"           => "Abreise:",
            "Occupants:"         => "Anzahl Reisende:",
            "Room description"   => "Room description",
            "Adult"              => "Erwachsene",
            "CheckInTime"        => "Check-in ist zwischen",
            'CheckOutTime'       => "Check-out ist um",
            'StartsCancellation' => "Stornierungen am oder nach",
            //            "Child" => "",

            // Car
            // "Supplier Confirmation" => "",
            "Car"               => "Mietwagen",
            "Car type:"         => "Fahrzeugtyp:",
            "Pickup:"           => ["Anmietung:", "Abholung"],
            "Pickup location:"  => "Adresse",
            "Voucher"           => "Voucher drucken",
            "Dropoff:"          => ["Rückgabe:", "Abgabe"],
            "Dropoff location:" => "Adresse",
            //            "Rate includes:" => "",
        ],
        "es" => [ // it-11571911.eml
            "Booking Number" => "Número de Confirmación",
            "Your Itinerary" => ['Resumen del viaje'],
            "Lead Traveller" => ["Viajero Principal"],
            //            "Main Contact Information" => "",
            "Payments Received"              => ["Saldo a pagar", 'Forma de Pago'],
            "Taxes and Carrier Imposed Fees" => ["Cargo por reservación* (IVA incluido)"],
            // "feesNames" => "",
            // "discount" => "",
            "Package Price"                  => "Tarifa del paquete",

            // Flight
            "Flights"              => ["Flights", 'Vuelo', 'FLIGHT'],
            "Depart"               => "Salida",
            "Agency Confirmation"  => "Confirmación de la agencia",
            "Operated By"          => 'Operado por:',
            "Plane:"               => "Equipo:",
            "Airline Confirmation" => "Confirmación de la aerolínea",
            "Arrive"               => "Llegada",
            "Duration"             => "Duración",

            // Hotel
            "Confirmation"     => ["Confirmación"],
            "Room type"        => "Tipo de Habitación",
            "Checkin"          => ["Entrada"],
            "Checkout"         => ["Salida"],
            "Occupants:"       => "Huéspedes:",
            "Room description" => "Descripción de la habitación",
            "Adult"            => "Adulto",
            "Child"            => "Niño",

            // Car
            "Supplier Confirmation" => "Confirmación Proveedores",
            "Car type:"             => ["Tipo de coche:", "Tipo de auto:"],
            "Pickup:"               => ["Fecha de recogida:", "Fecha de retiro:"],
            "Pickup location:"      => ["Lugar de recogida:", "Aeropuerto de retiro"],
            "Dropoff:"              => ["Fecha de entrega:"],
            //                        "Rate includes:" => [""],
        ],
        "it" => [ // it-116798418.eml
            "Booking Number" => "Numero di prenotazione",
            "Your Itinerary" => ["ITINERARIO COMPLETO", "Itinerario Completo"],
            "Lead Traveller" => ["Passeggero 1"],
            //            "Main Contact Information" => "",
            "Payments Received"              => ["Totale viaggio", "Saldo"],
            "Taxes and Carrier Imposed Fees" => ["Totale tasse"],
            "feesNames"                      => ["Booking fee", "Supplementi per il recupero dell'imposta"],
            "discount"                       => "Sconto applicato",
            "Package Price"                  => "Totale Servizi",

            // Flight
            "Flights"              => ["FLIGHT"],
            "Depart"               => "Partenza",
            "Agency Confirmation"  => "Numero di Conferma Agenzia",
            "Operated By"          => ["operato da"],
            "Plane:"               => "Aereomobile:",
            "Airline Confirmation" => "Numero di Conferma Compagnia Aerea",
            "Arrive"               => "Arrivo",
            "Duration"             => "Durata",

            // Hotel
            "Confirmation" => ["Conferma"],
            "Room type"    => "Soggiorno:",
            "Checkin"      => ["Checkin:"],
            "Checkout"     => ["Checkout:"],
            "Occupants:"   => "Persone:",
            //            "Room description" => "",
            "Adult" => "adult",
            //            "Child" => "",

            // Car
            // "Supplier Confirmation" => "",
            "Car type:"        => "Classe",
            "Pickup:"          => "Ritiro:",
            "Pickup location:" => "Località di ritiro:",
            "Dropoff:"         => ["Consegna:"],
            //            "Rate includes:" => [""],
        ],
        "nl" => [
            "Booking Number" => "Boekingsnummer",
            "Your Itinerary" => ["Uw reisschema", "UW REISSCHEMA"],
            "Lead Traveller" => ["Reiziger 1"],
            //            "Main Contact Information" => "",
            "Payments Received"              => ["Betalingen ontvangen", 'Totale Reissom'],
            "Taxes and Carrier Imposed Fees" => ["Belastingen", 'Belastingtoeslagen'],
            // "feesNames" => "",
            // "discount" => "",
            "Package Price"                  => "Totale prijs",

            // Flight
            "Flights"              => ["FLIGHT"],
            "Depart"               => "Vertrek",
            "Agency Confirmation"  => "Agency Confirmation",
            "Operated By"          => ["Uitgevoerd door"],
            "Plane:"               => "Toestel:",
            "Airline Confirmation" => "Airline Confirmation",
            "Arrive"               => "Aankomst",
            "Duration"             => "Duur",

            // Hotel
            // Hotel
            "Confirmation"     => ["Bevestiging:"],
            "Room type"        => "Kamertype:",
            "Checkin"          => ["Aankomst:"],
            "Checkout"         => ["Vertrek:"],
            "Occupants:"       => "Aantal personen:",
            "Room description" => "Kameromschrijving",
            "Adult"            => "Volwassene",
            //            "Child" => "",
            //
            // Car
            // "Supplier Confirmation" => "",
            //            "Car type:" => "",
            //            "Pickup:" => "",
            //            "Pickup location:" => "",
            //            "Dropoff:" => [""],
            //            "Rate includes:" => [""],
        ],
        "ja" => [
            "Booking Number" => "予約番号",
            "Your Itinerary" => '旅程',
            "Lead Traveller" => "旅行代表者様",
            //"Main Contact Information" => "",
            "Payments Received"              => "お支払い済",
            "Taxes and Carrier Imposed Fees" => "タックス リカバリー チャージおよびサービス料金",
            //"feesNames" => "",
            "discount" => "割引",
            //"Package Price"                  => "",

            // Flight
            "Flights"              => ["フライト"],
            "Depart"               => "出発",
            "Agency Confirmation"  => "予約ID",
            //"Operated By"          => [""],
            "Plane:"               => "機種:",
            "Airline Confirmation" => "航空会社予約番号",
            "Arrive"               => "到着",
            "Duration"             => "フライト時間",

            // Hotel
            "Confirmation"     => "予約番号:",
            "Room type"        => "部屋タイプ",
            "Checkin"          => "チェックイン:",
            "Checkout"         => "チェックアウト:",
            "Occupants:"       => "宿泊者数:",
            "Room description" => "部屋の詳細",
            "Adult"            => "大人",
            //"CheckInTime" => "",
            //'CheckOutTime' => "",
            //'StartsCancellation' => ""
            "cancellation" => "キャンセル規定",
            //            "Child" => "",

            // Car
            // "Supplier Confirmation" => "",
            //            "Car type:" => "",
            //            "Pickup:" => "",
            //            "Pickup location:" => "",
            //            "Dropoff:" => "",
            //            "Rate includes:" => "",
        ],
        "fr" => [
            "Booking Number" => 'Numéro de la réservation',
            "Your Itinerary" => 'Résumé de votre voyage',
            "Lead Traveller" => 'VOYAGEUR PRINCIPAL :',
            //            "Main Contact Information" => "",
            "Payments Received"              => 'Paiements reçus',
            "Taxes and Carrier Imposed Fees" => 'Taxes, frais et suppléments de transporteur',
            //"feesNames"                      => '',
            // "discount" => "",
            "Package Price" => 'Prix du forfait',

            // Flight
            "Flights"              => 'aériens',
            "Depart"               => 'Départ',
            "Agency Confirmation"  => 'Confirmation de l\'agence',
            "Operated By"          => 'fourni par',
            "Plane:"               => 'Appareil :',
            "Airline Confirmation" => 'Confirmation du transporteur',
            "Arrive"               => 'Arrivée',
            "Duration"             => 'Durée',

            // Hotel
            //"Confirmation"       => '',
            //"Room type"          => '',
            //"Checkin"            => '',
            //"Checkout"           => '',
            //"Occupants:"         => '',
            //"Room description"   => '',
            //"Adult"              => '',
            //"CheckInTime"        => '',
            //'CheckOutTime'       => '',
            //'StartsCancellation' => '',
            //            "Child" => "",

            // Car
            // "Supplier Confirmation" => "",
            //            "Car type:" => "",
            //            "Pickup:" => "",
            //            "Pickup location:" => "",
            //            "Dropoff:" => "",
            //            "Rate includes:" => "",
        ],
        "sv" => [
            "Booking Number" => 'Bokningsnummer',
            "Your Itinerary" => ['DIN TIDTABELL', 'Din tidtabell'],
            "Lead Traveller" => 'Huvudresenär',
            //            "Main Contact Information" => "",
            "Payments Received"              => ['Betalning mottagen', 'Hotellets totalpris'],
            "Taxes and Carrier Imposed Fees" => 'Avgifter för skatteåterbäring',
            //"feesNames"                      => '',
            // "discount" => "",
            "Package Price" => 'Pris',

            // Flight
            //            "Flights"              => '',
            //            "Depart"               => '',
            //            "Agency Confirmation"  => '',
            //            "Operated By"          => '',
            //            "Plane:"               => '',
            //            "Airline Confirmation" => '',
            //            "Arrive"               => '',
            //            "Duration"             => '',

            // Hotel
            "Confirmation"       => 'Ditt bokningsnummer är',
            "Room type"          => 'Rumskategori',
            "Checkin"            => 'Ankomst:',
            "Checkout"           => 'Checka ut:',
            "Occupants:"         => 'Antal personer',
            "Room description"   => 'Beskrivning',
            "Adult"              => ["vuxen", "vuxna"],
            //            "CheckInTime"        => '',
            //            'CheckOutTime'       => '',
            'StartsCancellation' => '',
            //            "Child" => "",

            // Car
            // "Supplier Confirmation" => "",
            //            "Car type:" => "",
            //            "Pickup:" => "",
            //            "Pickup location:" => "",
            //            "Dropoff:" => "",
            //            "Rate includes:" => "",
        ],
    ];

    private $detectFrom = ['americanexpress.com', 'aavacations@aa.com'];
    private $detectSubject = [
        'en' => 'Reservation', // +fr, de, nl // American Express Travel UK Reservation 6257244
        'it' => 'Viaggi Numero di Prenotazione',
    ];
    private $detectBody = [
        "en" => ['Your Itinerary', 'YOUR ITINERARY'],
        "de" => ['Ihr Reiseplan', 'IHR REISEPLAN', 'Preisinformationen'],
        "es" => ['Resumen del viaje'],
        "it" => ['ITINERARIO COMPLETO'],
        "nl" => ['Uw reisschema'],
        "ja" => ['旅程'],
        "fr" => ['Statut de votre réservation'],
        "sv" => ['DIN TIDTABELL', 'Din tidtabell'],
    ];

    private $lang = 'en';
    private $dateUSFormat = true;
    private $dateKnownFormat = false;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query('//tr[' . $this->starts($detectBody) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (!empty($this->http->FindSingleNode("(//text()[contains(., 'THIS IS A \"SAVED ITINERARY\" QUOTE')])[1]"))) {
            $email->setIsJunk(true);
        } else {
            $this->parseHtml($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->containsText($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		if (stripos($from, $this->detectFrom) !== false) {
        //			return true;
//        }
        if (strpos($headers['subject'], "American Express") !== false || strpos($headers['subject'], "American Airlines Vacations Reservation") !== false) {
            foreach ($this->detectSubject as $dSubject) {
                if (strpos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[' . $this->contains(['@travel.americanexpress.com', '@service.americanexpress.com', '@onlinetravel.americanexpress.com', 'American Express Travel Insurance offer', 'please call AAV at 800-321-2121', '@aa.com']) . ']')->length === 0;
        $condition2 = $this->http->XPath->query('//a[' . $this->contains(['//travel.americanexpress.', '//insurance.americanexpress.', 'aavacations.com', '.americanexpress.de', 'americanexpress.it', 'aavtascaavacations@aa.com'], '@href') . ']')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query('//tr[' . $this->starts($detectBody) . ']')->length > 0) {
                return true;
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

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parseHtml(Email $email): void
    {
        // Travel Agency
        $conf = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Booking Number")) . "])[1]", null, true, "#" . $this->preg_implode($this->t("Booking Number")) . "[: ]+(\d{5,})\b#");

        if (empty($conf) && $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Booking Number")) . "])[1]", null, true, "#" . $this->preg_implode($this->t("Booking Number")) . "\W*$#")) {
            $conf = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Booking Number")) . "])[1]/following::text()[normalize-space()][1]", null, true, "#^[: ]*(\d{5,})\s*$#");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Number'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking Number'))}\s*([A-Z\d]+)/");
        }
        $email->ota()
            ->confirmation($conf);

        if (preg_match_all('/\b(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*\d{2,4}\b/', $this->http->Response['body'], $dateMatches)) {
            $m = $dateMatches[1];
            $d = $dateMatches[2];
            rsort($m);
            rsort($d);

            if ($m[0] > 12 && $d[0] <= 12) {
                $this->dateUSFormat = false;
                $this->dateKnownFormat = true;
            } else {
                if ($d[0] > 12) {
                    $this->dateKnownFormat = true;
                } elseif ($d[0] <= 12 && count($d) > 2) {
                    if (count(array_unique(array_slice($dateMatches[2], 1))) == 1 && count(array_unique(array_slice($dateMatches[1], 1))) > count($d) / 2) {
                        $this->dateUSFormat = false;
                        $this->dateKnownFormat = true;
                    } elseif (count($d) > 6 && count(array_unique(array_slice($dateMatches[2], 1))) == 1 && count(array_unique(array_slice($dateMatches[1], 1))) >= 3) {
                        $this->dateUSFormat = false;
                        $this->dateKnownFormat = true;
                    }
                }
            }
        }

        $currency = [];
        $totalPrice = [];
        $totalPriceStr = [];

        foreach ((array) $this->t("Payments Received") as $totalTitle) {
            $value = $this->http->FindSingleNode("//td[{$this->starts($totalTitle)} and not(.//tr)]/following-sibling::td[normalize-space()][1]",
                null, true, '/^.*\d.*$/');

            if ($value !== null) {
                if (preg_match('/(.*\s+(?:points|Punkte|Punti|Puntos))\s+\+\s+(.*)/i', $value, $m)) {
                    $email->price()->spentAwards($m[1]);
                    $value = $m[2];
                } elseif (preg_match('/^\s*(\d[,. \d]*\s+(?:points|Punkte|Punti|Puntos))\s*$/i', $value, $m)) {
                    $email->price()->spentAwards($m[1]);

                    continue;
                }
                $totalPriceStr[] = $value;

                if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/', $value, $matches)
                    || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $value, $matches)
                ) {
                    $c = $this->currency($matches['currency']);
                    $currency[] = $c;
                    $totalPrice[] = $this->amount($matches['amount'], $c);
                }
            }
        }

        if (count(array_unique($currency)) == 1 && count($totalPrice) == count($totalPriceStr)) {
            $email->price()
                ->currency($currency[0])
                ->total(array_sum($totalPrice))
            ;

            foreach ((array) $this->t("Package Price") as $costTitle) {
                $cost = $this->http->FindSingleNode("//td[{$this->starts($costTitle)} and not(.//tr)]/following-sibling::td[normalize-space()][1]", null, true, '/^.*\d.*$/');

                if ($cost !== null) {
                    break;
                }
            }

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $cost, $m)
                || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $cost, $m)
            ) {
                $email->price()->cost($this->amount($m['amount'], $currency[0]));
            }

            $tax = $this->http->FindSingleNode("//td[{$this->starts($this->t("Taxes and Carrier Imposed Fees"))} and not(.//tr)]/following-sibling::td[normalize-space()][1]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $tax, $m)
                || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $tax, $m)
            ) {
                $email->price()->tax($this->amount($m['amount'], $currency[0]));
            }

            $feeRows = $this->http->XPath->query("//tr[ *[1][{$this->starts($this->t("feesNames"))}] and *[2][normalize-space()] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $feeCharge, $m)
                ) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $email->price()->fee($feeName, $this->amount($m['amount'], $currency[0]));
                }
            }

            $discount = $this->http->FindSingleNode("//td[{$this->starts($this->t("discount"))} and not(.//tr)]/following-sibling::td[normalize-space()][1]", null, true, '/^.*\d.*$/');

            if (preg_match('/^-?\s*(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $discount, $m)
                || preg_match('/^-?\s*(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $discount, $m)
            ) {
                // - € 150,00
                $email->price()->discount($this->amount($m['amount']));
            }
        }

        $travellerInfoLeftText = $this->htmlToText($this->http->FindHTMLByXpath("//tr/*[{$this->eq($this->t("Your Itinerary"))}]/preceding::tr[ *[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t("Lead Traveller"))}]]/following-sibling::*[{$this->starts($this->t("Main Contact Information"))} or normalize-space()=''] ]/*[normalize-space()][1]"));

        if (empty($travellerInfoLeftText)) {
            $travellerInfoLeftText = $this->htmlToText($this->http->FindHTMLByXpath("//tr/*[{$this->eq($this->t("Your Itinerary"))}]/preceding::tr[ *[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->starts("If the lead traveler's AADV number")}]]/following-sibling::*[{$this->starts($this->t("Main Contact Information"))} or normalize-space()=''] ]/*[normalize-space()][1]"));
        }

        $travellers = [];
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]'; // Mr. Hao-Li Huang

        if (preg_match("/^\s*{$this->opt($this->t("Lead Traveller"))}[ ]*\n+[ ]*({$patterns['travellerName']})(?:[ ]*\(|[ ]*\n|\s*$)/u", $travellerInfoLeftText, $m)) {
            $travellers[] = $m[1];
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t("Passengers"))}[ ]*\n+((?:[ ]*{$patterns['travellerName']}(?:[ ]*\(.*\).*)?[ ]*\n+)+)[ ]*{$this->opt($this->t("Agency"))}[ ]*\n/u", $travellerInfoLeftText, $m)) {
            $travellers = array_merge($travellers, array_map(function ($item) {
                return preg_replace("/^(.{2,}?)[ ]*\(.*\).*$/", '$1', $item);
            }, preg_split("/[ ]*\n+[ ]*/", trim($m[1]))));
        }

        $itsXPath = '//text()[' . $this->contains($this->t("Your Itinerary")) . ']/ancestor::tr[1]/following-sibling::tr[1]';

        $flightsXPath = $itsXPath . "//text()[" . $this->contains($this->t('Flights')) . "]/ancestor::tr[1]/following-sibling::tr[" . $this->contains($this->t('Depart')) . "]//tr[" . $this->contains($this->t('Depart')) . " and not(.//tr)]";
        // $this->logger->debug('FLIGHT XPath = ' . print_r($flightsXPath, true));
        $flights = $this->http->XPath->query($flightsXPath);

        if ($flights->length === 0) {
            $flightsXPath = $itsXPath . "//text()[" . $this->contains($this->t('Flights')) . "]/ancestor::tr[2]/following-sibling::tr[" . $this->contains($this->t('Depart')) . "]//tr[" . $this->contains($this->t('Depart')) . " and not(.//tr)]";
            $flights = $this->http->XPath->query($flightsXPath);
        }
        $this->parseFlights($email, $flights);

        $hotelsXPath = $itsXPath . "//text()[" . $this->contains($this->t('Room type')) . "]/ancestor::tr[4]";
//        $this->logger->debug('HOTEL XPath = ' . print_r($hotelsXPath, true));
        $hotels = $this->http->XPath->query($hotelsXPath);
        $this->parseHotels($email, $hotels, $travellers);

        $carsXPath = $itsXPath . "//text()[" . $this->contains($this->t('Car type:')) . "]/ancestor::tr[4]";
        // $this->logger->debug('CAR XPath = '.print_r( $carsXPath,true));
        $cars = $this->http->XPath->query($carsXPath);
        $this->parseCars($email, $cars, $travellers);
    }

    private function parseFlights(Email $email, $roots): void
    {
        if ($roots->length === 0) {
            return;
        }
        $f = $email->add()->flight();

        $passengers = [];

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $node = implode("\n", $this->http->FindNodes("./td[2]//text()[normalize-space()]", $root));
            $regexp = "#(?<alName>.+?)\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})(?:\s+\*?" . $this->preg_implode($this->t("Operated By")) . "\s+(?<oper>.+?))?\s*\n\s*(?<cabin>.+?)\s*\((?<class>[A-Z]{1,2}\s*\*?)\b.*?\)\s*(?:\|\s*" . $this->preg_implode($this->t("Plane:")) . "\s*(?<aircraft>.+))?#";
            $regexp2 = "#(?<alName>.+?)\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})(?:\s+\*?" . $this->preg_implode($this->t("Operated By")) . "\s+(?<oper>.+?))?\s*\n\s*(?<cabin>[^:\|]+)$#";

            if (preg_match($regexp, $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                if (!empty($m['oper'])) {
                    $s->airline()->operator($m['oper']);
                }

                $conf = $this->http->FindSingleNode("./ancestor::table[3]//text()[" . $this->starts($this->t("Airline Confirmation")) . "]/following::text()[normalize-space()][1]", $root, true, "#^\s*{$m['alName']}\s*\s+-\s+([\w\-]+)\b#");

                if (!empty($conf)) {
                    $s->airline()->confirmation($conf);
                }
                $s->extra()
                    ->cabin(trim($m['cabin']))
                    ->bookingCode($m['class'])
                    ->aircraft(trim($m['aircraft'] ?? null), true, true)
                ;
            } elseif (preg_match($regexp2, $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                if (!empty($m['oper'])) {
                    $s->airline()->operator($m['oper']);
                }

                $conf = $this->http->FindSingleNode("./ancestor::table[3]//text()[" . $this->starts($this->t("Airline Confirmation")) . "]/following::text()[normalize-space()][1]", $root, true, "#^\s*{$m['alName']}\s*\s+-\s+([\w\-]+)\b#");

                if (!empty($conf)) {
                    $s->airline()->confirmation($conf);
                }
                $s->extra()
                    ->cabin(trim($m['cabin']))
                ;
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./td[3]", $root, null, "#.+\s*-\s*\(([A-Z]{3})\)#"))
                ->name(trim($this->http->FindSingleNode("./td[3]", $root, null, "#(.+)\s*-\s*\([A-Z]{3}\)#")))
            ;

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./following-sibling::tr[1]/td[" . $this->contains($this->t("Arrive")) . "]/preceding-sibling::td[normalize-space()][1]", $root, null, "#.+\s*-\s*\(([A-Z]{3})\)#"))
                ->name(trim($this->http->FindSingleNode("./following-sibling::tr[1]/td[" . $this->contains($this->t("Arrive")) . "]/preceding-sibling::td[normalize-space()][1]", $root, null, "#(.+)\s*-\s*\([A-Z]{3}\)#")))
            ;

            $dDate = $this->http->FindSingleNode("./td[5]", $root);
            $aDate = $this->http->FindSingleNode("./following-sibling::tr[1]/td[" . $this->contains($this->t("Arrive")) . "]/following-sibling::td[normalize-space()][1]", $root, true, "#^(.+?)(?:\s*\(|$)#");

            if ($this->dateKnownFormat !== true && !empty($dDate) && !empty($aDate)) {
                $d1 = $this->normalizeDate($dDate);
                $d2 = $this->normalizeDate($dDate, !$this->dateUSFormat);
                $a1 = $this->normalizeDate($aDate);
                $a2 = $this->normalizeDate($aDate, !$this->dateUSFormat);

                if (abs($a1 - $d1) > 60 * 60 * 24 * 3 && abs($a2 - $d2) < 60 * 60 * 24 * 3) {
                    $this->dateUSFormat = !$this->dateUSFormat;
                    $this->dateKnownFormat = true;
                } elseif (abs($a1 - $d1) < 60 * 60 * 24 * 3 && abs($a2 - $d2) > 60 * 60 * 24 * 3) {
                    $this->dateKnownFormat = true;
                }
            }
            $s->departure()
                ->date($this->normalizeDate($dDate));
            $s->arrival()
                ->date($this->normalizeDate($aDate));

            // Extra
            $s->extra()->duration($this->http->FindSingleNode("./following-sibling::tr[2]/td[" . $this->contains($this->t("Duration")) . "]/following-sibling::td[normalize-space()][1]", $root), true, true);

            $row = array_unique($this->http->FindNodes('ancestor::table[position()<4 and (' . $this->starts($this->t('Flights')) . ')][1]//tr[(' . $this->contains($this->t('Adult')) . ' or ' . $this->contains($this->t('Child')) . ') and not(.//tr)]', $root));
            $seatsAll = [];

            foreach ($row as $key => $value) {
                if (preg_match("#.+\(.+\)\s+(.+)#", $value, $m) && preg_match_all("#\b(\d{1,3}[A-Z])\b#", $m[1], $mat)) {
                    $seatsAll[] = $mat[1];
                }
            }
            $seats = [];

            if (!empty($seatsAll)) {
                foreach ($seatsAll[0] as $i => $value) {
                    $seats[] = array_column($seatsAll, $i);
                }
            }

            if (!empty($seats) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                    && count($seats) == count($this->http->FindNodes('ancestor::table[position()<4 and (' . $this->starts($this->t('Flights')) . ')][1]//text()[' . $this->contains($this->t('Depart')) . ']', $root))) {
                $fls = $this->http->FindNodes('ancestor::table[position()<4 and (' . $this->starts($this->t('Flights')) . ')][1]//text()[' . $this->contains($this->t('Depart')) . ']/ancestor::tr[1]', $root);

                foreach ($fls as $key => $value) {
                    if (strpos($value, $s->getAirlineName() . $s->getFlightNumber()) !== false) {
                        $s->extra()->seats($seats[$key]);
                    }
                }
            }

            $conf = $this->http->FindSingleNode("./ancestor::table[3]//text()[" . $this->starts($this->t("Agency Confirmation")) . "]/following::text()[normalize-space()][1]", $root, true, "#^\s*([\w\- ]+)\s*\W*$#");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("./ancestor::table[3]//text()[" . $this->starts($this->t("Confirmation")) . "]/following::text()[normalize-space()][1]", $root, true, "#^\s*([\w\- ]+)\s*\W*$#");
            }

            $confirmaions[] = $conf;

            $pass = $this->http->FindNodes("./ancestor::table[1]//*[contains(@class, 'traveler_name') or contains(@class, 'travelername')]", $root, "#(.+?)\s*(?:\(.+\))#");

            if (empty($pass)) {
                $title = ['Adult', 'Erwachsener', 'Child', 'Infant', 'Infant in lap', 'Adulto'];
                $pass = array_filter(array_unique($this->http->FindNodes('.//tr[(' . $this->contains($title) . ') and not(.//tr)]', $root, '#(.+)\s+\((?:' . $this->preg_implode($title) . ').*#i')));
            }
            $passengers = array_merge($passengers, $pass);
        }

        $confirmaions = array_unique(array_filter($confirmaions));

        foreach ($confirmaions as $conf) {
            $f->general()
                ->confirmation($conf)
            ;
        }

        if (empty($passengers)) {
            $title = ['Adult', 'Erwachsener', 'Child', 'Infant', 'Infant in lap'];
            $passengers = array_filter(array_unique($this->http->FindNodes('//text()[' . $this->contains($this->t("Your Itinerary")) . ']/following::tr[(' . $this->contains($title) . ') and not(.//tr)]', null, '#(.+)\s+\((?:' . $this->preg_implode($title) . ').*#i')));
        }
        $f->general()
            ->travellers(array_unique(array_filter($passengers)));
    }

    private function parseHotels(Email $email, \DOMNodeList $roots, array $totalTravellers): void
    {
        if (count($totalTravellers) == 0) {
            $totalTravellers[] = $this->http->FindSingleNode("//text()[normalize-space()='Lead Traveller']/following::text()[normalize-space()][1]");
        }

        foreach ($roots as $root) {
            $h = $email->add()->hotel();

            // General
            $conf = str_replace(' ', '', $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Confirmation")) . "]/following::text()[normalize-space()][1]", $root, true, "#^[: ]*([\w\- \|]+)\s*$#"));

            if (empty($conf) && empty($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Confirmation")) . "]", $root))) {
                $h->general()->noConfirmation();
            } else {
                if (strpos($conf, '|') !== false) {
                    $conf = explode('|', $conf);
                } else {
                    $conf = [$conf];
                }

                foreach ($conf as $value) {
                    $h->general()->confirmation($value);
                }
            }

            $GuestNames = [];
            $nodesG = $this->http->XPath->query(".//*[contains(@class,'traveler_first_name') or contains(@class,'travelerfirstname')]", $root);

            foreach ($nodesG as $rootG) {
                if ($this->lang == 'ja') {
                    $GuestNames[] = $this->http->FindSingleNode("./ancestor::td[1]", $rootG);
                } else {
                    $GuestNames[] = $rootG->nodeValue . ' ' . $this->http->FindSingleNode("./following::*[position()<5][contains(@class, 'traveler_last_name') or contains(@class, 'travelerlastname')]", $rootG);
                }
            }

            if (empty($GuestNames)) {
                $GuestNames = $totalTravellers;
            }
            $h->general()
                ->travellers($GuestNames);

            $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('StartsCancellation'))}]");

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Room type")) . "]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][last()]", $root))
            ;
            $address = implode("\n", $this->http->FindNodes(".//text()[" . $this->contains($this->t("Room type")) . "]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][last()-1]//text()", $root));

            if (preg_match("#([\s\S]+)\n([\d \-+\(\)]+)\s*$#", $address, $m)) {
                $h->hotel()
                    ->address(str_replace("\n", ' ', trim($m[1])))
                    ->phone(trim($m[2]))
                ;
            } else {
                $h->hotel()->address(str_replace("\n", ' ', $address));
            }

            // Booked
            $chechIn = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Checkin")) . "]/following::text()[normalize-space()][1]", $root);

            if (empty($chechIn)) {
                $chechIn = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Checkout")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]/td[normalize-space()][last()]", $root);
            }

            $h->booked()
                ->checkIn($this->normalizeDate($chechIn))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("(.//text()[" . $this->contains($this->t("Checkout")) . "])[1]/following::text()[normalize-space()][1]", $root)))
                ->guests($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Occupants:")) . "]/following::text()[normalize-space()][1]", $root, true,
                        "#\b(\d{1,3})\s*" . $this->preg_implode($this->t("Adult")) . "#i"))
                ->kids($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Occupants:")) . "]/following::text()[normalize-space()][1]", $root, true,
                        "#\b(\d{1,3})\s*" . $this->preg_implode($this->t("Child")) . "#i"), true, true)
            ;

            $checkInTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CheckInTime'))}]", null, true, "/{$this->opt($this->t('CheckInTime'))}\s*([\d\:]+)/");

            if (!empty($checkInTime)) {
                $h->booked()
                    ->checkIn(strtotime($checkInTime, $h->getCheckInDate()));
            }

            $checkOutTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('CheckOutTime'))}]", null, true, "/{$this->opt($this->t('CheckOutTime'))}\s*([\d\:]+)/");

            if (!empty($checkOutTime)) {
                $h->booked()
                    ->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()));
            }

            // Rooms
            $h->addRoom()
                ->setType($this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Room type")) . "]/following::text()[normalize-space()][1]", $root))
                ->setDescription($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Room description")) . "]/following::text()[normalize-space()][1]", $root), true, true)
            ;
        }
    }

    private function parseCars(Email $email, \DOMNodeList $roots, array $totalTravellers): void
    {
        if (count($totalTravellers) == 0) {
            $totalTravellers[] = $this->http->FindSingleNode("//text()[normalize-space()='Lead Traveller']/following::text()[normalize-space()][1]");
        }

        foreach ($roots as $root) {
            $r = $email->add()->rental();

            // General
            $conf = str_replace(' ', '', $this->http->FindSingleNode("(.//text()[" . $this->starts($this->t("Confirmation")) . "])[1]/following::text()[normalize-space()][1]", $root, true, "#^[: ]*([\w\- ]+)\s*$#"));
            $r->general()
                ->confirmation($conf);
            $conf2 = str_replace(' ', '', $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Supplier Confirmation")) . "]/following::text()[normalize-space()][1]", $root, true, "#^[: ]*([\w\- ]+)\s*$#"));

            if (!empty($conf2)) {
                $r->general()
                    ->confirmation($conf2, $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Supplier Confirmation")) . "]",
                        $root, true, "#^\s*(.+?)[:\s]*$#"));
            }
            $r->general()->travellers($totalTravellers, true);

            // Pick Up
            $r->pickup()
                ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Pickup:")) . "]/following::text()[normalize-space()][1]", $root)));

            $pickUpLocation = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Pickup location:")) . "]/following::text()[normalize-space()][1]/ancestor-or-self::*[not(" . $this->contains($this->t("Pickup location:")) . ")][1]", $root);

            if (!empty($pickUpLocation)) {
                $r->pickup()
                    ->location($pickUpLocation);
            } else {
                $link = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Car'))}]/following::text()[{$this->eq($this->t('Voucher'))}][1]/ancestor::a[1]/@href");

                if (!empty($link)) {
                    $http2 = clone $this->http;
                    $http2->GetURL($link);

                    $pickUpLocation = $http2->FindSingleNode("//text()[{$this->starts($this->t('Pickup:'))}]/following::text()[{$this->contains($this->t('Pickup location:'))}][1]/following::text()[normalize-space()][1]");

                    if (!empty($pickUpLocation)) {
                        $r->pickup()
                            ->location($pickUpLocation);
                    }

                    $dropOffLocation = $http2->FindSingleNode("//text()[{$this->starts($this->t('Dropoff:'))}]/following::text()[{$this->contains($this->t('Dropoff location:'))}][1]/following::text()[normalize-space()][1]");

                    if (!empty($dropOffLocation)) {
                        $r->dropoff()
                            ->location($dropOffLocation);
                    }
                }
            }

            // Dropp Off
            $r->dropoff()
                ->date($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Dropoff:")) . "]/following::text()[normalize-space()][1]", $root)));

            if (empty($r->getDropOffLocation())) {
                $dropOffLocation = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Drop off Location:")) . "]/following::text()[normalize-space()][1]", $root);

                if (!empty($dropOffLocation)) {
                    $r->dropoff()
                        ->location($dropOffLocation);
                }
            }

            if (empty($r->getDropOffLocation())) {
                $r->dropoff()
                    ->noLocation();
            }

            // Car
            $r->car()
                ->model($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Car type:")) . "]/following::text()[normalize-space()][1]", $root))
                ->type($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Rate includes:")) . "]/following::text()[normalize-space()][1]", $root), true, true);

            // Extra
            $r->extra()
                ->company($this->http->FindSingleNode(".//text()[" . $this->contains($this->t("Car type:")) . "]/preceding::text()[normalize-space()][1]", $root));
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($str, $dateFormat = null)
    {
        $in = [
            //Tuesday 06/08/19 06:54
            // Friday06/16/239:54AM
            "#^\s*[^\d\s]+\s*(\d{1,2})/(\d{2})/(\d{2})\s*(\d{1,2}:\d+(?:\s*[ap]m)?)\s*$#i",
            "#^\s*[^\d\s]+\s+(\d{1,2})/(\d{2})/(\d{2})\s*$#", //Tuesday 06/08/19
            "#^\D+(\d{4})\/(\d+)\/(\d+)$#",
            "#^\D+(\d{4})\/(\d+)\/(\d+)\s+(\d{1,2}:\d+(?:\s*[ap]m)?)\s*$#",
        ];

        if ($dateFormat !== null) {
            $format = $dateFormat;
        } else {
            $format = $this->dateUSFormat;
        }

        if ($format == true) {
            $out = [
                "$2.$1.20$3 $4",
                "$2.$1.20$3",
                "$3.$2.$1",
                "$3.$2.$1, $4",
            ];
        } else {
            $out = [
                "$1.$2.20$3 $4",
                "$1.$2.20$3",
                "$3.$2.$1",
                "$3.$2.$1, $4",
            ];
        }

        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
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

    private function amount($price, $currency = null)
    {
        $price = trim($price);
        $price = PriceHelper::parse($price, $currency);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'  => 'EUR',
            'US$'=> 'USD',
            '£'  => 'GBP',
            '￥'  => 'JPY',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        if ($s === '$'
            && stripos($this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('TOTAL AMOUNT'))}]", null, true, "/^{$this->preg_implode($this->t('TOTAL AMOUNT'))}\s*\(\s*in\s+(.+?)\)$/i"), 'Australian Dollars') === 0
        ) {
            return 'AUD';
        }

        if ($this->http->FindSingleNode("//text()[{$this->contains(['All prices are quoted in US dollars', 'All prices are in U.S. dollars'])}]")) {
            return 'USD';
        }

        if ($this->http->FindSingleNode("//text()[{$this->contains('TOTAL AMOUNT (IN SINGAPORE DOLLARS)')}]")) {
            return 'SGD';
        }

        return $s;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
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
