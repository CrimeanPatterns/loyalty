<?php

namespace AwardWallet\Engine\airarabia\Email;

class It6117435 extends \TAccountChecker
{
    public $mailFiles = "airarabia/it-10070790.eml, airarabia/it-11767174.eml, airarabia/it-11919934.eml, airarabia/it-595003915.eml, airarabia/it-6054459.eml, airarabia/it-6117435.eml, airarabia/it-6125959.eml, airarabia/it-6127193.eml, airarabia/it-6129918.eml, airarabia/it-6144955.eml, airarabia/it-6268527.eml, airarabia/it-6284437.eml, airarabia/it-6308790.eml, airarabia/it-6747269.eml, airarabia/it-6808596.eml, airarabia/it-6863163.eml, airarabia/it-6863885.eml, airarabia/it-6870999.eml, airarabia/it-6871007.eml, airarabia/it-6889767.eml, airarabia/it-6933543.eml, airarabia/it-9978524.eml";

    public static $dictionary = [
        "en" => [
            "RESERVATION NUMBER"   => ["RESERVATION NUMBER", "RESERVATION NUMBER (PNR)"],
            "Origin / Destination" => ["Origin / Destination", "ORIGIN / DESTINATION"],
            "TOTAL IN"             => ["T O T A L", "TOTAL IN"],
        ],
        "ru" => [
            "RESERVATION NUMBER"   => "НОМЕР БРОНИРОВАНИЯ",
            "Passport No."         => "Номер паспорта",
            "Passenger Name(s)"    => "Имя(имена) пассажиров",
            "TOTAL IN"             => "Сумма в",
            "Origin / Destination" => "Место начала перевозки / Место назначения",
            "Terminal"             => "Терминал",
            "Aircraft:"            => "Aircraft:",
            "Duration:"            => "Duration:",
            "E TICKET NUMBER"      => "НОМЕР ЭЛЕКТРОННОГО БИЛЕТА",
        ],
        "fr" => [
            "RESERVATION NUMBER"   => "NUMERO DE RESERVATION",
            "Passport No."         => "Numero de passeport",
            "Passenger Name(s)"    => "Nom du passager",
            "TOTAL IN"             => "TOTAL EN",
            "Origin / Destination" => "Origine / destination",
            "Terminal"             => "T",
            "Aircraft:"            => "Aircraft:",
            "Duration:"            => "Durée du vol:",
            "E TICKET NUMBER"      => "NUMERO DE BILLET",
        ],
        "es" => [
            "RESERVATION NUMBER"   => "NÃšMERO DE RESERVA",
            "Passport No."         => "Numero De Pasaporte",
            "Passenger Name(s)"    => "Nombre(s) de pasajero",
            "TOTAL IN"             => "TOTAL EN",
            "Origin / Destination" => "Origen / destino",
            "Terminal"             => "Terminal", //nottranslated
            "Aircraft:"            => "Aircraft:",
            "Duration:"            => "Duration:",
            "E TICKET NUMBER"      => "NÃšMERO DE E-TICKET",
        ],
        "it" => [
            "RESERVATION NUMBER"   => "NUMERO DI PRENOTAZIONE",
            "Passport No."         => "Passport No.",
            "Passenger Name(s)"    => "Nome(i) del(i) passeggero(i)",
            "TOTAL IN"             => "TOTAL IN",
            "Origin / Destination" => "Origine / Destinazione",
            "Terminal"             => "T", //nottranslated
            "Aircraft:"            => "Aircraft:",
            "Duration:"            => "Duration:",
            "E TICKET NUMBER"      => "NUMERO DI BIGLIETTO ELETTRONICO",
        ],
        "tr" => [
            "RESERVATION NUMBER"   => "REZERVASYON NUMARASI",
            "Passport No."         => "Pasaport Yok.",
            "Passenger Name(s)"    => "Yolcu İsmi/İsimleri",
            "TOTAL IN"             => "TOPLAM",
            "Origin / Destination" => "Kalkış yeri / Varış yeri",
            "Terminal"             => "T", //nottranslated
            "Aircraft:"            => "Aircraft:",
            "Duration:"            => "Süre:",
            "E TICKET NUMBER"      => "E-BİLET NUMARASI",
        ],
        "de" => [
            "RESERVATION NUMBER"   => "Reservierung Nummer",
            "Passport No."         => "Passnummer",
            "Passenger Name(s)"    => "Passagiername (n)",
            "TOTAL IN"             => "Summe",
            "Origin / Destination" => "Ursprungsland/Reiseziehl",
            "Terminal"             => "T", //nottranslated
            "Aircraft:"            => "Aircraft:",
            "Duration:"            => "Duration:",
            "E TICKET NUMBER"      => "E Ticket Nummer",
        ],
        "ar" => [
            "RESERVATION NUMBER"   => "مراجع الحجز",
            //"Passport No."         => "",
            "Passenger Name(s)"    => "اسم المسافر",
            "TOTAL IN"             => "المجموع الكلي",
            "Origin / Destination" => "المصدر / الوجهة",
            "Terminal"             => "Terminal", //nottranslated
            "Aircraft:"            => "Aircraft:",
            "Duration:"            => "مدة الرحلة:",
            "E TICKET NUMBER"      => "رقم البطاقة الإلكترونية",
        ],
    ];

