<?php

namespace AwardWallet\Engine\concur\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountCheckerExtended
{
    public $mailFiles = "concur/it-10888104.eml, concur/it-1678470.eml, concur/it-1687579.eml, concur/it-1687852.eml, concur/it-1823323.eml, concur/it-208459250.eml, concur/it-276516644.eml, concur/it-286687788.eml, concur/it-2930583.eml, concur/it-33901379.eml";
    public $lang = '';
    public static $dict = [
        'en' => [
            //            // Common
            //            "Created" => "",
            //            "Agency Record Locator" => "",
            //            "Passengers" => "",
            "Reservation for" => ["Reservation for:", "Passengers:"],
            //            "Total Estimated Cost" => "",
            //            "Reservations" => "",
            //            "Status" => "",
            //            "Confirmation" => "",
            //            "Frequent Guest Number" => "",
            //
            //            // Flight
            //            "Flight" => "", // +train
            //            "to" => "", // +train
            //            "Operated by" => "",
            "Departure" => ['Departure', 'Departs'], // +train
            //            "Seat" => "", // +train
            //            "Terminal" => "",
            //            "Duration" => "",
            //            "stop" => "",
            //            "Arrival" => "", // +train
            //            "Air Frequent Flyer Number" => "",
            //            "Aircraft" => "",
            //            "Distance" => "",
            //            "Cabin" => "",
            //            "Meal" => "",
            'Air Total Price'       => ['Air Total Price', 'Total Estimated Cost'],
            'Airfare quoted amount' => ['Airfare quoted amount', 'Airfare amount'],
            "Taxes and fees"        => ["Taxes and fees:", "Agency service fee:"],
            // Train,  + see in Flight
            //            'Train Frequent Traveler Number' => '',
            //            'Class' => '',

            //
            //            // Hotel
            //            "Checking In" => "",
            //            "Room" => "",
            //            "Guests" => "",
            //            "Checking Out" => "",
            //            "Daily rate" => "",
            //            "Room Description" => "",
            //            "Cancellation Policy" => "",
            //            "Hotel:" => "",
            //
            //            // Car
            //            "Car Rental at" => "",
            //            "at" => "",
            //            "Pick-up at" => "",
            //            "Pick Up" => "",
            //            "Return" => "",
            //            "Returning to" => "",
            //            "Corporate Discount" => "",
            //            "Car:" => "",
        ],
        'es' => [
            //            // Common
            "Created" => "Creado",
            //            "Agency Record Locator" => "",
            "Passengers"           => "Pasajeros",
            "Reservation for"      => "Reservación de",
            "Total Estimated Cost" => "Costo total aproximado",
            "Reservations"         => "Reservaciones",
            "Status"               => "Estado",
            "Confirmation"         => "Confirmación",
            //            "Frequent Guest Number" => "",
            //
            //            // Flight
            "Flight"      => "Vuelo", // +train
            "to"          => "hasta", // +train
            "Operated by" => "Operado por",
            "Departure"   => "Partida", // +train
            "Seat"        => "Asiento", // +train
            //            "Terminal" => "",
            "Duration" => "Duración",
            //            "stop" => "",
            "Arrival"                   => "Llegada", // +train
            "Air Frequent Flyer Number" => "Número de viajero frecuente",
            "Aircraft"                  => "Avión",
            "Distance"                  => "Distancia",
            "Cabin"                     => "Cabina",
            "Meal"                      => "Comida",
            //            "Air Total Price" => "",
            "Airfare quoted amount" => "Monto de la tarifa aérea indicada",
            "Taxes and fees"        => "Impuestos y honorarios",
            // Train,  + see in Flight
            //            'Train Frequent Traveler Number' => '',
            //            'Class' => '',

            //
            //            // Hotel
            "Checking In"         => "Registro",
            "Room"                => "Habitación",
            "Guests"              => "Huéspedes",
            "Checking Out"        => "Salida",
            "Daily rate"          => "Tarifa diaria",
            "Room Description"    => "Descripción de la habitación",
            "Cancellation Policy" => "Política de cancelación",
            //            "Hotel:" => "",
            //
            //            // Car
            //            "Car Rental at" => "",
            //            "at" => "",
            //            "Pick-up at" => "",
            //            "Pick Up" => "",
            //            "Return" => "",
            //            "Returning to" => "",
            //            "Corporate Discount" => "",
            //            "Car:" => "",
        ],
        'de' => [
            //            // Common
            "Created"               => "Erstellt",
            "Agency Record Locator" => "Agenturbuchungscode",
            //            "Passengers" => "",
            "Reservation for"       => "Reservierung für",
            "Total Estimated Cost"  => "Geschätzte Gesamtkosten",
            "Reservations"          => "Reservierungen",
            "Status"                => "Status",
            "Confirmation"          => "Bestätigung",
            "Frequent Guest Number" => "Nummer des Programms für Vielreisende",
            //
            //            // Flight
            //            "Flight" => "", // +train
            //            "to" => "", // +train
            //            "Operated by" => "",
            //            "Departure" => "", // +train
            //            "Seat" => "", // +train
            //            "Terminal" => "",
            //            "Duration" => "",
            //            "stop" => "",
            //            "Arrival" => "", // +train
            //            "Air Frequent Flyer Number" => "",
            //            "Aircraft" => "",
            //            "Distance" => "",
            //            "Cabin" => "",
            //            "Meal" => "",
            //            "Air Total Price" => "",
            //            "Airfare quoted amount" => "",
            //            "Taxes and fees" => "",
            // Train,  + see in Flight
            //            'Train Frequent Traveler Number' => '',
            //            'Class' => '',

            //
            //            // Hotel
            "Checking In"         => "Check-In",
            "Room"                => "Zimmer",
            "Guests"              => "Gäste",
            "Checking Out"        => "Check-Out",
            "Daily rate"          => "Preis/Tag",
            "Room Description"    => "Raumbeschreibung",
            "Cancellation Policy" => "Stornierungsrichtlinien",
            "Hotel:"              => "Hotel:",
            //
            //            // Car
            //            "Car Rental at" => "",
            //            "at" => "",
            //            "Pick-up at" => "",
            //            "Pick Up" => "",
            //            "Return" => "",
            //            "Returning to" => "",
            //            "Corporate Discount" => "",
            //            "Car:" => "",
        ],
        'it' => [
            //            // Common
            "Created"               => "Creato",
            "Agency Record Locator" => "Ubicatore di dati agenzia",
            "Passengers"            => "Passeggeri",
            //"Reservation for"      => "",
            "Total Estimated Cost" => "Costo totale stimato",
            "Reservations"         => "Prenotazioni",
            "Status"               => "Stato",
            "Confirmation"         => "Conferma",
            //            "Frequent Guest Number" => "",
            //
            //            // Flight
            "Flight"      => "Volo", // +train
            "to"          => "a", // +train
            //"Operated by" => "",
            "Departure"   => "Partenza", // +train
            "Seat"        => "Posto a sedere", // +train
            //            "Terminal" => "",
            "Duration" => "Durata",
            //            "stop" => "",
            "Arrival"                   => "Arrivo", // +train
            //"Air Frequent Flyer Number" => "",
            //"Aircraft"                  => "",
            "Distance"                  => "Distanza",
            "Cabin"                     => "Cabina",
            //"Meal"                      => "",
            "Air Total Price" => "Voli Prezzo totale",
            //"Airfare quoted amount" => "",
            //"Taxes and fees"        => "",
            // Train,  + see in Flight
            //            'Train Frequent Traveler Number' => '',
            //            'Class' => '',
            //
            //            // Hotel
            "Checking In"         => "Check-in",
            "Room"                => "Camera",
            "Guests"              => "Ospiti",
            "Checking Out"        => "Check-out",
            "Daily rate"          => "Tariffa giornaliera",
            "Room Description"    => "Descrizione della stanza",
            "Cancellation Policy" => "Politica di cancellazione",
            'Start Date'          => 'Data di inizio',
            //            "Hotel:" => "",
            //
            //            // Car
            //            "Car Rental at" => "",
            //            "at" => "",
            //            "Pick-up at" => "",
            //            "Pick Up" => "",
            //            "Return" => "",
            //            "Returning to" => "",
            //            "Corporate Discount" => "",
            //            "Car:" => "",
        ],
        'fr' => [
            //            // Common
            "Created"               => "Créé",
            "Agency Record Locator" => "Numéro de dossier de l'agence",
            "Passengers"            => "Passagers",
            //"Reservation for"      => "",
            "Total Estimated Cost" => "Coût total estimé",
            "Reservations"         => "Réservations",
            "Status"               => "Statut",
            "Confirmation"         => "Confirmation",
            //            "Frequent Guest Number" => "",
            //
            //            // Flight
            //            "Flight"      => "Volo", // +train
            "to"          => "à", // +train
            //"Operated by" => "",
            "Departure"   => "Départ", // +train
            "Seat"        => "Siège", // +train
            //            "Terminal" => "",
            "Duration" => "Durée",
            //            "stop" => "",
            "Arrival"                   => "Arrivée", // +train
            //"Air Frequent Flyer Number" => "",
            //"Aircraft"                  => "",
            //            "Distance"                  => "Distanza",
            //            "Cabin"                     => "Cabina",
            //"Meal"                      => "",
            //            "Air Total Price" => "Voli Prezzo totale",
            //"Airfare quoted amount" => "",
            //"Taxes and fees"        => "",

            // Train,  + see in Flight
            //            'Train Frequent Traveler Number' => '',
            'Class' => 'Classe',

            //            // Hotel
            //            "Checking In"         => "Check-in",
            //            "Room"                => "Camera",
            //            "Guests"              => "Ospiti",
            //            "Checking Out"        => "Check-out",
            //            "Daily rate"          => "Tariffa giornaliera",
            //            "Room Description"    => "Descrizione della stanza",
            //            "Cancellation Policy" => "Politica di cancellazione",
            //            'Start Date'          => 'Data di inizio',
            //            "Hotel:" => "",
            //
            //            // Car
            //            "Car Rental at" => "",
            //            "at" => "",
            //            "Pick-up at" => "",
            //            "Pick Up" => "",
            //            "Return" => "",
            //            "Returning to" => "",
            //            "Corporate Discount" => "",
            //            "Car:" => "",
        ],
    ];
    private $date;
    private $resDate;

