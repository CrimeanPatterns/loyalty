<?php

namespace AwardWallet\Engine\aerolineas\Email;

class YourTicketIssuedPlain extends \TAccountChecker
{
    public $mailFiles = "aerolineas/it-6162065.eml";
    public $reFrom = "reserve@us.aerolineas.aero";
    public $reSubject = [
        "en"=> "Aerolineas Argentinas ticket issued for",
    ];
    public $reBody = 'Aerolineas Argentinas';
    public $reBody2 = [
        "en"=> "Departure:",
        "es"=> "Salida:",
        "pt"=> "Partida:",
        "it"=> "Partenza:",
    ];

    public static $dictionary = [
        "en" => [],
        "es" => [
            "Reservation code"    => ["Código de Reserva", "Reservation code"],
            "Seat(s):"            => "Asiento:",
            "Departure:"          => "Salida:",
            "Arrival:"            => "Llegada:",
            "Flight Number"       => "Número de vuelo",
            "Operated by:"        => "Operado por:",
            "Aircraft:"           => "Aeronave:",
            "Distance (in Miles):"=> "Distancia (en Millas):",
            "Class:"              => "Clase:",
            "Duration:"           => "Duración:",
            "Meal:"               => "Comida:",
        ],
        "pt" => [
            "Reservation code"    => ["Codigo da reserva", "Reservation code"],
            "Seat(s):"            => "Assento:",
            "Departure:"          => "Partida:",
            "Arrival:"            => "Chegada:",
            "Flight Number"       => "Número do vôo",
            "Operated by:"        => "Operado por:",
            "Aircraft:"           => "Aeronave:",
            "Distance (in Miles):"=> "Distância (em milhas):",
            "Class:"              => "Classe:",
            "Duration:"           => "Duração:",
            "Meal:"               => "Refeição:",
        ],
        "it" => [
            "Reservation code"    => "Codice di Prenotazione",
            "Seat(s):"            => "Posto:",
            "Departure:"          => "Partenza:",
            "Arrival:"            => "Arrivo:",
            "Flight Number"       => "Numero volo",
            "Operated by:"        => "NOTTRANSLATED",
            "Aircraft:"           => "Aeromobile:",
            "Distance (in Miles):"=> "Distanza (in miglia):",
            "Class:"              => "Classe:",
            "Duration:"           => "Durata:",
            "Meal:"               => "Pasto:",
        ],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = str_replace(["\n> ", "\r"], ["\n", ""], $this->http->Response['body']);
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->opt($this->t("Reservation code")) . "\s+(\w+)#", $text);

        // TripNumber
        // Passengers
        preg_match_all("#([^\n]+)\nAsiento:#", $text, $Passengers);
        $it['Passengers'] = array_unique($Passengers[1]);

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

        $segments = $this->split("#([^\n]+" . $this->t("Flight Number") . ")#", $text);
        $test = substr_count($text, $this->t("Flight Number"));

        if (count($segments) != $test) {
            return;
        }

        foreach ($segments as $stext) {
            $root = $this->http->XPath->query("/")->item(0);
            $all = $this->http->XPath->query("./..", $root)->item(0);

            $date = strtotime($this->normalizeDate($this->re("#	([^\n]+)\n" . $this->t("Departure:") . "#", $stext)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#" . $this->t("Flight Number") . "\s+(?:\w{2}\s+)?(\d+)#", $stext);

            // DepCode
            $itsegment['DepCode'] = $this->re("#" . $this->t("Departure:") . "\s+([A-Z]{3})\s+#", $stext);

            // DepName
            $itsegment['DepName'] = $this->re("#" . $this->t("Departure:") . "\s+[A-Z]{3}\s+(.+)#", $stext);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeTime($this->re("#" . $this->t("Departure:") . "\s+[^\n]+\n(.+)#", $stext)), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#" . $this->t("Arrival:") . "\s+([A-Z]{3})\s+#", $stext);

            // ArrName
            $itsegment['ArrName'] = $this->re("#" . $this->t("Arrival:") . "\s+[A-Z]{3}\s+(.+)#", $stext);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeTime($this->re("#" . $this->t("Arrival:") . "\s+[^\n]+\n(.+)#", $stext)), $date);

            // AirlineName
            if (!$itsegment['AirlineName'] = $this->re("#" . $this->t("Flight Number") . "\s+(\w{2})\s+\d+#", $stext)) {
                $itsegment['AirlineName'] = $this->re("#\s+(\w{2})\s+" . $this->t("Flight Number") . "\s+\d+#", $stext);
            }

            // Operator
            $itsegment['Operator'] = $this->re("#" . $this->t("Operated by:") . "\s+(.+)#", $stext);

            // Aircraft
            $itsegment['Aircraft'] = $this->re("#" . $this->t("Aircraft:") . "\s+(.+)#", $stext);

            // TraveledMiles
            $itsegment['TraveledMiles'] = $this->re("#" . $this->t("Distance (in Miles):") . "\s+(.+)#", $stext);

            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#" . $this->t("Class:") . "\s+(.+)#", $stext);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = implode(", ", array_filter(preg_match_all("#" . $this->t("Seat(s):") . "\s+(\d+\w)#", $stext, $m) ? $m[1] : []));

            // Duration
            $itsegment['Duration'] = $this->re("#" . $this->t("Duration:") . "\s+(.+)#", $stext);

            // Meal
            $itsegment['Meal'] = $this->re("#" . $this->t("Meal:") . "\s+(.+)#", $stext);

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
        $body = $parser->getPlainBody();

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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                return false;
            }
        }

        $this->http->setBody($parser->getPlainBody());

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => current(array_slice(explode('\\', __CLASS__), -1)) . ucfirst($this->lang),
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
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)$#", //Friday, 20 May
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+-\s+[^\d\s]+,\s+\d+\s+[^\d\s]+$#", //Friday, 20 May - Saturday, 21 May
        ];
        $out = [
            "$1 $2 $year",
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+)\s+(\+\d+)\s+(?:día|dia)$#", //15:35 +1 día , 09:55 +1 dia
            "#^(\d+:\d+)\s+arribo al día siguiente$#", //16:10 arribo al día siguiente
        ];
        $out = [
            "$1 $2 day",
            "$1 +1 day",
        ];
        $str = preg_replace($in, $out, $str);

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
