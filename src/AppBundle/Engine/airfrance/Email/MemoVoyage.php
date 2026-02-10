<?php

namespace AwardWallet\Engine\airfrance\Email;

class MemoVoyage extends \TAccountChecker
{
    // another exsamples maybe in airfrance/ETicketPDF
    public $mailFiles = "airfrance/it-12232648.eml, airfrance/it-2708749.eml, airfrance/it-2755956.eml, airfrance/it-2755960.eml, airfrance/it-27759394.eml, airfrance/it-2895556.eml, airfrance/it-3129052.eml, airfrance/it-3200807.eml, airfrance/it-4063779.eml, airfrance/it-4063802.eml, airfrance/it-4305012.eml, airfrance/it-5970976.eml";

    public $reFrom = "airfrance.com";
    public $reSubject = [
        'en' => 'Ticket and information for your trip on',
        'de' => 'Ticket und Informationen für Ihre Reise am',
        'zh' => '旅行的机票和信息',
        'ro' => 'Bilet şi informaţii pentru călătoria dumneavoastră din',
        'hu' => 'Repülőjegy és tudnivalók az ön',
        'sl' => 'Vozovnica in informacije za vaše potovanje dne',
        'fr' => 'Billet et informations pour votre voyage du',
        'it' => 'Biglietto e informazioni per il suo viaggio del',
        'ru' => 'Билет и информация о вашем путешествии',
        'nl' => 'Ticket en informatie over uw reis op',
        'pt' => 'Bilhete e informações para a sua viagem de',
        'cs' => 'Letenka a informace k vaší cestě dne',
        'pl' => 'Bilet i informacje dotyczące Twojej podróży w dniu',
        'ko' => '여행을 위한 항공권 및 정보',
    ];

    public $reBody = 'Air France';
    public $reBodyHtml = [
        'nl'  => 'U dient de referentie van uw dossier te bewaren',
        'es'  => 'Referencia de su reserva que deberá conservar',
        'de'  => 'Ihre Buchungsnummer',
        'it'  => 'Codice di riferimento del suo dossier da conservare',
        'pt'  => 'Código de reserva a ser lembrado',
        'pt2' => 'Referência do seu dossiê a guardar',
        'fr'  => 'Référence de votre dossier à conserver',
        'fr2' => 'vos billets Air France sont en pièce jointe',
        'en'  => 'Booking reference (Please keep for your records)',
        'ko'  => '예약번호',
        'pl'  => 'Należy zachować numer swojej rezerwacji',
        'ru'  => 'Номер бронирования',
        'ja'  => '予約番号は大切に保管してください',
        'ro'  => 'Numărul de referinţă al dosarului',
        'zh'  => '您的文档编号',
        'hu'  => 'Hivatkozási ügyiratszám',
        'sl'  => 'Referenčna številka vaše mape',
        'cs'  => 'PODROBNOSTI O VAŠICH LETECH',
    ];

    public $reBodyPdf = [
        'en' => 'Electronic Ticket',
    ];

    public $lang = '';
    public $date;
    public $segments = [];
    public $pdfNamePattern = "memo_voyage.*pdf";

