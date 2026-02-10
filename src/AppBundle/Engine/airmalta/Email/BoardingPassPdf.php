<?php

namespace AwardWallet\Engine\airmalta\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "airmalta/it-4549091.eml, airmalta/it-7430985.eml, airmalta/it-7534089.eml";

    public $reFrom = "@airmalta.com";
    public $reSubject = [
        "en"=> "Your Air Malta Boarding Pass",
    ];
    public $reBody = 'Air Malta';
    public $reBody2 = [
        "fr"=> "Carte d'Embarquement",
        "en"=> "Boarding Pass", // must be last
    ];
    public $pdfPattern = "BoardingPass.pdf";

    public static $dictionary = [
        "en" => [],
        "fr" => [
            "FREQUENT FLYER"    => "FQTV",
            "NEXT STEPS"        => "PROCHAINES ETAPES",
            "BOOKING REFERENCE" => "BOOKING REF",
            "Boarding Pass"     => "Boarding Pass",
            "ETKT"              => "ETKT",
            "TAKE-OFF"          => "DECOLLAGE",
            "FLIGHT"            => "VOL",
            "TRAVEL INFORMATION"=> "INFORMATIONS SUR LE VOYAGE",
        ],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $infoTable = $this->splitCols($this->re("#\n([^\n]+" . $this->t("FREQUENT FLYER") . ".*?)" . $this->t("NEXT STEPS") . "#ms", $text));

        if (count($infoTable) < 3) {
            $this->http->log("incorrect columns count infoTable");

            return;
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->t("BOOKING REFERENCE") . "\s+(\w+)#ms", $infoTable[2]);

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->re("#" . $this->t("Boarding Pass") . "\s+([^\n]+)#ms", $text);

        // TicketNumbers
        $it['TicketNumbers'] = $this->re("#" . $this->t("ETKT") . "\s+([^\n]+)#ms", $infoTable[2]);

        // AccountNumbers
        $it['AccountNumbers'] = str_replace("\n", " ", $this->re("#(" . $this->t("FREQUENT FLYER") . "\s+[^\n]+)#ms", $infoTable[2]));

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

        // match main big table with codes, times, names
        $FlMain = $this->re("#\n\s*" . $this->t("TAKE-OFF") . "[^\n]+\n(.*?)\n\s*" . $this->t("FLIGHT") . "#ms", $text);

        // split it for 2 tables codes(table1), times and terminals, names(table2)
        $rows = explode("\n", $FlMain);

        if (count($rows) < 6) {
            $this->http->log("incorrect rows count FlMain");

            return;
        }
        $table2 = implode("\n", [$rows[count($rows) - 2], $rows[count($rows) - 1]]);

        unset($rows[count($rows) - 1], $rows[count($rows) - 1]);
        $table1 = implode("\n", $rows);

        // match columns postions for table1
        if (!preg_match("#\n(((\s*\S.*?)\s{2,}[A-Z]{3}\s{2,})[A-Z]{3}\s{2,}).+#", $table1, $m)) {
            $this->http->log("positions for table1 not matched");

            return;
        }
        $pos = [];

        foreach ($m as $i=>$str) {
            if ($i == 0) {
                continue;
            }
            $pos[] = strlen($str);
        }
        $pos[] = 0;
        sort($pos);
        $table1 = $this->splitCols($table1, $pos);

        // match columns postions for table2
        if (!preg_match("#\n(\s*\S.*?\s{2,})\S.+#", $table2, $m)) {
            $this->http->log("positions for table2 not matched");

            return;
        }
        $pos = [];

        foreach ($m as $i=>$str) {
            if ($i == 0) {
                continue;
            }
            $pos[] = strlen($str);
        }
        $pos[] = 0;
        sort($pos);
        $table2 = $this->splitCols($table2, $pos);

        // match table3 flnum, seat, etc
        $table3 = array_merge([], array_filter(explode("\n", $this->re("#\n(\s*" . $this->t("FLIGHT") . ".*?)" . $this->t("TRAVEL INFORMATION") . "#ms", $text))));

        if (!isset($table3[1])) {
            $this->http->log("incorrect rows count table3");

            return;
        }
        $table3 = $this->splitCols($table3[1]);

        if (count($table3) < 4) {
            $this->http->log("incorrect cols count table3");

            return;
        }
        // print_r($table1);
        // print_r($table2);
        // print_r($table3);
        // die();

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#^\w{2}(\d+)$#", trim($table3[0]));

        // DepCode
        $itsegment['DepCode'] = trim($table1[1]);

        // DepName
        $a = array_filter(explode("\n", $table2[0]));
        $itsegment['DepName'] = end($a);

        // DepartureTerminal
        $itsegment['DepartureTerminal'] = $this->re("#(" . $this->t("Terminal") . ".+)#", $table2[0]);

        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(", ", array_filter(explode("\n", $table1[0])))));

        // ArrCode
        $itsegment['ArrCode'] = trim($table1[2]);

        // ArrName
        $a = array_filter(explode("\n", $table2[1]));
        $itsegment['ArrName'] = end($a);

        // ArrivalTerminal
        $itsegment['ArrivalTerminal'] = $this->re("#(" . $this->t("Terminal") . ".+)#", $table2[1]);

        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(", ", array_filter(explode("\n", $table1[3])))));

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#^(\w{2})\d+$#", trim($table3[0]));

        // Operator
        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = trim($table3[1]);

        // Duration
        // Meal
        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
            "#^(\d+:\d+),\s+(\d+\s+[^\d\s]+\s+\d{4})$#", //21:20, 05 Sep 2016
        ];
        $out = [
            "$2, $1",
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