    public $lang = "en";
    private $reFrom = ["reservationsma@airarabia.com", "reservations@mahan.aero"];
    private $reSubject = [
        "en" => "Itinerary for the Reservation",
    ];
    private $reBody = ['Air Arabia', 'Mahan Air'];
    private $reBody2 = [
        "ru" => "СЕГМЕНТЫ ПУТЕШЕСТВИЯ",
        "fr" => "SEGMENTS DE VOYAGE",
        "es" => "TRAMOS DE VIAJE",
        "it" => "SEGMENTI DI VIAGGIO",
        "tr" => "SEYAHAT ETAPLARI",
        "de" => "Reisesegmente",
        "ar" => "تفاصيل الرحلة",
        "en" => "DESTINATION", //must be last. html comment contains this
    ];

    /** @var \HttpBrowser */
    private $pdf;

    private $date;

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $f) {
            if (strpos($from, $f) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $flag = false;

        foreach ($this->reFrom as $f) {
            if (strpos($headers["from"], $f) !== false) {
                $flag = true;
            }
        }

        if (!$flag) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            $pdfBody = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
            $body .= $pdfBody;
        }
        $flag = false;

        foreach ($this->reBody as $r) {
            if (strpos($body, $r) !== false) {
                $flag = true;
            }
        }

        if (!$flag) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang => $re) {
            if (stripos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $body = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($pdfs)), \PDF::MODE_COMPLEX);

        if (!empty($body)) {
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($body, true);
        }

        $this->parseHtml($itineraries);

        if (empty($itineraries) || empty($itineraries[0]['TripSegments']) && !empty($this->pdf)) {
            $itineraries = [];
            $this->parsePdf($itineraries);
        }

        $result = [
            'emailType'  => 'Reservations' . ucfirst($this->lang),
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
        $codes = [];
        $rows = $this->http->FindNodes("(//text()[" . $this->eq($this->t("Passenger Name(s)")) . "])[last()]/ancestor::tr[1]/following-sibling::tr/td[last()-2]/descendant::text()[normalize-space(.)][1]");

        foreach ($rows as $str) {
            if (preg_match("#^([A-Z]{3})/([A-Z]{3})$#", $str, $m)) {
                $codes[] = [$m[1], $m[2]];
            }
        }
        $nodes = $this->http->XPath->query("//tr[contains(normalize-space(.), 'Passenger Name(s)') and contains(., 'Flight')]/following-sibling::tr");

        foreach ($nodes as $node) {
            if (
                ($fnum = $this->http->FindSingleNode("td[last()-1]/descendant::text()[normalize-space(.)][1]", $node, true, '/[A-Z\d]{2}\s*(\d+)/'))
                && ($c = $this->http->FindSingleNode("td[last()-2]/descendant::text()[normalize-space(.)][1]", $node))
                && (!isset($codes[$fnum]) || $codes[$fnum] !== $c)
            ) {
                $codes[$fnum] = $c;
            }
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        if (!$it['RecordLocator'] = $this->nextText($this->t("RESERVATION NUMBER"))) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("RESERVATION NUMBER")) . "]", null, true, "#\s+(\w+)$#");
        }
        //      if(empty($it['RecordLocator']))
        //          $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[".$this->starts($this->t("RESERVATION NUMBER"))."])[1]/following::text()[normalize-space(.)][1]", null, true, "#[A-Z\d]+#");

        // TripNumber
        // Passengers
        if (count($it['Passengers'] = $this->http->FindNodes("//text()[" . $this->starts($this->t("Passport No.")) . "]/preceding::text()[normalize-space(.)][1]")) == 0) {
            $it['Passengers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger Name(s)")) . "]/ancestor::tr[1][" . $this->contains($this->t("E TICKET NUMBER")) . "]/following-sibling::tr[./td[4]]/td[1]"));
            $it['Passengers'] = preg_replace("/^\s*(Child\.|Baby\.|Ребенок |Enfant |Bébé )\s*/", '', $it['Passengers']);
            $it['Passengers'] = preg_replace("/^\s*((MS|MRS|MR|DR|MISS|MSTR) )/", '', $it['Passengers']);
        }

        // AccountNumbers
        // Cancelled
        // TotalCharge
        if (!$total = $this->http->FindSingleNode("//td[" . $this->eq($this->t("TOTAL IN")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^([\d\,\.]+)\s+[A-Z]{3}$#")) {
            $total = $this->http->FindSingleNode("(//td[" . $this->starts($this->t("TOTAL IN")) . "])[last()]/../td[position()=last()-1]");
        }
        $it['TotalCharge'] = $this->amount($total);

        // BaseFare
        // Currency
        if (!$it['Currency'] = $this->http->FindSingleNode("//td[" . $this->eq($this->t("TOTAL IN")) . "][1]/following::text()[normalize-space(.)][1]", null, true, "#^[\d\,\.]+\s+([A-Z]{3})$#")) {
            $it['Currency'] = $this->http->FindSingleNode("(//td[" . $this->starts($this->t("TOTAL IN")) . "])[1]", null, true, "#" . $this->opt($this->t("TOTAL IN")) . "\s+([A-Z]{3})#");
        }

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = '//text()[' . $this->eq($this->t("Origin / Destination")) . ']/ancestor::tr[1]/following-sibling::tr[./td[@rowspan="2" or @rowspan="3"]]';
        //      $this->logger->info("ROOT: {$xpath}");
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(.), 'RESERVATION CANCELLED')]")->length > 0) {
                $it['Status'] = 'Cancelled';
                $it['Cancelled'] = true;
            }
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        $busSegments = [];
        $tNumbers = [];

        foreach ($nodes as $i => $root) {
            $itsegment = [];
            $isBusSegment = false;

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^[A-Z\d]{2}\s*(\d+)[A-Z]?$#");

            $ticketNums = array_values(array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger Name(s)")) . "]/ancestor::tr[1][" . $this->contains($this->t("E TICKET NUMBER")) . "]/following-sibling::tr[contains(., '{$itsegment['FlightNumber']}')]/td[last()]", null, "#(\d{5,}.*)#"))));

            if (0 < $this->http->XPath->query("following-sibling::tr[position() < 3][contains(normalize-space(.), 'Bus Stop') or contains(normalize-space(.), 'Bus from')]", $root)->length) {
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2}\d+)[A-Z]?$#");
                $tNumbers[$itsegment['FlightNumber']] = $ticketNums;
                $busSegments[] = $itsegment['FlightNumber'];
                $isBusSegment = true;
            } else {
                $tNumbers[$itsegment['FlightNumber']] = $ticketNums;
            }

            // DepCode
            if (count($codes) == $nodes->length && isset($codes[$i])) {
                $itsegment['DepCode'] = $codes[$i][0];
            } else {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // DepName
            $itsegment['DepName'] = trim($this->http->FindSingleNode("./td[normalize-space(.)][2]", $root, true, "#^\s*(.+?)\s*(?:\(|[\s-]*" . $this->t("Terminal") . "\s*\w+$|$)#s"), ' -');

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[normalize-space(.)][2]", $root, true, "#[\s-]*" . $this->t("Terminal") . "\s*(\w+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./td[normalize-space(.)][position()=3 or position()=4]", $root))));

            if (false === $itsegment['DepDate']) {
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindsingleNode("./td[normalize-space(.)][position()=3]", $root)));
            }

            // ArrCode
            if (count($codes) == $nodes->length && isset($codes[$i])) {
                $itsegment['ArrCode'] = $codes[$i][1];
            } else {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if (isset($codes[$itsegment['FlightNumber']]) && preg_match('/([A-Z]{3})\s*\/\s*([A-Z]{3})/', $codes[$itsegment['FlightNumber']], $m)) {
                $itsegment['DepCode'] = $m[1];
                $itsegment['ArrCode'] = $m[2];
            }

            // ArrName
            $itsegment['ArrName'] = trim($this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)][1]", $root, true, "#(.*?)(\s+\(|[\s-]*" . $this->t("Terminal") . "\s*\w+$|$)#"), ' -');

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[normalize-space(.)][1]", $root, true, "#[\s-]*" . $this->t("Terminal") . "\s*(\w+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./following-sibling::tr[1]/td[normalize-space(.)][position()=2 or position()=3]", $root))));

            // AirlineName
            if (!$isBusSegment) {
                $airline = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^([A-Z\d]{2})\s*\d+[A-Z]?$#");
                // Air Arabia Abu Dhabi and Hi Fly Malta both have `3L` code, assume these emails have only air arabia
                if ($airline === '3L') {
                    $airline = 'Air Arabia Abu Dhabi';
                }
                $itsegment['AirlineName'] = $airline;
            }

            // Operator
            // Aircraft
            if (!$isBusSegment) {
                $itsegment['Aircraft'] = $this->http->FindSingleNode("./following-sibling::tr[2]/td[normalize-space(.)][2]", $root, null, "#" . $this->t("Aircraft:") . "\s+(.+)#");
            }

            // TraveledMiles
            // AwardMiles

            $column = '[last()-1]';

            if ($this->http->XPath->query("//text()[{$this->starts($this->t('FLIGHT'))}]/ancestor::td[1]/following-sibling::td[last()-2][{$this->eq($this->t('CLASS OF SERVICE'))}]")->length > 0) {
                $column = '[last()-2]';
            }

            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[normalize-space(.)]$column/descendant::text()[normalize-space(.)][1]", $root);

            if (empty($itsegment['Cabin']) && $this->lang === 'ar') {
                $itsegment['Cabin'] = $this->http->FindSingleNode("./td[normalize-space(.)]$column/descendant::text()[normalize-space(.)][1]", $root);
            }

            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[normalize-space(.)]$column/descendant::text()[normalize-space(.)][2]", $root, true, '/.*\s*\b([A-Z])\b/');

            if (empty($itsegment['BookingClass']) && $this->lang === 'ar') {
                $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[normalize-space(.)]$column/descendant::text()[normalize-space(.)][2]", $root, true, '/^([A-Z])$/');
            }

            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./following-sibling::tr[2]/td[1]", $root, null, "#" . $this->t("Duration:") . "\s+(.+)#");

            $it['Status'] = $this->http->FindSingleNode("./td[normalize-space(.)][last()]/descendant::text()[normalize-space(.)][1]", $root);

            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }

        if (!empty($this->pdf) && empty($tNumbers) && array_key_exists('TripSegments', $it)) {
            $ticketNumbers = [];

            foreach ($it['TripSegments'] as $tripSegment) {
                $flightNum = $tripSegment['AirlineName'] . $tripSegment['FlightNumber'];
                $ticketNumbers = array_merge($ticketNumbers, $this->pdf->FindNodes("//p[starts-with(normalize-space(.), '{$flightNum}') and preceding-sibling::p[1][contains(., '/')]][1]/following-sibling::p[1][not(contains(., 'Kg'))]", null, "#(\d{5,}.*)#"));
            }
            $ticketNumbers = array_values(array_unique(array_filter(preg_replace("/^\s*(\d{13})\\/\d+\s*$/", '$1', $ticketNumbers))));
            $it['TicketNumbers'] = $ticketNumbers;
        }

        $itineraries[] = $it;

        foreach ($itineraries as $i => $itinerary) {
            if (!array_key_exists('TripSegments', $itinerary) || !is_array($itinerary['TripSegments'])) {
                continue;
            }

            foreach ($itinerary['TripSegments'] as $s => $tripSegment) {
                if (isset($tNumbers[$tripSegment['FlightNumber']]) && !in_array($tripSegment['FlightNumber'], $busSegments)) {
                    $itineraries[$i]['TicketNumbers'] = array_merge($itineraries[$i]['TicketNumbers'] ?? [], $tNumbers[$tripSegment['FlightNumber']]);
                    $itineraries[$i]['TicketNumbers'] = array_values(array_unique(array_filter(preg_replace("/^\s*(\d{13})\\/\d+\s*$/", '$1', $itineraries[$i]['TicketNumbers']))));
                }

                if (in_array($tripSegment['FlightNumber'], $busSegments)) {
                    $itineraries[] = [
                        'Kind'          => 'T',
                        'RecordLocator' => $itinerary['RecordLocator'],
                        'Passengers'    => $itinerary['Passengers'],
                        'TripCategory'  => TRIP_CATEGORY_BUS,
                        'TicketNumbers' => $tNumbers[$tripSegment['FlightNumber']] ?? [],
                        'TripSegments'  => [$tripSegment],
                    ];
                    unset($itineraries[$i]['TripSegments'][$s]);
                }
            }
        }
    }

    private function parsePdf(&$itineraries): void
    {
        //$this->logger->alert('parse pdf');
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
//      $this->logger->info("PDF");

        if ($this->pdf->XPath->query("//text()[contains(normalize-space(.), 'RESERVATION CANCELLED')]")->length > 0) {
            $it['Status'] = 'Cancelled';
            $it['Cancelled'] = true;
        }

        $it['RecordLocator'] = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(.), 'RESERVATION NUMBER')]/following-sibling::p[1]");

        $it['Passengers'] = array_unique($this->pdf->FindNodes("//p[starts-with(normalize-space(.), 'Passenger Name(s)')][1]/following-sibling::p[normalize-space(.)][contains(normalize-space(.), 'MR') or contains(normalize-space(.), 'MRS') or contains(normalize-space(.), 'MS')]/descendant-or-self::node()[normalize-space(.)][not(contains(normalize-space(.), 'MS') and contains(normalize-space(.), 'MR'))]"));
        $it['Passengers'] = preg_replace("/^\s*(Child\.|Baby\.|Ребенок |Enfant |Bébé )\s*/", '', $it['Passengers']);
        $it['Passengers'] = preg_replace("/^\s*((MS|MRS|MR|DR|MISS|MSTR) )/", '', $it['Passengers']);

        $it['Currency'] = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(.), 'TOTAL IN')]", null, true, '/TOTAL IN\s+([A-Z]{3})/i');

        $it['TotalCharge'] = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(.), 'TOTAL IN')]/following-sibling::p[3]");

