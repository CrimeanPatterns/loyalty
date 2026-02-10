<?php

namespace AwardWallet\Engine\aeroflot\Email;

use AwardWallet\Engine\MonthTranslate;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-11387574.eml, aeroflot/it-11419875.eml, aeroflot/it-11512155.eml, aeroflot/it-11714978.eml, aeroflot/it-33639418.eml, aeroflot/it-6073748.eml, aeroflot/it-8347975.eml, aeroflot/it-8675051.eml, aeroflot/it-8680620.eml, aeroflot/it-8698922.eml, aeroflot/it-8756227.eml, aeroflot/it-9640094.eml";
    public $reFrom = "@aeroflot.ru";
    public $reSubject = [
        "en" => "You have successfully checked in to your flight.",
        "ru" => "Вы успешно зарегистрированы на рейс",
        "it" => "Check-in eseguito correttamente per il volo",
        "es" => "Ha facturado correctamente para su vuelo.",
        "ja" => "フライトのチェックインに成功しました。",
        "fr" => "Vous êtes bien enregistré sur votre vol",
        "de" => "Reservierungscode:",
    ];
    public $reBody = 'Aeroflot';
    public $reBody2 = [
        "en" => ["Passengers:"],
        "ru" => ["Пассажиры:", 'Счастливого пути'],
        "it" => ["Passeggeri:"],
        "es" => ["Pasajeros:"],
        "ja" => ["搭乗者:"],
        "fr" => ["Passagers:"],
        "de" => ["Passagiere:"],
    ];
    public $ticket = [];
    public $accountNumbers = [];

    public static $dictionary = [
        "en" => [],
        "ru" => [
            "Reservation code:"=> ["Код бронирования:", "Reservation code:"],
            "Passengers:"      => "Пассажиры:",
            "Boarding gate"    => ["Выход на посадку", "Boarding gate"],
        ],
        "it"=> [
            "Reservation code:"=> ["Codice di prenotazione:"],
            "Passengers:"      => "Passeggeri:",
            "Boarding gate"    => ["Gate d'imbarco"],
        ],
        "es"=> [
            "Reservation code:"=> ["Código de reserva:"],
            "Passengers:"      => "Pasajeros:",
            "Boarding gate"    => ["Puerta de embarque"],
        ],
        "ja"=> [
            "Reservation code:"=> ["予約コード："],
            "Passengers:"      => "搭乗者:",
            "Boarding gate"    => ["搭乗ゲート"],
        ],
        "fr"=> [
            "Reservation code:"=> ["Code de réservation :"],
            "Passengers:"      => "Passagers:",
            "Boarding gate"    => ["Porte d'embarquement"],
        ],
        "de"=> [
            "Reservation code:"=> ["Reservierungscode:"],
            "Passengers:"      => "Passagiere:",
            "Boarding gate"    => ["Abfluggate", 'Boardingzeit'],
        ],
    ];

    public $lang = "en";

    private $date = 0;

    /** @var \HttpBrowser */
    private $pdf;

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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            $body .= \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
        }

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            foreach ($re as $r) {
                if (stripos($body, $r) !== false) {
                    return true;
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

        foreach ($this->reBody2 as $lang=>$re) {
            foreach ($re as $r) {
                if (strpos($this->http->Response["body"], $r) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*boarding_pass\.*.pdf');
        $text = '';

        foreach ($pdfs as $pdf) {
            if (($text .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                $this->pdf = clone $this->http;
                $this->pdf->SetEmailBody($text);
            } else {
                continue;
            }
        }

        $text = text($text);

        if (!empty($text) && preg_match_all("#Electronic ticket(?:.*\n){1,3}\s*([\d]{9,})\s#", $text, $m)) {
            $this->ticket = array_values(array_unique($m[1]));
        }

        if (!empty($text) && preg_match_all("#Member ID(?:.*\n){1,3}\s*([A-Z]{2}[ ]*\d{6,})\s#", $text, $m)) {
            $this->accountNumbers = array_values(array_unique($m[1]));
        }

        $this->parseHtml($itineraries);

        if (!empty($text) && empty($itineraries[0]['RecordLocator']) && empty($itineraries[0]['TripSegments'])) {
            $itineraries = [];
            $this->parsePdf($itineraries, $text);
            $type = 'pdf';
        }

        $emailType = 'AirTicket' . ucfirst($this->lang);

        if (isset($type)) {
            $emailType = 'AirTicket' . ucfirst($type) . ucfirst($this->lang);
        }
        $result = [
            'emailType'  => $emailType,
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
        return count(self::$dictionary) + 1;
    }

    private function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Reservation code:"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Passengers:")) . "]/ancestor::tr[1]/following-sibling::tr/td[1]");

        // TicketNumbers
        if (!empty($this->ticket)) {
            $it['TicketNumbers'] = $this->ticket;
        }
        // AccountNumbers
        if (!empty($this->accountNumbers)) {
            $it['AccountNumbers'] = $this->accountNumbers;
        }
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

        $xpath = "//text()[" . $this->eq($this->t("Boarding gate")) . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $ruleTime = "translate(normalize-space(.),'0123456789','dddddddddd--')='dd:dd'";

            $xpath = "//text()[{$ruleTime}]/ancestor::tr[count(./descendant::text()[{$ruleTime}])=2][1]/preceding-sibling::tr[normalize-space(.)][1]";
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length == 0) {
                $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
            }
        }
        $this->http->Log("segments root: $xpath", LOG_LEVEL_NORMAL);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)][1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s+\d+\s+.*?,\s+(.+)#")));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)][1]", $root, true, "#^\w{2}\s+(\d+)#");
            $node = implode(" ", $this->http->FindNodes("./following-sibling::tr[normalize-space(.)][1]//text()", $root));

            if (preg_match("#(?<DepTime>\d+:\d+)\s*(?<DepCode>[A-Z]{3})\s+(?<ArrCode>[A-Z]{3})\s*(?<ArrTime>\d+:\d+)(?<newDate>\s*\+\d)?#", $node, $m)) {
                $itsegment['DepCode'] = $m['DepCode'];
                $itsegment['DepDate'] = strtotime($m['DepTime'], $date);
                $itsegment['ArrCode'] = $m['ArrCode'];
                $itsegment['ArrDate'] = strtotime($m['ArrTime'], $date);

                if (isset($m['newDate'])) {
                    $itsegment['ArrDate'] = strtotime($m['newDate'] . ' day', $itsegment['ArrDate']);
                }
                $pos = count($this->http->FindNodes("//text()[normalize-space(.)='{$m['DepCode']} → {$m['ArrCode']}']/ancestor::td[1]/preceding-sibling::td"));
                $itsegment['Seats'] = $this->http->FindNodes("//text()[normalize-space(.)='{$m['DepCode']} → {$m['ArrCode']}']/ancestor::tr[1]/following-sibling::tr/td[" . ($pos + 1) . "]");
            }

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]", $root);

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[normalize-space(.)][1]/td[contains(.,'/')]", $root, true, "#\w+\s+/\s+(\w+)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]", $root);

            // ArrivalTerminal
            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)][1]", $root, true, "#^(\w{2})\s+\d+#");

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space(.)][1]", $root, true, "#^\w{2}\s+\d+\s+(.*?),#");

            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo

            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    private function parsePdf(&$itineraries, $text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        if (!empty($this->ticket)) {
            $it['TicketNumbers'] = $this->ticket;
        }
        // AccountNumbers
        if (!empty($this->accountNumbers)) {
            $it['AccountNumbers'] = $this->accountNumbers;
        }

        if ($this->pdf instanceof \HttpBrowser) {
            $psng = [];

            foreach ($this->pdf->FindNodes("//p[contains(., 'Passenger') and contains(., 'name')]/following-sibling::p[1]") as $i => $node) {
                if ($i % 2 === 0) {
                    $psng[] = $node;
                }
            }

            $lastNames = [];

            foreach ($this->pdf->FindNodes("//p[contains(., 'Passenger') and contains(., 'name')]/following-sibling::p[3]") as $i => $node) {
                if ($i % 2 === 0) {
                    $lastNames[] = $node;
                }
            }
            $passengers = array_map(function ($val) use (&$lastNames) {
                return $val . ' ' . array_shift($lastNames);
            }, $psng);
            $it['Passengers'] = array_values(array_unique($passengers));
        }

        preg_match_all('/PNR Code[\/\s]+Код брони\n\s+([A-Z\d]{5,7})/iu', $text, $m);

        if (count($m[1]) > 0 && count(array_unique($m[1])) === 1) {
            $it['RecordLocator'] = array_shift($m[1]);
        }

        $it['TripSegments'] = [];

        $xpath = "//div[contains(@id, 'page')]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            foreach ([
                'Dep' => 'From',
                'Arr' => 'To',
            ] as $key => $value) {
                $name = $this->orval($this->getNode($value, $root, 3));

                if (preg_match('/(.+)\s+\b([A-Z]{3})\b/', $name, $m)) {
                    $seg[$key . 'Name'] = trim(preg_replace(['/\//', "/\b{$m[2]}\b/"], ['', ''], $m[1]));
                    $seg[$key . 'Code'] = $m[2];
                } else {
                    $seg[$key . 'Name'] = $name;
                    $seg[$key . 'Code'] = $this->orval($this->getNode($value, $root, 1, "#^\s*/*\s*([A-Z]{3})\s*$#", 2), $this->getNode($value, $root, 1, "#^\s*/*\s*([A-Z]{3})\s*$#", 3));
                }
            }

            if ($this->pdf instanceof \HttpBrowser) {
                $seg['DepartureTerminal'] = $this->pdf->FindSingleNode("(.//p[contains(., 'TERMINAL') and following-sibling::p[1][contains(., 'From')]])[1]", $root, true, '/Terminal[\s:]+([A-Z\d]{1,3})/i');
                $seats = $this->pdf->FindNodes(".//p[contains(., 'Seat')]/following-sibling::p[preceding-sibling::p[not(contains(., 'Gate'))]][position() = 2 or position() = 3 or position() = 1]", $root, '/^\s*(\d{1,3}[A-Z])\s*$/');

                if (empty(array_filter($seats))) {
                    $seats = $this->pdf->FindNodes(".//text()[contains(normalize-space(), 'Gate') and contains(normalize-space(), 'closes')]/ancestor-or-self::p[1]/following-sibling::p[1]", $root, '/^\s*(\d{1,3}[A-Z])\s*$/');
                }
                $seg['Seats'] = array_values(array_unique(array_filter($seats)));
            }

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $this->getNode('Carrier', $root, 1, null, 2), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match('/([A-Z])\s*(\d+\s*\D+)/s', $this->getNode('Class/Date', $root, 1, null, 2), $m)) {
                $seg['BookingClass'] = $m[1];
                $date = $m[2] . ' ' . date('Y', $this->date);
            }

            if (isset($date) && preg_match('/(\d+:\d+)/', $this->getNode(['Departure', 'time'], $root, 1, null, 2), $m)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($date . ', ' . $m[1]));
            } elseif (isset($date) && preg_match('/(\d+:\d+)/', $this->getNode(['Depart', 'time'], $root, 1, null, 2), $m)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($date . ', ' . $m[1]));
            }

            $seg['ArrDate'] = MISSING_DATE;

            $finded = false;

            foreach ($it['TripSegments'] as $key => $value) {
                if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                        && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                        && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                    if (!empty($seg['Seats'])) {
                        $it['TripSegments'][$key]['Seats'] = array_values(array_unique(array_filter(array_merge($value['Seats'], $seg['Seats']))));
                    }
                    $finded = true;
                }
            }

            if ($finded == false) {
                $it['TripSegments'][] = $seg;
            }
        }

        $itineraries[] = $it;
    }

    private function orval(...$arr)
    {
        foreach ($arr as $item) {
            if (!empty($item)) {
                return $item;
            }
        }

        return null;
    }

    private function getNode($str, $root = null, $i = 1, $re = null, $p = 1)
    {
        if (!is_array($str)) {
            $str = [$str];
        }
        $str = implode(' and ', array_map(function ($s) {
            return "contains(normalize-space(.), '{$s}')";
        }, $str));

        if ($this->pdf instanceof \HttpBrowser) {
            return $this->pdf->FindSingleNode("(.//p[{$str}]/following-sibling::p[{$p}])[{$i}]", $root, true, $re);
        }

        return null;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    private function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\d\s]+),\s+([^\d\s]+)$#", //19 June, Monday
            "#^(\d+)\s+(\d+),\s+([^\d\s]+)$#u", //25 9, 月曜日
            "#^(\d+)([^\s\d]+) (\d{4}), (\d+:\d+)$#", //18MAR 2018, 20:40
        ];
        $out = [
            "$1 $2 $year",
            "$1.$2.$year",
            "$1 $2 $3, $4",
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
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
