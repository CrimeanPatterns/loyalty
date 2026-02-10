<?php

namespace AwardWallet\Engine\airarabia\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "airarabia/it-10129777.eml, airarabia/it-10137187.eml, airarabia/it-11332387.eml, airarabia/it-8195596.eml, airarabia/it-8255511.eml";

    public $reFrom = "webcheckin@airarabia.com";
    public $reProvider = "@airarabia.com";
    public $reSubject = [
        "en" => "Air Arabia boarding pass confirmation",
    ];
    public $reBody = 'Air Arabia';
    public $reBody2 = [
        "en"  => "THIS IS YOUR BOARDING PASS",
        "en2" => "This is not a boarding pass",
        'fr'  => 'Merci de voyager avec Air Arabia',
    ];
    public $pdfPattern = ".*"; // file without .pdf

    public static $dictionary = [
        "en" => [
            "BP"    => "THIS IS YOUR BOARDING PASS",
            "NotBP" => "This is not a boarding pass",
        ],
        'fr' => [
            'BP'                                            => 'CECI EST VOTRE CARTE D\'EMBARQUEMENT',
            'NotBP'                                         => 'CECI ne pass EST VOTRE CARTE D\'EMBARQUEMENT',
            'Please take this boarding pass to the airport' => "Veuillez utiliser cette carte d'embarquement à l'aeroport pour",
            'Name'                                          => 'Nom',
            'From'                                          => 'De',
            'Gate'                                          => 'Porte',
            'Flight\s*Number'                               => 'Numero de vol',
            'Date'                                          => 'Date',
            'To'                                            => 'Vers',
            'Departure\s+Time'                              => 'Horaire',
            'Class'                                         => 'Classe',
            'Seat'                                          => 'Siège',
        ],
    ];

    public $lang = "en";
    public $text;

    public function parsePdf1(&$its)
    { // Boarding Pass
        $stext = $this->text;

        $segmens = $this->split("#(?:^|\n)\s*({$this->t('BP')}\s*\n)#", $stext);

        foreach ($segmens as $text) {
            $pos = stripos($text, $this->t('Please take this boarding pass to the airport'));

            if (empty($pos)) {
                $pos = stripos($text, 'Traveling with baggage');
            }
            $flight = substr($text, 0, $pos);

            // Passengers
            if (preg_match("#\n\s*{$this->t('Name')}\s+.*\s*([A-Z \-]+\/[A-Z \-]+)\s+#", $flight, $m)) {
                $Passengers = trim($m[1]);
            }

            $seg = [];
            $posFrom = stripos($flight, $this->t('From'));
            $posGate = stripos($flight, $this->t('Gate'));

            $table = $this->SplitCols(preg_replace("#[\D\d\n]+?([ ]+{$this->t('From')}.+)#", "$1",
                    substr($flight, $posFrom - 20, $posGate - $posFrom + 20) // 20 - don't lose space from beggining of string to "From"
            ));

            if (count($table) < 5) {
                $this->logger->debug('incorrect table parse! (1-1)');

                return;
            }

            // FlightNumber
            $seg['FlightNumber'] = $this->re("#{$this->t('Flight\s*Number')}\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})#", $table[3]);
            // AirlineName
            $seg['AirlineName'] = $this->re("#{$this->t('Flight\s*Number')}\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})#", $table[3]);

            // DepCode
            $seg['DepCode'] = $this->re("#{$this->t('From')}\s+([A-Z]{3})\b#", $table[0]);

            // DepName
            // DepartureTerminal
            // DepDate
            $seg['DepDate'] = strtotime($this->normalizeDate($this->re("#{$this->t('Date')}\s+(\d+-\d+-\d+)\b#", $table[4]) . ' ' . $this->re("#{$this->t('Departure\s+Time')}\s+(\d+:\d+)\b#", $table[5])));

            if (!$this->re("#{$this->t('Date')}\s+(\d+-\d+-\d+)\b#", $table[4])) {
                $seg['DepDate'] = strtotime($this->normalizeDate($this->re("#{$this->t('Date')}\s+(\d{2,4})\b#", $table[4]) . '' . $this->re("#{$this->t('Departure\s+Time')}\s+(-\d+-\d+\s+\d+:\d+)\b#", $table[5])));
            }

            // ArrCode
            $seg['ArrCode'] = $this->re("#{$this->t('To')}\s+([A-Z]{3})\b#", $table[1]);

            // ArrName
            // ArrivalTerminal
            // ArrDate
            $seg['ArrDate'] = MISSING_DATE;

            $tableGateText = preg_replace("#^.*\n+#", "", mb_substr($flight, $posGate - 20));
            $tableGatePos = $this->TableHeadPos($this->re('/^(.+)$/m', $tableGateText));

            if (!empty($seg['ArrCode'])
                && preg_match("/^([ ]*[A-Z]{3}[ ]+){$seg['ArrCode']}(?: |$)/m", $flight, $m)
            ) {
                $tableGatePos[] = mb_strlen($m[1]);
                sort($tableGatePos);
                $tableGatePos = array_values(array_unique($tableGatePos));
            }

            $tableGate = $this->SplitCols($tableGateText, $tableGatePos);

            if (count($tableGate) < 5) {
                $this->logger->debug('incorrect table parse! (1-2)');

                return;
            }

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            $seg['BookingClass'] = $this->re("#{$this->t('Class')}\s*([A-Z]{1,2})\b#", $tableGate[2]);

            // PendingUpgradeTo
            // Seats
            $seg['Seats'][] = $this->re("#{$this->t('Seat')}\s*(\d{1,3}[A-Z])\b#", $tableGate[4]);

            // Duration
            // Meal
            // Smoking
            // Stops

            // RecordLocator
            $RecordLocator = $this->re("#PNR\s*(\d{5,})\b#", $tableGate[3]);
            // TripNumber
            $TicketNumbers = $this->re("#E-Ticket\s*(\d{9,})\b#", $tableGate[5]);

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                return null;
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($Passengers)) {
                        $its[$key]['Passengers'][] = $Passengers;
                    }

                    if (isset($TicketNumbers)) {
                        $its[$key]['TicketNumbers'][] = $TicketNumbers;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'][] = $Passengers;
                }

                if (isset($TicketNumbers)) {
                    $it['TicketNumbers'][] = $TicketNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
    }

    public function parsePdf2(&$its)
    { // NOT Boarding Pass
        $stext = $this->text;
        $segmens = $this->split("#(?:^|\n)\s*(This is not a boarding pass\.\s*\n)#", $stext);

        foreach ($segmens as $text) {
            $posFrom = stripos($text, 'Flights Out');
            $posEnd = stripos($text, 'THIS IS NOT A BOARDING PASS');

            if (empty($posEnd)) {
                $posEnd = stripos($text, 'Traveling with baggage');
            }

            if (empty($posEnd) || empty($posFrom)) {
                $this->logger->debug('not finded segment!');

                return null;
            }
            $flight = substr($text, $posFrom - 10, $posEnd - $posFrom + 10); // 10 - don't lose space from beggining of string to "From"
            $flight = preg_replace("#^[\s\S]*?\n(?=[ ]*Flights Out)#", "", $flight);
            $seg = [];

            $table = $this->SplitCols($flight);

            if (stripos($flight, 'Boarding Time') !== false) {
                if (count($table) < 7) {
                    $this->logger->debug('incorrect table parse! (2-1)');

                    return;
                }

                // FlightNumber
                $seg['FlightNumber'] = $this->re("#Flights\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})\b#", $table[4]);
                // AirlineName
                $seg['AirlineName'] = $this->re("#Flights\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})\b#", $table[4]);

                // DepCode
                $seg['DepCode'] = $this->re("#\b([A-Z]{3})\b#", $table[1]);

                // DepName
                // DepartureTerminal
                // DepDate
                $seg['DepDate'] = strtotime($this->normalizeDate($this->re("#\b(\d{4}-\d{1,2}-\d{1,2})\b#", $table[0]) . ' ' . $this->re("#From\s+(\d+:\d+)\b#", $table[1])));

                // ArrCode
                $seg['ArrCode'] = $this->re("#\b([A-Z]{3})\b#", $table[3]);

                // ArrName
                // ArrivalTerminal
                // ArrDate
                $seg['ArrDate'] = MISSING_DATE;

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                $seg['Seats'][] = $this->re("#Seat\s*(\d{1,3}[A-Z])\b#", $table[6]);

                // Duration
                // Meal
                // Smoking
                // Stops

                // Passengers
                $Passengers = $this->re("#Passenger\s+Name\s+(.+)#", $table[5]);
            } else {
                if (count($table) < 6) {
                    $this->logger->debug('incorrect table parse! (2-2)');

                    return;
                }

                // FlightNumber
                $seg['FlightNumber'] = $this->re("#Flights\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})\b#", $table[3]);
                // AirlineName
                $seg['AirlineName'] = $this->re("#Flights\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})\b#", $table[3]);

                // DepCode
                $seg['DepCode'] = $this->re("#\b([A-Z]{3})\b#", $table[1]);

                // DepName
                // DepartureTerminal
                // DepDate
                $seg['DepDate'] = strtotime($this->normalizeDate($this->re("#\b(\d{4}-\d{1,2}-\d{1,2})\b#", $table[0]) . ' ' . $this->re("#From\s+(\d+:\d+)\b#", $table[1])));

                // ArrCode
                $seg['ArrCode'] = $this->re("#\b([A-Z]{3})\b#", $table[2]);

                // ArrName
                // ArrivalTerminal
                // ArrDate
                $seg['ArrDate'] = strtotime($this->normalizeDate($this->re("#\b(\d{4}-\d{1,2}-\d{1,2})\b#", $table[0]) . ' ' . $this->re("#To\s+(\d+:\d+)\b#", $table[2])));

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                $seg['Seats'][] = $this->re("#Seat\s*(\d{1,3}[A-Z])\b#", $table[5]);

                // Duration
                // Meal
                // Smoking
                // Stops

                // Passengers
                $Passengers = $this->re("#Passenger\s+Name\s+(.+)#", $table[4]);
            }
            // RecordLocator
            $RecordLocator = CONFNO_UNKNOWN;
            // TripNumber

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                return null;
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($Passengers)) {
                        $its[$key]['Passengers'][] = $Passengers;
                    }

                    if (isset($TicketNumbers)) {
                        $its[$key]['TicketNumbers'][] = $TicketNumbers;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (isset($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (isset($Passengers)) {
                    $it['Passengers'][] = $Passengers;
                }

                if (isset($TicketNumbers)) {
                    $it['TicketNumbers'][] = $TicketNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reProvider) !== false;
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
        $text = '';

        foreach ($pdfs as $pdf) {
            if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }

        foreach ($pdfs as $pdf) {
            if (stripos($parser->getAttachmentHeader($pdf, "content-type"), 'application/pdf') === false) {
                continue;
            }

            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }

            if (stripos($this->text, $this->t("NotBP")) !== false) {
                $this->parsePdf2($its);
            } else {
                $this->parsePdf1($its);
            }

            foreach ($its as $key => $it) {
                foreach ($it['TripSegments'] as $i => $value) {
                    if (isset($its[$key]['Passengers'])) {
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    }

                    if (isset($its[$key]['TicketNumbers'])) {
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    }
                }
            }
        }
        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
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
        return count(self::$dictionary) * 3;
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
        //		$this->logger->debug($str);
        $in = [
            "#^\s*(\d{4})-(\d{1,2})-(\d{1,2})\s+(\d+:\d+)\s*$#", //2017-08-27 17:25
        ];
        $out = [
            "$3.$2.$1 $4",
        ];
        $str = preg_replace($in, $out, $str);

        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
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
}