//      position of node of segments start
        $cnt1 = $this->pdf->XPath->query("//p[starts-with(normalize-space(.), 'FLIGHT')]/preceding-sibling::p")->length;
//      position of node of segments end
        $cnt2 = $this->pdf->XPath->query("//p[contains(normalize-space(.), 'LOCAL CALL CENTER DETAILS') or contains(normalize-space(.), 'E TICKET DETAILS')]/preceding-sibling::p")->length;
        $cnt = $cnt2 - $cnt1;

        if (0 < $cnt) {
//            ['AF211' => 0, 'LH312' => 13]
            $flightNums = array_filter($this->pdf->FindNodes("//p[starts-with(., 'FLIGHT')]/following-sibling::p[(position() = 6 or position() = 7 or position() = 4 or position() = 3) and contains(., 'STATUS')]/following-sibling::p[normalize-space(.)][position() < {$cnt}]", null, '/^(?:[A-Z]\d|\d[A-Z]|[A-Z]{2})\s*\d{1,5}$/'));
//            [0 => 'AF211', 13 => 'LH312]
            $flightPos = array_flip($flightNums);
            $flightNums = array_values($flightNums);
            $roots = [];
            $ticketNumbers = [];

            foreach ($flightNums as $i => $flightNum) {
                if (isset($flightNums[$i + 1]) && ($pos = $flightPos[$flightNums[$i + 1]])) {
                    $lastUsedPos = $flightPos[$flightNums[$i]];
//                    it is doing for getting count of nodes in one segment
                    $newPos = $pos - $lastUsedPos;
                    $roots[$flightNum] = $this->pdf->XPath->query("//p[starts-with(normalize-space(.), '{$flightNum}')][1]/following-sibling::p[normalize-space(.)][position() < {$newPos}]");
                } else {
//                    getting last segment
                    $posForLastSegment = $cnt2 - $this->pdf->XPath->query("//p[starts-with(normalize-space(.), '{$flightNum}')][1]/preceding-sibling::p")->length;
                    $roots[$flightNum] = $this->pdf->XPath->query("//p[starts-with(normalize-space(.), '{$flightNum}')][1]/following-sibling::p[normalize-space(.)][position() < {$posForLastSegment}]");
                }
                $ticketNumbers = array_merge($ticketNumbers, $this->pdf->FindNodes("//p[starts-with(normalize-space(.), '{$flightNum}') and preceding-sibling::p[1][contains(., '/')]]/following-sibling::p[1][not(contains(., 'Kg'))]"));
            }

            $ticketNumbers = array_unique(preg_replace("/^\s*(\d{13})\\/\d+\s*$/", '$1', $ticketNumbers));
            $it['TicketNumbers'] = $ticketNumbers;

            //$codes = $this->pdf->FindSingleNode("//p[({$this->starts($this->t('Origin / Destination'))}) or starts-with(normalize-space(.), 'Segment')][1]/following-sibling::p[contains(normalize-space(.), '/') and position() < 6][1]", null, true, '/([A-Z]{3}\s*\/\s*[A-Z]{3})/');
            $nbsp = chr(194) . chr(160);

            foreach ($roots as $fn => $nodes) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                foreach ($nodes as $node) {
                    /** @var \DOMNode $node */
                    if (false !== stripos($node->nodeValue, 'non-stop')) {
                        continue;
                    }

                    if (!isset($seg['AirlineName']) && preg_match('/^((?:[A-Z]\d|\d[A-Z]|[A-Z]{2}))\s*(\d{1,5})$/', $fn, $m)) {
                        // Air Arabia Abu Dhabi and Hi Fly Malta both have `3L` code, assume these emails have only air arabia
                        $codes = $this->pdf->FindSingleNode("//text()[normalize-space()='Flight']/following::text()[{$this->eq($m[1] . $m[2])}][1]/preceding::text()[contains(normalize-space(), '/')][1]");

                        if ($m[1] === '3L') {
                            $m[1] = 'Air Arabia Abu Dhabi';
                        }
                        $seg['AirlineName'] = $m[1];
                        $seg['FlightNumber'] = $m[2];
                    }

                    if (!isset($seg['DepName']) && preg_match('/([^:\/\(\)\!.]+)/', $node->nodeValue, $m)) {
                        $seg['DepName'] = $m[1];

                        if (preg_match('/(.+)\s*-\s*(?:Terminal\s+)?([A-Z\d\s]+)/i', $seg['DepName'], $m)) {
                            $seg['DepName'] = $m[1];
                            $seg['DepartureTerminal'] = trim($m[2]);
                        } elseif (preg_match('/(.+)\s+\w+,\s+(\d{1,2} \w+ \d{2,4}\s+\d{1,2}:\d{2})\s+\w+,/', $node->nodeValue, $m)) {
                            $seg['DepName'] = $m[1];
                            $seg['DepDate'] = strtotime($m[2]);
                        }

                        continue;
                    }

                    if (!isset($seg['DepartureTerminal']) && isset($seg['DepName']) && false !== stripos($seg['DepName'], 'Terminal') && preg_match('/([A-Z\d]{1,5})/', $node->nodeValue, $m)) {
                        $seg['DepartureTerminal'] = trim($m[1]);
                        $seg['DepName'] = preg_replace('/Terminal/', '', $seg['DepName']);

                        continue;
                    }

                    if (!isset($seg['DepDate'])) {
                        if (preg_match('/^\w+,\s+(\d{1,2}\s+\w+)$/', $node->nodeValue, $m)) {
                            $seg['DepDate'] = strtotime($m[1] . ' ' . $this->pdf->FindSingleNode('following-sibling::p[1]', $node, true, '/(\d{2,4})/') . ', ' . $this->pdf->FindSingleNode('following-sibling::p[2]', $node, true, '/(\d{1,2}:\d{2})/'));

                            continue;
                        } elseif (preg_match('/\w+,\s+(\d{1,2}\s+\w+\s+\d{2,4})\s+(\d{1,2}:\d{2})/', $node->nodeValue, $m)) {
                            $seg['DepDate'] = strtotime($m[1] . ', ' . $m[2]);

                            continue;
                        }
                    }

                    if (!isset($seg['Cabin']) && preg_match('/(?:Basic|Economy|Business Class|Business|Comfort|Promo Fare|اساسي)/iu', $node->nodeValue)) {
                        $seg['Cabin'] = $node->nodeValue;

                        continue;
                    }

                    if (!isset($seg['BookingClass']) && isset($seg['Cabin']) && preg_match('/[A-Z]/', $node->nodeValue)) {
                        $seg['BookingClass'] = $node->nodeValue;

                        continue;
                    }

                    if (preg_match('/ok/i', $node->nodeValue)) {
                        if (!isset($it['Status'])) {
                            $it['Status'] = $node->nodeValue;

                            continue;
                        } else {
                            continue;
                        }
                    }

                    if (!isset($seg['ArrName']) && isset($it['Status']) && preg_match('/([^:\/\(\)\!.]+)/', $node->nodeValue)) {
                        $seg['ArrName'] = trim($node->nodeValue);

                        if (preg_match('/(.+)\s*-\s*Terminal\s+(.+)/', $seg['ArrName'], $m)) {
                            $seg['ArrName'] = $m[1];
                            $seg['ArrivalTerminal'] = $m[2];
                        }

                        continue;
                    }

                    if (preg_match('/\w+,\s+(\d{1,2} \w+ \d{2,4})\s+(\d{1,2}:\d{2})/', $node->nodeValue, $m)) {
                        $seg['ArrDate'] = strtotime($m[1] . ', ' . $m[2]);

                        continue;
                    } elseif (preg_match('/\w+,\s+(\d{1,2} \w+ \d{2,4})/', $node->nodeValue, $m)) {
                        $seg['ArrDate'] = strtotime($m[1] . ', ' . $this->pdf->FindSingleNode('following-sibling::p[string-length(normalize-space(.)) > 2][1]', $node));

                        continue;
                    }

                    if (!isset($seg['Duration']) && preg_match('/duration\s*:\s*(.+)/i', $node->nodeValue, $m)) {
                        $seg['Duration'] = trim(str_replace($nbsp, '', $m[1]));

                        continue;
                    }

                    if (!isset($seg['Aircraft']) && preg_match('/Aircraft\s*:\s*(.+)/', $node->nodeValue, $m)) {
                        $seg['Aircraft'] = str_replace($nbsp, '', $m[1]);

                        if (preg_match('/.+-$/', $seg['Aircraft'])) {
                            $seg['Aircraft'] .= $this->pdf->FindSingleNode('following-sibling::p[normalize-space(.)][1]', $node);
                        }
                        $seg['Aircraft'] = preg_replace('/(?:Transit.+|Remarks.+)/', '', $seg['Aircraft']);

                        continue;
                    }
                }

                if (!empty($codes) && ($depArrCode = explode('/', $codes))) {
                    $seg['DepCode'] = $depArrCode[0];
                    $seg['ArrCode'] = $depArrCode[1];
                } elseif (!empty($seg['DepName']) && !empty($seg['ArrName'])) {
                    $depName = '';

                    if (preg_match('/(\w+)/', $seg['DepName'], $m)) {
                        $depName = $m[1];
                    }
                    $arrName = '';

                    if (preg_match('/(\w+)/', $seg['ArrName'], $m)) {
                        $arrName = $m[1];
                    }
                    $seg['DepCode'] = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(.), 'LOCAL CALL CENTER DETAILS')]/following-sibling::p[starts-with(normalize-space(.), '{$depName}')]/following-sibling::p[normalize-space(.)][1]");
                    $seg['ArrCode'] = $this->pdf->FindSingleNode("//p[starts-with(normalize-space(.), 'LOCAL CALL CENTER DETAILS')]/following-sibling::p[starts-with(normalize-space(.), '{$arrName}')]/following-sibling::p[normalize-space(.)][1]");
                }

                $it['TripSegments'][] = $seg;
            }
        }

        $itineraries[] = $it;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
        //      $this->logger->debug("Date: {$str}");
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4}),?\s+(\d+:\d+)$#",
            "#^(\d+)\s+([^\d\s]+)\.\s+(\d{4}),\s+(\d+:\d+)$#", //08 avr. 2015, 13:40
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
        ];

        $str = preg_replace($in, $out, $str);
        // $this->http->log($str);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
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

        return '(?:' . implode("|", $field) . ')';
    }
}