    private $year;
    private $text;
    private $detectLangByBody = [
        'en' => ['Reservations', 'Total Estimated Cost'],
        'es' => ['Reservaciones', 'Costo total aproximado'],
        'de' => ['Reservierungen', 'Geschätzte Gesamtkosten'],
        'it' => ['Prenotazioni', 'Costo totale stimato'],
        'fr' => ['Réservations', 'Coût total estimé'],
    ];

    private $patterns = [
        'hotelName' => '[[:upper:]][-[:upper:] &(]*[!)[:upper:]]', // HOLIDAY INN EXPRESS HOTEL & SUITES
        'phone'     => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@concursolutions.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return (strpos($parser->getPlainBody(), '~~~~~~~~~~~~') !== false
            || stripos($parser->getPlainBody(), 'Ticketed: Travel Itinerary') !== false
                || stripos($parser->getPlainBody(), 'SPECIAL FARE CONDITIONS') !== false
                || stripos($parser->getPlainBody(), 'Trip Name:') !== false
                || $this->http->XPath->query('//text()[starts-with(normalize-space(),"Trip Name:")]')->length > 0
                || (stripos($parser->getPlainBody(), 'Trip Overview') !== false && stripos($parser->getHtmlBody(), 'Trip Overview') === false)
                || (stripos($parser->getPlainBody(), 'Información general del recorrido') !== false && stripos($parser->getHtmlBody(), 'Información general del recorrido') === false))
            && $this->http->XPath->query("//img[contains(@src, 'bcd')]")->length == 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->text = $parser->getBody();

        if (empty($this->text)) {
            $this->text = $this->htmlToText($this->http->FindHTMLByXpath("descendant::*[ br[3] and node()[normalize-space()][3] ][last()]"));
        }

        if (!$this->assignLang($this->text)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $this->text = preg_replace(["/\r/", "/\t/", "/^>+ (\S|\n)/m"], ['', ' ', '$1'], $this->text);
        // clean removed images
        $this->text = preg_replace([
            "/^.*Image removed.*$/im",
            "/^[ ]*\[[^\[\]\r\n\s]*[\d[:alpha:]]\/[^\[\]\r\n\s]+\][ ]*$/mu",
        ], '', $this->text);

        if (preg_match("/{$this->opt($this->t('Start Date'))}\:?\s+(?<year>\d{4})\,/u", $this->text, $m)
            || preg_match("/{$this->opt($this->t('Start Date'))}\:?.+\s(?<year>\d{4})\s*\n/u", $this->text, $m)) {
            $this->year = $m['year'];
        }

        if (preg_match("/{$this->opt($this->t('Created'))}\:?\s*(\d{4}\,\s*\w+\s*\d{1,2})\,/u", $this->text, $m)
            || preg_match("/{$this->opt($this->t('Created'))}\:?\s*(\w+\s*\d+\,\s*\d{4})\,/u", $this->text, $m)
            || preg_match("/{$this->opt($this->t('Created'))}\:?\s*(\d{1,2}\s*\w+\,\s*\d{4})\,/u", $this->text, $m)) {
            $this->resDate = $this->normalizeDate($m[1]);
        }

        $agencyConfirm = $this->re("/{$this->opt($this->t('Agency Record Locator'))}\:\s*([A-Z\d]{6,})/", $this->text);

        if (!empty($agencyConfirm)) {
            $email->ota()
                ->confirmation($agencyConfirm);
        }

        $segments = "\n" . preg_replace("/^[\s\S]+\n(?:Reservations|RESERVATIONS|{$this->opt($this->t('Reservations'))})\n+[^\w\n]{5,}\n+/", '', $this->text);
        $this->text = preg_replace("/\[https\:.*\]/", '', $this->text);
        $dateFormatRe = "[-[:alpha:]]+,\s*(?:[[:alpha:]]+\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]+),?\s*\d{2,4}";
        $textParts = splitter("/\n(.*?(?:(?:\n\s*{$dateFormatRe}\s+(?:\[.+\]\s+)?)?{$this->t('Flight')}\s+.*?\([A-Z]{3}\)|.*?{$this->t('Car Rental at')}:|(?:\s*{$dateFormatRe}\n+(?:\-+\n+)?)? *Train .+ {$this->t('to')} .+\n| *Train (?:.*\n+){1,3}[\`]{4,}\s+|.*Inn.*\s+-{4,}|(?:\]\s+(?:.{2,}\s+){4,5}|\n[\w ]{2,}\n+[\`]{4,}\s+[\s\S]+|\n[ ]*{$this->patterns['hotelName']}[ ]*(?:\n+[ ]*.{2,}){3,4}\n+[ ]*){$this->opt($this->t('Checking In'))}\s*:))/iu", $segments);

        foreach ($textParts as $textPart) {
            if (stripos($textPart, $this->t('Checking In')) !== false) {
                $this->ParseHotel($email, $textPart);
            } elseif (stripos($textPart, $this->t('Pick-up at')) !== false) {
                $this->ParseCar($email, $textPart);
            } elseif (stripos($textPart, $this->t('Flight')) !== false) {
                $this->ParseFlight($email, $textPart);
            } elseif (stripos($textPart, $this->t('Train')) !== false) {
                $this->ParseTrain($email, $textPart);
            }
        }

        if (preg_match("/{$this->opt($this->t('Total Estimated Cost'))}\:\s*\D\s*(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})/u", $this->text, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->re("/{$this->opt($this->t('Taxes and fees'))}\s*\D\s*([\d\.\,]+)\s*[A-Z]{3}/u", $this->text);

            if (!empty($tax)) {
                $email->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $cost = $this->re("/{$this->opt($this->t('Airfare quoted amount'))}\:?\s*\D\s*([\d\.\,]+)\s*[A-Z]{3}/u", $this->text);

            if (!empty($cost)) {
                $email->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@concursolutions.com') !== false;
    }

    public function ParseTrain(Email $email, $text): void
    {
        $year = '';

        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->re("/\s+{$this->t('Confirmation')}\s*:\s*([\d\w\-]+)/", $text))
            ->traveller($this->re("/\n\s*{$this->t('Passengers')}\s*:\s*(.*)/", $this->text))
            ->date($this->resDate);

        $status = $this->re("/\n\s*{$this->t('Status')}\s*:\s*(\w+)/", $text);

        if (!empty($status)) {
            $t->general()
                ->status($status);
        }

        $account = $this->re("/\n\s*{$this->t('Train Frequent Traveler Number')}\s*:\s*([\d\w\-]+)/", $text);

        if (!empty($account)) {
            $t->program()
                ->account($account, false);
        }

        if (preg_match("/Train\s*(?<depName>.+[A-Z]{2})\s+{$this->opt($this->t('to'))}\s+(?<arrName>.+\s*[A-Z]{2})\n/", $text, $m)) {
            $s = $t->addSegment();

            $s->departure()
                ->name($m['depName']);

            $s->arrival()
                ->name(str_replace("\n", "", $m['arrName']));

            if (preg_match("/(^|\n) *Train [\s\S]+?\n\n(?<serviceName>\D+)\s+(?<number>\d{2,4})\n/u", $text, $m)) {
                $s->setServiceName($m['serviceName']);
                $s->setNumber($m['number']);
            }

            //Departure Date
            $depTime = $this->re("/{$this->opt($this->t('Departure'))} ?\:?\s*(.+)\n/", $text);

            if (preg_match("#^\s*(?<date>[-[:alpha:]]+,\s*(?:[[:alpha:]]+\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]+),?\s*\d{2,4})\s+(?:\n*\-+\n*)?\s*Train .+#mu", $text, $m)
                ) {
                if (isset($m['date']) && !empty($m['date'])) {
                    $this->date = $m['date'];
                }
            }

            if (!empty($depTime) && !empty($this->date)) {
                $s->departure()
                    ->date($this->normalizeDate($this->date . ', ' . $depTime));
            }

            //Arrival Date
            $arrTime = $this->re("/{$this->opt($this->t('Arrival'))} ?\:?\s*(.+)\n/", $text);

            if (!empty($s->getDepName()) && preg_match("/\n\s*(?<date>\w+,\s*\w+\s*\d+,\s+\d{4})\n+(?:[\-]+\n*)?{$this->t('Train')} *{$s->getDepName()}\s*{$this->opt($this->t('to'))}/mu", $this->text, $match)) {
                $this->date = $match[1];
            }

            $s->arrival()
                ->date($this->normalizeDate($this->date . ', ' . $arrTime));

            $seat = $this->re("/{$this->opt($this->t('Seat'))} ?\:?\s*(\d.+)\s*\(/", $text);

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }

            if (preg_match("/{$this->opt($this->t('Class'))}\:?\s*(?<cabin>\D+)\s+\((?<bookingCode>[A-Z]{1,2})\)/", $text, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);
            }
        }
    }

    public function ParseHotel(Email $email, $text): void
    {
        $text = preg_replace("/^\s*\w+[ ,]+\w+[ ,]+\w+[ ,]+\d{4}\d*\n[\-]{5,}\n/", '', $text);

        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/\n\s*{$this->opt($this->t('Confirmation'))}\s*:\s*([\d\w\-]+)/", $text))
            ->date($this->resDate);

        $status = $this->re("/\n\s*{$this->t('Status')}\s*:\s*(\w+)/", $text);

        if (!empty($status)) {
            $h->general()
                ->status($status);
        }

        $traveller = $this->re("/{$this->opt($this->t('Reservation for'))}\:?\s*(\D+)(?:{$this->opt($this->t('Air Total Price'))}|\n)/", $this->text);

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller);
        }

        $cancellation = $this->re("/\n\s*{$this->opt($this->t('Cancellation Policy'))}\s*(.*)/", $text);

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $h->booked()
            ->guests($this->re("/\s+{$this->t('Guests')}*\s+(\d+)/", $text))
            ->rooms($this->re("/\s+{$this->t('Room')}*\s+(\d+)/", $text));

        if (
            // it-1678470.eml, it-1823323.eml, it-208459250.eml
            preg_match("/[\s\W]*(?<name>.{2,})[ ]*\n+[ ]*[^\w\s]{5,}\s+(?<address>(?:.{2,}\n+){1,4}?)[ ]*(?<phone>{$this->patterns['phone']})[ ]*\n/u", $text, $m)

            // it-2930583.eml
            || preg_match("/(?:^\s*|\n[ ]*)(?<name>.{2,}?)[ ]*\n+[ ]*(?<address>(?:.{2,}\n+){1,4}?)[ ]*(?<phone>{$this->patterns['phone']})[ ]*\n+[ ]*{$this->opt($this->t('Checking In'))}\s*:/i", $text, $m)

            // ?
            || preg_match("/.+\s+(?<name>.{2,})\s*[\`]+\s+(?<address>[\d\s,\w]{2,})\n+[ ]*(?<phone>{$this->patterns['phone']})/u", $text, $m)
            || preg_match("/.+\s+(?<name>.{2,})\s*[\`]+\s+(?<address>[\d\s,\w]{2,})\s+(?<phone>{$this->patterns['phone']})/u", $text, $m)
        ) {
            if (preg_match('/^\s*[\[(].+[)\]]\s*$/', $m['name']) > 0) {
                // [Image removed by sender]
                $m['name'] = '';
            }
            $h->hotel()
                ->name(trim($m['name']))
                ->address(preg_replace('/\s+/', ' ', trim($m['address'], ',')))
                ->phone(strlen(preg_replace('/\D/', '', $m['phone'])) > 0 ? $m['phone'] : null, false, true);
        }

        if (preg_match("/\n\s*{$this->opt($this->t('Checking In'))}\s*:\s*\w+\.?\s+(?<date>(?:\w+\.?\s+\d+|\d+\s+\w+))\s+(?<time>\d+:\d+)?/s", $text, $m)) {
            if (isset($m['time'])) {
                $checkIn = $m['date'] . ' ' . $m['time'];
            } else {
                $checkIn = $m['date'];
            }
        }

        if (preg_match("/\n\s*{$this->opt($this->t('Checking Out'))}\s*:\s*\w+\.?\s+(?<date>(?:\w+\.?\s+\d+|\d+\s+\w+))\s+(?<time>\d+:\d+)?/s", $text, $m)) {
            if (isset($m['time'])) {
                $checkOut = $m['date'] . ' ' . $m['time'];
            } else {
                $checkOut = $m['date'];
            }
        }

        $h->booked()
            ->checkIn(!empty($checkIn) ? $this->normalizeDate($checkIn . ' ' . $this->year) : null)
            ->checkOut(!empty($checkOut) ? $this->normalizeDate($checkOut . ' ' . $this->year) : null);

        $rate = $this->re("/\n\s*{$this->opt($this->t('Daily rate'))}\s*:\s*(\S\s*[\d\.]+\s+[A-Z]+)/", $text);
        $description = trim($this->re("/\n\s*{$this->t('Room Description')}\s*:\s*(.*)/u", $text));

        if (strpos($description, 'RoomDescriptionCode') !== false) {
            $description .= '; ' . trim($this->re("#\n\s*{$this->t('Room Description')}\s*:\s*.*\n(.*)#", $text));
        }

        if (!empty($rate) || !empty($description)) {
            $room = $h->addRoom();

            if (!empty($rate)) {
                $room->setRate($rate);
            }

            if (!empty($description)) {
                $room->setDescription($description);
            }
        }

        $account = $this->re("/{$this->opt($this->t('Frequent Guest Number'))}\s*:\s*([\w\-]+)/", $text);

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        $this->detectDeadLine($h, $h->getCancellation());
    }

    public function ParseCar(Email $email, $text): void
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/\s+{$this->t('Confirmation')}:\s*([\d\w\-]+)/", $text))
            ->status($this->re("/\n\s*{$this->t('Status')}\s*:\s*(.*)/", $text))
            ->traveller($this->re("/{$this->opt($this->t('Reservation for'))}\:?\s*(\D+)(?:{$this->opt($this->t('Air Total Price'))}|\n)/", $this->text))
            ->date($this->resDate);

        $r->car()
            ->type($this->re("#(.+)\/\s*Car\s*\/.+\/.+#", $text), true, true);

        $r->pickup()
            ->location($this->re("/\n.*?{$this->opt($this->t('Pick-up at'))}\s*[:]+[ ]*(.{3,}?)[ ]*\n/", $text))
            ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Pick Up:'))}\s*(.+)/", $text)));

        $r->dropoff()
            ->location($this->re("/\n.*?{$this->opt($this->t('Returning to'))}\s*[:]+[ ]*(.{3,}?)(?:[ ]+{$this->opt($this->t('Confirmation'))}\s*:|[ ]*\n)/", $text))
            ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Return:'))}\s*(.+)/", $text)));

        $r->setCompany($this->re("/^(.+)\s+at\:/mu", $text));

        if (preg_match("/{$this->opt($this->t('Frequent Guest Number'))}\s*[:]+[ ]*([-A-Z\d]{5,})[ ]*$/m", $text, $m)) {
            $r->program()->account($m[1], false);
        }
    }