    public static $dict = [
        'en' => [
            "Booking reference" => "Booking reference (Please keep for your records)",
            "Adult"             => ["Adult", "YTH", "Child", "YCD"],
            //			"Passenger(s)" => "",
            //			"Ticket number" => "",
            //			"FLIGHT" => "",
            //			"DEPARTURE" => "",
            "Terminal" => "Terminal",
            //			"ARRIVAL" => "",
        ],
        'nl' => [
            "Booking reference" => "U dient de referentie van uw dossier te bewaren",
            "Adult"             => "Volwassene",
            "Passenger(s)"      => "Passagier(s)",
            "Ticket number"     => "Ticketnummer",
            "FLIGHT"            => "VLUCHT",
            "DEPARTURE"         => "VERTREK",
            "Terminal"          => "Terminal",
            "ARRIVAL"           => "AANKOMST",
            "Card "             => "Kaart ",
        ],
        'es' => [
            "Booking reference" => "Referencia de su reserva",
            "Adult"             => ["Adulto", "Niño"],
            "Passenger(s)"      => "Pasajero(s)",
            "Ticket number"     => "Número del billete",
            "FLIGHT"            => "VUELO",
            "DEPARTURE"         => "SALIDA",
            "Terminal"          => "Terminal",
            "ARRIVAL"           => "LLEGADA",
        ],
        'de' => [
            "Booking reference" => "Ihre Buchungsnummer",
            "Adult"             => "Erwachsener",
            "Passenger(s)"      => "Passagier(e )",
            "Ticket number"     => "Ticketnummer",
            "FLIGHT"            => "FLUG",
            "DEPARTURE"         => "ABFLUG",
            "Terminal"          => "Terminal",
            "ARRIVAL"           => "ANKUNFT",
            "Card "             => "Karte ",
        ],
        'it' => [
            "Booking reference" => "Codice di riferimento",
            "Adult"             => "Adulto",
            "Passenger(s)"      => "Passeggero/i",
            "Ticket number"     => "Numero di biglietto",
            "FLIGHT"            => "VOLO",
            "DEPARTURE"         => "PARTENZA",
            "Terminal"          => "Terminal",
            "ARRIVAL"           => "ARRIVO",
            "Card "             => "Carta ",
        ],
        'pt' => [
            "Booking reference" => ['Código de reserva', 'Referência do seu dossiê a guardar'],
            "Adult"             => "Adulto",
            "Passenger(s)"      => "Passageiro(s)",
            "Ticket number"     => "Número de bilhete",
            "FLIGHT"            => "VOO",
            "DEPARTURE"         => "PARTIDA",
            "Terminal"          => "Terminal",
            "ARRIVAL"           => "CHEGADA",
            "Card "             => "Cartão ",
        ],
        'fr' => [
            "Booking reference" => "Référence de votre dossier à conserver",
            "Adult"             => ["Adulte", "YTH", 'YCD', "Bébé"],
            "Passenger(s)"      => "Passager(s)",
            "Ticket number"     => "Numéro de billet",
            "FLIGHT"            => "VOL",
            "DEPARTURE"         => "DÉPART",
            "Terminal"          => "Terminal",
            "ARRIVAL"           => "ARRIVÉE",
            "Card "             => "Carte ",
        ],
        'ko' => [
            "Booking reference" => "예약번호",
            "Adult"             => "성인",
            "Passenger(s)"      => "승객",
            "Ticket number"     => "항공권 번호",
            "FLIGHT"            => "항공편",
            "DEPARTURE"         => "출발",
            "Terminal"          => "터미널",
            "ARRIVAL"           => "도착",
        ],
        'pl' => [
            "Booking reference" => "Należy zachować numer swojej rezerwacji",
            "Adult"             => "Osoba dorosła",
            "Passenger(s)"      => "Pasażer/Pasażerowie",
            "Ticket number"     => "Numer biletu",
            "FLIGHT"            => "LOT",
            "DEPARTURE"         => "WYLOT",
            "Terminal"          => "Terminal",
            "ARRIVAL"           => "PRZYLOT",
        ],
        'ru' => [
            "Booking reference" => "Номер бронирования",
            "Adult"             => "Взрослый",
            "Passenger(s)"      => "Пассажир(ы)",
            "Ticket number"     => "Номер билета",
            "FLIGHT"            => "РЕЙС",
            "DEPARTURE"         => "ВЫЛЕТ",
            "Terminal"          => "Терминал",
            "ARRIVAL"           => "ПРИБЫТИЕ",
            "Card "             => "Карта ",
        ],
        'ja' => [
            "Booking reference" => "予約番号は大切に保管してください",
            "Adult"             => "大人",
            "Passenger(s)"      => "搭乗者",
            "Ticket number"     => "航空券番号",
            "FLIGHT"            => "フライト",
            "DEPARTURE"         => "出発",
            "Terminal"          => "ターミナル",
            "ARRIVAL"           => "到着",
            "Card "             => "会員番号 ",
        ],
        'ro' => [
            "Booking reference" => "Numărul de referinţă al dosarului",
            //			"Adult" => "",
            "Passenger(s)" => "Pasager(i)",
            //			"Ticket number" => "",
            "FLIGHT"    => "ZBOR",
            "DEPARTURE" => "PLECARE",
            "Terminal"  => "Terminal",
            "ARRIVAL"   => "SOSIRE",
        ],
        'zh' => [
            "Booking reference" => "您的文档编号",
            "Adult"             => "成人",
            "Passenger(s)"      => "乘客",
            "Ticket number"     => "机票编号",
            "FLIGHT"            => "航班",
            "DEPARTURE"         => "出发",
            "Terminal"          => "航站楼：",
            "ARRIVAL"           => "抵达",
        ],
        'hu' => [
            "Booking reference" => "Hivatkozási ügyiratszám Kérjük őrizze meg:",
            "Adult"             => "Felnőtt",
            "Passenger(s)"      => "Utas(ok)",
            "Ticket number"     => "0571427399820",
            "FLIGHT"            => "JARAT",
            "DEPARTURE"         => "INDULÁS",
            "Terminal"          => "terminál",
            "ARRIVAL"           => "ÉRKEZÉS",
        ],
        'sl' => [
            "Booking reference" => "Referenčna številka vaše mape, ki jo morate shraniti:",
            "Adult"             => "Odrasel",
            "Passenger(s)"      => "Potnik(i)",
            "Ticket number"     => "Št. vozovnice",
            "FLIGHT"            => "LET",
            "DEPARTURE"         => "ODHOD",
            "Terminal"          => "Terminal",
            "ARRIVAL"           => "PRIHOD",
        ],
        'cs' => [
            "Booking reference" => "Referenční čísla vaší složky, která si uschovejte:",
            "Adult"             => ["Dospělý", 'YTH'],
            "Passenger(s)"      => "Cestující",
            "Ticket number"     => "Číslo letenky",
            "FLIGHT"            => "LET",
            "DEPARTURE"         => "ODLET",
            "Terminal"          => "Terminál",
            "ARRIVAL"           => "PŘÍLET",
        ],
        // when a terminal translate other than the "terminal" is added, also add it to the file airfrance/ETicketPDF $terminalTitle
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $its = [];
        $body = $this->http->Response['body'];

        if (stripos($body, '[content.ticket-airfrance.com]') !== false) {
            $body = str_replace("[content.ticket-airfrance.com]", "", $body); //garbage like in 6156832.eml
            $this->http->SetEmailBody($body);
        }

        $this->AssignLang($this->http->Response['body']);
        $type = 'Html';
        $its[] = $this->parseHtml();

        if (empty($its) || empty($its[0]['TripSegments'])) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            if (isset($pdfs) && count($pdfs) > 0) {
                foreach ($pdfs as $pdf) {
                    if (($html = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                        $this->AssignLang($html, 'pdf');
                        $type = 'Pdf';
                        unset($its);
                        $its[] = $this->parsePdf($html);
                    } else {
                        $this->logger->debug("Itineraries did not found");

                        return null;
                    }
                }
            } else {
                return null;
            }
        } else {
            if (!empty($its[0]['RecordLocator'])) {
                $pdfs = $parser->searchAttachmentByName('.*\.pdf');

                foreach ($pdfs as $pdf) {
                    // totals from pdf like parser ETicketPDF
                    $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE);

                    if (empty($html)) {
                        continue;
                    }
                    $textPDF = text($html);

                    if ($this->re("#(?:RÉFÉRENCE DE VOTRE RÉSERVATION|YOUR BOOKING REFERENCE)\s+\b(" . $its[0]['RecordLocator'] . ")\b#", $textPDF)
                        || $this->re("#\n\s*(" . $its[0]['RecordLocator'] . ")\n\s*(?:RÉFÉRENCE DE VOTRE RÉSERVATION|YOUR BOOKING REFERENCE)#", $textPDF)) {
                        $textPay = $this->findСutSection($textPDF, 'Receipt', null);
                        //		$this->logger->debug('$textPay = '.$textPay);

                        $passTable = $this->re("#^([\s\S]+)\n.*\bTotal cost#", $textPay);

                        /* В резервации может быть больше пассажиров, чем указано

                         Total cost - это полная стомость резервации за всех пассажиров(даже если они не указаны)
                         Fare и Taxes, Fees - только за указанных

                        Для билетов купленных за мили, обменянных или других не полностью оплаченных деньгами total cost в валюте не соответсвует, в милях - соответствует
                        */
                        if (preg_match("/\b(?:Award ticket|Exchange|FORFAIT|NOFARE)\b/", $passTable)) {
                            if (preg_match("#Total cost[ ]*(?:/[^:]*)?:\s*.*\b(MILES \d+)\n#", $textPay, $m)) {
                                $its[0]['SpentAwards'] = $m[1];
                            }
                        } else {
                            $tot = $this->getTotalCurrency($this->re("#Total cost[ ]*(?:/[^:]*)?:\s*(.+)#", $textPay));

                            if (!empty($tot['Total']) || $tot['Total'] === 0.0) {
                                $its[0]['TotalCharge'] = $tot['Total'];
                                $its[0]['Currency'] = $tot['Currency'];
                            }

                            $taxesRows = explode("\n", $passTable);
                            $fees = [];

                            foreach ($taxesRows as $tRow) {
                                if (preg_match("/^ *(?<currency>" . ($its[0]['Currency'] ?? '[A-Z]{3}') . " )?(?<amount>\d+\.\d{2}) (?<name>\w+(?: \w+)* \/ \w+(?: \w+)*)$/u", $tRow, $m)) {
                                    if (empty($its[0]['Currency']) && !empty($m['currency'])) {
                                        $its[0]['Currency'] = trim($m['currency']);
                                    }

                                    if (isset($fees[$m['name']])) {
                                        $fees[$m['name']] += round((float) $m['amount'], 2);
                                    } else {
                                        $fees[$m['name']] = round((float) $m['amount'], 2);
                                    }
                                }
                            }

                            if (preg_match_all("/\s+(?:\d{3} ){4}\d\s*\n.+\n(" . ($its[0]['Currency'] ?? '[A-Z]{3}') . ") (?<cost>\d+[.\d]*)\n/",
                                $passTable, $m)) {
                                if (empty($its[0]['Currency']) && !empty($m['currency'])) {
                                    $its[0]['Currency'] = $m['currency'];
                                }
                                $fare = array_sum($m['cost']);

                                if (empty($fare)) {
                                    // скорее всего билет неполностью оплачен деньгами и тотал может быть неверный
                                    unset($its[0]['TotalCharge']);
                                }
                            } elseif (preg_match("/\s+(?:\d{3} ){4}\d\s*\n.+\n\s*([A-Z]{3})?\d*(?<cost>\d+[.\d]*)\n/", $passTable, $m)
                                && empty($m['cost'])) {
                                // скорее всего билет неполностью оплачен деньгами и тотал может быть неверный
                                unset($its[0]['TotalCharge']);
                            } elseif (preg_match("/\n\s*(?:\d{3} ){4}\d+\n.+\n\s*[^\d\s]+[^\d\n]*\n/", $passTable, $m)) {
                                // скорее всего билет неполностью оплачен деньгами и тотал может быть неверный
                                unset($its[0]['TotalCharge']);
                            }

                            if (!empty($fees) && !empty($fare) && !empty($its[0]['TotalCharge'])) {
                                if (array_sum($fees) + $fare == $its[0]['TotalCharge']) {
                                    $its[0]['BaseFare'] = $fare;

                                    foreach ($fees as $k => $fee) {
                                        $its[0]['Fees'][] = ["Name" => $k, "Charge" => $fee];
                                    }
                                } elseif (abs($its[0]['TotalCharge']) % (array_sum($fees) + $fare) < 0.01) {
                                    $cp = round($its[0]['TotalCharge'] / (array_sum($fees) + $fare), 0);
                                    $its[0]['BaseFare'] = $cp * $fare;

                                    foreach ($fees as $k => $fee) {
                                        $its[0]['Fees'][] = ["Name" => $k, "Charge" => $cp * $fee];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'emailType'  => 'MemoVoyage' . $type . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, 'airfrance.com') !== false || stripos($text, 'Air France') !== false) {
            $f = $this->AssignLang($text);

            if ($f == true) {
                return true;
            }
        }
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, 'airfrance.com') !== false || stripos($text, 'Air France') !== false) {
                return $this->AssignLang($text, 'pdf');
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseHtml()
    {
        $it = ["Kind" => "T"];
        $it['RecordLocator'] = $this->http->FindSingleNode("//td[not(.//td) and (" . $this->contains($this->t("Booking reference")) . ")]/ancestor::table[1]", null, true, "#:\s*([A-Z\d]{5,7})\b#");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//td[not(.//td) and (" . $this->contains($this->t("Booking reference")) . ")]/ancestor::table[1]", null, true, "#\s+([A-Z\d]{5,7})$#");
        }

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//td[not(.//td) and (" . $this->contains($this->t("Booking reference")) . ")]/ancestor::table[1]", null, true, "#^\s*" . $this->preg_implode($this->t("Booking reference")) . "[\s:]+([A-z\d]{5,})\s*$#u");
        }

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $it['Passengers'] = array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Adult")) . "]/preceding::text()[normalize-space()][position()<=2][normalize-space()][not(contains(normalize-space(), '•'))][1]"));

        if (empty($it['Passengers'])) {
            $it['Passengers'] = array_values(array_unique($this->http->FindNodes("//td[not(.//td) and (" . $this->contains($this->t("Passenger(s)")) . ")]/ancestor::thead[1]/following-sibling::tbody[1]/tr/descendant::td[not(.//td) and string-length() > 5][1]", null, "#(.+?\S)(\s*\(.+\))?$#")));
        }

        $it['TicketNumbers'] = array_values(array_unique(array_filter($this->http->FindNodes("//td[not(.//td) and (" . $this->contains($this->t("Ticket number")) . ")]/ancestor::thead[1]/following-sibling::tbody/tr/td[2]", null, "#^[\d\- ]{7,}$#"))));

        if (empty($it['TicketNumbers'])) {
            $it['TicketNumbers'] = $this->http->FindNodes("//text()[" . $this->contains($this->t("Adult")) . "]/ancestor::tr[2]/td[2]");
        }
        $node = array_filter(array_unique($this->http->FindNodes("//td[{$this->starts($this->t('Card '))}]", null, "#{$this->preg_implode($this->t('Card '))}([A-Z\d]{2} [\w\-]+)$#")));

        if (count($node) > 0) {
            $it['AccountNumbers'] = $node;
        }

        $xpath = "//td[not(.//td) and .//img and (" . $this->contains($this->t("DEPARTURE")) . ")]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $seg["FlightNumber"] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("FLIGHT")) . "]/following::text()[normalize-space()][1]", $root, true, "#^\s*[A-Z\d]{2}\s*(\d{1,5})\s*$#");
            $seg["AirlineName"] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("FLIGHT")) . "]/following::text()[normalize-space()][1]", $root, true, "#^\s*([A-Z\d]{2})\s*\d{1,5}\s*$#");

