<?php

namespace AwardWallet\Engine\airfrance\Email;

class It5200777 extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-2313825.eml, airfrance/it-2772112.eml, airfrance/it-2863052.eml, airfrance/it-3298762.eml, airfrance/it-4045652.eml, airfrance/it-4109213.eml, airfrance/it-4337924.eml, airfrance/it-5124370.eml, airfrance/it-5200777.eml";
    public $reFrom = "@infos-airfrance.com";
    public $reSubject = [
        "en"=> "Your trip to",
        "it"=> "Il suo viaggio a",
        "nl"=> "voltooi uw dossier voor uw vlucht van",
        "es"=> "Su viaje a",
        "fr"=> "votre vol du",
        "de"=> "Ihre Reise nach",
    ];
    public $reBody = 'Air France';
    public $reBody2 = [
        "en" => "Your flight summary",
        "it" => "Riepilogo dei suoi voli",
        "nl" => "Reisschema",
        "es" => "Resumen de sus vuelos",
        "fr" => "Résumé de vos vols",
        "de" => "Fluginformationen",
        'pt' => 'Obrigado por ter escolhido a Air France para sua ',
    ];

    public static $dictionary = [
        "en" => [
            "Departure"=> ["Departure", "Départ"],
            "from"     => "from|de",
            "to"       => "to|à|at",
        ],
        "it" => [
            "Booking number:"=> "Codice della prenotazione :",
            "Departure"      => "Partenza",
            "from"           => "da",
            "to"             => "alle|a",
        ],
        "nl" => [
            "Booking number:"=> ["Boekingsnummer", 'Boekingnummer :'],
            "Departure"      => "Vertrek",
            "from"           => "van",
            "to"             => "naar",
        ],
        "es" => [
            "Booking number:"=> "Referencia de reserva :",
            "Departure"      => "Salida",
            "from"           => "de",
            "to"             => "a",
        ],
        "fr" => [
            "Booking number:"=> "Numéro de réservation :",
            "Departure"      => "Départ",
            "from"           => "de",
            "to"             => "à",
        ],
        "de" => [
            "Booking number:"=> "Buchungscode :",
            "Departure"      => "Abflug",
            "from"           => "von",
            "to"             => "nach",
        ],
        "pt" => [
            "Booking number:"=> "Código de reserva :",
            "Departure"      => "Partida",
            "from"           => "de",
            "to"             => "para",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Booking number:"));

        // TripNumber
        // Passengers
        // TicketNumbers
        // AccountNumbers
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

        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.) and ./td[1]//td[3]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]//td[1]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            if (!($itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]//td[3]", $root, true, "#\(([A-Z]{3})\)$#"))) {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]//td[3]", $root, true, "#\d+:\d+\s+(?:" . $this->t("from") . ")\s+(.*?)(?:\s+\([A-Z]{3}\))?$#");

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]//td[2]", $root) . ',' . $this->http->FindSingleNode("./td[1]//td[3]", $root, true, "#\d+:\d+#")));

            // ArrCode
            if (!($itsegment['ArrCode'] = $this->http->FindSingleNode("./td[2]//td[2]", $root, true, "#\(([A-Z]{3})\)$#"))) {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[2]//td[2]", $root, true, "#\d+:\d+\s+(?:" . $this->t("to") . ")\s+(.*?)(?:\s+\([A-Z]{3}\))?$#");

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]//td[1]", $root) . ',' . $this->http->FindSingleNode("./td[2]//td[2]", $root, true, "#\d+:\d+#")));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]//td[1]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
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
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{4}),(\d+:\d+)$#",
        ];
        $out = [
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