    public function ParseFlight(Email $email, $text)
    {
        $text = str_replace("\n\n", "\n", $text);
        $year = '';

        if (preg_match("/{$this->opt($this->t('Start Date'))}\:?\s+(?<year>\d{4})\,/u", $this->text, $m)
        || preg_match("/{$this->opt($this->t('Start Date'))}\:?.+\s(?<year>\d{4})\s*\n/u", $this->text, $m)) {
            $year = $m['year'];
        }

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/\s+{$this->t('Confirmation')}:\s*([\d\w\-]+)/", $text))
            ->traveller($this->re("/\n\s*{$this->t('Passengers')}\s*:\s*(.*)/", $this->text))
            ->date($this->resDate);

        $status = $this->re("/\n\s*{$this->t('Status')}\s*:\s*(\w+)/", $text);

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $account = $this->re("/\n\s*{$this->t('Air Frequent Flyer Number')}\s*:\s*([\d\w\-]+)/", $text);

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        if (preg_match("/\((?<depCode>[A-Z]{3})\)\s*{$this->opt($this->t('to'))}\s*.+\((?<arrCode>[A-Z]{3})\)\n*[`\s]*\n*(?<airName>\D+)\s*(?<flNumber>\d{2,4})/", $text, $m)) {
            $s = $f->addSegment();

            $s->airline()
                ->name($m['airName'])
                ->number($m['flNumber']);

            $operator = $this->re("/{$this->opt($this->t('Operated by'))}\:?\s*(.+)\n/", $text);

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $depTerminal = $this->re("/{$this->opt($this->t('Terminal:'))}\s*(.+)\n{$this->opt($this->t('Duration'))}/u", $text);

            if (empty($depTerminal)) {
                $depTerminal = $this->re("/{$this->opt($this->t('Terminal:'))}\s*(.+)\n{$this->opt($this->t('Nonstop'))}/u", $text);
            }

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal(preg_replace("/(terminal)/i", "", $depTerminal));
            }

            $arrTerminal = $this->re("/{$this->opt($this->t('Terminal:'))}\s*(.+)\n{$this->opt($this->t('Confirmation'))}/u", $text);

            if (empty($arrTerminal)) {
                $arrTerminal = $this->re("/{$this->opt($this->t('Terminal:'))}\s*(.+)\n{$this->opt($this->t('Confirmation'))}/u", $text);
            }

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal(preg_replace("/(terminal)/i", "", $arrTerminal));
            }

