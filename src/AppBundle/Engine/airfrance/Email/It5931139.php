<?php

namespace AwardWallet\Engine\airfrance\Email;

class It5931139 extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-10549146.eml, airfrance/it-10588699.eml, airfrance/it-10592031.eml, airfrance/it-1626824.eml, airfrance/it-4022624.eml, airfrance/it-4043749.eml, airfrance/it-4045854.eml, airfrance/it-4067425.eml, airfrance/it-4271901.eml, airfrance/it-4337923.eml, airfrance/it-5064371.eml, airfrance/it-5136817.eml, airfrance/it-5462534.eml, airfrance/it-5598074.eml, airfrance/it-5931139.eml, airfrance/it-6719975.eml, airfrance/it-7540868.eml, airfrance/it-7742247.eml, airfrance/it-8900473.eml, airfrance/it-9043950.eml";
    public $reFrom = "check-in@service-airfrance.com";
    public $reSubject = [
        "en"=> "Check in for your flight to",
        "fr"=> "Enregistrez-vous pour votre voyage vers",
        "pt"=> "Imprima o cartão de embarque para o seu voo com destino a",
        "ru"=> "Распечатайте Ваш посадочный талон на рейс до",
        "it"=> "Effettui il check-in per il suo viaggio a",
        "es"=> "Se ha realizado el check-in para su vuelo a",
        "nl"=> "Druk uw instapkaart af voor uw vlucht naar",
        "de"=> "Check-in für",
        "ro"=> "Efectuaţi formalităţile de check-in pentru călători",
        "hu"=> "Szerezze be beszállókártyáját a következő járatra vonatkozóan",
        "pl"=> "Odpraw sie na podróz do",
    ];
    public $reBody = 'airfrance';
    public $reBody2 = [
        "en"=> "Departing from",
        "fr"=> ["Au départ de", 'Cet e-mail vous a été envoyé de façon automatique car vous voyagez sur un vol Air France. Merci de ne pas y répondre'],
        "pt"=> " partida de",
        "ru"=> "Пункт отправления",
        "it"=> "In partenza da",
        "es"=> "Salida de",
        "nl"=> "Met vertrek vanuit",
        "de"=> "Abflugdatum",
        "ro"=> "Cu plecare din",
        "hu"=> "Indulás innen:",
        "pl"=> "Data wylotu",
    ];

    public static $dictionary = [
        "en" => [
            "Reservation number:"=> ["Reservation number:", "Ticketnummer:"],
            "Ticket number:"     => ["Ticket number:", "Ticket number :", "Ticketnummer:"],
            "Passenger:"         => ["Passenger:", "Passenger :", "Passagier:"],
        ],
        "fr" => [
            "Reservation number:"=> "N° de réservation :",
            "Passenger:"         => "Passager :",
            "Ticket number:"     => "N° de billet :",
            "Departing from"     => "Au départ de",
        ],
        "pt" => [
            "Reservation number:"=> ["Nº da reserva:", "N.º de reserva:"],
            "Passenger:"         => "Passageiro:",
            "Ticket number:"     => ["Nº do bilhete:", "N.º de bilhete:"],
            "Departing from"     => ["Com partida de", "À partida de"],
        ],
        "ru" => [
            "Reservation number:"=> "№ брони",
            "Passenger:"         => "Пассажир:",
            "Ticket number:"     => "№ билета:",
            "Departing from"     => "Пункт отправления",
            "Terminal"           => "Терминал",
        ],
        "it" => [
            "Reservation number:"=> ["N° di prenotazione:", "N° d prenotazione:"],
            "Passenger:"         => "Passeggero:",
            "Ticket number:"     => "N° di biglietto:",
            "Departing from"     => "In partenza da",
        ],
        "es" => [
            "Reservation number:"=> "Nº de reserva:",
            "Passenger:"         => "Pasajero:",
            "Ticket number:"     => "N° de billete:",
            "Departing from"     => "Salida de",
        ],
        "nl" => [
            "Reservation number:"=> ["Reserveringsnr:", "Reserveringsnr.:"],
            "Passenger:"         => "Passagier:",
            "Ticket number:"     => "Ticketnummer:",
            "Departing from"     => "Met vertrek vanuit",
        ],
        "de" => [
            "Reservation number:"=> "Buchungscode:",
            "Passenger:"         => "Passagier:",
            "Ticket number:"     => "Ticketnummer:",
            "Departing from"     => "Abflugdatum",
        ],
        "ro" => [
            "Reservation number:"=> "Nr. rezervare:",
            "Passenger:"         => "Pasager:",
            "Ticket number:"     => "Nr. bilet:",
            "Departing from"     => "Cu plecare din",
        ],
        "hu" => [
            "Reservation number:"=> "Foglalás száma:",
            "Passenger:"         => "Utas:",
            "Ticket number:"     => "Jegyszám:",
            "Departing from"     => "Indulás innen:",
        ],
        "pl" => [
            "Reservation number:"=> "Nr rezerwacji:",
            "Passenger:"         => "Pasażer :",
            "Ticket number:"     => "Nr biletu:",
            "Departing from"     => "Z",
        ],
    ];

    public $lang = "en";

    public $pdf;
    public $pdfNamePattern = '(?:Carte_Embarquement|Boarding_pass)\.pdf';

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Reservation number:"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger:")) . "]/following::text()[normalize-space(.)][1]");

        // AccountNumbers
        $it['TicketNumbers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Ticket number:")) . "]/following::text()[normalize-space(.)][1]", null,
            "/.*\d+.*/"));

        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->eq($this->t("Departing from")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.) and ./td[4]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[1]", $root, true, "#^\w{2}(\d+)$#");

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[1]", $root, true, "#^(\w{2})\d+$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[2]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[2]", $root, true, "#(.*?)\s+(?:-\s+" . $this->t("Terminal") . "\s+\w+\s+)?\(([A-Z]{3})\)$#");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[2]", $root, true, "#" . $this->t("Terminal") . "\s+(\w+)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("(./td[normalize-space(.)!=''])[3]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[4]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[4]", $root, true, "#(.*?)\s+(?:-\s+" . $this->t("Terminal") . "\s+\w+\s+)?\(([A-Z]{3})\)$#");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[4]", $root, true, "#" . $this->t("Terminal") . "\s+(\w+)#");

            // ArrDate
            if (isset($this->pdf)) {
                $text = text($this->pdf->Response['body']);

                if ($this->re("#^(CARTE D'EMBARQUEMENT\nBOARDING PASS|BOARDING PASS\s+Please keep this document until the end of your trip)#", $text)) {
                    $itsegment['ArrDate'] = MISSING_DATE;
                } else {
                    $depTime = date('H:i', $itsegment['DepDate']);
                    $arrTime = $this->re("#{$itsegment['FlightNumber']}.*?(?:CLASSE?|CABINE)\s+[^\n]+?\s+\d+:\d+\n\s*{$depTime}\n\s*(\d+:\d+)#s", $text);

                    if (!$arrTime) {
                        $itsegment['ArrDate'] = MISSING_DATE;
                    } else {
                        $itsegment['ArrDate'] = strtotime($arrTime, $itsegment['DepDate']);
                    }
                }
                // Seats
                if (preg_match_all("#{$itsegment['AirlineName']} {$itsegment['FlightNumber']}.*?/\s*-\s*(\d+[A-Z])#ms", $text, $m) && is_array($m[1])) {
                    $itsegment['Seats'] = $m[1];
                } elseif (preg_match_all("#{$itsegment['AirlineName']}{$itsegment['FlightNumber']}.*?Seat\s+(\d+[A-Z])#ms", $text, $m) && is_array($m[1])) {
                    $itsegment['Seats'] = $m[1];
                }
            } else {
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Duration
            // Meal
            // Smoking
            // Stops
            if ($itsegment['DepCode'] !== $itsegment['ArrCode']) {//sometimes it has wrong codes -> not parse it's segments
                $it['TripSegments'][] = $itsegment;
            }
        }
        $itineraries[] = $it;
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
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_string($re) && strpos($body, $re) !== false) {
                return true;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (false !== stripos($body, $r)) {
                        return true;
                    }
                }
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
            if (is_string($re) && strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (false !== stripos($this->http->Response['body'], $r)) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                }
            }
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][{$n}]", $root);
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
        // echo $str."\n";
        //$year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{4})\s+at\s+(\d+:\d+)$#", //en
            "#^(\d+)/(\d+)/(\d{4})\s+à\s+(\d+:\d+)$#", //fr
            "#^(\d+)/(\d+)/(\d{4})\s+às\s+(\d+:\d+)$#", //pt
            "#^(\d+)/(\d+)/(\d{4})\s+в\s+(\d+:\d+)$#", //ru
            "#^(\d+)/(\d+)/(\d{4})\s+alle\s+(\d+:\d+)$#", //it
            "#^(\d+)/(\d+)/(\d{4})\s+a\s+(\d+:\d+)$#", //es
            "#^(\d+)/(\d+)/(\d{4})\s+naar\s+(\d+:\d+)$#", //nl
            "#^(\d+)/(\d+)/(\d{4})\s+um\s+(\d+:\d+)$#", //de
            "#^(\d+)/(\d+)/(\d{4})\s+la\s+(\d+:\d+)$#", //ro
            "#^(\d+)/(\d+)/(\d{4})\s+\-?kor\s+(\d+:\d+)$#", //hu
        ];
        $out = [
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
