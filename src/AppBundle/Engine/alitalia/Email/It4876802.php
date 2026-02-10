<?php

namespace AwardWallet\Engine\alitalia\Email;

class It4876802 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "alitalia/it-1886528.eml, alitalia/it-2073180.eml, alitalia/it-4091696.eml, alitalia/it-4246628.eml, alitalia/it-4578679.eml, alitalia/it-4873255.eml, alitalia/it-7973807.eml";

    public $reFrom = "noreply@alitalia.com";
    public $reSubject = [
        "en" => "YOUR BOARDING PASS",
        "en2"=> "Web Check-in — Send summary",
    ];
    public $reBody = 'alitalia.com';
    public $reBody2 = [
        "en"=> "BOOKING CODE",
        "fr"=> "CODE DE",
        "es"=> "CÓDIGO DE LA RESERVA",
        "it"=> "CODICE PRENOTAZIONE",
        "ro"=> "ZBORUL",
    ];
    public $reBodyPDF = [
        "en"=> "BOARDING PASS",
        "fr"=> "CARTE D'EMBARQUEMENT",
        "es"=> "TARJETA DE EMBARQUE",
        "it"=> "CARTA D'IMBARCO",
        "ro"=> "CARTE DE ÎMBARCARE",
    ];
    public $date;
    /** @var \HttpBrowser */
    public $pdf;
    public static $dictionary = [
        "en" => [],
        "fr" => [
            "BOOKING CODE"=> "CODE DE RÉSERVATION",
            "Booking code"=> "Code de réservation",
            "Name"        => "Nom",
            "FROM"        => "DE",
            "From"        => "De",
            "TO"          => "À",
            "To"          => "À",
        ],
        "es" => [
            "BOOKING CODE"=> "CÓDIGO DE LA RESERVA",
            "Booking code"=> "Código de la reserva",
            "Name"        => "Nombre",
            "FROM"        => "DE",
            "From"        => "De",
            "TO"          => "A",
            "To"          => "A",
        ],
        "it" => [
            "BOOKING CODE"=> "CODICE PRENOTAZIONE",
            "Booking code"=> "codice prenotazione",
            "Name"        => "Nome",
            "FROM"        => "DA",
            "From"        => "da",
            "TO"          => "A",
            "To"          => "a",
        ],
        "ro" => [
            "BOOKING CODE"=> "CODUL REZERVĂRII",
            "Booking code"=> "Codul rezervării",
            "Name"        => "Prenume",
            "FROM"        => "DE LA",
            "From"        => "De la",
            "TO"          => "LA",
            "To"          => "La",
        ],
    ];

    public $lang = "";

    public function parsePdf(&$itineraries)
    {
        //		 echo $this->lang;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), '" . $this->t("BOOKING CODE") . "') or starts-with(normalize-space(.), '" . $this->t("Booking code") . "')])[1]/following::text()[normalize-space(.)][1]");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }//not determinate in pdf

        // Passengers
        $it['Passengers'] = array_values(array_unique($this->pdf->FindNodes("(.//text()[normalize-space(.)='{$this->t("Name")}'])/following::text()[string-length(.)>1][1]")));

        //$xpath = "//text()[normalize-space(.)='".$this->t("FLIGHT")."']/ancestor::tr[1]";
        $xpath = "//text()[normalize-space(.)='FLIGHT']/ancestor::tr[1]";
        $nodes = $this->pdf->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }
        $flights = [];
        $seats = [];

        foreach ($nodes as $root) {
            $n = $this->pdf->XPath->query("./td[string-length(.)>1]", $root);
            $cols = [];

            foreach ($n as $node) {
                $cols[$this->pdf->FindSingleNode(".", $node)] = $node;
            }

            $keys = [
                'FLIGHT',
                'DEPARTURE',
                'TO',
                'DATE',
            ];

            foreach ($keys as $key) {
                if (!isset($cols[$key])) {
                    return;
                }
            }

            // Seats
            if (isset($cols['SEAT'])) {
                $seats[$this->cell($cols['FLIGHT'], 0, +1)][] = $this->cell($cols['SEAT'], 0, +1);
            }

            if (isset($flights[$this->cell($cols['FLIGHT'], 0, +1)])) {
                continue;
            }

            $flights[$this->cell($cols['FLIGHT'], 0, +1)] = 1;

            if (!empty($this->cell($cols['FLIGHT'], 0, +1))) {
                $fln = $this->cell($cols['FLIGHT'], 0, +1);
            } else {
                $fln = $this->cell($cols['FLIGHT'], 0, +2);
            }

            $htmlRoot = $this->http->XPath->query($q = "//text()[contains(., '" . $this->re("#(\d+)#", $fln) . "')]/ancestor::tr[./descendant::text()[normalize-space(.)='" . $this->t("FROM") . "' or normalize-space(.)='" . $this->t("From") . "']][1]")->item(0);

            $date = strtotime($this->normalizeDate($this->cell($cols['DATE'], 0, +1)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^\w{2}\s*(\d+)$#", $fln);

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = trim($this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("FROM") . "' or normalize-space(.)='" . $this->t("From") . "']/following::text()[string-length(normalize-space(.))>1][1]", $htmlRoot), ', ');

            // DepDate
            if (!($time = $this->cell($cols['DEPARTURE'], 0, +1))) {
                if (!($time = $this->re("#(\d+[:\.]\d+)#", $this->cell($cols['TO'], 0, +1)))) {
                    $time = $this->re("#(\d+[:\.]\d+)#", $this->cell($cols['FROM'], 0, +1));
                }
            }

            $itsegment['DepDate'] = strtotime($time, $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = trim($this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("TO") . "' or normalize-space(.)='" . $this->t("To") . "']/following::text()[string-length(normalize-space(.))>1][1]", $htmlRoot), ', ');

            // ArrDate
            if (isset($cols['ARRIVAL'])) {
                $time = $this->cell($cols['ARRIVAL'], 0, +1);
                $itsegment['ArrDate'] = strtotime($time, $date);
            } else {
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^(\w{2})\s*\d+$#", $fln);

            // Cabin
            if (isset($cols['CLASS'])) {
                if (!($itsegment['Cabin'] = $this->re("#\w(.+)#", $this->cell($cols['CLASS'], 0, +1)))) {
                    $itsegment['Cabin'] = $this->cell($cols['CLASS'], 0, +2);
                }

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#(\w)#", $this->cell($cols['CLASS'], 0, +1));
            }

            $it['TripSegments'][] = $itsegment;
        }

        if (isset($it['TripSegments'])) {
            foreach ($it['TripSegments'] as &$segment) {
                if (isset($seats[$segment['AirlineName'] . $segment['FlightNumber']])) {
                    $segment['Seats'] = array_values(array_unique($seats[$segment['AirlineName'] . $segment['FlightNumber']]));
                }
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (stripos($text, 'alitalia') !== false) {
                    foreach ($this->reBodyPDF as $lang => $re) {
                        if (stripos($text, $re) !== false) {
                            return true;
                        }
                    }
                }
            }
        } else {
            return false;
        }

        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
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

        if (!$this->tablePdf($parser)) {
            return null;
        }
        $NBSP = chr(194) . chr(160);
        $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($this->pdf->Response["body"])));

        foreach ($this->reBody2 as $lang=> $re) {
            if (stripos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            foreach ($this->reBodyPDF as $lang=>$re) {
                if (stripos($this->pdf->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parsePdf($itineraries);

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
            "#^(\d+)([^\d\s]+)$#",
            "#^(\d+)/(\d+)/(\d{4})$#",
        ];
        $out = [
            "$1 $2 $year",
            "$1.$2.$3",
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

    private function tablePdf($parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetBody($html);
        $html = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = trim($node->nodeValue);
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $cols[$left] = $left;
                $grid[$top][$left] = $text;
            }

            ksort($cols);

            // group rows by -8px;
            foreach ($grid as $row=>$c) {
                for ($i = $row - 8; $i < $row; $i++) {
                    if (isset($grid[$i])) {
                        foreach ($grid[$row] as $k=>$v) {
                            $grid[$i][$k] = $v;
                        }
                        unset($grid[$row]);

                        break;
                    }
                }
            }

            // group cols by -+6px
            $translate = [];

            foreach ($cols as $left) {
                for ($i = $left - 35; $i <= $left + 35; $i++) {
                    if ($left == $i) {
                        continue;
                    }

                    if (isset($cols[$i])) {
                        $translate[$left] = $cols[$i];
                        unset($cols[$left]);

                        break;
                    }
                }
            }

            foreach ($grid as $row=>&$c) {
                foreach ($translate as $from=>$to) {
                    if (isset($c[$from])) {
                        $c[$to] = $c[$from];
                        unset($c[$from]);
                    }
                }
                ksort($c);
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row=>$c) {
                $html .= "<tr>";

                foreach ($cols as $col) {
                    $html .= "<td>" . ($c[$col] ?? "&nbsp;") . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        // echo $html;
        $this->pdf->setBody($html);

        return true;
    }

    private function cell($node, $x = 0, $y = 0)
    {
        $n = count($this->pdf->FindNodes("./preceding-sibling::td", $node)) + 1;

        if ($y > 0) {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[" . abs($y) . "]/td[" . ($n + $x) . "]", $node);
        } elseif ($y < 0) {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[" . abs($y) . "]/td[" . ($n + $x) . "]", $node);
        } else {
            return $this->pdf->FindSingleNode("./ancestor::tr[1]/td[" . ($n + $x) . "]", $node);
        }
    }
}
