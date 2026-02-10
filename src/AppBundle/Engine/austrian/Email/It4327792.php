<?php

namespace AwardWallet\Engine\austrian\Email;

class It4327792 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "austrian/it-12324110.eml, austrian/it-20718299.eml, austrian/it-24174656.eml, austrian/it-4068228.eml, austrian/it-4327792.eml, austrian/it-4482607.eml, austrian/it-5050197.eml, austrian/it-5076397.eml, austrian/it-5079451.eml, austrian/it-6278661.eml, austrian/it-6282788.eml"; // +1 bcdtravel(pl)[html]

    public $reFrom = "no-reply@austrian.com";

    public $reSubject = [
        "en" => "Your Austrian reservation confirmation",
        "fr" => "Votre confirmation de réservation Austrian",
        "de" => "Ihre Austrian Buchungsbestätigung",
        "pl" => "Twoje potwierdzenie rezerwacji",
        "it" => "La tua conferma di prenotazione Austrian",
        "ru" => "Ваше подтверждение бронирования",
        "es" => "Su confirmación de reserva de Austrian",
        "sv" => "Din bokningsbekräftelse från Austrian",
    ];

    public $reBody = 'Austrian';

    public $reBody2 = [
        "en" => "Your booked flight",
        "fr" => "Votre réservation de vol",
        "de" => "Ihr gebuchter Flug",
        "pl" => "Zarezerwowana podróż",
        "it" => "Il suo volo prenotato",
        "ru" => "Забронированные вами билеты",
        "es" => "Su vuelo reservado",
        "sv" => "Ditt bokade flyg",
    ];

    public static $dictionary = [
        "en" => [
            "Your Booking code:"=> [
                "Votre code de réservation",
                "Your Booking code:",
            ],
            "Total"                    => "Total",
            'Passengers and services'  => ['Passengers and services', 'Passengers & Services'],
            "Frequent flyer programme" => ["Frequent flyer programme", "Programme de Frequent Flyer"],
            'Outbound'                 => ['Outbound', 'Aller', 'Retour'],
            'Mr'                       => ['Mr', 'Monsieur', 'Mrs. / Ms.', 'mr', 'ms', 'Dr.'],
            //			'Seat Reservation' => 'Seat Reservation',
            //			'meal ' => '',
        ],
        "fr" => [
            "Your Booking code:"       => ["Votre code de réservation", "Votre numéro de réservation:"],
            "Frequent flyer programme" => "Programme de Frequent Flyer",
            "Total"                    => "Total",
            "operated by"              => "opéré par",
            "Fare / booking class:"    => "tarif / classe:",
            'Outbound'                 => ['Aller', 'Vers l\'extérieur'],
            'Passengers and services'  => 'Passagers et services',
            'Mr'                       => ['Monsieur', 'Madame'],
            //			'Seat Reservation' => '',
            //			'meal ' => '',
        ],
        "de" => [
            "Your Booking code:"=> [
                "Buchungscode",
                "Ihr Buchungscode:",
            ],
            "Frequent flyer programme" => "Vielfliegerprogramm",
            "Total"                    => ["Summe", "Total"],
            "operated by"              => "durchgeführt von",
            "Fare / booking class:"    => "Tarif / ",
            'Outbound'                 => 'Hinflug',
            'Passengers and services'  => 'Passagiere und Services',
            'Mr'                       => ['Frau', 'Herr', 'Dr'],
            'Seat Reservation'         => 'Sitzplatzreservierung',
            //			'meal ' => '',
        ],
        "pl" => [
            "Your Booking code:"       => "Kod rezerwacji:",
            "Frequent flyer programme" => "Program lojalnościowy",
            "Total"                    => "Łącznie",
            "operated by"              => "obsługiwany przez",
            "Fare / booking class:"    => "taryfa / klasa rezerwacyjna",
            "Outbound"                 => "Wylot",
            "Passengers and services"  => "Pasażerowie i usługi",
            "Mr"                       => ["Pani"],
            //            "Seat Reservation" => "",
            //            "meal " => "",
        ],
        "it" => [
            "Your Booking code:"       => "Codice della sua prenotazione:",
            "Frequent flyer programme" => "Programma frequent flyer",
            //"Price for outbound" => "Prezzo per il volo",
            "Total"                   => "Totale",
            "operated by"             => "operato da",
            "Fare / booking class:"   => "tariffa / classe:",
            'Outbound'                => ['Andata', 'Esterno'],
            'Passengers and services' => 'Passeggeri e servizi',
            'Mr'                      => ['Sig.ra', 'Sig'],
            'Seat Reservation'        => 'Prenotazione posto',
            //			'meal ' => '',
        ],
        "ru" => [
            "Your Booking code:"       => "Код бронирования:",
            "Frequent flyer programme" => "Программа для постоянных клиентов",
            //"Price for outbound" => "",
            "Total"                   => "Всего",
            "operated by"             => "выполняется",
            "Fare / booking class:"   => "тариф/класс бронирования",
            'Outbound'                => 'Туда',
            'Passengers and services' => 'пассажиры и услуги',
            'Mr'                      => ['Господин', 'Госпожа'],
            //			'Seat Reservation' => '',
            'meal ' => 'Меню ',
        ],
        "es" => [
            "Your Booking code:" => "Su código de reserva:",
            //"Frequent flyer programme" => "",
            "Price for outbound"      => "Precio para vuelos",
            "Total"                   => "Precio total",
            "operated by"             => "operado por",
            "Fare / booking class:"   => "tarifa / clase de reserva:",
            'Outbound'                => 'Ida',
            'Passengers and services' => 'Pasajeros y servicios',
            'Mr'                      => 'Dra. Sra.',
            //			'Seat Reservation' => '',
            //			'meal ' => '',
        ],
        "sv" => [
            "Your Booking code:" => "Din bokningskod:",
            //"Frequent flyer programme" => "",
            "Price for outbound"      => "Flygpris",
            "Total"                   => "Total biljettpris",
            "operated by"             => "trafikeras av",
            "Fare / booking class:"   => "Pris / Bokningsklass",
            'Outbound'                => 'Utresa',
            'Passengers and services' => 'Passagerare och tjänster',
            'Mr'                      => 'Herr',
            //			'Seat Reservation' => '',
            //			'meal ' => '',
        ],
    ];

    public $lang = 'en';

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Your Booking code:"));

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//table[({$this->xpathParams($this->t('Passengers and services'))}) and not(descendant::table)]/following-sibling::table/descendant::tr[({$this->starts($this->t('Mr'))}) and not(descendant::tr)]", null, '/(?:' . $this->preg_implode($this->t('Mr')) . '\.?\s+)+(.+)/u'); //Dr. Mrs. / Ms. Melody Pond

        // AccountNumbers
        $accountNumbers = array_filter($this->http->FindNodes("//tr[{$this->starts($this->t("Frequent flyer programme"))}]", null, "/^{$this->preg_implode($this->t("Frequent flyer programme"))}\s*([*\d\s]{5,})$/"));

        if (count($accountNumbers)) {
            $it['AccountNumbers'] = array_unique($accountNumbers);
        }

        $total = $this->http->FindSingleNode("//text()[{$this->xpathParams($this->t('Total'), '.', 'starts-with')}]/ancestor::td[1]/following-sibling::td[1][normalize-space()]");

        if (!empty($total) && (preg_match("/(?<amount>\d[\d\,\. ]*)\s*(?<currency>[^\d\s]{1,5})(?:\s+|$)/", $total, $m)
                || preg_match("/(?:^|\s+)(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\,\. ]*)/", $total, $m))) {
            $it['TotalCharge'] = $this->cost($m['amount']);
            $it['Currency'] = $this->currency($m['currency']);
        }
        $xpath = '//table[' . $this->xpathParams($this->t('Outbound')) . ' and not(descendant::table)]/following-sibling::table[contains(., "' . $this->t('operated by') . '")]/descendant::tr[normalize-space(.)]';
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->debug('Segments root not found: ' . $xpath);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[8]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\(([A-Z]{3})#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[6]", $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[8]/descendant::text()[normalize-space(.)][1]", $root, true, "#^([A-Z][A-Z\d]|[A-Z\d][A-Z])\d+$#");

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode("./td[8]", $root, true, "#" . $this->t("operated by") . "\s+(.+)#");

            if ($itsegment['AirlineName'] == 'OS' && $itsegment['Operator'] == 'Austrian Airlines') {
                unset($itsegment['Operator']);
            }

            // Cabin
            $itsegment['Cabin'] = $this->re("#(.*?)\s+/\s+[A-Z]{1,2}$#", $this->nextText($this->t("Fare / booking class:")));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#.*?\s+/\s+([A-Z]{1,2})$#", $this->nextText($this->t("Fare / booking class:")));

            // Seats
            if (!empty($itsegment['AirlineName']) && !empty($itsegment['FlightNumber'])
                    && $seats = array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Seat Reservation")) . "]/ancestor::tr[1]//text()[starts-with(normalize-space(), '" . $itsegment['AirlineName'] . ' ' . $itsegment['FlightNumber'] . "')]", null, "/" . $itsegment['AirlineName'] . ' ' . $itsegment['FlightNumber'] . "\s*-\s*(\d{1,3}[A-Z])(?:$|\W)/"))) {
                $itsegment['Seats'] = $seats;
            }

            // Meal
            if (!empty($itsegment['AirlineName']) && !empty($itsegment['FlightNumber'])
                    && $meals = array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("meal ")) . "]/ancestor::tr[1]//text()[starts-with(normalize-space(), '" . $itsegment['AirlineName'] . ' ' . $itsegment['FlightNumber'] . "')]", null, "/" . $itsegment['AirlineName'] . ' ' . $itsegment['FlightNumber'] . "\s*-\s*(.+):/"))) {
                sort($meals);
                $itsegment['Meal'] = implode('; ', $meals);
            }

            // Stops

            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Austrian') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
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
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'It4327792' . ucfirst($this->lang),
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

    protected function xpathParams($array, $str1 = 'normalize-space(.)', $method = 'contains', $operator = 'or')
    {
        $arr = [];

        if (!is_array($array)) {
            $array = [$array];
        }

        foreach ($array as $str2) {
            $arr[] = $method . '(' . $str1 . ', "' . $str2 . '")';
        }

        return join(" {$operator} ", $arr);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.),'{$s}')"; }, $field));

        return $this->http->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[normalize-space(.)][1]", $root);
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
        $in = [
            "#^(\d+:\d{2})(\d+\.\d+\.\d{4})$#",
        ];
        $out = [
            "$2, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
            'Lv'=> 'BGN',
            'Kc'=> 'CZK',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (trim($s) == $f) {
                return $r;
            }
        }

        return null;
    }
}
