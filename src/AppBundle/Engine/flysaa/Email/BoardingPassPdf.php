<?php

namespace AwardWallet\Engine\flysaa\Email;

use AwardWallet\Engine\MonthTranslate;

// parsers with similar formats: flysaa/MobileBoardingPassText2015En

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "flysaa/it-4431601.eml, flysaa/it-9898277.eml, flysaa/it-9898296.eml";

    public $reFrom = "flysaa.com";
    public $reSubject = [
        "en"=> "Documento de",
    ];
    public $reBody = 'www.flysaa.com';
    public $reBody2 = [
        "en"=> "Your Boarding Pass",
    ];
    public $pdfPattern = ".*\.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        preg_match_all("#(?:\n|^)([^\n\S]*Your Boarding Pass.*?NEXT STEPS)#ms", $text, $segments);
        $airs = [];

        foreach ($segments[1] as $stext) {
            $table = $this->splitCols($this->re("#\n([^\n\S]*Boarding pass information.+)#ms", $stext));

            if (count($table) != 3) {
                $this->logger->info("incorrect parse table");

                return;
            }

            if (!$rl = $this->re("#BOOKING REFERENCE\n(\w+)\n#", $table[2])) {
                $this->logger->info("RL not found");

                return;
            }
            $airs[$rl][] = $stext;
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = [];

            foreach ($segments as $stext) {
                $it['Passengers'][] = $this->re("#Your Boarding Pass\n(.+)#", $stext);
            }
            $it['Passengers'] = array_unique(array_filter($it['Passengers']));

            // TicketNumbers
            $it['TicketNumbers'] = [];
            // AccountNumbers
            $it['AccountNumbers'] = [];

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

            foreach ($segments as $i=>$stext) {
                $table = $this->splitCols($this->re("#\n([^\n\S]*FLIGHT\s+FROM\s+TO.*?)Boarding pass informatio#ms", $stext));

                if (count($table) != 3) {
                    $this->logger->info("incorrect parse table {$rl} - {$i}");

                    return;
                }
                $FlightSeatTable = $this->splitCols($this->re("#\n([^\n\S]*Flight.+)#ms", $table[0]));

                if (count($FlightSeatTable) != 2) {
                    $this->logger->info("incorrect parse FlightSeatTable {$rl} - {$i}");

                    return;
                }
                $bpinfoTable = $this->splitCols($this->re("#\n([^\n\S]*Boarding pass information.+)#ms", $stext));

                if (count($bpinfoTable) != 3) {
                    $this->logger->info("incorrect parse bpinfoTable {$rl} - {$i}");

                    return;
                }

                // TicketNumbers
                $it['TicketNumbers'][] = $this->re("#ETKT\s+([^\n]+)#ms", $bpinfoTable[2]);
                // AccountNumbers
                $it['AccountNumbers'][] = $this->re("#FREQUENT FLYER\s+([^\n\s]+)\n#ms", $bpinfoTable[2]);

                $itsegment = [];
                // FlightNumber
                if (!$itsegment['FlightNumber'] = $this->re("#Flight\s+(\d+)#ms", $FlightSeatTable[0])) {
                    $itsegment['FlightNumber'] = $this->re("#Flight\s+\w{2}(\d+)#ms", $FlightSeatTable[0]);
                    // AirlineName
                    $itsegment['AirlineName'] = $this->re("#Flight\s+(\w{2})\d+#ms", $FlightSeatTable[0]);
                } else {
                    $itsegment['AirlineName'] = AIRLINE_UNKNOWN;
                }

                if (isset($uniq[$itsegment['FlightNumber']])) {
                    $uniq[$itsegment['FlightNumber']]['Seats'][] = $this->re("#Seat\s+(\d+\w)#ms", $FlightSeatTable[1]);

                    continue;
                }
                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = str_replace("\n", " ", $this->re("#FROM\s+(.*?)\n\n#ms", $table[1]));

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#Terminal (.+)#", $table[1]);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate(str_replace("\n", " ", $this->re("#\n(\d+\s+[^\s\d]+\s+\d{4}\n\d+:\d+)\n#", $table[1]))));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = str_replace("\n", " ", $this->re("#TO\s+(.*?)\n\n#ms", $table[2]));

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(str_replace("\n", " ", $this->re("#\n(\d+\s+[^\s\d]+\s+\d{4}\n\d+:\d+)\n#", $table[2]))));

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#CLASS OF TRAVEL\s+(.* CLASS)#msi", $bpinfoTable[2]);

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'][] = $this->re("#Seat\s+(\d+\w)#ms", $FlightSeatTable[1]);

                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
                $uniq[$itsegment['FlightNumber']] = &$it['TripSegments'][count($it['TripSegments']) - 1];
            }
            $it['TicketNumbers'] = array_unique(array_filter($it['TicketNumbers']));
            $it['AccountNumbers'] = array_unique(array_filter($it['AccountNumbers']));
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);
        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
            "#^(\d+)/(\d+)/(\d{2})$#", //06/19/16
            "#^(\d+:\d+)\s+(\d+)/(\d+)/(\d{2})$#", //08:25 06/19/16
        ];
        $out = [
            "$2.$1.20$3",
            "$3.$2.20$4, $1",
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
}
