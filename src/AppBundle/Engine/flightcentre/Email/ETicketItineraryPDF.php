<?php

namespace AwardWallet\Engine\flightcentre\Email;

class ETicketItineraryPDF extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-10782090.eml, flightcentre/it-27816508.eml, flightcentre/it-6834950.eml, flightcentre/it-6988462.eml, flightcentre/it-6988475.eml, flightcentre/it-7025610.eml";

    public $reFrom = "flightcentre.com.au";
    public $reBody = [
        'en' => [
            'format1' => ['Electronic Ticket Receipt', 'Agency Information'],
            'format2' => ['Itinerary Information', 'Agency Information'],
        ],
    ];
    public $reSubject = [
        'E-Tickets',
        'Travel Docs',
    ];
    public $lang = '';
    public $pax;
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $type = [];
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $body = $html;

                    if (!$this->assignLang($body)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }
                    $this->pax = $this->re("#Passenger Name\s+(?:.+?\n){1}(.+?) {3,}#", $body);

                    if (strpos($body, 'Electronic Ticket Receipt') !== false) {
                        $type[] = '1';
                        $et = $this->splitter("#(^ *e-Ticket Receipt[\s\-]+)#m", $body);

                        foreach ($et as $e) {
                            $flights = $this->parseEmailETicket($e);

                            foreach ($flights as $flight) {
                                $its[] = $flight;
                            }
                        }
                    } else {
                        if (!$this->assignLang($body)) {
                            $this->logger->debug('can\'t determine a language');

                            continue;
                        }
                        $type[] = '2';
                        $et = $this->splitter("#((?:^Passenger Name|Itinerary Information).+?Agency Information)#sm", $body);

                        foreach ($et as $e) {
                            if (strpos($e, 'Itinerary Information') !== false) {
                                $flights = $this->parseEmailItinerary($e);

                                foreach ($flights as $flight) {
                                    $its[] = $flight;
                                }
                            }
                        }
                    }
                } else {
                    return null;
                }
            }
        }

        $its = $this->mergeItineraries($its);
        $type = array_unique($type);
        sort($type);
        $type = implode('', $type);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ETicketItineraryPDF' . $type . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            for ($i = 0; $i < count($pdf); $i++) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf[$i]));

                if ($this->assignLang($text)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2;
        $cnt = count(self::$dict) * $types;

        return $cnt;
    }

    private function parseEmailETicket($textPDF)
    {
        $textFlight = strstr($textPDF, 'Flight Information');
        $textInfo = strstr($textPDF, 'Flight Information', true);
        $pax = $this->re("#Passenger.+?\n([A-Z,\s]{3,})#", $textInfo);

        if (empty($pax)) {
            $pax = $this->pax;
        }

        $table = $this->re("#( *e-Ticket Number.+)#s", $textInfo);
        $table = $this->splitCols($table, $this->colsPos($table, 11));

        if (count($table) > 3) {
            $table[2] = $this->mergeCols($table[2], $table[3]);
            unset($table[3]);
        }

        if (count($table) !== 3) {
            $this->logger->debug('other format');

            return [];
        }
        $resDate = strtotime(preg_replace("/\s+/", ' ', $this->re("#Ticket Issue Date[\s:]+(.+)#s", $table[2])));
        $tripNum = $this->re("#Number[\s:]+([A-Z\d]+)#s", $table[1]);
        $ticket = str_replace(" ", "", $this->re("#e-Ticket Number[:\s]+([\d \-]{5,})#", $table[0]));

        $nodes = $this->splitter("#^ *(\d+ \w+ \d+(?:\n[^\n]+){1,4}\n *Depart:)#m", $textFlight);
        $airs = [];
        $its = [];

        foreach ($nodes as $root) {
            $rl = $this->re("#Confirmation\s+Number[\s:]+([A-Z\d]+)#", $root);

            if (empty($rl)) {
                $airs[CONFNO_UNKNOWN][] = $root;
            } else {
                $airs[$rl][] = $root;
            }
        }

        foreach ($airs as $rl=>$nodes) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['ReservationDate'] = $resDate;
            $it['Passengers'][] = $pax;
            $it['TripNumber'] = $tripNum;
            $it['TicketNumbers'][] = $ticket;

            foreach ($nodes as $root) {
                $seg = [];
                $date = strtotime($this->re("#^(\d+ \w+ \d{4})#", $root));

                if (preg_match("#\(([A-Z\d]{2})\)\s+(\d+)\s+([^\n]+?)\s+\(([A-Z]{1,2})\)#", $root, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                    $seg['Cabin'] = $this->re("#(.+?)(?: {3,}|$)#", $m[3]);
                    $seg['BookingClass'] = $m[4];

                    $accNum = $this->re("#Frequent\s+Traveller\s+Number\s+Passenger\s*([A-Z\d]+).+?\({$m[1]}\)#", $textInfo);

                    if (!empty($accNum)) {
                        $it['AccountNumbers'][] = $accNum;
                    }
                }
                $seg['Operator'] = $this->re("#Operated by:\s+(.+)#i", $root);

                if (preg_match("#Depart:\s+(.+?)\s+\(([A-Z]{3})\)\s*,?\s+(?:(Terminal.*)\s+)?(\d+:\d+(?:\s*[APap][Mm])?)#",
                    $root, $m)) {
                    //service will set value
                    //					$seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['DepartureTerminal'] = $this->re("#Terminal[\s:]+(\w+)#", $m[3]);
                    }
                    $seg['DepDate'] = strtotime($m[4], $date);
                } elseif (preg_match("#Depart:\s+(.+?)\s+(\d+:\d+(?:\s*[APap][Mm])?).+?\(([A-Z]{3})\)\s*,?\s*(?:(Terminal.*)\s+)?#s",
                    $root, $m)) {
                    //service will set value
                    //					$seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[3];

                    if (isset($m[4]) && !empty($m[4])) {
                        $seg['DepartureTerminal'] = $this->re("#Terminal[\s:]+(\w+)#", $m[4]);
                    }
                    $seg['DepDate'] = strtotime($m[2], $date);
                }

                if (preg_match("#Arrive:\s+(.+?)\s+\(([A-Z]{3})\)\s*,?\s*(?:(Terminal.*)\s+)?(\d+:\d+(?:\s*[APap][Mm])?)#",
                    $root, $m)) {
                    //service will set value
                    //					$seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['ArrivalTerminal'] = $this->re("#Terminal[\s:]+(\w+)#", $m[3]);
                    }
                    $seg['ArrDate'] = strtotime($m[4], $date);
                } elseif (preg_match("#Arrive:\s+(.+?)\s+(\d+:\d+(?:\s*[APap][Mm])?).+?\(([A-Z]{3})\)\s*,?\s*(?:(Terminal.*)\s+)?#s",
                    $root, $m)) {
                    //service will set value
                    //					$seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[3];

                    if (isset($m[4]) && !empty($m[4])) {
                        $seg['ArrivalTerminal'] = $this->re("#Terminal[\s:]+(\w+)#", $m[4]);
                    }
                    $seg['ArrDate'] = strtotime($m[2], $date);
                }
                $seg = array_filter($seg);
                ksort($seg);
                $it['TripSegments'][] = $seg;
            }

            if (isset($it['AccountNumbers'])) {
                $it['AccountNumbers'] = array_values(array_unique($it['AccountNumbers']));
            }

            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailItinerary($textPDF)
    {
        $textFlight = "sometextForsplitter\n" . mb_strstr($textPDF, 'Flight');
        $textInfo = $this->findСutSection($textPDF, null, 'Flight');

        $pax = $this->re("#Traveller\s+(.+)#", $textInfo);

        if (empty($pax)) {
            $pax = $this->pax;
        }

        $tripNum = $this->re("#Reservation ID[\s:]+([A-Z\d]+)#", $textInfo);

        $nodes = $this->splitter("#^\s*(Flight\s+\-.+?\([A-Z\d]{2}\))#sm", $textFlight);
        $airs = [];
        $its = [];

        foreach ($nodes as $root) {
            $rl = $this->re("#Confirmation\s+Number[\s:]+([A-Z\d]+)#", $root);

            if (empty($rl)) {
                $airs[CONFNO_UNKNOWN][] = $root;
            } else {
                $airs[$rl][] = $root;
            }
        }

        foreach ($airs as $rl=>$nodes) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'][] = $pax;
            $it['TripNumber'] = $tripNum;

            foreach ($nodes as $root) {
                $seg = [];
                $date = null;

                if (preg_match("#\(([A-Z\d]{2})\)[\s\-]*(\d+)\s+\w+\s+(.+)#", $root, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                    $date = strtotime($m[3]);
                }
                $seg['Operator'] = $this->re("#Operated by:\s+(.+)#i", $root);

                if (preg_match("#Depart:\s+(\d+:\d+(?:\s*[APap][Mm])?)\s+(.+?)\s+\(([A-Z]{3})\)[\s,]+(?:(Terminal\s+\w+))?(?:\s*\w+\s+(\d+.+?\d{4}))?.*?Arrive#s", $root, $m)) {
                    //service will set value
                    //					$seg['DepName'] = $m[2];
                    $seg['DepCode'] = $m[3];

                    if (isset($m[4]) && !empty($m[4])) {
                        $seg['DepartureTerminal'] = $this->re("#Terminal[\s:]+(\w+)#", $m[4]);
                    }

                    if (isset($m[5]) && !empty($m[5])) {
                        $seg['DepDate'] = strtotime($m[5] . ' ' . $m[1]);
                    } else {
                        $seg['DepDate'] = strtotime($m[1], $date);
                    }
                }

                if (preg_match("#Arrive:?\s+(\d+:\d+(?:\s*[APap][Mm])?)\s+(.+?)\s+\(([A-Z]{3})\)[\s,]+(?:(Terminal[^\n]+))?\s*(?:\w+\s+(\d+\s+\w+\s+\d{4}))?\s*.*?Flight#s", $root, $m)) {
                    //service will set value
                    //					$seg['ArrName'] = $m[2];
                    $seg['ArrCode'] = $m[3];

                    if (isset($m[4]) && !empty($m[4])) {
                        $seg['ArrivalTerminal'] = $this->re("#Terminal[\s:]+(\w+)#", $m[4]);
                    }
                    //service will correct by AirCodes
                    //					if (isset($m[5]) && !empty($m[5]))
                    //						$seg['ArrDate'] = strtotime($m[5].' '.$m[1]);
                    //					else
                    $seg['ArrDate'] = strtotime($m[1], $date);
                }

                if (preg_match("#Class of Service[\s:]+(.+)\s+\(([A-Z]{1,2})\)#", $root, $m)) {
                    $seg['Cabin'] = $this->re("#(.+?)(?: {3,}|$)#", $m[1]);
                    $seg['BookingClass'] = $m[2];
                }
                $seg['Aircraft'] = $this->re("#Equipment[\s:]+(.+?) {3,}#", $root);
                $seg['Duration'] = $this->re("#Flying Time[\s:]+(.+)#", $root);
                $seg['Meal'] = $this->re("#Meal Service[\s:]+(.+)#", $root);

                if ($info = $this->re("#Seat(.+?)Flight Service Information#", $root)) {
                    if (preg_match_all("#\b(\d+[A-Zaz])\b#", $info, $v)) {
                        $seg['Seats'] = implode(",", $v[1]);
                    }

                    if (preg_match_all("#^([A-Z\s ,]{3,})#m", $info, $v)) {
                        $it['Passengers'] = $v[1];
                    }
                }

                if ($tic = $this->re("#Ticket Numbers.+?(\d{5,}[A-Z\d]+)#", $root)) {
                    $it['TicketNumbers'][] = $tic;
                }
                $seg = array_filter($seg);
                ksort($seg);
                $it['TripSegments'][] = $seg;
            }

            if (isset($it['AccountNumbers'])) {
                $it['AccountNumbers'] = array_values(array_unique($it['AccountNumbers']));
            }

            if (isset($it['TicketNumbers'])) {
                $it['TicketNumbers'] = array_values(array_unique($it['TicketNumbers']));
            }

            $its[] = $it;
        }

        return $its;
    }

    private function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if (isset($it['RecordLocator']) && $i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                if (isset($its[$j]['TripSegments'])) {
                    foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                        foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                            if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])
                                && (isset($tsJ['Seats']) || isset($tsI['Seats']))
                            ) {
                                $new = "";

                                if (isset($tsJ['Seats'])) {
                                    $new .= "," . $tsJ['Seats'];
                                }

                                if (isset($tsI['Seats'])) {
                                    $new .= "," . $tsI['Seats'];
                                }
                                $new = implode(",", array_filter(array_unique(array_map("trim", explode(",", $new)))));
                                $its[$j]['TripSegments'][$flJ]['Seats'] = $new;
                                $its[$i]['TripSegments'][$flI]['Seats'] = $new;
                            }
                        }
                    }

                    $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                    $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                }

                if (isset($its[$j]['Passengers']) || isset($its[$i]['Passengers'])) {
                    $new = "";

                    if (isset($its[$j]['Passengers'])) {
                        $new .= ";" . implode(";", $its[$j]['Passengers']);
                    }

                    if (isset($its[$i]['Passengers'])) {
                        $new .= ";" . implode(";", $its[$i]['Passengers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(";", $new)))));
                    $its[$j]['Passengers'] = $new;
                }

                if (isset($its[$j]['AccountNumbers']) || isset($its[$i]['AccountNumbers'])) {
                    $new = "";

                    if (isset($its[$j]['AccountNumbers'])) {
                        $new .= ";" . implode(",", $its[$j]['AccountNumbers']);
                    }

                    if (isset($its[$i]['AccountNumbers'])) {
                        $new .= ";" . implode(";", $its[$i]['AccountNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(";", $new)))));
                    $its[$j]['AccountNumbers'] = $new;
                }

                if (isset($its[$j]['TicketNumbers']) || isset($its[$i]['TicketNumbers'])) {
                    $new = "";

                    if (isset($its[$j]['TicketNumbers'])) {
                        $new .= "," . implode(",", $its[$j]['TicketNumbers']);
                    }

                    if (isset($its[$i]['TicketNumbers'])) {
                        $new .= "," . implode(",", $its[$i]['TicketNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['TicketNumbers'] = $new;
                }

                unset($its[$i]);
            }
        }

        return array_values($its);
    }

    private function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if (isset($it['RecordLocator']) && $g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $re) {
                foreach ($re as $reBody) {
                    if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (!$searchFinish) {
            return $left;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function mergeCols($col1, $col2)
    {
        $rows1 = explode("\n", $col1);
        $rows2 = explode("\n", $col2);
        $newRows = [];

        foreach ($rows1 as $i => $row) {
            if (isset($rows2[$i])) {
                $newRows[] = $row . $rows2[$i];
            } else {
                $newRows[] = $row;
            }
        }

        if (($i = count($rows1)) > count($rows2)) {
            for ($j = $i; $j < count($rows2); $j++) {
                $newRows[] = $rows2[$j];
            }
        }

        return implode("\n", $newRows);
    }
}
