<?php

namespace AwardWallet\Engine\colombia\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "colombia/it-5920835.eml, colombia/it-5920836.eml, colombia/it-5942062.eml, colombia/it-9793481.eml, colombia/it-9914901.eml, colombia/it-9955792.eml";
    public $reFrom = ["reservas@vivacolombia.co", "reserva@vivacolombia.co"];
    public $reSubject = [
        "es"=> "Confirmación de tu reserva - VivaColombia",
        "en"=> "Booking confirmation",
    ];
    public $reBody = 'vivacolombia';
    public $reBody2 = [
        "es"=> "Tu reserva",
        "en"=> "Your booking",
    ];

    public static $dictionary = [
        "es" => [
            //			'Código de reserva' => '',
            //			'Detalles pasajero(s)' => '',
            //			'Vuelo ida' => '',
            //			'Vuelo regreso' => '',
            //			'Vuelo' => '',
            //			'Confirmación de tu reserva' => '',
            //			'Total' => '',
            //			'Total tarifa:' => '',
            //			'Total IVA' => '',
            //			'Total tasas aeroportuarias' => '',
            //			'Servicios adicionales' => '',
        ],
        "en" => [
            'Código de reserva'          => 'Booking number',
            'Detalles pasajero(s)'       => 'Passenger(s) details',
            'Vuelo ida'                  => 'Departing flight',
            'Vuelo regreso'              => 'Returning flight',
            'Vuelo'                      => 'Flight',
            'Confirmación de tu reserva' => 'Booking confirmation',
            'Total'                      => 'Total',
            'Total tarifa:'              => 'Total fare:',
            'Total IVA'                  => 'Total VAT',
            'Total tasas aeroportuarias' => 'Total airport taxes',
            'Servicios adicionales'      => 'Total additional services',
        ],
    ];

    public $lang = "es";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Código de reserva"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Detalles pasajero(s)")) . "]/ancestor::tr[1]/following-sibling::tr/td/h5/following::text()[normalize-space(.)][1]");

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText($this->t("Total"), null, 2));

        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Total")));

        // BaseFare
        $fare = $this->getNodes($this->t('Total tarifa:'), '');
        $it['BaseFare'] = 0.0;

        foreach ($fare as $value) {
            $it['BaseFare'] += $this->amount($value);
        }

        // Tax
        $tax = $this->getNodes($this->t('Total IVA'));
        $it['Tax'] = 0.0;

        foreach ($tax as $value) {
            $it['Tax'] += $this->amount($value);
        }

        $it['Fees'] = [];
        $fees = $this->http->XPath->query("//td[(contains(normalize-space(.), '" . $this->t('Total tasas aeroportuarias') . "') or contains(normalize-space(.), '" . $this->t('Servicios adicionales') . "')) and not(.//td)]/ancestor::tr[1]/preceding-sibling::tr[position()<last()]");

        foreach ($fees as $key => $value) {
            $name = $this->http->FindSingleNode("./td[1]", $value);
            $chargeT = $this->http->FindSingleNode("./td[2]", $value);

            if (preg_match("#^(?:(\d+)\s*x\s*)?\D+(.+)#", $chargeT, $m)) {
                $charge = $this->amount($m[2]);

                if (!empty($m[1])) {
                    $charge = $m[1] * $charge;
                }
            }

            if (!isset($charge) || empty($name)) {
                continue;
            }

            foreach ($it['Fees'] as $i => $f) {
                if ($name == $f['Name']) {
                    $it['Fees'][$i]['Charge'] += $charge;

                    continue 2;
                }
            }
            $it['Fees'][] = [
                'Name'   => $name,
                'Charge' => $charge,
            ];
            unset($charge);
        }

        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("//td[normalize-space(.)='" . $this->t('Total') . "' and not(.//td)]/following-sibling::td[1]"));
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//td[normalize-space(.)='" . $this->t('Total') . "' and not(.//td)]/following-sibling::td[1]"));

        // SpentAwards
        // EarnedAwards
        // Status
        if (!empty($this->http->FindSingleNode("(//*[contains(normalize-space(.), '" . $this->t('Confirmación de tu reserva') . "')])[1]"))) {
            $it['Status'] = 'confirmed';
        }

        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//h4[" . $this->eq($this->t("Vuelo ida")) . " or " . $this->eq($this->t("Vuelo regreso")) . "]/following-sibling::table[1]//tr[2]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate(implode(" ", $this->http->FindNodes("./tr[1]/descendant::text()[normalize-space(.)][position()>1]", $root))));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Vuelo")) . "]", $root, true, "#" . $this->t("Vuelo") . "\s+[A-Z\d]{2}[A-Z]?\s*(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[2]/descendant::text()[normalize-space(.)][2]", $root);

            // DepName
            $route = $this->http->FindSingleNode("./tr[1]/descendant::text()[normalize-space(.)][1]", $root);
            $nameparts = explode(" - ", $route);
            $itsegment['DepName'] = count($nameparts) == 2 ? $nameparts[0] : (count($nameparts) == 4 ? $nameparts[0] . ' - ' . $nameparts[1] : null);

            if (count($nameparts) == 3) {
                $route = str_replace(" - El Dorado", "-El Dorado", $route);
                $route = str_replace(" - Panamá Pacífico", "-Panamá Pacífico", $route);
                $nameparts = explode(" - ", $route);
                $itsegment['DepName'] = count($nameparts) == 2 ? $nameparts[0] : (count($nameparts) == 4 ? $nameparts[0] . ' - ' . $nameparts[1] : null);
            }
            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./tr[2]/descendant::text()[normalize-space(.)][1]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[2]/descendant::text()[normalize-space(.)][4]", $root);

            // ArrName
            $itsegment['ArrName'] = count($nameparts) == 2 ? $nameparts[1] : (count($nameparts) == 4 ? $nameparts[2] . ' - ' . $nameparts[3] : null);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./tr[2]/descendant::text()[normalize-space(.)][3]", $root), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Vuelo")) . "]", $root, true, "#" . $this->t("Vuelo") . "\s+([A-Z\d]{2}[A-Z]?)\s*\d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./tr[2]/td[4]", $root);

            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (strpos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
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
            'emailType'  => 'BookingConfirmation' . ucfirst($this->lang),
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

    private function getNodes($str, $node = '/h3')
    {
        return $this->http->FindNodes("//td[contains(normalize-space(.), '{$str}') and not(.//td)]/following-sibling::td{$node}[1]");
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
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //THU, 07  DIC  2017
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], 'es')) {
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            'COP$'=> 'COP',
            'USD$'=> 'USD',
            'US$' => 'USD',
            '€'   => 'EUR',
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
}
