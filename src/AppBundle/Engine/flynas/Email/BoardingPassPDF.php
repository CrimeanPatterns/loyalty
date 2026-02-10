<?php

namespace AwardWallet\Engine\flynas\Email;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "flynas/it-10477700.eml, flynas/it-10501894.eml, flynas/it-30812759.eml, flynas/it-7149357.eml, flynas/it-8288494.eml, flynas/it-8558062.eml";

    public $reBody = [
        'en'  => ['Boarding Pass', 'Flight Details'],
        'en1' => ['Document Check', 'BOOKING REF'],
    ];

    public $lang = '';

    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $type = '';
        $this->lang = 'en';

        $its = [];
        $pdfs = $parser->getAttachments();

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $key => $pdf) {
                $name = $pdf['headers']['content-type'];

                if (strpos($name, 'pdf')) {
                    if (($text = \PDF::convertToText($parser->getAttachmentBody($key))) !== null) {
                        $NBSP = chr(194) . chr(160);
                        $text = str_replace($NBSP, ' ', $text);

                        if (strpos($text, 'Document Check') !== false) {
                            $type = 2;
                            $this->parseEmailPdf_type2($text, $its);
                        } elseif (strpos($text, 'Gate Closing') !== false) {
                            $type = 1;
                            $this->parseEmailPdf_type1($text, $its);
                        }
                    } else {
                        continue;
                    }
                }
            }
        } else {
            return null;
        }

        foreach ($its as $key => $it) {
            if (!empty($its[$key]['Passengers'][0])) {
                $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
            }

            if (!empty($its[$key]['AccountNumbers'][0])) {
                $its[$key]['AccountNumbers'] = array_unique($its[$key]['AccountNumbers']);
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BoardingPassPDF' . $type . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->getAttachments();
        $text = '';

        foreach ($pdfs as $key => $pdf) {
            $name = $pdf['headers']['content-type'];

            if (strpos($name, 'pdf')) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($key))) !== null) {
                    $NBSP = chr(194) . chr(160);
                    $text = str_replace($NBSP, ' ', htmlspecialchars($text));

                    foreach ($this->reBody as $reBody) {
                        if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your flynas boarding pass') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flynas.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 2 * count(self::$dict);
    }

    private function parseEmailPdf_type1($texts, &$its)
    {
        $bpass = $this->split("#(\S.*\s+Boarding Pass /)#", $texts);

        foreach ($bpass as $key => $text) {
            $pos = strpos($text, 'Gate Closing');
            $text = substr($text, 0, $pos);

            if (preg_match("#\n(.*\s)Depar?ture Time /#", $text, $m)) {
                $pos2 = mb_strlen($m[1], 'UTF-8') - 5;
            }

            if (preg_match("#\n(.*\s)From /#", $text, $m)) {
                $pos3 = mb_strlen($m[1], 'UTF-8') - 10;
            }

            if (empty($pos2) || empty($pos3)) {
                continue;
            }

            $table = $this->SplitCols($text, [0, $pos2, $pos3]);

            if (preg_match("#Booking Reference\s*/\s*.+\s+([A-Z\d]+)#", $table[0], $m)) {
                $RecordLocator = $m[1];
            }

            if (preg_match("#Passenger Name\s*/\s*.+\s+([A-Z\- ]+)#", $table[0], $m)) {
                $Passengers = $m[1];
            }

            $seg = [];

            if (preg_match("#Flight Number\s*/\s*.+\s+([A-Z\d]{2})[ ]*(\d+)#", $table[0], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#Date\s*/\s*.+\s+([\w, ]+)#", $table[1], $m)) {
                $date = strtotime($m[1]);
            }

            if ($date && preg_match("#Depa(?:r?)ture Time\s*/\s*.+\s+(\d+:\d+[ ]*\w*)#", $table[1], $m)) {
                $seg['DepDate'] = strtotime($m[1], $date);
                $seg['ArrDate'] = MISSING_DATE;
            }

            if (preg_match("#From\s*/\s*.+\s+(.+)#", $table[2], $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match("#To\s*/\s*.+\s+(.+)#", $table[2], $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if (preg_match("#^([^/]+)/\s.*#", $table[0], $m)) {
                $seg['Cabin'] = trim($m[1]);
            }

            if (preg_match("#Seat Number\s*/\s*.+\s+(\d{1,3}[A-Z])#", $table[0], $m)) {
                $seg['Seats'][] = $m[1];
            }

            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    if (isset($Passengers)) {
                        $its[$key]['Passengers'][] = $Passengers;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName'] && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber'] && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $its[$key]['TripSegments'][$key2]['Seats'] = array_merge($value['Seats'], $seg['Seats']);
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
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }
    }

    private function parseEmailPdf_type2($texts, &$its)
    {
        $AccountNumbers = [];

        if (preg_match_all("#(?:^|\s{3,})ID:\s([A-Z\d]+)#", $texts, $m)) {
            $AccountNumbers = $m[1];
            $texts = preg_replace("#(?:^|\s{3,})ID:\s([A-Z\d]+)#", '', $texts);
        }

        $bpass = $this->split("#(.*SEAT\s*\n)#", $texts);

        $n = count($bpass);

        for ($i = 1; $i < $n; $i += 2) {
            unset($bpass[$i]);
        }

        foreach ($bpass as $key => $text) {
            $pos = strpos($text, 'Document Check');

            if ($pos !== false) {
                $text = substr($text, 0, $pos);
            }

            if (preg_match("#\n(.*\s{3})CLASS\s{3,}#", $text, $m)) {
                $pos2 = mb_strlen($m[1], 'UTF-8');
            }

            if (preg_match("#\n(.*\s{3})DEPART(\s{3,}|\n)#", $text, $m)) {
                $pos3 = mb_strlen($m[1], 'UTF-8') - 6; // it-10501894.eml
            }

            if (isset($pos3) && preg_match("#(?:\n|^)(.*\s{3})SEAT\s*\n#", $text, $m)) {
                $pos4 = mb_strlen($m[1], 'UTF-8');
                $pos4 -= ($pos4 - $pos3) / 2;
            }

            if (!isset($pos2) || !isset($pos3) || !isset($pos4)) {
                $this->logger->debug("incorrect columns position");

                return null;
            }

            $table = $this->SplitCols($text, [0, $pos2, $pos3, $pos4]);

            if (count($table) < 4) {
                $this->logger->log("incorrect columns count");

                return;
            }

            if (preg_match("#BOOKING REF\s+([A-Z\d]+)#", $table[0], $m)) {
                $RecordLocator = $m[1];
            }

            if (preg_match("#PASSENGER\s+(([A-Z\- ]+\n+){1,3})\s*FLIGHT#", $table[0], $m)) {
                $Passengers = trim(preg_replace("#\s+#", ' ', $m[1]));
            }

            $seg = [];

            if (preg_match("#FLIGHT NUMBER\s+([A-Z\d]{2})[ ]*(\d+)#", $table[0], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#DATE\s+(\d+)\s*(\w*)\s*(\d{2})#", $table[1], $m)) {
                $date = strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3]);
            }

            if (preg_match("#^([\s\S]+)\s+([A-Z]{3})\s+(\d+:\d+)#", $table[0], $m)) {
                $seg['DepName'] = trim($m[1]);
                $seg['DepCode'] = $m[2];
                $seg['DepDate'] = strtotime($m[3], $date);
            }

            if (preg_match("#^([\s\S]+)\s+([A-Z]{3})\s+(\d+:\d+)#", $table[2], $m)) {
                $seg['ArrName'] = trim($m[1]);
                $seg['ArrCode'] = $m[2];
                $seg['ArrDate'] = strtotime($m[3], $date);
            }

            if (preg_match("#TERMINAL\s+(.*)#", $table[3], $m)) {
                $seg['DepartureTerminal'] = trim(str_ireplace('Terminal', '', $m[1]));
            }

            if (preg_match("#SEAT\s+(\d{1,3}[A-Z])#", $table[3], $m)) {
                $seg['Seats'][] = $m[1];
            }

            if (preg_match("#CLASS\s+(.+)#", $table[1], $m)) {
                $seg['Cabin'] = $m[1];
            }

            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] === $RecordLocator) {
                    if (isset($Passengers)) {
                        $its[$key]['Passengers'][] = $Passengers;
                    }
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName'] && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber'] && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $its[$key]['TripSegments'][$key2]['Seats'] = array_merge($value['Seats'], $seg['Seats']);
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

                if (!empty($AccountNumbers)) {
                    $it['AccountNumbers'] = $AccountNumbers;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            if (empty(trim($r[0])) || stripos($r[0], 'SEAT') === false) {
                array_shift($r);
            }

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
