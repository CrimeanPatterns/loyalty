<?php

namespace AwardWallet\Engine\austrian\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "austrian/it-11979569.eml";

    public $reFrom = "";
    public $reSubject = [];

    public $reBody = 'Austrian Airlines';
    public $reBody2 = [
        "en"=> "BOARDING PASS",
    ];
    public $pdfPattern = ".+\.pdf";
    public $text;

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$its)
    {
        $text = $this->text;
        $bps = $this->split("#(?:^|\n\s*)(BOARDING PASS\s*\n)#", $text);

        foreach ($bps as $stext) {
            $pos = stripos($stext, 'Hand Baggage');

            if (!empty($pos)) {
                $stext = substr($stext, 0, $pos);
            }

            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $this->re("#(?:\n|\s{3,})" . $this->preg_implode($this->t("PNR")) . "[ ]+(.+)#", $stext);

            // TripNumber
            // Passengers
            preg_match("#" . $this->preg_implode($this->t("BOARDING PASS")) . "\s*\n([\s\S]+?)\n[ ]*" . $this->preg_implode($this->t("From")) . "\s+#i", $stext, $m);

            if (!empty($m[1]) && preg_match_all("#^[ ]{0,5}(\S.+)#m", $m[1], $mat)) {
                $passenger = [];

                foreach ($mat[1] as $value) {
                    $passenger[] = preg_split("#\s{3,}#", trim($value))[0];
                }

                if (!empty($passenger)) {
                    $it['Passengers'][] = implode(" ", $passenger);
                }
            }

            // TicketNumbers
            if (preg_match("#(?:\n\s*|\s{3,})" . $this->preg_implode($this->t("TKT No")) . "[ ]+([\d\ -]+)#", $stext, $m)) {
                $it['TicketNumbers'][] = $m[1];
            }
            // AccountNumbers
            if (preg_match("#(?:\n\s*|\s{3,})" . $this->preg_implode($this->t("FQTV No")) . "[ ]+(.+)#", $stext, $m)) {
                $it['AccountNumbers'][] = $m[1];
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

            if (preg_match("#\n([ ]*Departure date[ ]+[\s\S]+?)\n([ ]*Flight[ ]+[\s\S]+)\n\n#", $stext, $m)) {
                $table1 = $this->splitCols($m[1]);
                $table2 = $this->splitCols($m[2]);
            }

            $seg = [];
            // FlightNumber
            $seg['FlightNumber'] = $this->re("#" . $this->preg_implode($this->t("Flight")) . "\s+[A-Z\d]{2}\s*(\d{1,5})\b#", $table2[0]);

            // DepCode
            // DepName
            if (preg_match("#\n\s*" . $this->preg_implode($this->t("From")) . "\s+(.+)\(([A-Z]{3})\)#", $stext, $m)) {
                $seg['DepName'] = trim(preg_replace("#\s{2,}#", ', ', $m[1]));
                $seg['DepCode'] = $m[2];
            }

            // DepartureTerminal

            // DepDate
            $date = $this->re("#" . $this->preg_implode($this->t("Departure date")) . "\s+(.+)#", $table1[0]);
            $time = $this->re("#" . $this->preg_implode($this->t("Departure time")) . "\s+(\d+:\d+)\b#", $table1[1]);

            if (!empty($date) && !empty($time)) {
                $seg['DepDate'] = strtotime($date . ' ' . $time);
            }

            // ArrCode
            // ArrName
            if (preg_match("#\n\s*" . $this->preg_implode($this->t("To")) . "\s+(.+)\(([A-Z]{3})\)#", $stext, $m)) {
                $seg['ArrName'] = trim(preg_replace("#\s{2,}#", ', ', $m[1]));
                $seg['ArrCode'] = $m[2];
            }

            // ArrivalTerminal
            // ArrDate
            $seg['ArrDate'] = MISSING_DATE;

            // AirlineName
            $seg['AirlineName'] = $this->re("#" . $this->preg_implode($this->t("Flight")) . "\s+([A-Z\d]{2})\s*\d{1,5}\b#", $table2[0]);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles

            // Cabin
            // BookingClass
            if (preg_match("#" . $this->preg_implode($this->t("Travel class")) . "\s+(.+?)(?:\(([A-Z]{1,2})\))?(\n|$)#", $table1[3], $m)) {
                $seg['Cabin'] = trim($m[1]);

                if (!empty($m[2])) {
                    $seg['BookingClass'] = $m[2];
                }
            }

            // PendingUpgradeTo
            // Seats
            $seg['Seats'][] = $this->re("#" . $this->preg_implode($this->t("Seat")) . "\s+(\d{1,3}[A-Z])\b#", $table2[3]);

            // Duration
            // Meal
            // Smoking
            // Stops

            $finded = false;

            foreach ($its as $key => $itG) {
                if (isset($it['RecordLocator']) && $itG['RecordLocator'] == $it['RecordLocator']) {
                    if (isset($it['Passengers'])) {
                        $its[$key]['Passengers'] = (isset($its[$key]['Passengers'])) ? array_merge($its[$key]['Passengers'], $it['Passengers']) : $it['Passengers'];
                        $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    }

                    if (isset($it['TicketNumbers'])) {
                        $its[$key]['TicketNumbers'] = (isset($its[$key]['TicketNumbers'])) ? array_merge($its[$key]['TicketNumbers'], $it['TicketNumbers']) : $it['TicketNumbers'];
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    }

                    if (isset($it['AccountNumbers'])) {
                        $its[$key]['AccountNumbers'] = (isset($its[$key]['AccountNumbers'])) ? array_merge($its[$key]['AccountNumbers'], $it['AccountNumbers']) : $it['AccountNumbers'];
                        $its[$key]['AccountNumbers'] = array_unique($its[$key]['AccountNumbers']);
                    }
                    $finded2 = false;

                    foreach ($itG['TripSegments'] as $key2 => $value) {
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

            if ($finded == false) {
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        //		return strpos($from, $this->reFrom)!==false;
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		if(strpos($headers["from"], $this->reFrom)===false)
        //			return false;
//
        //		foreach($this->reSubject as $re){
        //			if(stripos($headers["subject"], $re) !== false)
        //				return true;
        //		}

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

        if (stripos($text, $this->reBody) === false) {
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
        $its = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->reBody2 as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->parsePdf($its);
        }

        $name = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map('preg_quote', $field));
    }
}