            $s->departure()
                ->code($m['depCode']);
            $s->arrival()
                ->code($m['arrCode']);

            //Departure Date
            $depTime = $this->re("/{$this->opt($this->t('Departure'))}\:?\s*(.+)\n/", $text);

            if (preg_match("#^(?<date>(?:\w+\,\s*\w+\s*\d+\,\s+\d{4}|\d{4}\s*\w+\s*\d+\,\s*\w+|\w+\,\s*\d+\s*\w+\s*\d{2,4}))?\s*\n+(?:[\-]+\n*)?\s*{$this->opt($this->t('Flight'))}.+\({$s->getDepCode()}\)\s*{$this->opt($this->t('to'))}#mu", $this->text, $m)
                || preg_match("#^(?<date>\w+\,\s*\w+\s*\d+\,\s+\d{4})\s*\n+(?:[\-]+\n*)?.+\({$s->getDepCode()}\)\s*\n{$this->opt($this->t('Flight'))}.*\({$s->getDepCode()}\)\s*.+\({$s->getArrCode()}\)\s*\n#mu", $this->text, $m)) {
                if (isset($m['date']) && !empty($m['date'])) {
                    $this->date = $m['date'];
                }
            } elseif (preg_match("/{$this->opt($this->t('leaves on'))}\s*(\w+\s*\d+)\s+.+/u", $text, $match)) {
                $this->date = $match[1] . ' ' . $year;
            }

