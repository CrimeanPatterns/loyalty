<?php

namespace AwardWallet\Engine\aeromexico\Email;

use AwardWallet\Engine\MonthTranslate;

class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-8824924.eml, aeromexico/it-8870749.eml";

    public $reFrom = "@aeromexico.com";
    public $reSubject = [
        "en"=> "Aeromexico Itinerary",
        "fr"=> "Confirmation de voyage",
    ];
    public $reBody = 'AEROMEXICO';
    public $reBody2 = [
        "es"=> "LLEGADA",
        "fr"=> "ARRIVÉE",
    ];

    public static $dictionary = [
        "es" => [
            //			"CÓDIGO DE RESERVACIÓN" => "",
            //			"Preparado para" => "",
            //			"NÚMERO DE BOLETO" => "",
            //			"OTRAS NOTAS" => "",
            //			"Detalles De Pago" => "",
            "SegmentEnd" => ["Detalles De Pago"],
            //			"FECHA" => "",
            //			"AEROLÍNEA" => "",
            //			"SALIDA" => "",
            //			"LLEGADA" => "",
            //			"Hora" => "",
            //			"TERMINAL" => "",
            //			"Operado por:" => "",
            //			"Clase" => "",
            //			"Número de asiento" => "",
            //			"Cantidad equivalente pagada" => "",
            //			"Tarifa" => "",
            //			"Impuestos / comisiones / cargos" => "",
            //			"Importe Total" => "",
        ],
        "fr" => [
            "CÓDIGO DE RESERVACIÓN" => "CODE DE RÉSERVATION",
            "Preparado para"        => "Préparé pour",
            "NÚMERO DE BOLETO"      => "NUMÉRO DE BILLET",
            "OTRAS NOTAS"           => "AUTRES REMARQUES",
            "SegmentEnd"            => ["Informations De Paiement", "Franchises"],
            "Detalles De Pago"      => "Informations De Paiement",
            "FECHA"                 => "VOYAGE", // last str
            "AEROLÍNEA"             => "AÉRIENNE", // last str
            "SALIDA"                => "DÉPART",
            "LLEGADA"               => "ARRIVÉE",
            "Hora"                  => "Heure",
            "TERMINAL"              => "TERMINAL",
            //			"Operado por:" => "",
            //			"Clase" => "",
            "Número de asiento"               => "Numéro de siège",
            "Cantidad equivalente pagada"     => "Montant payé équivalent",
            "Tarifa"                          => "Tarifs",
            "Impuestos / comisiones / cargos" => "Taxes / charges / frais",
            "Importe Total"                   => "Montant total",
        ],
    ];

    public $lang = "es";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $itsRepeated = false;
        $itsRepeatedIndex = 0;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->t('CÓDIGO DE RESERVACIÓN') . "\s+(\w+)#", $text);

        foreach ($itineraries as $key => $itin) {
            if ($itin['RecordLocator'] == $it['RecordLocator']) {
                $itsRepeated = true;
                $itsRepeatedIndex = $key;
            }
        }

        // TripNumber
        // Passengers
        if ($itsRepeated) {
            $itineraries[$itsRepeatedIndex]['Passengers'][] = trim($this->re("#" . $this->t('Preparado para') . "\n\s*(.+)\s+" . $this->t('CÓDIGO DE RESERVACIÓN') . "#ms", $text));
        } else {
            $it['Passengers'][] = trim($this->re("#" . $this->t('Preparado para') . "\n\s*(.+)\s+" . $this->t('CÓDIGO DE RESERVACIÓN') . "#ms", $text));
        }

        // TicketNumbers
        if ($itsRepeated) {
            $itineraries[$itsRepeatedIndex]['TicketNumbers'][] = $this->re("#" . $this->t('NÚMERO DE BOLETO') . "\s+([^\n]+)#", $text);
        } else {
            $it['TicketNumbers'][] = $this->re("#" . $this->t('NÚMERO DE BOLETO') . "\s+([^\n]+)#", $text);
        }

        // AccountNumbers
        // Cancelled

        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // Fees
        $posCost = mb_strpos($text, $this->t('Detalles De Pago'), 0, 'UTF-8');

        if ($posCost > 0) {
            $costInfo = mb_substr($text, $posCost);

            if (preg_match("#" . $this->t('Tarifa') . "\s+(?<cur1>[A-Z]{3})\s*(?<price1>\d[\d., ]+)\s+" . $this->t('Cantidad equivalente pagada') . "\s+(?<cur2>[A-Z]{3})\s*(?<price2>\d[\d., ]+)\s+"
                    . $this->t('Impuestos / comisiones / cargos') . "(?<fees>[\s\S]+)\s+" . $this->t('Importe Total') . "\s+(?<curT>[A-Z]{3})\s*(?<priceT>\d[\d., ]+)#", $costInfo, $m)) {
                $it['TotalCharge'] = $this->normalizePrice($m['priceT']);
                $it['Currency'] = $m['curT'];

                if ($m['curT'] == $m['cur2']) {
                    $it['BaseFare'] = $this->normalizePrice($m['price2']);
                } elseif ($m['curT'] == $m['cur1']) {
                    $it['BaseFare'] = $this->normalizePrice($m['price1']);
                }

                if (preg_match_all("#\s+[A-Z]{3}\s*(\d[\d., ]+)\s+(\S(?:.*\n){1,4})(?=(\s+[A-Z]{3}\s*\d|$))#uU", $m['fees'], $mat)) {
                    foreach ($mat[1] as $i => $name) {
                        $it['Fees'][] = [
                            "Name"   => trim(preg_replace("#\s+#", " ", $mat[2][$i])),
                            "Charge" => $this->normalizePrice($mat[1][$i]),
                        ];
                    }
                }

                if ($itsRepeated) {
                    $itineraries[$itsRepeatedIndex]['TotalCharge'] += $it['TotalCharge'];
                    $itineraries[$itsRepeatedIndex]['BaseFare'] += $it['BaseFare'];

                    foreach ($itineraries[$itsRepeatedIndex]['Fees'] as $i => $value) {
                        foreach ($it['Fees'] as $j => $name) {
                            if ($value["Name"] == $name["Name"]) {
                                $itineraries[$itsRepeatedIndex]['Fees'][$i]["Charge"] += $name["Charge"];
                            }
                        }
                    }
                }
            }
        }

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $posBegin = mb_strpos($text, $this->t('OTRAS NOTAS'));

        if ($posBegin === false) {
            return [];
        }

        foreach ($this->t('SegmentEnd') as $value) {
            $pos[] = mb_strpos($text, $value, $posBegin, 'UTF-8');
        }

        if (isset($pos)) {
            $pos = array_filter($pos);
            asort($pos);
            $posEnd = array_shift($pos);
        }

        if ($posEnd !== false) {
            $segments = $this->split("#\n(\s{0,10}\d+\w+\.?\d{2}\s{2,})#", mb_substr($text,
                $posBegin + mb_strlen($this->t('OTRAS NOTAS'), 'UTF-8'),
                $posEnd - $posBegin - mb_strlen($this->t('OTRAS NOTAS'), 'UTF-8'),
                'UTF-8'
            ));
        } else {
            $segments = $this->split("#\n(\s{0,10}\d+\w+\.?\d{2}\s{2,})#", mb_substr($text,
                mb_strpos($text, $this->t('OTRAS NOTAS')) + mb_strlen($this->t('OTRAS NOTAS'), 'UTF-8'),
                mb_strpos($text, $this->t('Detalles De Pago'), 0, 'UTF-8') - mb_strpos($text, $this->t('OTRAS NOTAS'), 0, 'UTF-8') - mb_strlen($this->t('OTRAS NOTAS'), 'UTF-8'),
                'UTF-8'
            ));
        }
        $rows = explode("\n", $text);

        //macth column positions
        foreach ($rows as $i => $row) {
            if (strpos($row, $this->t("OTRAS NOTAS")) !== false) {
                $positions = [];
                $positions[$this->t('OTRAS NOTAS')] = mb_strpos($row, $this->t('OTRAS NOTAS'), 0, 'UTF-8');
                $positions[$this->t('FECHA')] = mb_strpos($row, $this->t('FECHA'), 0, 'UTF-8');
                $positions[$this->t('AEROLÍNEA')] = mb_strpos($row, $this->t('AEROLÍNEA'), 0, 'UTF-8');
                $positions[$this->t('SALIDA')] = mb_strpos($row, $this->t('SALIDA'), 0, 'UTF-8');
                $positions[$this->t('LLEGADA')] = mb_strpos($row, $this->t('LLEGADA'), 0, 'UTF-8');

                foreach ($positions as $key => $value) {
                    if ($value === false) {
                        $positions[$key] = mb_strpos($rows[$i + 1], $key, 0, 'UTF-8');
                    }
                }
                asort($positions);

                break;
            }
        }

        if (!isset($positions) || count(array_filter($positions)) < 4) {
            return;
        }
        arsort($positions);

        foreach ($segments as $k=>$stext) {
            //match columns
            $cols = [];
            $rows = explode("\n", $stext);

            foreach ($rows as $row) {
                foreach ($positions as $name=>$pos) {
                    $cols[$name][] = trim(mb_substr($row, $pos, null, 'UTF-8'));
                    $row = mb_substr($row, 0, $pos, 'UTF-8');
                }
            }

            if (!isset($cols[$this->t('FECHA')], $cols[$this->t('AEROLÍNEA')], $cols[$this->t('SALIDA')], $cols[$this->t('LLEGADA')], $cols[$this->t('OTRAS NOTAS')])) {
                return;
            }

            foreach ($cols as &$col) {
                $col = implode("\n", $col);
            }

            $dateStr = trim($cols[$this->t('FECHA')]);
            $dateStr = explode("-", $dateStr);
            $date = strtotime($this->normalizeDate(trim($dateStr[0])));

            if (!empty($dateStr[1])) {
                $dateArr = strtotime($this->normalizeDate(trim($dateStr[1])));
            }

            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#\n\w{2}\s+(\d+)\n#", $cols[$this->t('AEROLÍNEA')]);

            // DepName
            $itsegment['DepName'] = trim(preg_replace("#\s+#", " ", trim($this->re("#(.*?)" . $this->t('Hora') . "#ms", $cols[$this->t('SALIDA')]))));

            // DepCode
            $code = array_unique(array_filter($this->http->FindNodes("//text()[contains(normalize-space(),'" . $itsegment['DepName'] . "')][1]", null, "#^\s*([A-Z]{3})\s+" . $itsegment['DepName'] . "\s*$#u")));

            if (count($code) == 1) {
                $itsegment['DepCode'] = array_shift($code);
            } else {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->re("#(" . $this->t('TERMINAL') . "[^\n]+)#ms", $cols[$this->t('SALIDA')]);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->re("#" . $this->t('Hora') . "\s+([^\n]+)#ms", $cols[$this->t('SALIDA')]), $date);

            // ArrName
            $itsegment['ArrName'] = str_replace("\n", " ", trim($this->re("#(.*?)" . $this->t('Hora') . "#ms", $cols[$this->t('LLEGADA')])));

            // ArrCode
            $code = array_unique(array_filter($this->http->FindNodes("//text()[contains(normalize-space(),'" . $itsegment['ArrName'] . "')][1]", null, "#^\s*([A-Z]{3})\s+" . $itsegment['ArrName'] . "\s*$#u")));

            if (count($code) == 1) {
                $itsegment['ArrCode'] = array_shift($code);
            } else {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->re("#(" . $this->t('TERMINAL') . "[^\n]+)#ms", $cols[$this->t('LLEGADA')]);

            // ArrDate
            if (isset($dateArr) && $dateArr) {
                $itsegment['ArrDate'] = strtotime($this->re("#" . $this->t('Hora') . "\s+([^\n]+)#ms", $cols[$this->t('LLEGADA')]), $dateArr);
            } else {
                $itsegment['ArrDate'] = strtotime($this->re("#" . $this->t('Hora') . "\s+([^\n]+)#ms", $cols[$this->t('LLEGADA')]), $date);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#\n(\w{2})\s+\d+\n#", $cols[$this->t('AEROLÍNEA')]);

            // Operator
            $itsegment['Operator'] = $this->re("#" . $this->t('Operado por:') . "\s+(.*?)(\n\n|$)#ms", $cols[$this->t('AEROLÍNEA')]);

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#" . $this->t('Clase') . "\s+([^\n]+)#", $cols[$this->t('OTRAS NOTAS')]);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'][] = $this->re("#" . $this->t('Número de asiento') . "\s+(\d{1,3}[A-Z])#", $cols[$this->t('OTRAS NOTAS')]);

            // Duration
            // Meal
            // Smoking
            // Stops
            if ($itsRepeated) {
                foreach ($itineraries[$itsRepeatedIndex]['TripSegments'] as $key => $value) {
                    if (isset($itsegment['FlightNumber']) && $itsegment['FlightNumber'] == $value['FlightNumber'] && isset($itsegment['AirlineName']) && $itsegment['AirlineName'] == $value['AirlineName'] && isset($itsegment['DepDate']) && $itsegment['DepDate'] == $value['DepDate']) {
                        $itineraries[$itsRepeatedIndex]['TripSegments'][$key]['Seats'] = array_filter(array_merge($value['Seats'], $itsegment['Seats']));
                        $finded2 = true;
                    }
                }
            } else {
                $it['TripSegments'][] = $itsegment;
            }
        }

        if (!$itsRepeated) {
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('(Recibo de pasaje electrónico|.*billet.*ectronique).*.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('(Recibo de pasaje electrónico|.*billet.*ectronique).*.pdf');

        if (!isset($pdfs[0])) {
            return null;
        }

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            foreach ($this->reBody2 as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->parsePdf($itineraries);
        }

        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
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

    protected function normalizePrice($cost)
    {
        if (empty($cost)) {
            return 0.0;
        }
        $cost = preg_replace('/\s+/', '', $cost);			// 11 507.00	->	11507.00
        $cost = preg_replace('/[,.](\d{3})/', '$1', $cost);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $cost = preg_replace('/,(\d{2})$/', '.$1', $cost);	// 18800,00		->	18800.00

        return (float) $cost;
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
            "#^(\d+)([^\d\s\.]+)\.?(\d{2})$#", //11may17, 03oct.17
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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
