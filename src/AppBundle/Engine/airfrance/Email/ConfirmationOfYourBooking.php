<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\ItineraryArrays\AirTrip;

class ConfirmationOfYourBooking extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-1623699.eml, airfrance/it-1645123.eml, airfrance/it-1676585.eml, airfrance/it-1689640.eml, airfrance/it-1908529.eml, airfrance/it-27659665.eml, airfrance/it-2834003.eml, airfrance/it-3047071.eml, airfrance/it-4.eml, airfrance/it-4111345.eml, airfrance/it-4159497.eml, airfrance/it-4162368.eml, airfrance/it-4198705.eml, airfrance/it-4219105.eml, airfrance/it-4275484.eml, airfrance/it-4344799.eml, airfrance/it-5480681.eml, airfrance/it-5599629.eml, airfrance/it-5599634.eml, airfrance/it-5624279.eml, airfrance/it-5676495.eml, airfrance/it-5678531.eml, airfrance/it-5843647.eml, airfrance/it-5950162.eml, airfrance/it-6276608.eml, airfrance/it-6322356.eml, airfrance/it-6349326.eml";
    public $reFrom = "info@service.airfrance.com";
    public $reSubject = [
        "de" => "Ihre Air France Buchung",
        "ru" => "Подтверждение Вашего бронирования Air France",
        "pt" => "Confirmação da sua reserva Air France",
        "it" => "Il suo biglietto premio BlueBiz Air France",
        "es" => "Confirmación de su reserva Air France",
        "pl" => "Potwierdzenie rezerwacji internetowej Air France",
        "nl" => "Bevestiging van uw Air France",
        "ko" => "에어 프랑스 예약 확약 메일",
    ];
    public $reBody = 'airfrance';
    public $reBody2 = [
        "en"  => "Aircraft",
        "de"  => "Flugzeug",
        "ru"  => "Самолет",
        "pt"  => "Aeronave",
        "pt2" => "Avião",
        "fr"  => "Appareil",
        "it"  => "Aeromobile",
        "es"  => "Avión",
        "pl"  => "Samolot",
        "nl"  => "Vliegtuigtype",
        "ko"  => "기종",
    ];

    public static $dictionary = [
        "en" => [
            "Your reservation reference number" => ["Your reservation reference number", "Reference of your booking"],
            "Reservation status"                => ["Reservation status", "Status"],
            "ticket number"                     => ["ticket number", "Ticket number"],
            "Passenger"                         => ["Passenger", "Passengers", 'Passengers details'],
            "Duration"                          => ["Duration", "Flight time"],
            "Meal"                              => ["Meal", "Meal(s)", "Meals on board"],
            "Booking class"                     => ["Booking class", "Class of reservation"],
            "Total amount paid online"          => ["Total amount paid online", "Total amount :"],
            "Fare excluding taxes"              => ["Fare excluding taxes", "Surcharges :"],
            "Taxes and surcharges"              => ["Taxes and surcharges", "Taxes :"],
            "Miles debited"                     => ["Miles debited", "Amount in miles"],
            "You will earn"                     => ["You will earn", "You could earn"],
        ],
        "de" => [
            "Your reservation reference number" => "Buchungscode",
            "Passenger"                         => "Passagiere",
            "ticket number"                     => "Ticketnummer(n)",
            "Total amount paid online"          => "NOTTRANSLATED",
            "Fare excluding taxes"              => "NOTTRANSLATED",
            "Taxes and surcharges"              => "NOTTRANSLATED",
            "Reservation status"                => "Status",
            "Your reservation was created on"   => "Datum Ihrer Buchung",
            "Aircraft"                          => "Flugzeug",
            "Terminal"                          => "Terminal",
            "Operated by"                       => "Durchgeführt von",
            "Duration"                          => "Dauer",
            "Meal"                              => "Mahlzeiten an Bord",
            //			"Booking class" => "",
            "You will earn" => ["können Sie mit dieser Reise"],
            "miles"         => "Meilen",
        ],
        "ru" => [
            "Your reservation reference number" => ["Номер бронирования", "Справочный номер Вашего бронирования"],
            "Passenger"                         => "Пассажиры",
            "ticket number"                     => "Номер билета",
            "Total amount paid online"          => ["К оплате - всего", "Общая сумма, уплаченная в режиме он-лайн"],
            "Fare excluding taxes"              => "Тариф без учета налогов",
            "Taxes and surcharges"              => "Налоги и доплаты",
            "Reservation status"                => "Статус",
            "Your reservation was created on"   => ["Бронирование создано", "Ваше бронирование было создано"],
            "Aircraft"                          => "Самолет",
            "Terminal"                          => "терминал",
            "Operated by"                       => "Рейс выполняется",
            "Duration"                          => ["Время полёта", "Продолжительность"],
            "Meal"                              => ["Питание на борту", "Блюда, подаваемые на борту"],
            //			"Booking class" => "",
            "You will earn" => ["Вы получите"],
            "miles"         => "премиальных миль",
        ],
        "pt" => [
            "Your reservation reference number" => ["Código de reserva", "A referência da sua reserva"],
            "Passenger"                         => "Passageiros",
            "ticket number"                     => "Número(s) de bilhete",
            "Total amount paid online"          => "Montante total pago online",
            "Fare excluding taxes"              => "Tarifas sem taxas",
            "Taxes and surcharges"              => "Taxas e suplementos",
            "Reservation status"                => ["Status", "Estado da reserva"],
            "Your reservation was created on"   => ["Sua reserva foi criada", "A sua reserva foi criada em"],
            "Aircraft"                          => ["Aeronave", "Avião"],
            "Terminal"                          => "Terminal",
            "Operated by"                       => ["Operado por", "Efectuado por"],
            "Duration"                          => ["Tempo de vôo", "Duração"],
            "Meal"                              => ["Refeições servidas a bordo", "Refeição(ões) servida(s) a bordo"],
            "Booking class"                     => "Classe",
            "You will earn"                     => ["Você pode acumular", "poderá acumular"],
            "miles"                             => ["Milhas", "milhas"],
        ],
        "fr" => [
            "Your reservation reference number" => ["Référence de votre dossier de réservation", "Référence de votre dossier", "Référence de votre réservation"],
            "Passenger"                         => "Passagers",
            "ticket number"                     => "Numéro(s) de billet",
            "Total amount paid online"          => "Montant total payé en ligne",
            "Fare excluding taxes"              => "Tarif hors taxes",
            "Taxes and surcharges"              => ["Taxes et surcharges"],
            "Reservation status"                => ["Statut de la réservation", "Statut"],
            "Your reservation was created on"   => ["Votre dossier a été modifié le", "Votre dossier a été créé le"],
            "Aircraft"                          => "Appareil",
            "Terminal"                          => "Terminal",
            "Operated by"                       => "Effectué par",
            "Duration"                          => "Temps de vol",
            "Meal"                              => "Repas à bord",
            "Booking class"                     => "Classe de réservation",
        ],
        "it" => [
            "Your reservation reference number" => "Codice del dossier di prenotazione",
            "Passenger"                         => "Passeggeri",
            "ticket number"                     => "N° di biglietto",
            "Total amount paid online"          => "Importo totale pagato online :",
            "Fare excluding taxes"              => "Tariffa tasse escluse :",
            "Taxes and surcharges"              => "Tasse e supplementi :",
            "Reservation status"                => "Stato",
            "Your reservation was created on"   => "Il suo dossier è stato creato",
            "Aircraft"                          => "Aeromobile",
            "Terminal"                          => "Terminal",
            "Operated by"                       => "Operato da",
            "Duration"                          => "Durata del volo",
            "Meal"                              => "Pasti a bordo",
            "Booking class"                     => "Classe",
            "You will earn"                     => "Potrebbe accumulare",
            "miles"                             => "Miglia",
        ],
        "es" => [
            "Your reservation reference number" => "Referencia de su expediente de reserva",
            "Passenger"                         => "Pasajero",
            "ticket number"                     => "Número(s) de billete(s)",
            "Total amount paid online"          => "Importe total pagado online",
            "Fare excluding taxes"              => "Tarifa sin tasas",
            "Taxes and surcharges"              => "Tasas y recargo",
            "Reservation status"                => "Estado de la reserva",
            "Your reservation was created on"   => "Su expediente fue creado el",
            "Aircraft"                          => "Avión",
            "Terminal"                          => "Terminal",
            "Operated by"                       => "Operado por",
            "Duration"                          => "Duración del vuelo",
            "Meal"                              => "Comida(s) servida(s) a bordo",
            //			"Booking class" => "",
            "You will earn" => ["podría acumular"], //, ""],
            "miles"         => "Millas",
        ],
        "pl" => [
            "Your reservation reference number" => "Numer rezerwacji",
            "Passenger"                         => "Pasażerowie",
            "ticket number"                     => "Numer biletu",
            "Total amount paid online"          => "Suma opłacona online",
            "Fare excluding taxes"              => ["Cena Twojego biletu", "Cena bez podatków"],
            "Taxes and surcharges"              => "Podatki i opłaty",
            "Reservation status"                => "Status rezerwacji",
            "Your reservation was created on"   => ["Rezerwacja była zmieniana", "Rezerwacji dokonałeś"],
            "Aircraft"                          => "Samolot",
            "Terminal"                          => "Terminal",
            "Operated by"                       => "Operowany przez",
            "Duration"                          => "Czas trwania",
            "Meal"                              => "Posiłek na pokładzie",
            "Booking class"                     => "Klasa",
            "You will earn"                     => "mogłbyś zdobyć za tą podróż",
            "miles"                             => "mil",
        ],
        "nl" => [
            "Your reservation reference number" => "Boekingsnummer",
            "Passenger"                         => "Passagiers",
            "ticket number"                     => "Ticketnummer",
            "Total amount paid online"          => "Totaal online betaalde bedrag",
            "Fare excluding taxes"              => "Tarief zonder taksen",
            "Taxes and surcharges"              => "Taksen en toeslagen",
            "Reservation status"                => "Status van de boeking",
            "Your reservation was created on"   => "Uw boeking werd gemaakt op",
            "Aircraft"                          => "Vliegtuigtype",
            "Terminal"                          => "Terminal",
            "Operated by"                       => "Uitgevoerd door",
            "Duration"                          => "Duur",
            "Meal"                              => "Maaltijd(en) aan boord",
            "Booking class"                     => "Reisklasse",
            "You will earn"                     => "spaart u met deze reis",
            "miles"                             => "Miles",
        ],
        "ko" => [
            "Your reservation reference number" => "예약 번호",
            "Passenger"                         => "승객",
            "ticket number"                     => "항공권 번호",
            "Total amount paid online"          => "총 지불 금액:",
            //			"Fare excluding taxes" => "",
            //			"Taxes and surcharges" => "",
            "Reservation status" => "예약 상태",
            //			"Your reservation was created on" => "",
            "Aircraft"    => "기종",
            "Terminal"    => "터미널",
            "Operated by" => "운항 항공사",
            "Duration"    => "비행 시간",
            "Meal"        => "기내식 제공",
            //			"Booking class" => "",
        ],
    ];

    public $lang = "en";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($parser->getHTMLBody());

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
        $NBSP = chr(194) . chr(160);
        $html = str_replace($NBSP, ' ', html_entity_decode($parser->getHTMLBody()));
        $html = str_replace('Ã', 'à', str_replace('Ã©', 'é', $html)); //a few broken emails from bcd
        $this->http->SetEmailBody($html);

        $itineraries = [];

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = trim($lang, "1234567890");

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ConfirmationOfYourBooking' . ucfirst($this->lang),
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

    private function parseHtml(&$itineraries): void
    {
        /** @var AirTrip $it */
        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        if (!$it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your reservation reference number")) . "]", null, true, "#" . $this->opt($this->t("Your reservation reference number")) . "\s*:\s*(\w+)#")) {
            if (!$it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Your reservation reference number"))}]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{5,}$/")) {
                $it['RecordLocator'] = $this->nextText($this->t("Your reservation reference number"));
            }
        }

        if (empty($it['RecordLocator']) && $this->http->XPath->query("//*[{$this->contains($this->t('You\'ve received an email from'))}]")->length > 0) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // Passengers
        $it['Passengers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger")) . "]/following::table[1]/descendant::table[not(.//table)]//tr[1]/td[1][normalize-space(.)]"));

        if (empty($it['Passengers'])) {
            $it['Passengers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger")) . "]/ancestor::*[following-sibling::table][1]/following-sibling::table[following-sibling::table[1][contains(normalize-space(.), \"Ticket number\")]]"));
        }

        // TicketNumbers
        $it['TicketNumbers'] = [];
        $xpath = "//text()[" . $this->starts($this->t("ticket number")) . "]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            while ($root = $this->http->XPath->query("./following::text()[string-length(normalize-space(.))>1][1]", $root)->item(0)) {
                if (!$tn = $this->http->FindSingleNode(".", $root, true, "#^[\d-\s]+$#")) {
                    break;
                }
                $it['TicketNumbers'][] = $tn;
            }
        }

        // SpentAwards
        $spent = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Miles debited"))}]/ancestor::td[1]/following-sibling::td[1]");

        if (empty($spent)) {
            $spent = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Cost of your ticket:"))}]/ancestor::td[1]/following-sibling::td[1][{$this->contains($this->t('miles'))}]");
        }

        if ($spent !== null) {
            if (preg_match("#^\d+$#", $spent)) {
                $spent .= ' ' . ((array) $this->t('miles'))[0];
            }
            $it['SpentAwards'] = $spent;
        }

        // EarnedAwards
        // for now not sum: it-1676585.eml - maybe later should sum the miles
        $earn = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('You will earn'))}])[1]/ancestor::*[{$this->contains($this->t('miles'))}][1]",
            null, false, "#{$this->opt($this->t('You will earn'))}\s*(\d.+?\b{$this->opt($this->t('miles'))})#u");

        if ($earn !== null) {
            $it['EarnedAwards'] = $earn;
        }

        // TotalCharge
        $totalCharge = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Total amount paid online"))}]/ancestor::td[1]/following-sibling::td[1]");

        if ($totalCharge !== null) {
            $it['TotalCharge'] = $this->amount($totalCharge);
        }

        // BaseFare
        $baseFare = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Fare excluding taxes"))}]/ancestor::td[1]/following-sibling::td[1]");

        if ($baseFare !== null) {
            $it['BaseFare'] = $this->amount($baseFare);
        }

        // Currency
        $it['Currency'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total amount paid online")) . "]/ancestor::td[1]/following-sibling::td[2]");

        if (empty($it['Currency'])) {
            $it['Currency'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total amount paid online")) . "]/ancestor::td[1]/following-sibling::td[1]",
                null, false, "#[\d\.]+\s*([A-Z]{3})$#");
        }

        // Tax
        $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Taxes and surcharges"))}]/ancestor::td[1]/following-sibling::td[1]");

        if ($tax !== null) {
            $it['Tax'] = $this->amount($tax);
        }

        // Status
        $it['Status'] = $this->nextText($this->t("Reservation status"));

        // ReservationDate
        if (!$date = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your reservation was created on")) . "]", null, true, "#" . $this->opt($this->t("Your reservation was created on")) . "\s+(.+)#")) {
            $date = $this->nextText($this->t("Your reservation was created on"));
        }
        $it['ReservationDate'] = strtotime($this->normalizeDate($date));

        $xpath = "//text()[" . $this->starts($this->t("Aircraft")) . "]/ancestor::table[1]/preceding::table[1]/descendant::tr[1]/..";
        $segments = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH]: " . $xpath);

        if ($segments->length === 0) {
            $this->logger->notice("segments root not found: $xpath");
        }

        foreach ($segments as $root) {
            $root2 = $this->http->XPath->query("./following::table[1]", $root)->item(0);
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::text()[string-length(normalize-space(.))>1][1]", $root)));

            if (false === $date) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::font[1]", $root)));
            }

            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]/td[1]", $root, true, "#^\s*(\d+)\s+-\s*$#");

            if (empty($itsegment['FlightNumber'])) {
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]/td[1]", $root, true, "#^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?(\d+)\s+-#");
            }

            if (empty($itsegment['FlightNumber'])) {
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]/td[last()]", $root, true, "#^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)$#");
                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[1]/td[last()]", $root, true, "#^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\d+$#");

                // DepCode
                if ($itsegment['DepCode'] = $this->http->FindSingleNode("./tr[1]/td[1]", $root, true, "#\(([A-Z]{3})\)#")) {
                    // DepName
                    $itsegment['DepName'] = $this->http->FindSingleNode("./tr[1]/td[1]", $root, true, "#\d+:\d+[ \-]+(.*?)\s+\([A-Z]{3}\)#");
                }

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./descendant::tr[2]/td[3]", $root2, true, "#" . $this->t("Terminal") . "[:\s]+(\w+)#i");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./tr[1]/td[1]", $root, false, "#^(\d+:\d+(?:[ ]*[ap]m)?)[ \-]+#i"), $date);

                // ArrCode
                if ($itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#\(([A-Z]{3})\)#")) {
                    // ArrName
                    $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#\d+:\d+[ \-]+(.*?)\s+\([A-Z]{3}\)#");
                }

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./descendant::tr[2]/td[3]", $root2, true, "#" . $this->t("Terminal") . "[:\s]+(\w+)#i");

                // ArrDate
                $node = $this->http->FindSingleNode("./tr[1]/td[3]", $root);

                if (preg_match("#(\d+:\d+\s*(?:[ap]m)?)\s*(?:\(([\+\-]\s*\d+)\))?#i", $node, $m)) {
                    $itsegment['ArrDate'] = strtotime($m[1], $date);

                    if (isset($m[2]) && !empty($m[2])) {
                        $itsegment['ArrDate'] = strtotime($m[2] . ' days', $itsegment['ArrDate']);
                    }
                }
            } else {
                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[1]/td[1]", $root, true, "#^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\d+\s+-#");

                if (empty($itsegment['AirlineName'])) {
                    $itsegment['AirlineName'] = AIRLINE_UNKNOWN;
                }

                // DepCode
                if ($itsegment['DepCode'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#\(([A-Z]{3})\)#")) {
                    // DepName
                    $itsegment['DepName'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");
                } else {
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                    // DepName
                    $itsegment['DepName'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#(.*?)(?:\s+-|$)#");
                }

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#" . $this->t("Terminal") . "\s+\w+#");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./tr[1]/td[2]", $root), $date);

                // ArrCode
                if ($itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[3]/td[3]", $root, true, "#\(([A-Z]{3})\)#")) {
                    // ArrName
                    $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[3]/td[3]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");
                } else {
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                    // ArrName
                    $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[3]/td[3]", $root, true, "#(.*?)(?:\s+-|$)#");
                }

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./tr[3]/td[3]", $root, true, "#" . $this->t("Terminal") . "\s+\w+#");

                // ArrDate
                $node = $this->http->FindSingleNode("./tr[3]/td[2]", $root);

                if (preg_match("#(\d+:\d+\s*(?:[ap]m)?)\s*(?:\(([\+\-]\s*\d+)\))?$#i", $node, $m)) {
                    $itsegment['ArrDate'] = strtotime($m[1], $date);

                    if (isset($m[2]) && !empty($m[2])) {
                        $itsegment['ArrDate'] = strtotime($m[2] . ' days', $itsegment['ArrDate']);
                    }
                }
            }

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Operated by")) . "]/ancestor::td[1]", $root2, true, "#" . $this->opt($this->t("Operated by")) . "\s*:\s*(.+)#");

            // Aircraft
            $itsegment['Aircraft'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Aircraft")) . "]/ancestor::td[1]", $root2, true, "#" . $this->opt($this->t("Aircraft")) . "\s*:\s*(.+)#");

            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./tr[1]/td[1]", $root, true, "#^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\d+\s+-\s+(.+)#");

            if (empty($itsegment['Cabin'])) {
                $itsegment['Cabin'] = str_replace(['ECO_AF', 'ECO_KL'], ['Economy', 'Economy'], $this->http->FindSingleNode("./tr[1]/td[4]", $root, true, '/(?:Business|Economy|ECO_KL|ECO_AF|First class)/'));
            }

            // BookingClass
            $bookingClass = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("Booking class"))}]/ancestor::td[1]", $root2, true, "#{$this->opt($this->t("Booking class"))}\s*:\s*([A-Z]{1,2})$#");
            $itsegment['BookingClass'] = $bookingClass;

            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Duration"))}]/ancestor::td[1]", $root2, true, "#{$this->opt($this->t("Duration"))}\s*:\s*(\d.*?)(?:,|$)#");

            // Meal
            $meal = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Meal"))}]/ancestor::td[1]", $root2, true, "#{$this->opt($this->t("Meal"))}\s*:\s*(.+)#");

            if ($meal) {
                $itsegment['Meal'] = trim($meal, ', ');
            }

            // Stops
            $stops = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Duration"))}]/ancestor::td[1]", $root2, true, "#{$this->opt($this->t("Duration"))}\s*:\s*.*?,\s*(.+)#");

            if (preg_match('/(?:non?[-\s]*stop|ohne Zwischenstopp)/iu', $stops, $m)) {
                $itsegment['Stops'] = 0;
            } elseif (preg_match('/^\d+$/', $stops)) {
                $itsegment['Stops'] = $stops;
            }

            if (isset($itsegment['DepCode'], $itsegment['ArrCode'])) {
                // not every time will collect. example: it-2834003.eml  |  different codes
                $seats = $this->http->FindNodes("//td[translate(normalize-space(.),' ','')='{$itsegment['DepCode']}-{$itsegment['ArrCode']}']/following-sibling::td[normalize-space()!=''][3]",
                    null, "/^\d+[A-z]$/");
                $seats = array_filter($seats);

                if (!empty($seats)) {
                    $itsegment['Seats'] = array_values($seats);
                }
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->starts($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[string-length(normalize-space(.))>1][{$n}]", $root);
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
        //		$this->logger->info("DATE: {$str}");
        $year = date("Y", $this->date);
        $str = str_replace('?', 'u', $str);
        $in = [
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s,.]+),?\s+(\d{4})$#", //Friday 18 April 2014
            "#^[^\d\s]+[,\s]+(\d+)\s*월\s*(\d+),?\s+(\d{4})$#u", //일요일, 5월 22, 2016
        ];
        $out = [
            "$1 $2 $3",
            "$2.$1.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $s));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