            $s->departure()
                ->date($this->normalizeDate($this->date . ', ' . $depTime));

            //Arrival Date
            $arrTime = $this->re("/{$this->opt($this->t('Arrival'))}\:?\s*(.+)\n/", $text);

            if (!preg_match("/{$this->opt($this->t('layover at'))}.+\n+{$this->opt($this->t('Flight'))}.+\s\([A-Z]{3}\)/", $this->text)) {
                if (!empty($s->getDepCode()) && preg_match("/^(\w+,\s*\w+\s*\d+,\s+\d{4})\n+(?:[\-]+\n*)?{$this->t('Flight')}.+\({$s->getDepCode()}\)\s*{$this->opt($this->t('to'))}/mu", $this->text, $match)) {
                    $this->date = $match[1];
                } elseif (preg_match("/{$this->opt($this->t('leaves on'))}\s*(\w+\s*\d+)\./u", $text, $match)) {
                    $this->date = $match[1] . ' ' . $year;
                }
            }

            $s->arrival()
                ->date($this->normalizeDate($this->date . ', ' . $arrTime));

            $duration = $this->re("/{$this->opt($this->t('Duration'))}\:?\s*(.+)/", $text);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $aircraft = $this->re("/{$this->opt($this->t('Aircraft'))}\:?\s*(.+)\s+{$this->opt($this->t('Distance:'))}/s", $text);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $meal = $this->re("/{$this->opt($this->t('Meal'))}\:?\s*(.+)/", $text);

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $miles = $this->re("/{$this->opt($this->t('Distance'))}\:?\s*(.+)/", $text);