            $departure = implode("\n", $this->http->FindNodes(".//text()[" . $this->eq($this->t("DEPARTURE")) . "]/ancestor::tr[1]/following-sibling::tr[1]//tr[not(.//tr)]", $root));
            // $this->logger->debug('$departure = '.print_r( $departure,true));

            // Paris
            // Dimanche 5 janvier 2025 à 08:30
            // (CDG), FRANCE
            // Terminal 2F
            // Aéroport Charles de Gaulle
            $re1 = "#\s*(?<name2>.+)\s*\n+\s*(?<date>.*)(?<time>\b\d+:\d+)\b(\s*\(.{0,5}[\+\-](?<overday>\d)\))?\s*.*\s+\((?<code>[A-Z]{3})\)\W+(?<name3>.+)\s+" . $this->preg_implode($this->t("Terminal")) . "[ :]*(?<term>.*)\n\s*(?<name1>.+)#u";
            $re2NoTerminal = "#\s*(?<name2>.+)\s+(?<date>.*)(?<time>\b\d+:\d+)\b(\s*\(.{0,5}[\+\-](?<overday>\d)\))?\s*.*\s+\((?<code>[A-Z]{3})\)\W+(?<name3>.+)\s*\n\s*(?<name1>.+)#u";
            // Jeudi 12 décembre 2024 à 16:00
            // Terminal 2E
            $re3 = "#^(?<date>.*\b\d{4}\b.*)(?<time>\b\d+:\d+)\b(\s*\(.{0,5}[\+\-](?<overday>\d)\))?\s*.*\s*\n\s*" . $this->preg_implode($this->t("Terminal")) . "[ :]*(?<term>.*)\s*$#u";
            // Zamunda
            // Jeudi 12 octobre 2024 à 12:15
            // South Africa
            // Terminal 2
            $re4 = "#^(?<name2>.+)\s*\n+\s*(?<date>.*\b\d{4}\b.*)(?<time>\b\d+:\d+)\b(\s*\(.{0,5}[\+\-](?<overday>\d)\))?\s*.*\s*\n\s*(?<name3>.+)\s*\n\s*" . $this->preg_implode($this->t("Terminal")) . "[ :]*(?<term>.*)\s*$#u";

