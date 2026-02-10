<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "edreams/it-166496606.eml, edreams/it-29307703.eml, edreams/it-29466373.eml, edreams/it-29525500.eml, edreams/it-29621468.eml, edreams/it-29633019.eml, edreams/it-30033711.eml, edreams/it-30139777.eml, edreams/it-30197226.eml, edreams/it-51961563.eml, edreams/it-61623300.eml, edreams/it-701894697.eml";
    private $subjects = [
        'en' => ['Your booking is confirmed'],
        'es' => ['Se ha confirmado tu reserva'],
        'pt' => ['A sua reserva está confirmada'],
        'fr' => ['Votre réservation est confirmée !'],
        'nl' => ['Uw reservering is bevestigd!'],
        'it' => ['La tua prenotazione è confermata!'],
        'de' => ['Ihre Buchung wurde bestätigt!'],
        'ja' => ['ご予約が確定しました。(予約番号:'],
        'no' => ['Bestillingen er bekreftet!'],
        'da' => ['Din booking er bekræftet'],
        'tr' => ['Rezervasyonunuz onaylandı!'],
    ];
    private $langDetectors = [
        'en' => ['booking reference:'],
        'es' => ['Referencia de la reserva', 'Localizador de reserva', 'Localizador de la reserva de'],
        'pt' => ['Referência da reserva', 'Referência de reserva'],
        'fr' => ['Référence de réservation'],
        'nl' => ['reserveringsnummer:'],
        'it' => ['Numero di prenotazione'],
        'de' => ['Buchungsnummer der Airline', 'Buchungsnummer der Fluggesellschaft', 'Wir bearbeiten Ihre Buchung'],
        'ja' => ['eDreams の予約番号:'],
        'no' => ['Din bestillingsreferanse hos'],
        'da' => ['bookingnummer:'],
        'tr' => ['rezervasyon referansı:'],
    ];
    private $lang = '';
    private $date;
    private static $dict = [
        'en' => [
            //            "booking reference:" => "",
            //            "Your booking is confirmed" => "",
            //            "Airline reference" => "",
            //            "Terminal" => "",
            //            "Duration:" => "",
            //            "Operated by" => "",
            //            "Aircraft type:" => "",
            //            "Class:" => "",
            //            "Who's going?" => "",
            //            "Age:" => "",
            //            "Seat selection" => "",
            //            "Choose your seat" => "",
            //            "Seat:" => "",
            //            "e-ticket numbers" => "",
            //            "The total cost of your reservation is:" => "",
            "We are processing your booking" => ["We are processing your booking", "Success! We got your booking", "we are processing your booking with the airline"],
        ],
        'da' => [ // it-29466373.eml, it-29525500.eml
            "booking reference:"                     => ["Travellink-bookingnummer:"],
            "Your booking is confirmed"              => ["Din booking er bekræftet"],
            "Airline reference"                      => ["Flyselskabets bookingnummer"],
            "Terminal"                               => "Terminal",
            "Duration:"                              => "Varighed:",
            //"Operated by"                            => "",
            //"Aircraft type:"                         => "",
            "Class:"                                 => "Klasse",
            "Who's going?"                           => ["Hvem skal med?"],
            "Age:"                                   => "Alder:",
            "Seat selection"                         => "Selección de asiento",
            "Choose your seat"                       => "Sædevalg",
            "Seat:"                                  => "sæde",
            //"e-ticket numbers"                       => [""],
            "The total cost of your reservation is:" => ["Den samlede pris for din reservation er:"],
            //"We are processing your booking"         => "",
        ],
        'tr' => [ // it-29466373.eml, it-29525500.eml
            "booking reference:"                     => ["eDreams rezervasyon referansı:"],
            "Your booking is confirmed"              => ["Rezervasyonunuz onaylandı"],
            "Airline reference"                      => ["Hava yolu şirketi referansı"],
            "Terminal"                               => "Terminal",
            "Duration:"                              => "Süre: ",
            //"Operated by"                            => "",
            //"Aircraft type:"                         => "",
            "Class:"                                 => "Sınıf:",
            "Who's going?"                           => ["Kim seyahat ediyor?"],
            "Age:"                                   => "Yaş:",
            "Seat selection"                         => "Koltuk seçimi",
            "Choose your seat"                       => "Koltuk seçilmedi",
            //"Seat:"                                  => "",
            //"e-ticket numbers"                       => [""],
            "The total cost of your reservation is:" => ["Rezervasyonunuzun toplam maliyeti:"],
            "We are processing your booking"         => "Rezervasyonunuzu işleme alıyoruz",
        ],
        'es' => [ // it-29466373.eml, it-29525500.eml
            "booking reference:"                     => ["Referencia de la reserva", "Localizador de reserva", "Localizador de la reserva de"],
            "Your booking is confirmed"              => ["Se ha confirmado tu reserva", "Hemos confirmado tu reserva"],
            "Airline reference"                      => ["Referencia de aerolínea", "Código de aerolínea", "Localizador de la aerolínea"],
            "Terminal"                               => "Terminal",
            "Duration:"                              => "Duración:",
            "Operated by"                            => "Operado por",
            "Aircraft type:"                         => "Tipo de avión:",
            "Class:"                                 => "Clase:",
            "Who's going?"                           => ["¿Quiénes viajan?", "Pasajeros"],
            "Age:"                                   => "Edad:",
            "Seat selection"                         => "Selección de asiento",
            "Choose your seat"                       => "Elige tu asiento",
            "Seat:"                                  => "Asiento:",
            "e-ticket numbers"                       => ["Números de pasajes electrónicos", "Números de e-ticket"],
            "The total cost of your reservation is:" => ["El precio total de tu reservación es de:", "Coste total de tu reserva:"],
            "We are processing your booking"         => "Estamos procesando tu reserva",
        ],
        'pt' => [ // it-30139777.eml
            "booking reference:"                     => ["Referência da reserva", "Referência de reserva"],
            "Your booking is confirmed"              => "A sua reserva está confirmada",
            "Airline reference"                      => ["Referência da companhia aérea", "Referência companhia aérea"],
            "Terminal"                               => "Terminal",
            "Duration:"                              => "Duração:",
            "Operated by"                            => "Operado por",
            "Aircraft type:"                         => "Tipo de avião:",
            "Class:"                                 => "Classe:",
            "Who's going?"                           => ["Quem vai?", "O que está acontecendo?"],
            "Age:"                                   => "Idade:",
            "Seat selection"                         => "Seleção de assentos",
            "Choose your seat"                       => "Escolher assento",
            "Seat:"                                  => "Assento:",
            "e-ticket numbers"                       => ["Números dos e-tickets", "Números de bilhete eletrônico"],
            "The total cost of your reservation is:" => "O custo total da sua reserva é de:",
            // "We are processing your booking" => "",
        ],
        'fr' => [ // it-29633019.eml, it-51961563.eml
            "booking reference:"        => "Référence de réservation",
            "Your booking is confirmed" => ["Votre réservation est confirmée !", "Réservation confirmée"],
            "Airline reference"         => ["Réf. compagnie aérienne", "Nº de référence de la compagnie aérienne"],
            "Terminal"                  => "Terminal",
            "Duration:"                 => "Durée :",
            "Operated by"               => "Opéré par",
            "Aircraft type:"            => "Type d'avion :",
            "Class:"                    => "Classe :",
            "Who's going?"              => "Passagers",
            "Age:"                      => "Âge :",
            "Seat selection"            => "Sélection de sièges",
            "Choose your seat"          => "Choisir un siège",
            //            "Seat:" => "",
            "e-ticket numbers"                       => "Numéro d'e-billet",
            "The total cost of your reservation is:" => "Le coût total de votre réservation est de:",
            "We are processing your booking"         => "Nous traitons votre demande de réservation",
        ],
        'nl' => [ // it-30197226.eml
            "booking reference:"                     => "reserveringsnummer:",
            "Your booking is confirmed"              => "Uw reservering is bevestigd",
            "Airline reference"                      => "Referentie van luchtvaartmaatschappij",
            "Terminal"                               => "Terminal",
            "Duration:"                              => "Duur:",
            "Operated by"                            => "Beheerd door",
            "Aircraft type:"                         => "Vliegtuigtype:",
            "Class:"                                 => "Klasse:",
            "Who's going?"                           => "Wie gaat er mee?",
            "Age:"                                   => "Leeftijd:",
            "Seat selection"                         => "Stoelselectie",
            "Choose your seat"                       => "Kies uw stoel",
            "Seat:"                                  => "Stoel:",
            "e-ticket numbers"                       => "e-ticketnummers",
            "The total cost of your reservation is:" => "De totale prijs van uw reservering is:",
            "We are processing your booking"         => "We zijn uw reservering aan het verwerken",
        ],
        'it' => [ // it-30033711.eml
            "booking reference:"        => "Numero di prenotazione",
            "Your booking is confirmed" => "La tua prenotazione è confermata",
            "Airline reference"         => ["Nº prenotazione compagnia aerea", "Nº di prenotazione della compagnia aerea"],
            "Terminal"                  => "Terminale",
            "Duration:"                 => "Durata:",
            //            "Operated by" => "",
            "Aircraft type:"                         => "Modello di aereo:",
            "Class:"                                 => "Classe:",
            "Who's going?"                           => "Chi viaggia?",
            "Age:"                                   => "Età:",
            "Seat selection"                         => "Selezione dei posti",
            "Choose your seat"                       => "Scegli il tuo posto",
            "Seat:"                                  => "Posto:", // to check
            "e-ticket numbers"                       => "Numero di e-ticket",
            "The total cost of your reservation is:" => "Il costo totale della tua prenotazione è:",
            // "We are processing your booking" => "",
        ],
        'de' => [ // it-29621468.eml
            "booking reference:"        => "Buchungsnummer:",
            "Your booking is confirmed" => "Ihre Buchung ist bestätigt",
            "Airline reference"         => ["Buchungsnummer der Airline", 'Buchungsnummer der Fluggesellschaft'],
            //            "Terminal" => "",
            "Duration:" => "Dauer:",
            //            "Operated by" => "",
            "Aircraft type:"   => "Flugzeugtyp:",
            "Class:"           => "Klasse:",
            "Who's going?"     => "Reisende",
            "Age:"             => "Alter:",
            "Seat selection"   => "Sitzplatzwahl",
            "Choose your seat" => "Wählen Sie Ihren Sitzplatz",
            //            "Seat:" => "",
            "e-ticket numbers"                       => "E-Ticket-Nummern",
            "The total cost of your reservation is:" => "Der Gesamtpreis Ihrer Buchung ist:",
            "We are processing your booking"         => "Wir bearbeiten Ihre Buchung",
        ],
        'ja' => [ // it-61623300.eml
            "booking reference:"        => "の予約番号:",
            "Your booking is confirmed" => "予約が確定しました",
            "Airline reference"         => ['航空会社参照番号'],
            //            "Terminal" => "",
            "Duration:" => "所要時間:",
            //            "Operated by" => "",
            //            "Aircraft type:" => "Flugzeugtyp:",
            "Class:"           => "搭乗クラス：",
            "Who's going?"     => "搭乗者情報",
            "Age:"             => "年齢:",
            "Seat selection"   => "座席指定",
            "Choose your seat" => "座席を選択",
            //            "Seat:" => "",
            //            "e-ticket numbers" => "E-Ticket-Nummern",
            "The total cost of your reservation is:" => "予約計金額：:",
            // "We are processing your booking" => "",
        ],
        'no' => [
            "booking reference:" => "Din bestillingsreferanse hos ",
            //            "Your booking is confirmed" => "",
            "Airline reference" => "Referanse hos flyselskapet",
            "Terminal"          => "Terminal",
            "Duration:"         => "Varighet:",
            //            "Operated by" => "",
            "Aircraft type:"   => "Flytype:",
            "Class:"           => "Klasse:",
            "Who's going?"     => "Hvem skal reise?",
            "Age:"             => "Alder:",
            "Seat selection"   => "Setevalg",
            "Choose your seat" => "Velg sete",
            "Seat:"            => "Sete:",
            //            "e-ticket numbers" => "",
            "The total cost of your reservation is:" => "Totalpris:",
            //             "We are processing your booking" => "",
        ],
    ];

    private $providerCode = '';

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@](?:edreams|opodo|еravellink)\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('YourBooking' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['edreams', 'opodo', 'govoyages', 'tllink'];
    }

    private function parseEmail(Email $email)
    {
        $patterns = [
            'time' => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.    |    3pm
        ];

        $email->obtainTravelAgency(); // because eDreams is travel agency

        $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->contains($this->t('booking reference:'))}][1]");

        if (empty($confirmationTitle)) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Airline reference'))}]/preceding::text()[{$this->contains($this->t('booking reference:'))}][1]");
        }
        $confirmation = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('booking reference:'))}]/following::text()[normalize-space(.)][1]", null, '/^(\d{7,})(?:\s.*)?$/'));
        $confirmation = array_shift($confirmation);
        $email->ota()->confirmation($confirmation, preg_replace('/\s*:\s*$/', '', $confirmationTitle));

        $f = $email->add()->flight();

        // status
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Your booking is confirmed'))}]")->length > 0) {
            $f->general()->status('confirmed');
        }

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Airline reference'))}])[1]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Airline reference'))}]/following::text()[normalize-space(.)][1]", null, true, '/^([A-Z\d]{5,})$/');

        if (!empty($confirmationNumber)) {
            $f->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));
        } else {
            if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('We are processing your booking'))}]"))) {
                $f->general()
                    ->noConfirmation();
            }

            if (!empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('We are processing your booking'))}]"))) {
                $f->general()
                    ->noConfirmation();
            }
        }

        // segments
        $segments = $this->http->XPath->query("//*[ ./tr[1][string-length(normalize-space(.))>5] and ./tr[2][{$this->starts($this->t('Duration:'))}] and ./tr[3][string-length(normalize-space(.))>5] ]");

        foreach ($segments as $key => $segment) {
            $flightTexts = $this->http->FindNodes("./ancestor::tr[ ./preceding-sibling::*[normalize-space(.)] ][1]/preceding-sibling::*[normalize-space(.)]/descendant::text()[normalize-space(.)]", $segment);
            $flight = implode("\n", $flightTexts);

            $s = $f->addSegment();

            // airlineName
            // flightNumber
            if (preg_match('/^(.* )?(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d{1,5})$/m', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);
            }

            if (empty($confirmationNumber)) {
                if (preg_match("/{$this->opt($this->t('Airline reference'))}\s+([A-Z\d]{5,})/su", $flight, $m)) {
                    $s->airline()->confirmation($m[1]);
                    $f->general()->noConfirmation();
                }
            }

            // operatedBy
            if (preg_match("/{$this->opt($this->t('Operated by'))}\s*(.+)/", $flight, $m) && $m[1] !== '0') {
                $s->airline()->operator($m[1]);
            }

            // date => dateDetailed
            $dateVariants = [
                // 09:35 mer., 2 oct.
                "\d+:\d+\s*[^\d\W]{2,}[\s\.]*,\s*\d{1,2}\s+[^\d\W]{3,}[.]*" => "(?<time>\d+:\d+)\s*(?<wday>[^\d\W]{2,})[\s\.]*,\s*(?<date>\d{1,2}\s+[^\d\W]{3,})[\.]?",
                // 07/12 日
                "\d/\d+ 日 {$patterns['time']}" => "(?<date>\d+/\d+)\s+(?<wday>.)\s*(?<time>{$patterns['time']})",
            ];

            /*
            09:35 mer., 2 oct.
            Paris (France),
            Charles De Gaulle (CDG)
            , Terminal 2E
            */
            $patterns['airport'] = "/^"
                . "\s*(?<date>.+?)\n"
                . ".+?\((?<code>[A-Z]{3})\)"
                . "(?:\s*,\s*{$this->opt($this->t("Terminal"))}\s+(?<terminal>[A-z\d\s]+))?\s*" // , Terminal 3
                . "$/su";

            // depDate
            // depCode
            // depTerminal
            $departure = join("\n", $this->http->FindNodes("./tr[1]//text()", $segment));
            $arrival = join("\n", $this->http->FindNodes("./tr[3]//text()", $segment));

            if (preg_match($patterns['airport'], $departure, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date']))
                    ->code($m['code'])
                    ->terminal($m['terminal'] ?? null, false, true)
                ;
            }

            if (preg_match($patterns['airport'], $arrival, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($m['date']))
                    ->code($m['code'])
                    ->terminal($m['terminal'] ?? null, false, true)
                ;
            }

            // duration
            $duration = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Duration:'))}]", $segment, true, "/{$this->opt($this->t('Duration:'))}\s*(\d.+)/");
            $s->extra()->duration($duration);

            // aircraft
            // cabin
            $aircraftClass = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Aircraft type:'))} or {$this->contains($this->t('Class:'))}]", $segment);

            if (preg_match("/{$this->opt($this->t('Aircraft type:'))}\s*(.+?)\s*-\s*{$this->opt($this->t('Class:'))}\s*(.+)/", $aircraftClass, $m)) {
                // Aircraft type: 319 - Class: Economy
                $s->extra()
                    ->aircraft($m[1])
                    ->cabin($m[2])
                ;
            } elseif (preg_match("/{$this->opt($this->t('Class:'))}\s*(.+)/", $aircraftClass, $m)) {
                // Class: Economy
                $s->extra()->cabin($m[1]);
            }
            // seats
            $seats = $this->http->FindNodes("//text()[{$this->eq($this->t('Seat selection'))}]/ancestor::tr[ ./following-sibling::* ][1]/following-sibling::*[normalize-space(.)][1]/descendant::text()[{$this->eq($this->t('Choose your seat'))}][{$key}+1]/ancestor::*[ ./preceding-sibling::* ][1]/preceding-sibling::*/descendant::text()[{$this->starts($this->t('Seat:'))}]", null, "/{$this->opt($this->t('Seat:'))}\s*(\d{1,5}[A-Z])$/");
            $seats = array_filter($seats);

            if (count($seats)) {
                $s->extra()->seats($seats);
            }
        }
        // travellers
        // ticketNumbers
        $travellers = $this->http->FindNodes("//text()[" . $this->eq($this->t("Who's going?")) . "]/following::table[" . $this->contains($this->t("Age:")) . "][1]//text()[" . $this->eq($this->t("Age:")) . "]/preceding::text()[normalize-space()][1]/ancestor::td[1]");

        if ($travellers) {
            $f->general()->travellers($travellers, true);
        }
        $ticketRows = $this->http->XPath->query("//text()[{$this->eq($this->t('e-ticket numbers'))}]/ancestor::tr[ ./following-sibling::* ][1]/following-sibling::*[normalize-space(.)][1]/descendant::td[count(./*)=2]");

        foreach ($ticketRows as $ticketRow) {
            if (empty($travellers)) {
                $traveller = $this->http->FindSingleNode("./*[1]", $ticketRow, true, '/^([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])$/');

                if ($traveller) {
                    $f->addTraveller($traveller);
                }
            }
            $ticketNo = $this->http->FindSingleNode("./*[2]", $ticketRow, true, '/ETKT\s*(\d[-\d\s]{5,}\d)$/i');

            if ($ticketNo) {
                $f->addTicketNumber($ticketNo, false);
            }
        }
        // p.currencyCode
        // p.total
        $payment = $this->http->FindSingleNode("//td[not(.//td) and {$this->eq($this->t('The total cost of your reservation is:'))}]/following-sibling::td[normalize-space(.)][last()]");

        if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)/', $payment, $matches)
                || preg_match('/^\s*(?<amount>\d[,.\'\d]*)\s*(?<currency>[^\d\s]+)/', $payment, $matches)) {
            // € 909.69
            $f->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($this->normalizeCurrency($matches['currency']))
            ;
        }
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            // 09:35 mer., 2 oct.
            "/^(\d+:\d+)\s*([^\d\W]{2,})[\s\.]*,\s*(\d{1,2})\s+([^\d\W]{3,})[.]*$/u",
            // 07/12 日 18:50
            '#^(\d+)\/(\d+) (.) (\d+:\d+)$#u',
        ];
        $out = [
            "$2, $3 $4 $year, $1",
            "$3, $1/$2/$year, $4",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)
        || preg_match("/^(?<week>\w+), (?<date>\d+\/\d+.+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'CHF' => ['Fr.'],
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignProvider($headers): bool
    {
        $condition1 = preg_match('/[.@]edreams\.com/i', $headers['from']) > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".edreams.")]')->length > 0; // edreams.com, edreams.net
        $condition3 = $this->http->XPath->query('//node()[' . $this->contains([
            "en"  => "Thank you so much for choosing eDreams",
            "en1" => "You have chosen eDreams",
            "en2" => "eDreams. All rights reserved",
            "es"  => "Muchas gracias por elegir eDreams",
            "es2" => "Gracias por elegir eDreams",
            "es3" => "Todos los derechos reservados",
            "pt"  => "Obrigado por escolher a eDreams",
            "pt2" => "eDreams. Todos os direitos reservados",
            "fr"  => "Merci d'avoir choisi eDreams",
            "fr2" => "eDreams. Tous droits réservés",
        ]) . ']')->length > 0;

        if ($condition1 || $condition2 || $condition3) {
            $this->providerCode = 'edreams';

            return true;
        }

        $condition1 = preg_match('/[.@]opodo\.com/i', $headers['from']) > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".opodo.")]')->length > 0;
        $condition3 = $this->http->XPath->query('//node()[' . $this->contains([
            "en" => "Thank you so much for choosing Opodo",
            "de" => "Vielen Dank, dass Sie Opodo gewählt haben",
        ]) . ']')->length > 0;

        if ($condition1 || $condition2 || $condition3) {
            $this->providerCode = 'opodo';

            return true;
        }

        $condition1 = preg_match('/[.@]govoyages\.com/i', $headers['from']) > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".govoyages.com/") or contains(@href,"www.govoyages.com")]')->length > 0;
        $condition3 = $this->http->XPath->query('//node()[' . $this->contains([
            "fr" => "GO Voyages. Tous droits réservés",
        ]) . ']')->length > 0;

        if ($condition1 || $condition2 || $condition3) {
            $this->providerCode = 'govoyages';

            return true;
        }
        $condition1 = preg_match('/[.@]travellink\.com/i', $headers['from']) > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"travellink.")]')->length > 0;
        $condition3 = $this->http->XPath->query('//node()[' . $this->contains([
            "no"  => "Travellink. Med enerett",
            "no2" => "Din bestillingsreferanse hos Travellink",
        ]) . ']')->length > 0;

        if ($condition1 || $condition2 || $condition3) {
            $this->providerCode = 'tllink';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
