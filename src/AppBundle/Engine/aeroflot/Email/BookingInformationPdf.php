<?php

namespace AwardWallet\Engine\aeroflot\Email;

class BookingInformationPdf extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-2510431.eml, aeroflot/it-4344771.eml, aeroflot/it-4975606.eml, aeroflot/it-4976322.eml, aeroflot/it-5577173.eml, aeroflot/it-5675736.eml, aeroflot/it-5828200.eml, aeroflot/it-5838351.eml, aeroflot/it-5851129.eml, aeroflot/it-5911043.eml, aeroflot/it-5915283.eml, aeroflot/it-5919893.eml, aeroflot/it-6224641.eml";

    public $reFrom = "@aeroflot.ru";
    public $reSubject = [
        "ru" => "Открыта регистрация на рейс для бронирования",
        "en" => "Check-in is open for booking",
    ];
    public $reBody = ['Aeroflot', 'Аэрофлот'];
    public $reBody2 = [
        "ru" => ["Маршрутная квитанция"],
        "en" => ["eTicket Receipt", "Itinerary e-ticket receipt"],
        "es" => ["Recibo de billete electrónico del itinerario"],
        "it" => ["Ricevuta del biglietto elettronico"],
    ];

    public static $dictionary = [
        "ru" => [
            //			"Exchanged" => "",
        ],
        "en" => [
            "Код бронирования"         => ["Booking reference", "Booking code"],
            "Подготовлено для"         => "Prepared for",
            "Номер(а) билета(ов)"      => "Ticket(s) number(s)",
            "Билет недействителен до:" => ["Not valid before:", "Ticket not valid before:"],
            "Нормы провоза багажа:"    => "Baggage Allowance:",
            //			"Exchanged" => "",
        ],
        "es" => [
            "Код бронирования"         => "Código de reserva",
            "Подготовлено для"         => "Preparado por",
            "Номер(а) билета(ов)"      => "Número(s) de billete(s)",
            "Билет недействителен до:" => "Billete no válido antes del:",
            "Нормы провоза багажа:"    => "Baggage Allowance:",
            //			"Exchanged" => "",
        ],
        "it" => [
            "Код бронирования"         => "Codice prenotazione",
            "Подготовлено для"         => "Preparato per",
            "Номер(а) билета(ов)"      => "Numero/i biglietto/i",
            "Билет недействителен до:" => "Biglietto non valido prima del:",
            "Нормы провоза багажа:"    => "Bagagli consentiti:",
            //			"Exchanged" => "",
        ],
    ];

    public $lang = "ru";

    public function parsePdf(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Код бронирования"));

        // TripNumber
        // Passengers
        $roots = $this->pdf->XPath->query("//text()[contains(.,'" . $this->t('Подготовлено для') . "')]/ancestor::tr[1]");
        $pax = [];

        foreach ($roots as $i => $root) {
            if (!$pax[$i] = $this->cell($root, 1, 1)) {
                if (!$pax[$i] = $this->cell($root, 1, 2)) {
                    $pax[$i] = $this->cell($root, 1, 3);
                }
            }

            if (stripos($pax[$i], 'Booking code') !== false) { // it-8312129
                unset($pax[$i]);
            }
        }
        $it['Passengers'] = array_unique($pax);
        // TicketNumbers
        $it['TicketNumbers'] = $this->pdf->FindNodes("//text()[normalize-space(.)='" . $this->t('Номер(а) билета(ов)') . "' and not(./following::*[normalize-space() = '" . $this->t('Exchanged') . "' and position()<20])]/following::text()[string-length(normalize-space(.))>1][1]");

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

        $xpath = "//text()[" . $this->contains($this->t('Билет недействителен до:'), '.') . " and not(./preceding::*[normalize-space() = '" . $this->t('Exchanged') . "'])]/ancestor::tr[1] | "
            . "//td[contains(translate(., '1234567890', 'dddddddddd'), 'd:dd')]/following-sibling::td[contains(translate(., '1234567890', 'dddddddddd'), 'd:dd')]/ancestor::tr[1] | "
            . "//td[contains(., '" . $this->t('Нормы провоза багажа:') . "')]/preceding-sibling::td[contains(translate(., '1234567890', 'dddddddddd'), 'd:dd')]/ancestor::tr[1]";
        $nodes = $this->pdf->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        $uniq = [];

        foreach ($nodes as $root) {
            if (!$date = $this->cell($root, 3, 1)) {
                $date = $this->cell($root, 3, 2);
            }

            $date = strtotime($this->normalizeDate($date));

            $itsegment = [];
            // FlightNumber

            $itsegment['FlightNumber'] = $this->re("#^\w{2}\s*(\d+)$#", $this->cell($root, 2, 0));

            if (isset($uniq[$itsegment['FlightNumber']])) {
                continue;
            }
            $uniq[$itsegment['FlightNumber']] = 1;

            $coords = [
                [3, 3],
                [3, 4],
                [3, 5],
                [4, 3],
                [4, 4],
                [5, 3],
                [5, 4],
            ];

            foreach ($coords as $c) {
                if ($itsegment['DepCode'] = $this->re("#^([A-Z]{3})$#", $this->cell($root, $c[0], $c[1]))) {
                    break;
                }
            }
            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->cell($root, 3, 0), $date);

            // ArrivalTerminal
            // ArrDate
            if ($time = $this->re("#(\d+:\d+)#", $this->cell($root, 6, 0))) {
                $coords = [
                    [8, 3],
                    [7, 3],
                    [6, 3],
                    [7, 4],
                    [6, 4],
                ];

                foreach ($coords as $c) {
                    if ($itsegment['ArrCode'] = $this->re("#^([A-Z]{3})$#", $this->cell($root, $c[0], $c[1]))) {
                        break;
                    }
                }

                $itsegment['ArrDate'] = strtotime($time, $date);

                if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                    $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
                }

                // Duration
                $itsegment['Duration'] = $this->cell($root, 5, 0);
            } elseif ($time = $this->re("#(\d+:\d+)#", $this->cell($root, 5, 0))) {
                $coords = [
                    [7, 3],
                    [6, 3],
                    [5, 3],
                    [6, 4],
                    [5, 4],
                    [5, 5],
                ];

                foreach ($coords as $c) {
                    if ($itsegment['ArrCode'] = $this->re("#^([A-Z]{3})$#", $this->cell($root, $c[0], $c[1]))) {
                        break;
                    }
                }

                $itsegment['ArrDate'] = strtotime($time, $date);

                if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                    $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
                }

                // Duration
                $itsegment['Duration'] = $this->cell($root, 4, 0);
            } else {
                // ArrCode
                if (!$itsegment['ArrCode'] = $this->re("#^([A-Z]{3})$#", $this->cell($root, 7, 1))) {
                    if (!$itsegment['ArrCode'] = $this->re("#^([A-Z]{3})$#", $this->cell($root, 6, 1))) {
                        $itsegment['ArrCode'] = $this->re("#^([A-Z]{3})$#", $this->cell($root, 5, 1));
                        $itsegment['ArrName'] = $this->cell($root, 5, 0);
                    } else {
                        $itsegment['ArrName'] = $this->cell($root, 5, 0);
                    }
                } else {
                    $itsegment['ArrName'] = $this->cell($root, 6, 0);
                }

                // ArrName
                //				if (!$itsegment['ArrName'] = $this->cell($root, 6, 0)) {
                //					$itsegment['ArrName'] = $this->cell($root, 5, 0);
                //					$itsegment['ArrName'] = $this->cell($root, 5, 0);
                //				}

                $itsegment['ArrDate'] = MISSING_DATE;

                // Duration
                $itsegment['Duration'] = $this->re("#\d\s+\w+#", $this->cell($root, 5, 0));
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^(\w{2})\s*\d+$#", $this->cell($root, 2, 0));

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->cell($root, 1, 1);

            // TraveledMiles
            // Cabin
            if (!$itsegment['Cabin'] = $this->pdf->FindSingleNode("./following-sibling::tr[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^(.*?)\s+/\s+\w$#")) {
                $itsegment['Cabin'] = $this->pdf->FindSingleNode("./following-sibling::tr[2]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^(.*?)\s+/\s+\w$#");
            }

            // BookingClass
            if (!$itsegment['BookingClass'] = $this->pdf->FindSingleNode("./following-sibling::tr[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\s+/\s+(\w)$#")) {
                $itsegment['BookingClass'] = $this->pdf->FindSingleNode("./following-sibling::tr[2]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\s+/\s+(\w)$#");
            }

            // PendingUpgradeTo
            // Seats

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
        // $body = html_entity_decode($parser->getHTMLBody());

        $pdfs = $parser->searchAttachmentByName('[A-Z0-9]{6}_tickets\.pdf|\d+\.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        $isset = false;

        foreach ($this->reBody as $re) {
            if (strpos($text, $re) !== false) {
                $isset = true;
            }
        }

        if (!$isset) {
            return false;
        }

        foreach ($this->reBody2 as $reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($text, $re) !== false) {
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

        if (!$this->tablePdf($parser)) {
            return null;
        }

        foreach ($this->reBody2 as $lang => $reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($this->pdf->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parsePdf($itineraries);

        $result = [
            'emailType'  => 'BookingInformationPdf_' . $this->lang,
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if (strpos($parser->getHtmlBody(), "Flying Blue") !== false) {//FlyingBlue
            $result['providerCode'] = 'airfrance';
        }

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

    public static function getEmailProviders()
    {
        return ['aeroflot', 'airfrance'];
    }

    public function translateMonth($month, $lang)
    {
        $t = [
            "ru" => [
                "январь"  => 0, "янв" => 0, "января" => 0,
                "февраля" => 1, "фев" => 1, "февраль" => 1,
                "марта"   => 2, "мар" => 2, "март" => 2,
                "апреля"  => 3, "апр" => 3, "апрель" => 3,
                "мая"     => 4, "май" => 4,
                "июн"     => 5, "июня" => 5, "июнь" => 5,
                "июля"    => 6, "июль" => 6, "июл" => 6,
                "августа" => 7, "авг" => 7, "август" => 7,
                "сен"     => 8, "сентябрь" => 8, "сентября" => 8,
                "окт"     => 9, "октября" => 9, "октябрь" => 9,
                "ноя"     => 10, "ноября" => 10, "ноябрь" => 10,
                "дек"     => 11, "декабрь" => 11, "декабря" => 11,
            ],
            "en" => [
                "january"   => 0,
                "february"  => 1,
                "march"     => 2,
                "april"     => 3,
                "may"       => 4,
                "june"      => 5,
                "july"      => 6,
                "august"    => 7,
                "september" => 8,
                "october"   => 9,
                "november"  => 10,
                "december"  => 11,
            ],
            "es" => [
                "enero"  => 0,
                "feb"    => 1, "febrero" => 1,
                "marzo"  => 2,
                "abr"    => 3, "abril" => 3,
                "mayo"   => 4,
                "jun"    => 5, "junio" => 5,
                "julio"  => 6, "jul" => 6,
                "agosto" => 7,
                "sept"   => 8, "septiembre" => 8,
                "oct"    => 9, "octubre" => 9,
                "nov"    => 10, "noviembre" => 10,
                "dic"    => 11, "diciembre" => 11,
            ],
            "it" => [
                "gen"       => 0, "gennaio" => 0,
                "feb"       => 1, "febbraio" => 1,
                "marzo"     => 2, "mar" => 2,
                "apr"       => 3, "aprile" => 3,
                "maggio"    => 4, "mag" => 4,
                "giu"       => 5, "giugno" => 5,
                "luglio"    => 6, "lug" => 6,
                "ago"       => 7, "agosto" => 7,
                "settembre" => 8, "set" => 8,
                "ott"       => 9, "ottobre" => 9,
                "novembre"  => 10, "nov" => 10,
                "dic"       => 11, "dicembre" => 11,
            ],
        ];
        $o = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($t[$lang]) && isset($t[$lang][$month])) {
            return $o[$t[$lang][$month]];
        }

        return false;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(" or ", array_map(function ($s) {
            return "normalize-space(.)='{$s}'";
        }, $field));

        return $this->pdf->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[string-length(.)>1][1]", $root);
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
            "#^(?<day>\d+)\s+(?<rmonth>[^\d\s]+)\s+(?<year>\d{4})$#",
        ];

        foreach ($in as $re) {
            if (preg_match($re, $str, $m)) {
                if (isset($m['rmonth'])) {
                    $m['month'] = $this->translateMonth($m['rmonth'], $this->lang);

                    if ($m['month'] === false) {
                        return false;
                    }
                }
                $str = $m['day'] . ' ' . $m['month'] . ' ' . $m['year'];

                break;
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

    private function tablePdf($parser)
    {
        $pdfs = $parser->searchAttachmentByName('[A-Z0-9]{6}_tickets\.pdf|\d+\.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($html);
        $html = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = $this->pdf->FindSingleNode(".", $node);
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $cols[round($left / 10)] = round($left / 10);
                $grid[$top][round($left / 10)] = $text;
            }

            ksort($cols);

            // group cols by -4px
            $translate = [];

            foreach ($cols as $left) {
                for ($i = $left - 4; $i < $left; $i++) {
                    if (isset($cols[$i])) {
                        $translate[$left] = $cols[$i];
                        unset($cols[$left]);

                        break;
                    }
                }
            }

            foreach ($grid as $row => &$c) {
                foreach ($translate as $from => $to) {
                    if (isset($c[$from])) {
                        $c[$to] = $c[$from];
                        unset($c[$from]);
                    }
                }
                ksort($c);
            }

            // group rows by -8px;
            foreach ($grid as $row => $c) {
                for ($i = $row - 8; $i < $row; $i++) {
                    if (isset($grid[$i])) {
                        foreach ($grid[$row] as $k => $v) {
                            $grid[$i][$k] = $v;
                        }
                        unset($grid[$row]);

                        break;
                    }
                }
            }

            ksort($grid);

            $htmlTable = "<table border='1'>";

            foreach ($grid as $row => $c) {
                $htmlTable .= "<tr>";

                foreach ($cols as $col) {
                    $htmlTable .= "<td>" . ($c[$col] ?? "&nbsp;") . "</td>";
                }
                $htmlTable .= "</tr>";
            }
            $htmlTable .= "</table>";
            //По билету произведён обмен
            if (mb_stripos($htmlTable, 'Произведён обмен') === false) {//exclude wrong reservations
                $html .= $htmlTable;
            }
        }
        $this->pdf->SetEmailBody($html);
        // echo $html;
        return true;
    }

    private function cell($node, $x = 0, $y = 0)
    {
        if ($y > 0) {
            return $this->pdf->FindSingleNode("./following-sibling::tr[" . abs($y) . "]/td[" . $x . "]", $node);
        } elseif ($y < 0) {
            return $this->pdf->FindSingleNode("./preceding-sibling::tr[" . abs($y) . "]/td[" . $x . "]", $node);
        } else {
            return $this->pdf->FindSingleNode("./td[" . abs($x) . "]", $node);
        }
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return '';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains({$text}, \"{$s}\")"; }, $field));
    }
}
