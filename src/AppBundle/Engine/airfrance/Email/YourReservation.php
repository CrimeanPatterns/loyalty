<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-21854533.eml, airfrance/it-22027233.eml, airfrance/it-22908697.eml, airfrance/it-4808186.eml, airfrance/it-5079051.eml, airfrance/it-5532969.eml, airfrance/it-578056091.eml, airfrance/it-6648419.eml, airfrance/it-7002216.eml, airfrance/it-7064949.eml, airfrance/it-7087577.eml, airfrance/it-7093977.eml, airfrance/it-7101772.eml, airfrance/it-7141157.eml, airfrance/it-7165049.eml, airfrance/it-8653456.eml, airfrance/it-8670574.eml, airfrance/it-8764369.eml";

    public static $dictionary = [
        'en' => [
            //	        'Departing on' => '',
            'Booking reference'   => ['Booking reference', 'BOOKING CONFIRMED'],
            'YOUR FLIGHT DETAILS' => ['YOUR FLIGHT DETAILS', 'Your flight details'],
            'Reservation on hold' => ['Reservation on hold', 'Booking on hold'],
            //	        'on hold' => '',
            //	        'RESERVATION CONFIRMED' => '',
            //	        'PAYMENT NOT CONFIRMED' => '',
            //	        'CONFIRMED' => '',
            'Flight' => ['Flight', 'flight'],
            //	        'Operated by' => '',
            'Passenger(s)' => ['Passenger(s)', 'PASSENGER(S)'],
            //	        'Card' => '',
            // 'Miles' => '',
            'Payment'                => ['PAYMENT', 'Payment'],
            'Total excl. tax'        => ['Total excl. tax', 'Total (not including tax)'],
            'Taxes and issuance fee' => ['Taxes and issuance fee', 'Taxes'],
            'Total amount to pay'    => ['Total amount to pay', 'Total :'],
        ],
        'fr' => [
            'Departing on'          => 'Départ le',
            'Booking reference'     => 'Référence de réservation',
            'YOUR FLIGHT DETAILS'   => ['DÉTAIL DE VOS VOLS', 'Détail de vos vols'],
            'Reservation on hold'   => 'Réservation en attente',
            'on hold'               => 'en attente',
            'RESERVATION CONFIRMED' => 'RÉSERVATION CONFIRMÉE',
            //	        'PAYMENT NOT CONFIRMED' => '',
            'CONFIRMED'              => 'CONFIRMÉE',
            'Flight'                 => ['Vol', 'vol'],
            'Operated by'            => 'Effectué par',
            'Passenger(s)'           => ['Passager(s)', 'PASSAGER(S)'],
            'Card'                   => 'Carte',
            // 'Miles' => '',
            'Payment'                => ['Paiement', 'PAIEMENT'],
            'Total excl. tax'        => ['Tarif hors taxes', 'Montant HT'],
            'Taxes and issuance fee' => ['Taxes et surcharges', 'Montant taxes', 'Frais d\'émission'],
            'Total amount to pay'    => ['Total à payer', 'Total'],
        ],
        'es' => [
            'Departing on'        => 'Salida el',
            'Booking reference'   => 'Referencia de la reserva',
            'YOUR FLIGHT DETAILS' => ['INFORMACIÓN DE SUS VUELOS', 'Información detallada de sus vuelos'],
            //	        'Reservation on hold' => '',
            //	        'on hold' => '',
            'RESERVATION CONFIRMED' => 'RESERVA CONFIRMADA',
            //	        'PAYMENT NOT CONFIRMED' => '',
            //	        'CONFIRMED' => '',
            'Flight'                 => ['Vuelo', 'vuelo'],
            'Operated by'            => 'Operado por',
            'Passenger(s)'           => ['Pasajero(s)', 'PASAJERO(S)'],
            'Card'                   => 'Tarjeta',
            'Miles'                  => 'Millas',
            'Payment'                => ['Pago', 'PAGO'],
            'Total excl. tax'        => ['Total sin tasas', 'Tarifa sin tasas', 'Importe sin tasas'],
            'Taxes and issuance fee' => ['Tasas y gastos de emisión', 'Tasas'],
            'Total amount to pay'    => ['Total a pagar', 'Total :'],
        ],
        'nl' => [
            'Departing on'        => 'Vertrek op',
            'Booking reference'   => 'Boekingsreferentie',
            'YOUR FLIGHT DETAILS' => ['Vluchtgegevens'],
            //	        'Reservation on hold' => '',
            //	        'on hold' => '',
            //	        'RESERVATION CONFIRMED' => '',
            //	        'PAYMENT NOT CONFIRMED' => '',
            //	        'CONFIRMED' => '',
            'Flight' => ['Vlucht', 'vlucht'],
            //	        'Operated by' => '',
            'Passenger(s)' => 'Passagier(s)',
            //	        'Card' => '',
            // 'Miles' => '',
            'Payment'                => 'Betaling',
            'Total excl. tax'        => ['Bedrag excl. belastingen'],
            'Taxes and issuance fee' => ['Belastingen'],
            'Total amount to pay'    => ['Totaal'],
        ],
        'pt' => [
            'Departing on'        => ['Ida dia', 'Partida a'],
            'Booking reference'   => ['Código da reserva', 'Referência de reserva', 'Referência da reserva'],
            'YOUR FLIGHT DETAILS' => ['Detalhes de seus voos', 'Detalhe dos seus voos', 'DETALHE DOS SEUS VOOS'],
            //	        'Reservation on hold' => '',
            //	        'on hold' => '',
            'RESERVATION CONFIRMED' => 'RESERVA CONFIRMADA',
            //	        'PAYMENT NOT CONFIRMED' => '',
            'CONFIRMED'              => 'CONFIRMADA',
            'Flight'                 => ['Voo', 'voo'],
            'Operated by'            => 'Efetuado pela',
            'Passenger(s)'           => 'Passageiro(s)',
            'Card'                   => ['Cartão', 'Número Flying Blue'],
            'Miles'                  => 'Milhas',
            'Payment'                => 'Pagamento',
            'Total excl. tax'        => ['Valor sem taxas', 'Total sem taxas'],
            'Taxes and issuance fee' => 'Taxas',
            'Total amount to pay'    => 'Total',
        ],
        'it' => [
            'Departing on'        => 'Partenza il',
            'Booking reference'   => 'Codice di prenotazione',
            'YOUR FLIGHT DETAILS' => ['Dettaglio dei suoi voli'],
            //	        'Reservation on hold' => '',
            //	        'on hold' => '',
            //	        'RESERVATION CONFIRMED' => '',
            //	        'PAYMENT NOT CONFIRMED' => '',
            //	        'CONFIRMED' => '',
            'Flight' => ['Volo', 'volo'],
            //	        'Operated by' => '',
            'Passenger(s)'           => 'Passeggero/i',
            'Card'                   => 'Carta',
            // 'Miles' => '',
            'Payment'                => 'Pagamento',
            'Total excl. tax'        => ['Importo IVA esclusa'],
            'Taxes and issuance fee' => ['Tasse'],
            'Total amount to pay'    => ['Totale'],
        ],
        'de' => [
            'Departing on'        => 'Abflug am',
            'Booking reference'   => ['Buchungscode', 'Buchungs code'],
            'YOUR FLIGHT DETAILS' => ['Ihre Fluginformationen'],
            //	        'Reservation on hold' => '',
            //	        'on hold' => '',
            'RESERVATION CONFIRMED' => 'BESTÄTIGTE BUCHUNG',
            //	        'PAYMENT NOT CONFIRMED' => '',
            //	        'CONFIRMED' => '',
            'Flight' => ['Flug', 'flug'],
            //	        'Operated by' => '',
            'Passenger(s)'           => 'Passagier(e)',
            'Card'                   => 'Karte',
            'Miles'                  => 'Meilen',
            'Payment'                => 'Zahlung',
            'Total excl. tax'        => ['Preis ohne Steuern und Gebühren'],
            'Taxes and issuance fee' => ['Steuern und Gebühren', '1. zusätzliches Gepäckstück', 'Sitzplatzreservierung', 'Reiseversicherungspaket'],
            'Total amount to pay'    => 'Gesamt',
        ],
        'ja' => [
            'Departing on'        => '出発',
            'Booking reference'   => 'ご予約番号',
            'YOUR FLIGHT DETAILS' => 'ご利用便の詳細',
            //	        'Reservation on hold' => '',
            //	        'on hold' => '',
            'RESERVATION CONFIRMED' => 'ご予約が確定しました',
            //	        'PAYMENT NOT CONFIRMED' => '',
            //	        'CONFIRMED' => '',
            'Terminal'     => ['ターミナル'],
            'Flight'       => ['フライト', 'フライト'],
            //	        'Operated by' => '',
            'Passenger(s)' => '搭乗者',
            'Card'         => '会員番号',
            // 'Miles' => '',
            'Payment'                => 'お支払い',
            'Total excl. tax'        => '税抜き金額 :',
            'Taxes and issuance fee' => '諸税 :',
            'Total amount to pay'    => '合計',
        ],
        'pl' => [
            'Departing on'        => 'Wylot dnia',
            'Booking reference'   => 'Numer referencyjny rezerwacji',
            'YOUR FLIGHT DETAILS' => 'SZCZEGÓŁY DOTYCZĄCE TWOICH LOTÓW',
            //	        'Reservation on hold' => '',
            //	        'on hold' => '',
            //	        'RESERVATION CONFIRMED' => '',
            //	        'PAYMENT NOT CONFIRMED' => '',
            //	        'CONFIRMED' => '',
            // 'Terminal'     => [''],
            'Flight' => ['Lot', 'lot'],
            //	        'Operated by' => '',
            'Passenger(s)' => 'Pasażer',
            //	        'Card' => '',
            // 'Miles' => '',
            'Payment'                => 'Płatność',
            'Total excl. tax'        => 'Kwota bez podatków',
            'Taxes and issuance fee' => 'Podatki',
            'Total amount to pay'    => 'Razem',
        ],
        'ru' => [
            'Departing on'        => 'Вылет',
            'Booking reference'   => ['Номер бронирования'],
            'YOUR FLIGHT DETAILS' => ['Информация о Ваших рейсах', 'ИНФОРМАЦИЯ О ВАШИХ РЕЙСАХ'],
            //            'Reservation on hold' => [''],
            //	        'on hold' => '',
            'RESERVATION CONFIRMED' => 'БРОНИРОВАНИЕ ПОДТВЕРЖДЕНО',
            //	        'PAYMENT NOT CONFIRMED' => '',
            'CONFIRMED'    => 'ПОДТВЕРЖДЕНО',
            'Terminal'     => ['Терминал'],
            'Flight'       => ['Рейс', 'рейс'],
            'Operated by'  => 'Выполняется',
            'Passenger(s)' => ['Пассажир(ы)', 'ПАССАЖИР(Ы)'],
            //	        'Card' => '',
            // 'Miles' => '',
            'Payment'                => ['Оплата', 'ОПЛАТА'],
            'Total excl. tax'        => ['Сумма за вычетом налогов'],
            'Taxes and issuance fee' => ['Налоги'],
            'Total amount to pay'    => ['Всего :'],
        ],
        'ko' => [
            'Departing on'        => '시 출발',
            'Booking reference'   => ['예약 번호'],
            // 'YOUR FLIGHT DETAILS' => ['Информация о Ваших рейсах', 'ИНФОРМАЦИЯ О ВАШИХ РЕЙСАХ'],
            //            'Reservation on hold' => [''],
            //	        'on hold' => '',
            'RESERVATION CONFIRMED' => '고객님의 예약',
            //	        'PAYMENT NOT CONFIRMED' => '',
            // 'CONFIRMED'    => 'ПОДТВЕРЖДЕНО',
            'Terminal'               => ['터미널'],
            'Flight'                 => ['항공편'],
            'Operated by'            => '운항편',
            'Passenger(s)'           => ['승객'],
            'Card'                   => '회원 번호',
            'Miles'                  => '마일',
            'Payment'                => ['결제'],
            'Total excl. tax'        => ['세전 총액:'],
            'Taxes and issuance fee' => ['세금:'],
            'Total amount to pay'    => ['합계 :'],
        ],
    ];

    private $lang = "en";

    private $detectSubjectContainsProviderName = [
        // en
        'Your Air France reservation',
        'Your Air France booking is pending payment',
        // fr
        'Votre réservation Air France',
    ];
    private $detectSubject = [
        // en
        'Booking confirmed - ',
        // fr
        'Réservation confirmée - ',
        // ru
        'Бронирование подтверждено - ',
        // ko
        '예약 확인 -',
        // es, pt
        'Reserva confirmada - ',
    ];
    private static $langDetectors = [
        'en' => [
            'Thank you for booking a trip on our website',
            'Thank you for booking a flight on our website',
            'Thank you for reserving a flight on our website',
            'This is the confirmation e-mail for my booking',
            'This is the confirmation e-mail for your booking',
            'This is the confirmation e-mail for your reservation',
            'We have processed your booking but we have not yet received confirmation of your payment',
        ],
        'fr' => [
            'Vous venez de réserver un voyage sur notre site et nous vous en remercions',
            'Détail de vos vols',
            'VOTRE RÉSERVATION',
        ],
        'es' => [
            'Acaba de reservar un viaje en nuestro sitio web',
            'Este es el mensaje de confirmación de su reserva',
            'En primer lugar, permítanos agradecerle la reserva que acaba',
        ],
        'nl' => [
            'Dit is de bevestigingsmail van uw boeking',
        ],
        'pt' => [
            'Desejamos a você uma ótima viagem',
            'Desejamos-lhe uma óptima viagem',
            'Este e-mail confirma sua reserva',
        ],
        'it' => [
            'Le auguriamo buon viaggio',
        ],
        'de' => [
            'Wir wünschen eine angenehme Reise',
        ],
        'ja' => [
            'ご利用便の詳細',
        ],
        'pl' => [
            'Szczegóły dotyczące Twoich lotów',
        ],
        'ru' => [
            'Направляем Вам подтверждение Вашего бронирования',
        ],
        'ko' => [
            '이것은 예약 확인 이메일입니다',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubjectContainsProviderName as $phrase) {
            if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        if ($this->detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubject as $phrase) {
            if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a/@href[{$this->contains(['.service-airfrance.com/', '.airfrance.fr/', 'content.service-airfrance.com', 'www.airfrance.fr', 'airfrance.us', '.airfrance.es%2F', '.airfrance.fr%2F'])}]")->length === 0
            || $this->http->XPath->query("//a/@href[{$this->contains(['.service-airfrance.com/', '.airfrance.fr/', 'content.service-airfrance.com', 'www.airfrance.fr', 'airfrance.us', '.airfrance.es%2F', '.airfrance.fr%2F'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@service-airfrance.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->ParseEmail($email);
        $email->setType('YourReservation' . ucfirst($this->lang));

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function ParseEmail(Email $email): void
    {
        $flight = $email->add()->flight();

        $year = $this->http->FindSingleNode('(.//td[(' . $this->starts($this->t('Departing on')) . ') and not(.//td)])[1]', null, true, '/\d{1,2}\/\d{2}\/(\d{4})/');

        if ($this->lang === 'ko') {
            $year = $this->http->FindSingleNode('(.//td[(' . $this->contains($this->t('Departing on')) . ') and not(.//td)])[1]', null, true, '/\d{1,2}\/\d{2}\/(\d{4}).*' . $this->preg_implode($this->t('Departing on')) . '/u');
        }

        $ConfNumber = $this->http->FindSingleNode('.//text()[' . $this->starts($this->t('Booking reference')) . ']/ancestor::table[1]/following-sibling::table[1]//tr//strong', null, true, '/^([A-Z\d]{6})$/');

        if (empty($ConfNumber)) {
            $ConfNumber = $this->http->FindSingleNode('.//text()[' . $this->starts($this->t('Booking reference')) . ']/following::text()[normalize-space(.)!=""][1]',
                null, true, '/^([A-Z\d]{6})$/');

            if (empty($ConfNumber)) {
                $ConfNumber = $this->http->FindSingleNode('.//tr[(' . $this->starts($this->t('Booking reference')) . ') and not(.//tr)]/following-sibling::tr[normalize-space(.)!=""][1]',
                    null, true, '/^([A-Z\d]{6})$/');
            }

            if (empty($ConfNumber)) {
                $confs = array_unique(array_filter($this->http->FindNodes('.//text()[' . $this->starts($this->t('Booking reference')) . ']/following::text()[normalize-space(.)!=""][1]',
                    null, '/^([A-Z\d]{6})$/')));

                if (count($confs) === 1) {
                    $ConfNumber = array_shift($confs);
                }
            }
        }

        if (!empty($ConfNumber)) {
            $flight->general()->confirmation($ConfNumber);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Reservation on hold'))}]")->length > 0) {
            $flight->general()
                ->status($this->t('on hold'));
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('RESERVATION CONFIRMED'))}]")->length > 0) {
            $flight->general()
                ->status($this->t('CONFIRMED'));
        } elseif ($this->http->XPath->query("//text()[{$this->eq($this->t('PAYMENT NOT CONFIRMED'))}]")->length > 0) {
            $flight->general()
                ->status($this->t('PAYMENT NOT CONFIRMED'));
        }

        $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Passenger(s)'))}]/following::text()[contains(normalize-space(), 'Miles')]/preceding::text()[normalize-space()][1][not(contains(normalize-space(), 'Create')) and not(ancestor::a)]");

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes('//table[(' . $this->starts($this->t('Passenger(s)')) . ') and not(.//table)]/following::table[normalize-space()][1]//tr[count(./td)>1]/td[1][count(./descendant::text()[normalize-space()])=1][not(ancestor::a)]');
        }

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes('//table[(' . $this->starts($this->t('Passenger(s)')) . ') and not(.//table)]/following::table[normalize-space()][1]//tr[count(.//tr)=0]/td[1][not(.//a)]');
        }

        if (count($travellers) > 0 && preg_match("/\W+XP\W+/u", $travellers[count($travellers) - 1])) {
            unset($travellers[count($travellers) - 1]);
        }
        $travellers = array_filter(preg_replace("#.*(?:\d|(?:\w+ ){8,}).*#u", '', $travellers));

        if (count($travellers) > 0) {
            $flight->general()
                ->travellers($travellers);
        }

        $xpath = '//tr[count(*[normalize-space()])=1]/*[normalize-space()][1]/descendant-or-self::*[count(table[normalize-space()])>1 and table[normalize-space()][2][starts-with(translate(normalize-space(),"0123456789","dddddddddd"),"dd")] and ' . $this->contains($this->t('Flight')) . '][1]';
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//img[contains(@src, 'pplan')]/ancestor::td[{$this->contains($this->t('Flight'))}][1]";
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length === 0) {
            $this->logger->info("Segments not found by xpath: {$xpath}");

            return;
        }
        $this->logger->debug("Segments found by xpath: {$xpath}");

        foreach ($segments as $segment) {
            $seg = $flight->addSegment();

            $route = $this->http->FindSingleNode('./preceding::table[contains(.,"(") and contains(.,")") and contains(.,"-")][1]', $segment);
            //$route = $this->http->FindSingleNode('(./ancestor::table/preceding-sibling::table[contains(.,"(") and contains(.,")") and contains(.,"-")])[1]', $segment);
            if (preg_match('/\(([A-Z]{3})\)\s+-\s+[^(]+\s+(?:\(\w+\)[ ]*)?\(([A-Z]{3})\)/', $route, $matches)) {
                $seg->departure()
                    ->code($matches[1]);
                $seg->arrival()
                    ->code($matches[2]);
            }

            $monthAndDay = $this->normalizeMonthAndDay($this->http->FindSingleNode('(.//table)[1]', $segment));

            if ($this->http->XPath->query('(.//table[.//img])[1]//img[@alt="Economy"]', $segment)->length > 0) {
                $seg->extra()
                    ->cabin('Economy');
            }

            $pattern1 = '/^(?<time>\d{1,2}h\d{1,2})\s*(?<nextDay>\([^)]+\)|)\s*(?<name>[^\n]+\S)\s*-\s*(?:Terminal|' . $this->preg_implode($this->t("Terminal")) . ')\s+(?<terminal>[A-Z\d]{1,2})$/';
            $pattern2 = '/^(?<time>\d{1,2}h\d{1,2})\s*(?<nextDay>\([^)]+\)|)\s*(?<name>[^\n]+\S)$/';
            $departure = $this->http->FindSingleNode('.//table[normalize-space(.)="•" and not(.//table)][1]/preceding-sibling::table[1]//tr[normalize-space(.)!=""][1]', $segment);

            if (preg_match($pattern1, $departure, $matches) || preg_match($pattern2, $departure, $matches)) {
                $timeDep = $matches['time'];
                $seg->departure()
                    ->name($matches['name'])
                    ->terminal($matches['terminal'] ?? null, true, true);

                if (preg_match("/\([[:alpha:]]([+-]\d+)\)/u", $matches['nextDay'], $mday)) {
                    $nextDayDep = $mday[1] . ' day';
                } else {
                    $nextDayDep = null;
                }
            }

            $flights = $this->http->FindSingleNode('.//table[normalize-space(.)="•" and not(.//table)][1]/preceding-sibling::table[1]//tr[normalize-space(.)!=""][2]', $segment);

            if (preg_match("/{$this->preg_implode($this->t("Flight"))}\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)\b/ui", $flights, $matches)) {
                $seg->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
            }
            $operator = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Operated by'))}][1]", $segment, true,
                "/{$this->preg_implode($this->t('Operated by'))} (\S.+)/");

            if (!empty($operator)) {
                $seg->airline()
                    ->operator($operator);
            }

            $arrival = $this->http->FindSingleNode('.//table[normalize-space(.)="•" and not(.//table)][1]/preceding-sibling::table[1]//tr[normalize-space(.)!=""][3]', $segment);

            if (preg_match($pattern1, $arrival, $matches) || preg_match($pattern2, $arrival, $matches)) {
                $timeArr = $matches['time'];
                $seg->arrival()
                    ->name($matches['name'])
                    ->terminal($matches['terminal'] ?? null, true, true);

                if (preg_match("/\([[:alpha:]]([+-]\d+)\)/u", $matches['nextDay'], $mday)) {
                    $nextDayArr = $mday[1] . ' day';
                } else {
                    $nextDayArr = null;
                }
            }

            if ($year && $monthAndDay && !empty($timeDep) && !empty($timeArr)) {
                $week = null;

                if (preg_match("/^([\w\-]+),\s*(.+)/", $monthAndDay, $m)) {
                    $week = $m[1];
                    $monthAndDay = $m[2];
                }

                if (preg_match("/^[\d\W]{2,}$/", $monthAndDay)) {
                    $date = $year . '-' . $monthAndDay;
                } else {
                    $date = $monthAndDay . ' ' . $year;
                }

                if (!empty($week)) {
                    $weeknum = WeekTranslate::number1($week);
                    $date = EmailDateHelper::parseDateUsingWeekDay($date, $weeknum);
                    $time = str_replace('h', ':', [$timeDep, $timeArr]);

                    if (!empty($date)) {
                        $seg->departure()
                            ->date(strtotime($time[0], ($nextDayDep ? strtotime($nextDayDep, $date) : $date)));
                        $seg->arrival()
                            ->date(strtotime($time[1], ($nextDayArr ? strtotime($nextDayArr, $date) : $date)));
                    }
                }
            }
        }

        $accountNumbers = array_filter($this->http->FindNodes("//table[not(.//table) and {$this->starts($this->t('Passenger(s)'))}]/following::table[normalize-space()][1]//tr[count(*)>1]/*[2]/descendant::td[not(.//td) and {$this->starts($this->t('Card'))}]", null, "/{$this->preg_implode($this->t('Card'))}\s+(.+)/"));

        if (count($accountNumbers) === 0) {
            $accountNumbers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger(s)'))}]/following::text()[{$this->contains($this->t('Card'))}]", null, "/^{$this->preg_implode($this->t('Card'))}\s+(\d{5,})$/"));
        }

        if (count($accountNumbers) > 0) {
            $flight->program()->accounts(array_unique($accountNumbers), false);
        }

        $earnedAwards_Amounts = $earnedAwards_Currencies = [];
        $earnedAwardsTexts = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger(s)'))}]/following::tr[not(.//tr) and ({$this->contains('Miles')} or {$this->contains($this->t('Miles'))})]", null, "/^(\d[,.\'\d ]*(?:{$this->preg_implode($this->t('Miles'))}|Miles))(?:\s*[,.;(]|$)/i"));

        if (empty($earnedAwardsTexts)) {
            $earnedAwardsTexts = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger(s)'))}]/following::tr[not(.//tr) and ({$this->contains('Miles')} or {$this->contains($this->t('Miles'))})]", null,
                "/^\s*[[:alpha:] ]+:\s*(\d[,.\'\d ]* *(?:{$this->preg_implode($this->t('Miles'))}|Miles))(?:\s*\(.+?\))?$/iu"));
        }

        foreach ($earnedAwardsTexts as $eText) {
            if (preg_match("/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/", $eText, $m)) {
                // 1 942 Miles
                $earnedAwards_Amounts[] = $this->normalizeAmount($m['amount']);
                $earnedAwards_Currencies[] = ucfirst(strtolower($m['currency']));
            } else {
                $earnedAwards_Amounts = $earnedAwards_Currencies = [];

                break;
            }
        }

        if (count($earnedAwards_Amounts) > 0 && count(array_unique($earnedAwards_Currencies)) === 1) {
            $flight->program()->earnedAwards(array_sum($earnedAwards_Amounts) . ' ' . array_shift($earnedAwards_Currencies));
        }

        $payments = $this->http->XPath->query('//table[descendant::text()[normalize-space()][1][' . $this->eq($this->t('Payment')) . ']]');

        if ($payments->length > 0) {
            $payment = $payments->item(0);
            // !! Валюты могут несовпадать
            $currency = '';

            $rule = $this->starts($this->t('Total amount to pay'));
            $totalCharge = $this->http->FindSingleNode('.//td[' . $rule . ']/following-sibling::td[1]', $payment);

            if (preg_match('/^([.\d]+)\s*(.*)/', $totalCharge, $matches)) {
                $flight->price()
                    ->total($matches[1])
                    ->currency(preg_replace('/[*]+$/', '', $matches[2]));
                $currency = preg_quote(trim($matches[2], '*'), '/');
            }

            $rule = $this->starts($this->t('Total excl. tax'));
            $cost = $this->http->FindSingleNode('.//td[' . $rule . ']/following-sibling::td[1]', $payment, true, '/([.\d]+)\s*' . $currency . '/');

            if (!empty($cost)) {
                $flight->price()
                    ->cost($cost);
            }
            $tNodes = $this->http->XPath->query(".//td[" . $this->starts($this->t('Taxes and issuance fee')) . "]/following-sibling::td[1]", $payment);

            foreach ($tNodes as $tRoot) {
                $value = $this->http->FindSingleNode(".", $tRoot, true, '/([.\d]+)\s*' . $currency . '/');

                if (!empty($value)) {
                    $flight->price()
                        ->fee($this->http->FindSingleNode("./preceding-sibling::td[normalize-space()]", $tRoot, true, "#(.+?)[\s:]*$#"), $value);
                }
            }
            $rule = $this->starts($this->t('Issuing fee'));
            $feeSum = $this->http->FindSingleNode('.//td[' . $rule . ']/following-sibling::td[1]', $payment, true, '/([.\d]+)\s*' . $currency . '/');

            if (!empty($feeSum)) {
                $flight->price()
                    ->fee($this->http->FindSingleNode("//text()[{$this->starts($this->t('Issuing fee'))}]"), $feeSum);
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$langDetectors, $this->lang)) {
            return false;
        }

        foreach (self::$langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeMonthAndDay(?string $str): string
    {
        // $this->logger->debug('$str = '.print_r( $str,true));

        if ($this->lang == 'fr' && stripos($str, '?') !== false) {
            $str = str_replace("?", "û", $str);
        }

        $regexp = [
            'en' => '/^\s*(?<week>\w+)\s*[.,\s]+\s*(?<month>[^\d\s]+)\s+(?<day>\d{1,2})$/',
            'fr' => '/^\s*(?<week>\w+)\s*[.,\s]+\s*(?<day>\d{1,2})\s+(?<month>[^\d\s]+)$/u',
            'es' => '/^\s*(?<week>[\w\-]+)\s*[.,\s]+\s*(?<day>\d{1,2})\s+de\s+(?<month>[^\d\s]+)\s*$/iu',
            'nl' => '/^\s*(?<week>\w+)\s*[.,\s]+\s*(?<day>\d{1,2})\s+(?<month>[^\d\s]+)$/u',
            'pt' => '/^\s*(?<week>[\w\-]+)\s*[.,\s]+\s*(?<day>\d{1,2})\s+de\s+(?<month>\w+)/iu',
            'it' => '/^\s*(?<week>\w+)\s*[.,\s]+\s*(?<day>\d{1,2})\s+(?<month>\w+)/u',
            'de' => '/^\s*(?<week>\w+)\s*[.,\s]+\s*(?<day>\d{1,2})\s*\.\s*(?<month>\w+)/u',
            'ja' => '/^\s*年\s*(?<month>\d{1,2})\s*月\s*(?<day>\d{1,2})\s*日\s*(?<week>[[:alpha:]]+)曜日\s*$/u',
            'ko' => '/^\s*년\s*(?<month>\d{1,2})\s*월\s*(?<day>\d{1,2})\s*일\s*(?<week>[[:alpha:]]+)\s*$/u',
            'pl' => '/^\s*(?<week>\w+)\s*[.,\s]+\s*(?<day>\d{1,2})[ ]+(?<month>\w+)/u',
            'ru' => '/^\s*(?<week>\w+)\s*[.,\s]+\s*(?<day>\d{1,2})[ ]+(?<month>\w+)/u',
        ];

        if (preg_match($regexp[$this->lang], $str, $m)) {
            $m['week'] = WeekTranslate::translate($m['week'], $this->lang);

            if ('ja' !== $this->lang) {
                if ($lc = MonthTranslate::translate($m["month"], $this->lang)) {
                    return $m['week'] . ', ' . $m["day"] . ' ' . $lc;
                } else {
                    return $m['week'] . ', ' . $m["day"] . ' ' . $m["month"];
                }
            } else {
                return $m['week'] . ', ' . $m['month'] . '-' . $m['day'];
            }
        }

        return '';
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

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