            if (!empty($miles)) {
                $s->extra()
                    ->miles($miles);
            }

            $seat = $this->re("/{$this->opt($this->t('Seat'))}\:?\s*(\d.+)\s*\(/", $text);

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }

            if (preg_match("/{$this->opt($this->t('Cabin'))}\:?\s*(?<cabin>\D+)\s+\((?<bookingCode>[A-Z])\)/", $text, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 3; // flght | hotel | car
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function normalizeDate($str)
    {
        $in = [
            //Sep 14 2022
            "#^\s*(\w+)\s+(\d+)\,?\s+(\d{4})\s*$#u",
            //12:00 PM Mon Jul 14
            "#^\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s+([[:alpha:]]+)\s*([[:alpha:]]+)\s*(\d{1,2})\s*$#u",
            //2023 January 22, Sunday, 04:00 PM
            "#^(\d{4})\s*(\w+)\s*(\d+)\,\s*\w+\,\s*([\d\:]+\s*A?P?M)$#",
            //14 Febbraio, 2023
            "#^(\d+)\s*(\w+)\,\s*(\d{4})$#u",
        ];

        $out = [
            "$2 $1 $3",
            "$2, $4 $3 $this->year, $1",
            "$3 $2 $1, $4",
            "$1 $2 $3",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->detectLangByBody)) {
            foreach ($this->detectLangByBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s, '/')); }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectDeadLine(Hotel $h, $cancellationText)
    {
        if (preg_match("#Cancellation Free Of Charge When Cancelling Before ([\d\/]+\s*[\d\:]+\s*A?P?m)#i", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1]));
        } elseif (preg_match("#Must Be Cancelled By\s*(?<hours>\d{1,2})(?<min>\d{2})\s*On\s*(?<day>\d+)(?<month>\D+)(?<year>\d{2})$#i", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['hours'] . ':' . $m['min']));
        } elseif (preg_match("#Must Cancel (\d+) Hours Prior To Arrival\.#ui", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours');
        } elseif (preg_match("#Annullamento Senza Spese Fino Alle ([\d\:]+) \(Ora Locale\) Del Giornodi Arrivo#ui", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 days', $m[1]);
        }
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