            // $this->logger->debug('$re1 = '.print_r( $re1,true));
            // $this->logger->debug('$re2NoTerminal = '.print_r( $re2NoTerminal,true));
            // $this->logger->debug('$re3 = '.print_r( $re3,true));
            // $this->logger->debug('$re4 = '.print_r( $re4,true));

            if (preg_match($re1, $departure, $m)
                || (!preg_match("#" . $this->preg_implode($this->t("Terminal")) . "#", $departure) && preg_match($re2NoTerminal, $departure, $m))
                || preg_match($re3, $departure, $m)
                || preg_match($re4, $departure, $m)
            ) {
                // DepCode
                if (!isset($m['code'])) {
                    $seg["DepCode"] = TRIP_CODE_UNKNOWN;
                } else {
                    $seg["DepCode"] = $m['code'];
                }
                // DepName
                $seg["DepName"] = implode(', ', array_filter(array_map('trim', [$m['name1'], $m['name2'], $m['name3']])));
                // DepartureTerminal
                $seg["DepartureTerminal"] = $m['term'];
                // DepDate
                $seg["DepDate"] = strtotime($this->normalizeDate($m['date'] . ' ' . $m['time']));

                $date = $m['date'];
            }

            $arrival = implode("\n", $this->http->FindNodes(".//text()[" . $this->eq($this->t("ARRIVAL")) . "]/ancestor::tr[1]/following-sibling::tr[1]//tr[not(.//tr)]", $root));

