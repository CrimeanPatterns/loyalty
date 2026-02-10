<?php

namespace AwardWallet\Engine\amadeus\Email;

//ToDo: if in email there is "more services", look at old emails. maybe it's could be parsed too
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TripPlan extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-11474754.eml, amadeus/it-11558135.eml, amadeus/it-11558195.eml, amadeus/it-11558257.eml, amadeus/it-11736417.eml, amadeus/it-16.eml, amadeus/it-1636242.eml, amadeus/it-1636245.eml, amadeus/it-1636246.eml, amadeus/it-1636265.eml, amadeus/it-1682923.eml, amadeus/it-17.eml, amadeus/it-1707944.eml, amadeus/it-1707955.eml, amadeus/it-1709770.eml, amadeus/it-1994905.eml, amadeus/it-1994909.eml, amadeus/it-20.eml, amadeus/it-2084622.eml, amadeus/it-2085049.eml, amadeus/it-22.eml, amadeus/it-23.eml, amadeus/it-2327108.eml, amadeus/it-2336627.eml, amadeus/it-2475445.eml, amadeus/it-2538236.eml, amadeus/it-26663552.eml, amadeus/it-3130427.eml, amadeus/it-5227268.eml, amadeus/it-5227269.eml, amadeus/it-5227285.eml, amadeus/it-7428800.eml, amadeus/it-8557397.eml, amadeus/it-9854664.eml, amadeus/it-31271501.eml";

    public $reBody = [
        "en" => [
            "Trip Plan for",
            [
                "Electronic Ticketing",
                "Trip Purpose",
                "Rail Trip - Booking Date",
                "Reserva de Renfe",
                "Trip reason detail",
            ],
        ],
        "en2" => [
            "Reservation cancelled for",
            ["Electronic Ticketing", "Trip Purpose", "Rail Trip - Booking Date", "Reserva de Renfe"],
        ],
        "de" => ["Reiseplan für", ["Elektronisches Ticket", "Reisegrund"]],
        "nl" => ["Reisschema voor", ["Reisdoel"]],
        "fr" => [
            "Voyage pour",
            ["Ticket électronique", "Numéro de voiture"],
        ],
        "es" => [
            "Plan de viaje para",
            ["Emisión de billetes electrónicos", "Nombre del plan de viaje", "Reserva de Renfe", "Motivo del viaje"],
        ],
        "es2" => [
            "Reserva cancelada para",
            ["Emisión de billetes electrónicos", "Nombre del plan de viaje", "Reserva de Renfe"],
        ],
        "pt" => [
            "Plano de viagem para",
            ["Emissão de bilhete eletrônico", "Nome da viagem", "Valor estimado da viagem"],
        ],
        "pl" => ["Plan podróży", ["Szacowana cena podróży"]],
        "sv" => ["Resplan för", ["Typ av resa", "Bokningsreferens"]],
    ];

    public $lang = null;

    public static $dict = [
        'en' => [
            '_flights'            => ['Flights', 'Webfares'],
            '_rails'              => [],
            '_hotels'             => ['Hotels'],
            '_cars'               => ['Cars'],
            '_transfer'           => ['More services'],
            'Reservation Number'  => ['Reservation Number', 'Reservation number'],
            'RoomTypeDescription' => ['Bed Type', 'Bed type', 'Traveller requirement', 'Meal plan'],
            'Segment'             => ['Segment', 'Web Fare Confirmation Number', 'Aircraft'],
        ],
        'de' => [
            '_flights'           => ['Flüge'],
            '_rails'             => ['More services'],
            '_hotels'            => ['Hotels'],
            '_cars'              => ['Mietwagen'],
            '_transfer'          => ['More services'],
            'Reservation Number' => 'Reservierungsnummer',
            'Trip Plan for'      => 'Reiseplan für',
            //            'Class of service:' => '',
            //            'Number of car:' => '',
            //            'Number of place:' => '',
            'Vehicle type'      => 'Fahrzeugtyp',
            'Aircraft'          => 'Flugzeug',
            'Confirmation code' => 'Bestätigungscode',
            'Cabin'             => 'Serviceklasse',
            'for car rental'    => 'Geschätzter Preis',
        ],
        'nl' => [
            '_flights'           => ['Vlucht'],
            '_rails'             => [],
            '_hotels'            => ['Hotels'],
            '_cars'              => [],
            '_transfer'          => [],
            'Reservation Number' => 'Reserveringsnummer',
            'Trip Plan for'      => 'Reisschema voor',
            //            'Class of service:' => '',
            //            'Number of car:' => '',
            //            'Number of place:' => '',
            //			'Vehicle type' => '',
            'Aircraft'                    => 'Toestel',
            'Confirmation code'           => 'Bevestigingsnummer',
            'Check-in'                    => 'Aankomst',
            'Lowest nightly rate offered' => 'Laagst aangeboden nachttarief',
            'Room Type'                   => 'Kamertype',
            'Tel:'                        => 'Telefoon:',
            'Fax:'                        => 'Fax:',
            'RoomTypeDescription'         => 'Type bed',
            'Cancellation policy'         => 'Annuleringsbeleid',
            'Cabin'                       => 'Klasse',
            //			'for car rental' => ''
            'Estimated trip price' => 'Geschatte reiskosten',
            'Segment'              => ['Rechtstreeks', 'Segment'],
        ],
        'fr' => [
            '_flights'           => ['Vols'],
            '_rails'             => ['Trains'],
            '_hotels'            => ['Hôtels'],
            '_cars'              => [],
            '_transfer'          => [],
            'Reservation Number' => 'Numéro de réservation',
            'Trip Plan for'      => 'Voyage pour',
            'Class of service:'  => 'Classe de service:',
            'Number of car:'     => 'Numéro de voiture:',
            'Number of place:'   => 'Numéro de place:',
            // 'Vehicle type' => '',
            'Aircraft'          => 'Appareil',
            'Confirmation code' => 'Code de confirmation',
            'Check-in'          => 'Arrivée',
            // 'Lowest nightly rate offered' => '',
            'Room Type' => 'Type de chambre',
            'Tel:'      => 'téléphone:',
            // 'Fax:' => '',
            'RoomTypeDescription' => 'Type de lit',
            'Cancellation policy' => "Conditions d'annulation",
            'Cabin'               => 'Cabine',
            //			'for car rental' => ''
            'Estimated trip price' => 'Tarif estimé pour le voyage',
        ],
        'es' => [
            '_flights'           => ['Vuelos', 'Tarifas web'],
            '_rails'             => [],
            '_hotels'            => ['Hoteles'],
            '_cars'              => ['Coches'],
            '_transfer'          => ['Más servicios'],
            'Reservation Number' => 'Número de reserva',
            'Trip Plan for'      => 'Plan de viaje para',
            //            'Class of service:' => '',
            //            'Number of car:' => '',
            //            'Number of place:' => '',
            'Vehicle type'      => 'Tipo de vehículo',
            'Aircraft'          => 'Avión',
            'Confirmation code' => 'Código de confirmación',
            'Check-in'          => 'Entrada',
            // 'Lowest nightly rate offered' => '',
            'Room Type'                 => 'Tipo de habitación',
            'Tel:'                      => 'Teléfono:',
            'Fax:'                      => 'Fax:',
            'RoomTypeDescription'       => 'Tipo de cama',
            'Cancellation policy'       => 'Condiciones de cancelación',
            'Cabin'                     => 'Cabina',
            'for car rental'            => ' el alquiler de coche',
            'Opening Hours'             => 'Horario de apertura',
            'Estimated trip price'      => 'Precio estimado del viaje',
            'Segment'                   => ['Segmento', 'Sin escalas', 'Avión'],
            'Confirmation Number'       => 'Número de confirmación',
            'Reservation cancelled for' => 'Reserva cancelada para',
        ],
        'pl' => [
            '_flights'           => ['Loty'],
            '_rails'             => [],
            '_hotels'            => ['Hotele'],
            '_cars'              => [],
            '_transfer'          => [],
            'Reservation Number' => 'Numer rezerwacji',
            'Trip Plan for'      => 'Plan podróży dla',
            //            'Class of service:' => '',
            //            'Number of car:' => '',
            //            'Number of place:' => '',
            //			'Vehicle type' => 'Tipo de vehículo',
            'Aircraft'          => 'Samolot',
            'Confirmation code' => 'Numer rezerwacji',
            'Check-in'          => 'Zameldowanie',
            // 'Lowest nightly rate offered' => '',
            'Room Type'           => 'Rodzaj pokoju',
            'Tel:'                => 'Tel.:',
            'Fax:'                => 'Faks:',
            'RoomTypeDescription' => 'Wymagania dla podróżnego',
            'Cancellation policy' => 'Zasady anulowania',
            'Cabin'               => 'Standard kabiny',
            //			'for car rental' => ' el alquiler de coche',
            //			'Opening Hours'=>'Horario de apertura',
            //			'Estimated trip price' => 'Precio estimado del viaje',
            'Segment'             => ['Segment', 'Bez postojów', 'Samolot'],
            'Confirmation Number' => 'Numer rezerwacji',
            //			'Reservation cancelled for' => 'Reserva cancelada para',
        ],
        'pt' => [
            '_flights'           => ['Voos'],
            '_rails'             => [],
            '_hotels'            => ['Hotéis'],
            '_cars'              => [],
            '_transfer'          => [],
            'Reservation Number' => 'Reserva:',
            'Trip Plan for'      => 'Plano de viagem para',
            //            'Class of service:' => '',
            //            'Number of car:' => '',
            //            'Number of place:' => '',
            //			'Vehicle type' => '',
            'Aircraft'                    => 'Aeronave',
            'Confirmation code'           => 'Código de confirmação',
            'Check-in'                    => 'Entrada',
            'Lowest nightly rate offered' => 'Tarifa noturna mais baixa oferecida',
            'Room Type'                   => 'Tipo de acomodação',
            'Tel:'                        => 'Tel:',
            'Fax:'                        => 'Fax:',
            'RoomTypeDescription'         => 'Tipo de cama',
            'Cancellation policy'         => 'Política de cancelamento',
            'Cabin'                       => 'Cabine',
            //			'for car rental' => ' el alquiler de coche',
            //			'Opening Hours'=>'Horario de apertura',
            'Estimated trip price' => 'Valor estimado da viagem',
            'Segment'              => ['Segmento', 'Sem escalas', 'Aeronave'],
        ],
        'sv' => [
            '_flights'           => [],
            '_rails'             => ['Tåg'],
            '_hotels'            => ['Hotell'],
            '_cars'              => [],
            '_transfer'          => ['Fler tjänster'],
            'Reservation Number' => 'Bokningsreferens:',
            'Trip Plan for'      => 'Resplan för',
            //            'Class of service:' => '',
            'Number of car:'   => 'Vagn:',
            'Number of place:' => 'Platsnummer:',
            //			'Vehicle type' => '',
            //            'Aircraft' => '',
            'Confirmation code'           => 'Bokningsreferens',
            'Check-in'                    => 'Ankomst',
            'Lowest nightly rate offered' => 'Lägsta erbjudna hotellpris',
            'Room Type'                   => 'umstyp',
            'Tel:'                        => 'Tel.:',
            'Fax:'                        => 'Fax:',
            'RoomTypeDescription'         => 'Sängtyp', //??
            'Cancellation policy'         => 'Sammanfattning',
            'Cabin'                       => 'Cabine',
            //			'for car rental' => '',
            //			'Opening Hours'=>'',
            //            'Estimated trip price' => '',
            //            'Segment' => [''],
            //            'Description: Rail Trip'=>'',
            //            'Description: Rail Trip'=>'',
        ],
    ];

    private static $headers = [
        'amadeus' => [
            'from' => ['@amadeus.net', '@amadeus.com'],
            'subj' => [
                'Reservation confirmed', //en
                'Genehmigung f?r Reservierung erforderlich', //de
                'Approval needed for reservation', //en
                'Réservation confirmée', //fr
                'Se necesita aprobacion para', //es
                'Bokning bekräftad', //sv
            ],
        ],
        'amextravel' => [
            'from' => ['@amexbarcelo.com'],
            'subj' => [
                'Reservation confirmed', //en
            ],
        ],
        'hoggrob' => [
            'from' => ['@hrgworldwide.com'],
            'subj' => [
                'Best?tigung der Reservierung', //de
                'Booking CONFIRMATION for', //en
                'Reservering bevestigd voor', //nl
            ],
        ],
        'fcmtravel' => [
            'from' => ['.fcm.travel'],
            'subj' => [
                'Reservation for', //en
            ],
        ],
        'atpi' => [
            'from' => ['@atpi.com'],
            'subj' => [
                'Reservation confirmed',
            ],
        ],
    ];

    private $bodies = [
        'amadeus' => [
            'amadeus.net',
            'amadeus.com',
            'Amadeus',
        ],
        'fcmtravel' => [
            '//a[contains(@href,".fcm.travel")]',
            'FCM Travel Solution',
            'FCm Travel Solutions',
        ],
        'hoggrob' => [
            '//img[contains(@src, "HRG")]',
            'This is your automatic e-mail confirmation from HRG',
            'HRG',
        ],
        'amextravel' => [
            '//img[contains(@src,"travelocity.com")]',
            'American Express Travel',
            'AMERICAN EXPRESS GLOBAL BUSINESS TRAVEL',
            'American Express Global Business Travel',
        ],
        'atpi' => [
            'ATPI Norway AS',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $email->setType('TripPlan' . ucfirst($this->lang));

        $tripNum = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Reservation Number'))}]/following::text()[normalize-space(.)!=''])[1]",
            null, true, "#[\s:]*([A-Z\d]{5,})#");
        $email->ota()->confirmation($tripNum);

        $types = array_filter(array_merge((array) $this->t('_flights'), (array) $this->t('_rails'), (array) $this->t('_hotels'), (array) $this->t('_cars'), (array) $this->t('_transfer')));

        if ($this->http->XPath->query("//text()[{$this->eq($types)}]/ancestor::tr[2]")->length > 0) {
            if (!$this->parseFlights($email)) {
                return null;
            }

            if (!$this->parseHotels($email)) {
                return null;
            }

            if (!$this->parseCars($email)) {
                return null;
            }

            if (!$this->parseRails($email)) {
                return null;
            }
        }

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation cancelled for'))}]"))) {
            foreach ($email->getItineraries() as $itinerary) {
                $itinerary->general()
                    ->status('cancelled')
                    ->cancelled();
            }
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Estimated trip price'))}]/following::text()[normalize-space(.)][1]"));

        if (!empty($tot['Total'])) {
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (null !== $this->getProviderBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;
            }

            if ($byFrom) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
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
        $types = 3;
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'amadeus') {
                return null;
            } else {
                return $this->code;
            }
        }

        return $this->getProviderBody();
    }

    private function getProviderBody()
    {
        $providerBody = null;

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        $providerBody = $code;

                        break;
                    }
                }
            }
        }

        return $providerBody;
    }

    private function parseFlights(Email $email)
    {
        $pax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Trip Plan for'))} or {$this->starts($this->t('Reservation cancelled for'))}]/following::text()[normalize-space(.)!=''][1]");
        $tripNum = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Reservation Number'))}]/following::text()[normalize-space(.)!=''])[1]",
            null, true, "#[\s:]*([A-Z\d]{5,})#");
        $airs = [];
        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Segment'))}]/ancestor::table[contains(.,'>')][1]/descendant::tr[count(descendant::td[not(.//td)])=3 and normalize-space(descendant::td[2]/descendant::text()[normalize-space(.)!=''][1])='>']");

        foreach ($nodes as $node) {
            $airline = $this->http->FindSingleNode("./preceding::tr[count(descendant::td)>1][1]/descendant::td[not(.//td)][string-length(normalize-space(.))>2][1]",
                $node, true, "#(.+?)\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+#");

            if (!empty($airline)) {
                $rl = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'{$airline}') and contains(.,':')]/following::text()[normalize-space(.)!=''][1]");
            }

            if (empty($rl)) {
                $rl = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Vendor'))} and contains(.,'{$airline}')]/following::text()[normalize-space(.)!=''][1]",
                    null, true, "#{$this->opt($this->t('Reservation Number'))}[\s:]+([A-Z\d]+)#");
            }

            if (empty($rl)) {
                $airs[$tripNum][] = $node;
            } else {
                $airs[$rl][] = $node;
            }
        }

        foreach ($airs as $rl => $roots) {
            $f = $email->add()->flight();

            if ($rl == $tripNum) {
                $f->general()
                    ->noConfirmation();
            } else {
                $f->general()
                    ->confirmation($rl);
            }
            $f->general()
                ->traveller($pax);

            foreach ($roots as $root) {
                $s = $f->addSegment();
                $s->extra()
                    ->status($this->http->FindSingleNode("./preceding::tr[count(descendant::td)>1][1]/descendant::td[not(.//td)][string-length(normalize-space(.))>2][2]",
                        $root));

                $node = $this->http->FindSingleNode("./preceding::tr[count(descendant::td)>1][1]/descendant::td[not(.//td)][string-length(normalize-space(.))>2][1]",
                    $root, true, "#.+?\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+.*)#");

                if (preg_match("#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)[\s\-]*(?:{$this->opt($this->t('Operated by'))}[\s:]+(.+))?#",
                    $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->airline()
                            ->operator($m[3]);
                    }
                }
                $node = implode("\n",
                    $this->http->FindNodes("./descendant::td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']",
                        $root));

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)\s+(?:Terminal[\s:]+(.+)\n)?(.+)\s(\d+:\d+(?:\s*[AaPp][Mm])?)\s+(.+)#",
                    $node, $m)) {
                    $s->departure()
                        ->code($m[2])
                        ->date(strtotime($this->normalizeDate($m[6] . ' ' . $m[5])));

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->departure()
                            ->terminal($m[3]);
                    }
                    $depName = $m[1] . ', ' . $m[4];
                }
                $node = implode("\n",
                    $this->http->FindNodes("./descendant::td[string-length(normalize-space(.))>2][2]//text()[normalize-space(.)!='']",
                        $root));

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)\s+(?:Terminal[\s:]+(.+)\n)?(.+)\s(\d+:\d+(?:\s*[AaPp][Mm])?)\s+(.+)#",
                    $node, $m)) {
                    $s->arrival()
                        ->code($m[2])
                        ->date(strtotime($this->normalizeDate($m[6] . ' ' . $m[5])));

                    if (isset($m[3]) && !empty($m[3])) {
                        $s->arrival()
                            ->terminal($m[3]);
                    }
                    //not collect like: $seg['DepName'] = 'Airport' and $seg['ArrName'] = 'Airport'
                    $arrName = $m[1] . ', ' . $m[4];

                    if (isset($depName) && $depName !== $arrName) {
                        $s->departure()
                            ->name($depName);
                        $s->arrival()
                            ->name($arrName);
                    }
                }

                $s->extra()
                    ->aircraft($this->http->FindSingleNode("./following::tr[1]//text()[{$this->starts($this->t('Aircraft'))}]",
                        $root, true, "#{$this->opt($this->t('Aircraft'))}[\s:]+(.+)#"), false, true)
                    ->cabin($this->http->FindSingleNode("./following::tr[1]//text()[{$this->starts($this->t('Cabin'))}]",
                        $root, true, "#{$this->opt($this->t('Cabin'))}[\s:]+(.+)#"), false, true);
                $node = $this->http->FindSingleNode("./following::tr[1]//text()[{$this->starts($this->t('Seat(s)'))}]",
                    $root, true, "#{$this->opt($this->t('Seat(s)'))}[\s:]+(.+)#");

                if (!empty($node) && preg_match_all("#\s*(\d+[A-Z])\s*#i", $node, $m)) {
                    $s->extra()->seats($m[1]);
                }

                if ($this->http->XPath->query("./following::tr[1]//text()[starts-with(normalize-space(.),'Non-stop')]",
                        $root)->length > 0
                ) {
                    $s->extra()->stops(0);
                }
            }
        }

        if (count($airs) == 1 && isset($f)) {
            $tot = $this->getTotalCurrency(implode(" ",
                $this->http->FindNodes("//text()[{$this->eq($this->t('_flights'))}]/ancestor::tr[1]//text()[normalize-space(.)!='']")));

            if (!empty($tot['Total'])) {
                $f->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        return true;
    }

    private function parseRails(Email $email)
    {
        // examples: it-11474754.eml, it-31271501.eml

        $segments = $this->http->XPath->query("//text()[{$this->starts($this->t('Description: Rail Trip'))} or {$this->starts($this->t('Number of car:'))} or contains(.,'renfevav-ip')]/ancestor::table[contains(.,'>')][1]/descendant::tr[count(descendant::td[not(.//td)])=3 and normalize-space(descendant::td[2]/descendant::text()[normalize-space(.)!=''][1])='>']");

        if ($segments->length === 0) {
            return true; //just no rail - not fail
        }

        $r = $email->add()->train();

        $pax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Trip Plan for'))} or {$this->starts($this->t('Reservation cancelled for'))}]/following::text()[normalize-space(.)][1]");
        $r->general()->traveller($pax);

        $confirmationNumber = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Confirmation Number'))}]", $segments[0], true, "#:\s*([A-Z\d]+)#");

        if ($confirmationNumber) {
            $r->general()->confirmation($confirmationNumber);
        } else {
            $r->general()->noConfirmation();
        }

        foreach ($segments as $root) {
            $s = $r->addSegment();

            $xpathFragmentRowBefore = "./ancestor::tr[ ./preceding-sibling::tr or ./following-sibling::tr ][1]/preceding-sibling::tr[1]/descendant::tr[ ./td[3] ][1]";

            // serviceName
            // number
            $train = $this->http->FindSingleNode($xpathFragmentRowBefore . "/*[3]", $root);

            if (preg_match('/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+(\d+)$/', $train, $m)) {
                // TGV Duplex 5284
                $s->extra()
                    ->service($m[1])
                    ->number($m[2])
                ;
            } else {
                $s->extra()->noNumber();
            }

            // status
            $status = $this->http->FindSingleNode($xpathFragmentRowBefore . "/*[4]", $root);

            if ($status) {
                $s->extra()->status($status);
            }

            // depName
            // depDate
            $node = implode("\n",
                $this->http->FindNodes("./descendant::td[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']",
                    $root));

            if (preg_match("#(.+)\s+(\d+:\d+(?:\s*[AaPp][Mm])?)\s+(.+)#", $node, $m)) {
                $s->departure()
                    ->name(trim($m[1]))
                    ->date(strtotime($this->normalizeDate($m[3] . ' ' . $m[2])));
            }

            // arrName
            // arrDate
            $node = implode("\n",
                $this->http->FindNodes("./descendant::td[string-length(normalize-space(.))>2][2]//text()[normalize-space(.)!='']",
                    $root));

            if (preg_match("#(.+)\s+(\d+:\d+(?:\s*[AaPp][Mm])?)\s+(.+)#", $node, $m)) {
                $s->arrival()
                    ->name(trim($m[1]))
                    ->date(strtotime($this->normalizeDate($m[3] . ' ' . $m[2])));
            }

            $xpathFragmentRowAfter = "./ancestor::tr[ ./preceding-sibling::tr or ./following-sibling::tr ][1]/following-sibling::tr[1]";

            // cabin
            $class = $this->http->FindSingleNode($xpathFragmentRowAfter . "/descendant::text()[{$this->starts($this->t('Class of service:'))}]", $root, true, "/{$this->opt($this->t('Class of service:'))}\s*(.+)/");

            if ($class) {
                $s->extra()->cabin($class);
            }

            // carNumber
            $carNumber = $this->http->FindSingleNode($xpathFragmentRowAfter . "/descendant::text()[{$this->starts($this->t('Number of car:'))}]", $root, true, "/{$this->opt($this->t('Number of car:'))}\s*(\d+)$/");

            if ($carNumber) {
                $s->extra()->car($carNumber);
            }

            // seats
            $seats = $this->http->FindNodes($xpathFragmentRowAfter . "/descendant::text()[{$this->starts($this->t('Number of place:'))}]", $root, "/{$this->opt($this->t('Number of place:'))}\s*(\d+)$/");
            $seats = array_filter($seats);

            if (count($seats)) {
                $s->extra()->seats($seats);
            }
        }

        if (count($email->getItineraries()) == 1) {
            $tot = $this->getTotalCurrency(implode(" ",
                $this->http->FindNodes("//text()[{$this->eq($this->t('Rail Trip'))}]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]")));

            if (!empty($tot['Total'])) {
                $email->getItineraries()[0]
                    ->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        return true;
    }

    private function parseHotels(Email $email)
    {
        $pax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Trip Plan for'))} or {$this->starts($this->t('Reservation cancelled for'))}]/following::text()[normalize-space(.)!=''][1]");

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Check-in'))}]/ancestor::table[{$this->contains($this->t('Confirmation code'))}][1]");

        if ($nodes->length == 0) {
            $this->logger->debug("no hotels found");

            return true;
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();
            $h->general()
                ->traveller($pax)
                ->status($this->http->FindSingleNode("(./descendant::tr[1]/descendant::td[normalize-space(.)!=''][1]//text()[normalize-space(.)!=''])[last()]",
                    $root))
                ->confirmation($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[3]//text()[{$this->contains($this->t('Confirmation code'))}]",
                    $root, null, "#{$this->opt($this->t('Confirmation code'))}[\s:]+(.+)#"));

            $h->hotel()
                ->name($this->http->FindSingleNode("(./descendant::tr[1]/following-sibling::tr[1]/descendant::td[normalize-space(.)!=''][1]//text()[normalize-space(.)!=''])[position() = 1]",
                    $root))
                ->address(implode(", ",
                    $this->http->FindNodes("(./descendant::tr[1]/following-sibling::tr[1]/descendant::td[normalize-space(.)!=''][1]//text()[normalize-space(.)!=''])[position() > 1]",
                        $root)));

            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[2]/descendant::td[not(.//td) and string-length(normalize-space(.))>2][1]//text()[normalize-space(.)!=''][2]",
                    $root))))
                ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[2]/descendant::td[not(.//td) and string-length(normalize-space(.))>2][2]//text()[normalize-space(.)!=''][2]",
                    $root))));

            $room = $h->addRoom();

            $room->setRate($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[3]//text()[{$this->contains($this->t('Lowest nightly rate offered'))}]",
                $root, null, "#{$this->opt($this->t('Lowest nightly rate offered'))}[\s:]+(.+)#"), false, true);

            $roomType = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[3]//text()[{$this->contains($this->t('Room Type'))}]",
                $root, null, "#{$this->opt($this->t('Room Type'))}[\s:]+(.+)#");
            $node = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[3]//text()[{$this->contains($this->t('Room Type'))}]/ancestor::*[{$this->contains($this->t('RoomTypeDescription'))}][1]",
                $root);

            if (!empty($node)) {
                $roomTypeDescription = $this->http->FindPreg("#{$this->opt($this->t('Room Type'))}[\s:]+(.+?)\s*{$this->opt($this->t('RoomTypeDescription'))}#su",
                    false, $node);
            } else {
                $node = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[3]//text()[{$this->contains($this->t('Room Type'))}]/following::text()[normalize-space(.)!=''][1][not({$this->contains($this->t('RoomTypeDescription'))})]",
                    $root);

                if (!empty($node)) {
                    $roomTypeDescription = $node;
                }
            }

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (isset($roomTypeDescription)) {
                $room->setDescription($roomTypeDescription);
            } else {
                $roomTypeDescription = implode("; ",
                    $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[3]//text()[{$this->contains($this->t('RoomTypeDescription'))}]",
                        $root, "#{$this->opt($this->t('RoomTypeDescription'))}[\s:]+(.+)#"));

                if (!empty($roomTypeDescription)) {
                    $room->setDescription($roomTypeDescription);
                }
            }

            if (!$phone = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[3]//text()[{$this->contains($this->t('Tel:'))}]",
                $root, null, "#{$this->opt($this->t('Tel:'))}[\s:]+(.+)#")
            ) {
                $phone = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[3]//text()[{$this->contains($this->t('Tel:'))}]/following::*[1][name()='a']",
                    $root);
            }

            if (!empty($phone)) {
                $h->hotel()->phone($phone);
            }
            $fax = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[3]//text()[{$this->contains($this->t('Fax:'))}]",
                $root, null, "#{$this->opt($this->t('Fax:'))}[\s:]+(.+)#");

            if (!empty($fax)) {
                $h->hotel()->fax($fax);
            }

            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./preceding::tr[normalize-space(.)!=''][1]//td[normalize-space(.)!=''][2]",
                $root));

            if (!empty($tot['Total'])) {
                $h->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $h->general()
                ->cancellation(str_replace('Cancellation policy:', '', $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[3]//text()[{$this->contains($this->t('Cancellation policy'))}]",
                $root)), true, true);

            if (!empty($node = $h->getCancellation())) {
                $this->detectDeadLine($h, $node);
            }
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        $year = date('Y', $h->getCheckInDate());
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#Anuluj przed ([\w\-]+, \w+ \d+, \d{4}) (\d+:\d+)(?::00)? lokalnego czasu hotelu, aby uniknąć opłaty 100% za następującą liczbę nocy#iu",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m[1]) . ', ' . $m[2]));
        } elseif (preg_match('/No cancellation charge applies prior to\s+(\d{1,2}:\d{2})/', $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1], $h->getCheckInDate()));
        } elseif (preg_match('/after\s+(\d{1,2})\s*(\d{2})\s+(\d{1,2})\s*(\w+)\s+forfeit first nite stay/', $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[3] . ' ' . $m[4] . ' ' . $year . ', ' . $m[1] . ':' . $m[2]));
        } elseif (preg_match('/Cancel by\s+(\d{1,2}[pa]m)\s+hotel time\s+(\d{1,2} hours)\s+prior to ar/', $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[2], $m[1]);
        } elseif (preg_match('/aftr\s+(\d{1,2})\s*([a-z]+)\s*(\d{1,2})\w+\s+(\d{1,2}:\d{2})/', $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[4]));
        } elseif (preg_match('/after (\d{1,2})(\d{2})\s+(\d{1,2})\s*([a-z]+)\s+forfeit one nite stay/', $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[3] . ' ' . $m[4] . ' ' . $year . ', ' . $m[1] . ':' . $m[2]));
        } elseif (preg_match('/No charge for cancellation up to the day of arrival,\s+(\d{1,2}[ap]m)/', $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('1 day', $m[1]);
        } elseif (preg_match('/Cancel by\s+\w+, (\w+) (\d{1,2}), (\d{2,4}) (\d{1,2}:\d{2})(?:\:\d{1,2})? local hotel time to avoid/', $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3] . ', ' . $m[4]));
        } elseif (preg_match('/(\d{1,2})\-(H|D) prior to (\d{1,2}\-\d{2})h local time\s*to avoid/', $cancellationText, $m)) {
            $prior = str_replace(['H', 'D'], ['hour', 'day'], $m[2]);
            $h->booked()
                ->deadlineRelative($m[1] . ' ' . $prior, str_replace('-', ':', $m[3]));
        } elseif (preg_match('/Prior to (\d{1,2})\-p local timeto avoid charge/', $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ':00');
        } elseif (preg_match('/cancellations must be made by (\d{1,2}[ap]m)/', $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }
    }

    private function parseCars(Email $email)
    {
        $pax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Trip Plan for'))} or {$this->starts($this->t('Reservation cancelled for'))}]/following::text()[normalize-space(.)!=''][1]");

        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('Opening Hours'))}]/ancestor::table[{$this->contains($this->t('Confirmation code'))}][1] | //text()[{$this->contains($this->t('for car rental'))}]/ancestor::tr[1]/following::tr[1]/descendant::table[1]");

        if ($nodes->length == 0) {
            $this->logger->debug("no cars found");

            return true;
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation(preg_replace("#\s+#", '-',
                    $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[2]//text()[{$this->contains($this->t('Confirmation code'))}]",
                        $root, null, "#{$this->opt($this->t('Confirmation code'))}[\s:]+(.+)#")))
                ->traveller($pax)
                ->status($this->http->FindSingleNode("./descendant::tr[1]/descendant::td[not(.//td) and string-length(normalize-space(.))>2][2]",
                    $root));

            $r->extra()
                ->company($this->http->FindSingleNode("./descendant::tr[1]/descendant::td[not(.//td) and string-length(normalize-space(.))>2][1]",
                    $root));

            $node = implode("\n",
                $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[1]/descendant::td[not(.//td) and string-length(normalize-space(.))>2][1]//text()[normalize-space(.)!='']",
                    $root));

            if (preg_match("#(.+?)\s*(?:{$this->opt($this->t('Tel:'))}[\s:]+(.+?)\n)?\s*(?:{$this->opt($this->t('Fax:'))}[\s:]+(.+?)\n)?\s*(?:{$this->opt($this->t('Opening Hours'))}[\s:]+(.+?))?\n\s*(\d+:\d+(?:\s*[AP]M)?)\s+(.+)#is",
                $node, $m)) {
                $r->pickup()
                    ->location(str_replace("\n", ' ', $m[1]))
                    ->date(strtotime($this->normalizeDate($m[6] . ' ' . $m[5])));

                if (isset($m[2]) && !empty($m[2])) {
                    $r->pickup()
                        ->phone($m[2]);
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $r->pickup()
                        ->fax($m[3]);
                }

                if (isset($m[4]) && !empty($m[4])) {
                    $r->pickup()
                        ->openingHours($m[4]);
                }
            }
            $node = implode("\n",
                $this->http->FindNodes("./descendant::tr[1]/following-sibling::tr[1]/descendant::td[not(.//td) and string-length(normalize-space(.))>2][2]//text()[normalize-space(.)!='']",
                    $root));

            if (preg_match("#(.+?)\s*(?:{$this->opt($this->t('Tel:'))}[\s:]+(.+?)\n)?\s*(?:{$this->opt($this->t('Fax:'))}[\s:]+(.+?)\n)?\s*(?:{$this->opt($this->t('Opening Hours'))}[\s:]+(.+?))?\n\s*(\d+:\d+(?:\s*[AP]M)?)\s+(.+)#is",
                $node, $m)) {
                $r->dropoff()
                    ->location(str_replace("\n", ' ', $m[1]))
                    ->date(strtotime($this->normalizeDate($m[6] . ' ' . $m[5])));

                if (isset($m[2]) && !empty($m[2])) {
                    $r->dropoff()
                        ->phone($m[2]);
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $r->dropoff()
                        ->fax($m[3]);
                }

                if (isset($m[4]) && !empty($m[4])) {
                    $r->dropoff()
                        ->openingHours($m[4]);
                }
            }

            $r->car()
                ->type($this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[2]/descendant::text()[{$this->contains($this->t('Vehicle type'))}]",
                    $root, true, "#{$this->opt($this->t('Vehicle type'))}[\s:]+(.+)#"));
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./preceding::tr[normalize-space(.)!=''][1]//td[normalize-space(.)!=''][2]",
                $root));

            if (!empty($tot['Total'])) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    $re = (array) $reBody[1];

                    foreach ($re as $r) {
                        if (stripos($body, $r) !== false) {
                            $this->lang = substr($lang, 0, 2);

                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        //		 $this->logger->info($date);
        $in = [
            // 05/17/2010 at 10:30 P.M.
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*([ap])[\.\s]?(m)[\.\s]?\s*$#i',
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*$#i',
            // 10:30 P.M.
            '#^\s*(\d+:\d+)\s*([ap])[\.\s]?(m)[\.\s]?\s*$#i',
            //Thursday, January 1, 2009
            '#^\s*\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*$#',
            //Thu, Apr 14, 2016 at 08:02 AM
            '#^\s*\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*([ap])[\.\s]?(m)[\.\s]?\s*$#i',
            '#^\s*\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*$#i',
            //March 29, 2014 - 11:00am
            '#^\s*(\w+)\s+(\d+),\s+(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*([ap])[\.\s]?(m)[\.\s]?\s*$#i',
            '#^\s*(\w+)\s+(\d+),\s+(\d{4})\s*(?:at|\-)\s*(\d+:\d+)\s*$#i',
            //Sonntag, 10. August 2014            //Sexta-feira, 7. Abril 2017
            '#^[\w\-]+,\s+(\d+)\.?\s+(\w+)\s+(\d+)$#ui',
            '#^[\w\-]+,\s+(\d+)\.?\s+(\w+)\s+(\d+)\s+(\d+:\d+(?:\s*[ap]m)?)$#ui',
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //Vendredi 16 Mai 2014
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4}) (\d+:\d+)$#", //Vendredi 16 Mai 2014 08:30
            "#^[^\s\d]+,\s*(\d+)\s+de\s+([^\s\d]+)\s+de\s+(\d{4})\s+(\d+:\d+)$#", //Miércoles, 06 de Julio de 2016 06:50
            "#^[^\s\d]+,\s*(\d+)\s+de\s+([^\s\d]+)\s+de\s+(\d{4})$#", //Miércoles, 06 de Julio de 2016
        ];
        $out = [
            '$3-$1-$2 $4 $5$6',
            '$3-$1-$2 $4',
            '$1 $2$3',
            '$2 $1 $3',
            '$2 $1 $3 $4 $5$6',
            '$2 $1 $3 $4',
            '$2 $1 $3 $4 $5$6',
            '$2 $1 $3 $4',
            '$1 $2 $3',
            '$1 $2 $3 $4',
            '$1',
            '$1, $2',
            '$1 $2 $3 $4',
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("Pond Sterling", "GBP", $node);
        $node = str_replace("U.S. Dollar", "USD", $node);
        $node = str_replace("Euro", "EUR", $node);
        $node = str_replace("KANADISCHE DOLLAR", "CAD", $node);
        $node = str_replace('Swiss Franc', 'CHF', $node);
        $node = str_replace('Thailand Baht', 'THD', $node);
        $node = str_replace('Singapore Dollar', 'SGD', $node);
        $node = str_replace('Thai Baht', 'THB', $node);
        $node = str_replace('Hong Kong Dollar', 'HKD', $node);
        $node = str_replace('Indian Rupee', 'INR', $node);
        $node = str_replace('Indonesian Rupiah', 'IDR', $node);

        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;
        $str = str_replace(")", "\)", str_replace("(", "\(", implode("|", $field)));

        return '(?:' . $str . ')';
    }
}
