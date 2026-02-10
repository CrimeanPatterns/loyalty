<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Engine\MonthTranslate;

class CompleteYourFile extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-10172824.eml, airfrance/it-10211620.eml, airfrance/it-27140323.eml, airfrance/it-9787698.eml, airfrance/it-9792033.eml";
    public $reFrom = ["formalites@infos-airfrance.com", "check-in@service-airfrance.com"];
    public $reProvider = "airfrance.com";
    public $reSubject = [
        "en"  => "Please complete your file for your flight",
        "en2" => "Get your boarding pass for your flight on",
        "en3" => "Check in for your trip to",
        "en4" => "Your trip to",
        "nl"  => "Belangrijk: voltooi uw dossier voor uw vlucht van",
        "nl2" => "Check in voor uw reis naar",
        "fr"  => "Obtenez votre carte d'embarquement pour votre vol du",
        "fr2" => "Enregistrez-vous pour votre voyage vers",
        "fr3" => "Complétez votre dossier pour votre vol du",
        "fr4" => "Votre voyage à",
        "es"  => "Realice el check-in para su viaje a",
        "es2" => "Obtenga su tarjeta de embarque para su vuelo de",
        "it"  => "Richieda la carta d'imbarco per il volo de",
    ];
    public $reBody = 'airfrance';
    public $reBody2 = [
        "en"  => "Complete your file",
        "en2" => "Your boarding pass is ready",
        "en3" => "Check in now for your flight",
        "en4" => "Thank you for choosing Air France for your trip to",
        "nl"  => "Uw dossier aanvullen",
        "nl2" => "UW BOARDINGPASS",
        "nl3" => "Door online in te checken wint u tijd in de luchthaven",
        "fr"  => "Votre carte d'embarquement est prête",
        "fr2" => "VOTRE CARTE D'EMBARQUEMENT",
        "fr3" => "Veuillez compléter les informations",
        "fr4" => "vous bénéficiez pour ce voyage des avantages liés à votre carte",
        "es"  => "SU TARJETA DE EMBARQUE",
        "es2" => "Su tarjeta de embarque está lista",
        "it"  => "La sua carta d'imbarco è pronta",
        "de"  => "Visum, Reisepass und andere Formalitäten:",
    ];

    public static $dictionary = [
        "en" => [
            "Booking reference no"=> ["Booking reference no", "Booking reference"],
            //			"Dear"=>"",
            //			"Departing on"=>"",
        ],
        "nl" => [
            "Booking reference no"=> "Boekingsreferentie r",
            "Dear"                => "Geachte",
            "Departing on"        => "Vertrek op",
        ],
        "fr" => [
            "Booking reference no" => ["Référence de réservation", "Référence deréservation"],
            "Dear"                 => "Chère",
            "Departing on"         => "Départ le",
        ],
        "es" => [
            "Booking reference no" => "Buchungs code",
            //			"Dear" => "",
            "Departing on" => "Salida el",
        ],
        "it" => [
            "Booking reference no" => "Codice di rprenotazione",
            //			"Dear" => "",
            "Departing on" => "Partenza il giorno",
        ],
        "de" => [
            "Booking reference no" => "Buchungs code",
            "Dear"                 => "Sehr geehrter Herr",
            "Departing on"         => "Abflug am",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("(//*[" . $this->starts($this->t("Booking reference no")) . "]/following::text()[normalize-space(.)][1])[1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '" . $this->t("Dear") . "')]", null, true, "#" . $this->t("Dear") . "\s*([^,]+),#")]);
        //		$it['Passengers'] = $this->http->FindNodes("//img[contains(@src, '/profil-display-avatar.png')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]");

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

        $xpath = "//text()[starts-with(normalize-space(.), '" . $this->t("Departing on") . "')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            // ArrName
            $route = $this->http->FindSingleNode(".//tr[1]", $root);

            if (preg_match("#(.+)(?:>| - )(.+)#", $route, $m)) {
                $itsegment['DepName'] = trim($m[1]);
                $itsegment['ArrName'] = trim($m[2]);
            }

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//tr[2]", $root, true, "#" . $this->t("Departing on") . "\s*(.+)#")));

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            if ($this->http->XPath->query("//a[contains(@href,'airfrance')]")->length > 0) {
                $itsegment['AirlineName'] = 'AF';
            }
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
        return strpos($from, $this->reProvider) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $foundFrom = false;

        foreach ($this->reFrom as $reFrom) {
            if (strpos($headers["from"], $reFrom) !== false) {
                $foundFrom = true;
            }
        }

        if ($foundFrom == false) {
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
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        if ($this->http->XPath->query("//text()[contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','##########################'),'(###)')]")->length > 0) {
            $this->logger->debug("looks like hase detaeils and air codes");
            $this->logger->debug("go to parse by airfrance:YourReservation");

            return null;
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'CompleteYourFile' . ucfirst($this->lang),
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		$this->logger->info('date = ' . $str);
        if ($this->lang == 'en' && !empty($this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Please complete the information')])[1]"))) {
            $str = $this->normalizeDateFormatUS($str);
        }
        $in = [
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s*(\d{2})\s+(?:at|om|à|a las|alle ore)\s+(\d+:\d+)[.\s]*$#", //Mon 20 November 17 at 10:50, Za 11 November 17 om 09:00
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})[.]?\s+(?:at|om|à|a las|alle ore|um)\s+(\d+:\d+)[.\s]*$#", // 28/09/2018. à 21:05
        ];
        $out = [
            "$1 $2 20$3, $4",
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeDateFormatUS($date)
    {
        if (preg_match("/^\s*(?<d>\d+)\/(?<m>\d+)\/(?<y>\d{4})\s*$/u", $date, $m)) {
            if ($m['m'] > 12 && $m['d'] < 12) {
                $date = $m['m'] . '.' . $m['d'] . '.' . $m['y'];
            } elseif ($m['d'] > 12 && $m['m'] < 12) {
                $date = $m['d'] . '.' . $m['m'] . '.' . $m['y'];
            } else {
                $date = null;
            }
        }

        return $date;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
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
