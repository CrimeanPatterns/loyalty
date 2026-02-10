<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingDocumentsPdf extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-12234281.eml, airfrance/it-12234284.eml, airfrance/it-2320154.eml, airfrance/it-2971335.eml, airfrance/it-4009405.eml, airfrance/it-4027667.eml, airfrance/it-4036915.eml, airfrance/it-4036944.eml, airfrance/it-4127340.eml, airfrance/it-4149789.eml, airfrance/it-4155443.eml, airfrance/it-4185455.eml, airfrance/it-4185465.eml, airfrance/it-4241191.eml, airfrance/it-4249564.eml, airfrance/it-5132704.eml, airfrance/it-6145384.eml, airfrance/it-6145387.eml, airfrance/it-6145391.eml, airfrance/it-7981229.eml, airfrance/it-7984290.eml, airfrance/it-8011581.eml, airfrance/it-8305815.eml, airfrance/it-8332867.eml, airfrance/it-8399282.eml, airfrance/it-8621842.eml, airfrance/it-8666780.eml, airfrance/it-8736666.eml";

    public $reFrom = "@airfrance.fr";
    public $reSubject = [
        "en" => "Your Air France boarding documents on",
        "pt" => "Os seus documentos de embarque Air France",
        "ru" => "Ваш посадочный документ (-ы)",
        "es" => "Sus documentos de embarque Air France",
        "it" => "I suoi documenti d'imbarco Air France",
        "fr" => "Vos documents de confirmation d'enregistrement Air France pour le vol",
    ];
    public $reBody = 'Air France';
    public $reBody2 = [
        "en" => "DEPARTURE",
        "fr" => ["DÉPART", 'Info bagage cabine indisponible', 'Heure limite pour déposer vos bagages'],
    ];
    public $pdfPattern = "(Boarding-documents|Documentos-de-embarque|Boarding-Unterlagen|Documenti-dimbarco|Documents-d'embarquement|Documents-dembarquement|Boarding_pass|Documents-d\’embarquement|Documentos-de-embarque|Documenti-d.*imbarco|Vos documents de confirmation d'enregistrement|Check-in confirmation documents)(-\d+[^\d\s]+)?.pdf";

    public static $dictionary = [
        "en" => [
            "NAME"         => ["NAME", "Name"],
            "TICKET NUMBER"=> ["TICKET NUMBER", "Ticket number"],
        ],
        "fr" => [
            "NAME"         => ["NOM", "Nom"],
            "TICKET NUMBER"=> ["NUMÉRO DE BILLET", "Numéro de billet"],
            "FREQUENT FLYER"=>"N° DE CARTE DE FIDÉLITÉ",
            "OPERATED BY"=> "EFFECTUÉ PAR",
            "PROVIDED BY"=> "EFFECTUÉ PAR",
            "DEPARTURE"  => "DÉPART",
            "DATE"       => "DATE",
            'CLASS'      => ['CLASSE', 'CABINE'],
        ],
        "es" => [],
        "pt" => [],
    ];

    public $lang = "en";
    private $text = '';
    private $pdf;

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        // RecordLocator
        $rl = [
            'Reservation number:',
            'Buchungscode:',
            'Codice di prenotazione:',
            'Booking code',
            'Référence de réservation :',
            '№ брони',
            'Nº de reserva:',
            'N° d prenotazione:',
            'Nr rezerwacji:',
            'Booking reference:',
            'Referência de reserva:',
            'RÉFÉRENCE DE RÉSERVATION',
            'BOOKING REFERENCE',
            'Код бронирования',
            'Referencia de la reserva:',
            '予約番号：',
        ];

        $rls = $this->http->FindNodes("(//text()[" . $this->eq($rl) . "])/following::text()[normalize-space(.)][1]");

        $airs = [];
        $start = null;

        foreach ((array) $this->t("TICKET NUMBER") as $str) {
            if (($p = mb_stripos($text, $str, 0, "UTF-8")) !== false) {
                $start = $p;
            }
        }
        $end = null;

        foreach (["BAGGAGE DROP-OFF", "OBTER O SEU CARTÃO DE", "ENTREGA DE BAGAGENS", "GEPÄCKAUFGABE", "Deposito bagagli", "DROP-OFF", "OBTAIN YOUR BOARDING PASS"] as $str) {
            if (($p = mb_strripos($text, $str, 0, "UTF-8")) !== false) {
                $end = $p;
            }
        }

        if (!empty($end)) {
            $flights = mb_substr($text, $start, $end - $start, "UTF-8");
        } else {
            $flights = mb_substr($text, $start, null, "UTF-8");
        }

        preg_match_all("#([^\n]+" . $this->t("OPERATED BY") . "[^\n]*\n.*?" . $this->t("DEPARTURE") . ".*?\s+.+?(?:" . $this->opt($this->t('CLASS')) . ")\s+(?:\w+\s*)?\d+ \w+ \d+\s+.+?\n)#ms", $flights, $segments);

        if (empty($segments[1]) && substr_count($flights, $this->t("DEPARTURE")) === 1) {
            $segments[1] = [$flights];
        } elseif (empty($segments[1])) {
            preg_match_all("#(\n[A-Z]{3}\s{2,}[A-Z]{3}\s{2,}\w{2,}\s+\d+.*?)\n\n#ms", $flights, $segments);
        } elseif (count($segments[1]) < substr_count($flights, $this->t("DEPARTURE"))) {
            preg_match_all("#(" . $this->t("OPERATED BY") . "[^\n]+\n.*?" . $this->t("DEPARTURE") . ".*?\s+)\n(?=([^\n]+ " . $this->t("OPERATED BY") . ")|\n)#ms", $flights, $segments);
        }

        if (empty($rls) && !empty($this->http->Response['body'])) {
            if (preg_match("#" . $this->opt($rl) . "\s*([A-Z\d]{5,6})\b#", $this->http->Response['body'], $m)) {
                $rls[] = $m[1];
            }
        } elseif (!empty($this->pdf)) {
            $rls = $this->pdf->FindNodes("(//text()[" . $this->eq($rl) . "])/following::text()[normalize-space(.)][1]");
        }

        $countRls = count(array_unique($rls));

        if (($countRls === 1 || count(array_unique($rls)) === 1) && !empty($rls[0])) {
            $airs[$rls[0]] = $segments[1];
        } elseif ($countRls > 1) {
            foreach ($rls as $i => $r) {
                if (isset($segments[1][$i])) {
                    $airs[$r][] = $segments[1][$i];
                }
            }
        }

        $re = "#" . $this->opt($this->t("NAME")) . "\s+(.+)\s*(?:" . $this->opt($rl) . "\s+([A-Z\d]{5,9})|(?:PASSPORT NUMBER|Passport number|NUMÉRO DE PASSEPORT)\s+[\dA-Z]+)?\s*(?:SEC\..*\s+)?(?:(?:FREQUENT FLYER(?:\s*CARD NO)?|" . $this->opt($this->t("FREQUENT FLYER")) . ")\s+([A-Z\d]+))?(?:\s*.*\s*|.*|\s*)" . $this->opt($this->t("TICKET NUMBER")) . "\s+([\d\s]+)#i";
        preg_match_all($re, $text, $m);

        //		$this->logger->info($re);

        //		$this->logger->info($text);

        $passengers = [];
        $ticketNumbers = [];
        $accountNumbers = [];

        foreach ($m[1] as $i => $psng) {
            if ($countRls === 1) {
                $passengers[$rls[0]][] = $psng;

                if (!empty($m[3][$i])) {
                    $accountNumbers[$rls[0]][] = $m[3][$i];
                }

                if (!empty($m[4][$i])) {
                    $ticketNumbers[$rls[0]][] = trim(preg_replace('/\s+/', ' ', $m[4][$i]));
                }

                continue;
            }

            if (!empty($m[2][$i])) {
                $passengers[$m[2][$i]][] = $psng;
            }

            if (!empty($m[3]) && isset($m[2][$i]) && !empty($m[3][$i])) {
                $accountNumbers[$m[2][$i]][] = $m[3][$i];
            }

            if (!empty($m[4]) && isset($m[2][$i]) && !empty($m[4][$i])) {
                $ticketNumbers[$m[2][$i]][] = trim(preg_replace('/\s+/', ' ', $m[4][$i]));
            }

            if (empty($m[2][$i]) && !empty($rls[$i])) {
                $passengers[$rls[$i]][] = $psng;
            }

            if (empty($m[2][$i]) && !empty($rls[$i]) && !empty($m[3][$i])) {
                $accountNumbers[$rls[$i]][] = $m[3][$i];
            }

            if (empty($m[2][$i]) && !empty($rls[$i]) && !empty($m[4][$i])) {
                $ticketNumbers[$rls[$i]][] = trim(preg_replace('/\s+/', ' ', $m[4][$i]));
            }
        }

        if (empty(array_filter($m)) && !preg_match("#(?:" . $this->opt($rl) . ")#", $text)) {
            if (preg_match_all("#(?:" . $this->opt($this->t("TICKET NUMBER")) . ")\s+([\d ]{9,20})#", $text, $m)) {
                $ticketNumbers[$rls[0]][] = array_map('trim', $m[1]);
            }
        }

        foreach ($airs as $rl => $segs) {
            $it = [];

            $it['Kind'] = "T";
            $it['RecordLocator'] = $rl;

            if (isset($passengers[$rl])) {
                $it['Passengers'] = array_unique($passengers[$rl]);
            }

            if (isset($ticketNumbers[$rl])) {
                $it['TicketNumbers'] = array_unique($ticketNumbers[$rl]);
            }

            if (isset($accountNumbers[$rl])) {
                $it['AccountNumbers'] = array_unique($accountNumbers[$rl]);
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

            $uniq = [];

            foreach ($segs as $stext) {
                $itsegment = [];

                // DepCode
                // ArrCode
                if (!preg_match("#\n\s*[A-Z]{3}\s+[A-Z]{3}(?:\n|\s{2,})#", $stext)) {
                    // if dep and arr code is on different line
                    if (preg_match("#\n\s*([A-Z]{3})\s*(\n\s*DATE.*)(\n\s*([A-Z]{3})\s*\n)(\s+)\S#ms", $stext, $m)) {
                        $itsegment['DepCode'] = $m[1];
                        $itsegment['ArrCode'] = $m[4];
                        $pos = strlen($m[5]);
                        $st = str_pad($m[2], $pos + 1);
                        $stext = preg_replace("#\n\s*DATE.*\n\s*[A-Z]{3}\s*\n\s*#ms", $st, $stext);
                    }
                } else {
                    $itsegment['DepCode'] = $this->re("#\n\s*([A-Z]{3})\s+[A-Z]{3}(?:\n|\s{2,})#", $stext);
                    $itsegment['ArrCode'] = $this->re("#\n\s*[A-Z]{3}\s+([A-Z]{3})(?:\n|\s{2,})#", $stext);
                }
                $table = $this->re("#\n([^\n]*" . $this->t("DATE") . ".+)#ms", $stext);
                $rows = array_merge([], array_filter(explode("\n", $table), function ($s) {
                    return strlen(trim($s)) > 5;
                }));

                if (count($rows) < 2) {
                    $this->logger->info("incorrect rows count");

                    return;
                }
                $row = $rows[1];

                if (isset($rows[2]) && strlen(trim($rows[1])) < 15) {
                    $row .= "\n" . $rows[2];
                }
                $table = $this->splitCols($row, $this->TableHeadPos($rows[0]));

                if (count($table) < 6) {
                    $this->logger->info("incorrect table parse");

                    return;
                }
                $date = strtotime($this->normalizeDate($table[0]));

                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\s+\w{2}\s+(\d+)A?\n#", $stext);

                if (isset($uniq[$itsegment['FlightNumber']])) {
                    $n = $uniq[$itsegment['FlightNumber']];

                    if (!$it['TripSegments'][$n]['Seats'][] = $this->re("#\s+(\d+\w)$#", $table[4])) {
                        $it['TripSegments'][$n]['Seats'][] = $this->re("#^(\d+\w|\d+\/\d+)$#", $table[5]);
                    }
                    $it['TripSegments'][$n]['Seats'] = array_filter($it['TripSegments'][$n]['Seats']);

                    continue;
                }
                $uniq[$itsegment['FlightNumber']] = isset($it['TripSegments']) ? count($it['TripSegments']) : 0;

                // DepName
                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#(.*?)\s*/#", $table[4]);
                $anchor = false;

                if (empty($itsegment['DepartureTerminal'])) {
                    $itsegment['DepartureTerminal'] = $this->re("#(.*?)\s*/#", $table[3]);
                    $anchor = true;
                }

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($table[2]), $date);

                if (empty($itsegment['DepDate'])) {
                    if (preg_match("#\d+:\d+\s*\(.*\)\s*(\d+:\d+)#", $table[1], $m)) {
                        $itsegment['DepDate'] = strtotime($m[1], $date);
                    }
                }

                // ArrName
                // ArrivalTerminal
                // ArrDate
                if ($anchor === false) {
                    $itsegment['ArrDate'] = strtotime($this->normalizeDate($table[3]), $date);
                }

                if (empty($itsegment['ArrDate']) && empty($itsegment['DepDate'])) {
                    if (preg_match("#(\d+:\d+)\s+(\d+:\d+)#", $table[2], $m)) {
                        $itsegment['DepDate'] = strtotime($m[1], $date);
                        $itsegment['ArrDate'] = strtotime($m[2], $date);
                    }
                }
                //this thing could be when just gate, without terminal
                if (empty($itsegment['ArrDate']) && empty($itsegment['DepartureTerminal'])) {
                    $itsegment['ArrDate'] = strtotime($this->normalizeDate($table[3]), $date);
                }
                // AirlineName
                $itsegment['AirlineName'] = $this->re("#\s+(\w{2})\s+\d+A?\n#", $stext);

                // Operator
                $itsegment['Operator'] = trim($this->re("#(?:" . $this->t("OPERATED BY") . "|" . $this->t("PROVIDED BY") . ")([^\n]*)\n#", $stext));

                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = end($table);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                if (!$itsegment['Seats'][] = $this->re("#\s+(\d{1,3}[A-Z])$#", $table[4])) {
                    $itsegment['Seats'][] = $this->re("#^\s*(\d{1,3}[A-Z])$#", $table[5]);
                }
                $itsegment['Seats'] = array_filter($itsegment['Seats']);
                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }
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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }
        $body = $parser->getHTMLBody();

        if ((strpos($body, $this->reBody) === false && strpos($parser->getHeader('from'), $this->reFrom) === false)
            && (stripos($text, $this->reBody) === false)
        ) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_string($re) && stripos($text, $re) !== false) {
                return true;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($text, $r) !== false) {
                        return true;
                    }
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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (is_string($re) && strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($this->text, $r) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
        }

        if (empty($this->http->Response['body'])) {
            $this->tablePdf($parser);
        }

        $this->parsePdf($itineraries);
        $result = [
            'emailType'  => 'BoardingDocumentsPdf' . ucfirst($this->lang),
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
        // $this->http->log($word);
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
            "#^(\d+)\s+([^\d\s]+)\s+(\d{2})$#", // 06/19/16
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

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));

        foreach ($head as $pos => $hd) {
            if (stripos($hd, 'EMBARQUEMENT DÉPART') !== false) {
                $nodes = explode(' ', $hd);

                foreach ($nodes as $i => $node) {
                    $head[$pos + $i] = $node;
                }
            }
        }
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,\s](\d{3})#", "$1", $s));
    }

    private function eq($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function tablePdf(\PlancakeEmailParser $parser, $num = 0)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[$num])) {
            return false;
        }
        $pdf = $pdfs[$num];

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
                $text = $this->pdf->FindSingleNode(".", $node);
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row=>$c) {
                ksort($c);
                $html .= "<tr>";

                foreach ($c as $col) {
                    $html .= "<td>" . $col . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        $this->pdf->setBody($html);

        return true;
    }
}