            if (preg_match($re1, $arrival, $m)
                || (!preg_match("#" . $this->preg_implode($this->t("Terminal")) . "#", $arrival) && preg_match($re2NoTerminal, $arrival, $m))
                || preg_match($re3, $arrival, $m)
                || preg_match($re4, $arrival, $m)
                ) {
                // ArrCode
                if (!isset($m['code'])) {
                    $seg["ArrCode"] = TRIP_CODE_UNKNOWN;
                } else {
                    $seg["ArrCode"] = $m['code'];
                }
                // ArrName
                $seg["ArrName"] = implode(', ', array_filter(array_map('trim', [$m['name1'], $m['name2'], $m['name3']])));
                // ArrivalTerminal
                $seg["ArrivalTerminal"] = $m['term'] ?? null;
                // ArrDate
                if (!empty($date)) {
                    $seg["ArrDate"] = strtotime($this->normalizeDate($date . ' ' . $m['time']));
                }

                if (!empty($m['overday'])) {
                    $seg["ArrDate"] = strtotime("+" . $m['overday'] . "day", $seg["ArrDate"]);
                }
            }

            $seg["Aircraft"] = $this->http->FindSingleNode('(./following-sibling::table[.//td[count(./table)=3]][1]//img[contains(@src,"b-appareil.png")][1]/ancestor::td[1]/following-sibling::td[1]//text()[string-length(normalize-space(.))>1])[2]', $root);

