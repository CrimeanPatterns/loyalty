<?php

namespace AwardWallet\Engine\colombia\Email;

class It5924233 extends \TAccountChecker
{
    public $mailFiles = "colombia/it-5924233.eml, colombia/it-5924236.eml, colombia/it-6062468.eml";
    public $reFrom = "reservaciones@vivacolombia.co";
    public $reSubject = [
        "es"=> "Confirmación de Reserva:",
    ];
    public $reBody = 'VivaColombia';
    public $reBody2 = [
        "es"=> "Itinerario",
    ];

    public static $dictionary = [
        "es" => [
            "Código de reserva:"=> ["Código de reserva:", "Codigo de reserva:", "Reservacion:"],
            "Descripcion"       => ["Descripcion", "Descripción"],
        ],
    ];

    public $lang = "es";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Código de reserva:"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//td[1][" . $this->eq($this->t("Pasajero")) . "]/ancestor::tr[1][" . $this->contains($this->t("Descripcion")) . "]/following-sibling::tr/td[1]");

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Resumen")) . "]/ancestor::tr[1]/../tr[last()]/td[2]", null, true, "#^[\d\,\.]+$#"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Currency")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[5]");

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $this->cols = $this->http->FindNodes("//text()[" . $this->eq($this->t("Vuelo")) . "]/ancestor::tr[1]/td");
        $xpath = "//text()[" . $this->eq($this->t("Vuelo")) . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }
        //		echo $xpath."\n";

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[" . $this->c('Fecha') . "]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            // DepCode
            if (!$itsegment['DepCode'] = $this->http->FindSingleNode("./td[" . $this->c('Origen') . "]", $root, true, "#^([A-Z]{3})\s+#")) {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // DepName
            if ($itsegment['DepCode'] == TRIP_CODE_UNKNOWN) {
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[" . $this->c('Origen') . "]/descendant::text()[normalize-space(.)][1]", $root);
            } else {
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[" . $this->c('Origen') . "]/descendant::text()[normalize-space(.)][1]", $root, true, "#^[A-Z]{3}\s+-\s+(.+)#");
            }

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[" . $this->c('Salida') . "]", $root), $date);

            // ArrCode
            if (!$itsegment['ArrCode'] = $this->http->FindSingleNode("./td[" . $this->c('Destino') . "]", $root, true, "#^([A-Z]{3})\s+#")) {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // ArrName
            if ($itsegment['DepCode'] == TRIP_CODE_UNKNOWN) {
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[" . $this->c('Destino') . "]/descendant::text()[normalize-space(.)][1]", $root);
            } else {
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[" . $this->c('Destino') . "]/descendant::text()[normalize-space(.)][1]", $root, true, "#^[A-Z]{3}\s+-\s+(.+)#");
            }

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[" . $this->c('Llegada') . "]", $root), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[" . $this->c('Código Tarifa') . "]", $root);

            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[" . $this->c('Clase') . "]", $root);

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

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

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
            "#^(\d+)([^\d\s]+)(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3",
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

    private function c($n)
    {
        if (($k = array_search($n, $this->cols)) !== false) {
            return $k + 1;
        }

        return 0;
    }
}
