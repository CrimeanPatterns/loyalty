<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf2015 extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-8674046.eml, alitalia/it-8674500.eml";

    public $reFrom = "noreply@alitalia.com";
    public $reSubject = [
        "en"=> "Carta d’imbarco Alitalia Web check-in",
    ];
    public $reBody = 'alitalia.com';
    public $reBody2 = [
        "en"=> "Boarding Pass",
    ];
    public $pdfPattern = "\w{2}_4\d+_\d+_[A-Z_]+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        $it['Passengers'] = [];

        // TicketNumbers
        $it['TicketNumbers'] = [];

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
        preg_match_all("#Boarding Pass\n(.*?)\n\n\n#s", $text, $segments);
        $segments[1] = array_filter($segments[1], function ($s) { return !empty(trim($s)); });
        $uniq = [];

        foreach ($segments[1] as $ticket) {
            $pos = [0, strlen($this->re("#\n(.*\s{2,})FLIGHT#", $ticket))];
            $ticket = $this->splitCols($ticket, $pos, false);

            if (count($ticket) != 2) {
                $this->http->log("incorrect parse ticket");

                return;
            }
            $flightTable = $this->splitCols($this->re("#\nFLIGHT[^\n]+\n\n(.+)#", $ticket[1]));

            if (count($flightTable) != 4) {
                $this->http->log("incorrect parse flightTable");

                return;
            }
            $boardingTable = $this->splitCols($this->re("#\nGATE[^\n]+\n\n(.+)#", $ticket[1]), $this->tableHeadPos($this->re("#\n(GATE[^\n]+)\n\n(.+)#", $ticket[1])));

            if (count($boardingTable) < 3) {
                $this->http->log("incorrect parse boardingTable");

                return;
            }

            $it['Passengers'][] = $this->re("#NAME\s+([^\n]+)#ms", $ticket[1]);
            $it['TicketNumbers'][] = $this->re("#BOARDING TIME[^\n]+\s+[^\n]+\s+(\d+)-#", $ticket[1]);

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#^\w{2}\s+(\d+)$#", $flightTable[0]);

            if (isset($uniq[$itsegment['FlightNumber']])) {
                continue;
            }
            $uniq[$itsegment['FlightNumber']] = 1;

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->re("#FROM\s+(.+)#", $ticket[1]);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($flightTable[2] . ', ' . $flightTable[3]));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->re("#TO\s+(.+)#", $ticket[1]);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#^(\w{2})\s+\d+$#", $flightTable[0]);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $flightTable[1];

            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = $boardingTable[2];

            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
        $it['Passengers'] = array_unique($it['Passengers']);
        $it['TicketNumbers'] = array_unique($it['TicketNumbers']);

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

    private function SplitCols($text, $pos = false, $trim = true)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $val = mb_substr($row, $p, null, 'UTF-8');

                if ($trim) {
                    $val = trim($val);
                }
                $cols[$k][] = $val;

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