            if (empty($seg["Aircraft"])) {
                $seg["Aircraft"] = $this->http->FindSingleNode('(./following-sibling::table[.//td[count(./table)=3]][1]//img[contains(@src,"b-appareil.png")][1]/ancestor::td[1]/following-sibling::td[1]//text()[string-length(normalize-space(.))>1])[1]', $root, null, "#.*\d.*#");
            }
            $boldTextRules = 'ancestor::*[contains(@style,"font-weight:bold") or contains(@style,"font-weight: bold")] or ancestor::b';
            $seg["Cabin"] = $this->http->FindSingleNode('(./following-sibling::table[.//td[count(./table)=3]][1]//img[contains(@src,"b-siege.png")][1]/ancestor::td[1]/following-sibling::td[1]//text()[string-length(normalize-space(.))>1])[position()<3][' . $boldTextRules . ']', $root);

            if (empty($seg["Cabin"])) {
                $seg["Cabin"] = implode(" ", $this->http->FindNodes('(./following-sibling::table[.//td[count(./table)=3]][1]//img[contains(@src,"b-siege.png")][1]/ancestor::td[1]/following-sibling::td[1]//text()[string-length(normalize-space(.))>1])[position()<3]', $root));
            }
            $node = array_filter($this->http->FindNodes("following-sibling::table[1]/descendant::img[contains(@src,'siege.')]/following::text()[normalize-space()!=''][1]",
                $root, "#^\d+[A-z]$#"));

            if (count($node) > 0) {
                if (isset($seg['Seats'])) {
                    $seg['Seats'] = array_values(array_unique(array_merge($seg['Seats'], $node)));
                } else {
                    $seg['Seats'] = $node;
                }
            }

            unset($date);
            $it['TripSegments'][] = $seg;
            $this->segments[] = $seg;

            if (!isset($seg["ArrCode"])) {
                $this->logger->debug("error. try parse form pdf");

                return null;
            }
        }

        return $it;
    }

    private function parsePdf($textPDF)
    {
        $textInfo = $this->findСutSection($textPDF, 'YOUR BOOKING REFERENCE', 'ITINERARY');
        $textPass = $this->findСutSection($textPDF, 'Passenger', 'Itinerary');
        $textIt = $this->findСutSection($textPDF, 'Itinerary', "\n" . '(*)');
        $textPay = $this->findСutSection($textPDF, 'Receipt', null);

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->re("#Your\s+Booking\s+Reference\s*(?:/[^:]+\s*)?:\s*([A-Z\d]{5,8})\b#", $textPDF);

        $date = strtotime($this->normalizeDate($this->re("#(?:Date and place).*\s*:\s*(\d+\s+\w+\s+\d{4})#", $textPay)));

        if (!empty($date)) {
            $this->date = $date;
        }

        preg_match_all("#\n[ ]{0,8}([A-Z ]+)[ ]*\(.+\)[ ]+([\d ]+)#", $textPass, $m);

        if (!empty($m[1])) {
            $it['Passengers'] = array_map("trim", $m[1]);
        }

        if (!empty($m[2])) {
            $it['TicketNumbers'] = array_map(function ($value) { return str_replace(' ', '', $value); }, $m[2]);
        }

        $tot = $this->getTotalCurrency($this->re("#Total cost\s*:\s*(.+)#", $textPay));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $nodes = $this->splitter("#\n(.+ \d{1,2}:\d{2}[ ]+\d{1,2}:\d{2})#", $textIt);

        if (preg_match("#(?:^|\n)([ ]*From[ ]+)To#", $textIt, $m)) {
            $posHead = [0, strlen($m[1])];
        }

        foreach ($nodes as $root) {
            $seg = [];

            if (preg_match("#(?<col3>.+?[ ]*)(?<al>[A-Z\d]{2})\s*(?<fn>\d{1,5})\s*(?<bc>[A-Z]{1,2})\s+(?<date>\d{1,2}[A-Za-z]+)\s+(?<dtime>\d+:\d+)\s+(?<atime>\d+:\d+)#", $root, $m)) {
                $seg['AirlineName'] = $m['al'];
                $seg['FlightNumber'] = $m['fn'];
                $seg['BookingClass'] = $m['bc'];
                $seg['DepDate'] = strtotime($this->normalizeDate($m['date'] . ' ' . $m['dtime']));
                $seg['ArrDate'] = strtotime($this->normalizeDate($m['date'] . ' ' . $m['atime']));

                if (preg_match("#(.+[ ]{2,})\S.+#", $m['col3'], $mat)) {
                    $posHead[1] = mb_strlen($mat[1]);
                }
                $posHead[2] = mb_strlen($m['col3']);
            }

            if (preg_match("#([\S\s]+?)(?:\n.+/.+|Arrival day.+|$)#", $root, $m)) {
                $table = $this->SplitCols($m[1], $posHead);

                if (!empty($table[0])) {
                    if (preg_match("#(.+) ([\dA-Z]{1,2})$#s", $table[0], $m)) {
                        $seg['DepName'] = str_replace("\n", ' ', $m[1]);
                        $seg['DepartureTerminal'] = $m[2];
                    } else {
                        $seg['DepName'] = str_replace("\n", ' ', $table[0]);
                    }
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                if (!empty($table[1])) {
                    if (preg_match("#(.+) ([\dA-Z]{1,2})$#s", $table[1], $m)) {
                        $seg['ArrName'] = str_replace("\n", ' ', $m[1]);
                        $seg['ArrivalTerminal'] = $m[2];
                    } else {
                        $seg['ArrName'] = str_replace("\n", ' ', $table[1]);
                    }
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
            }

            foreach ($this->segments as $key => $value) {
                if (!empty($value['AirlineName']) && !empty($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                    && !empty($value['FlightNumber']) && !empty($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                    && !empty($value['DepDate']) && !empty($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                    $seg['DepCode'] = (!empty($value['DepCode'])) ? $value['DepCode'] : $seg['DepCode'];
                    $seg['ArrCode'] = (!empty($value['ArrCode'])) ? $value['ArrCode'] : $seg['ArrCode'];
                }
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function normalizeDate($date)
    {
//        $this->http->log('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            '#^\s*(\d+\s+\w+\s+\d{4})\s*$#u',
            '#^\s*(\d{2})\s*(\w{3})\s+(\d+:\d+)\s*$#',
            '#^\s*[^\d\s\.\,]+,\s*([^\d\s\.\,]+)\s*(\d{1,2}),\s*(\d{4})\b.*\b(\d+:\d+)\s*$#u', //Friday, July 24, 2015 at 09:55
            '#^\s*[^\d\s]+\s*(\d{1,2})[,.]?\s*([^\d\s\,\.]+)[.,]?\s*(\d{4})\b.*\b(\d+:\d+)\s*$#u', //Mercredi 21 octobre 2015 à 20:40
            '#^\s*[^\d\s\,\.]+[,.]?\s*(\d{1,2})\s+de\s+([^\d\s\,\.]+)\s+de\s+(\d{4})\b.*\b(\d+:\d+)\s*$#u', //Mercredi 21 octobre 2015 à 20:40
            '#^\s*(\d{4})\s*년\s*(\d{1,2})\s*월\s*(\d{1,2})\s*일.*\D(\d+:\d+)\s*$#u', //2017년 5월 19일 금요일 13:20
            '#^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日.*\D(\d+:\d+)\s*$#u', //2016 年 7 月 30 日 土曜日 20:05 時
            '#^\s*(\d{4})[.\s]+([^\d\s\,\.]+)\s+(\d{1,2})[\s.]+[^\d\s\,\.]+\s+(\d+:\d+)\s*$#u', //2019. február 27. Szerda 13:20 óra
            '#^\w+\s*(\d+)\/(\d+)\/(\d{4})\s*\w\s+(\d+\:\d+)$#u', //Vendredi 24/08/2024 à  08:40
        ];
        $out = [
            '$1',
            '$1 $2 ' . $year . ' $3',
            '$2 $1 $3 $4',
            '$1 $2 $3 $4',
            '$1 $2 $3 $4',
            '$3.$2.$1 $4',
            '$3.$2.$1 $4',
            '$3 $2 $1 $4',
            '$1.$2.$3, $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body, $type = 'html')
    {
        if ($type == 'html') {
            foreach ($this->reBodyHtml as $lang => $reBody) {
                if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $reBody . '")]')->length > 0) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        if ($type == 'pdf') {
            foreach ($this->reBodyPdf as $lang => $reBody) {
                if (stripos($body, $reBody) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));

            if (is_numeric($m['t'])) {
                $m['t'] = round((float) $m['t'], 2);
            } else {
                $m['t'] = null;
            }
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                if ($k == 1) {
                    if (isset($row[$p]) && $row[$p] !== ' ') {
                        $p = strrpos(mb_substr($row, 0, $p, 'UTF-8'), ' ');
                    }
                }
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map('preg_quote', $field)) . ')';
    }
}
