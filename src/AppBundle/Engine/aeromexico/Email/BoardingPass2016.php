<?php

namespace AwardWallet\Engine\aeromexico\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class BoardingPass2016 extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-12168452.eml, aeromexico/it-13957404.eml, aeromexico/it-191384420.eml, aeromexico/it-4888168.eml, aeromexico/it-6300804.eml, aeromexico/it-7973591.eml, aeromexico/it-8445248.eml, aeromexico/it-8635572.eml, aeromexico/it-8858607.eml";

    public static $dictionary = [
        "en" => [],
        "es" => [
            'FLIGHT'   => 'VUELO',
            'DEPART'   => 'SALIDA',
            'ARRIVAL'  => 'LLEGADA',
            'SEAT'     => 'ASIENTO',
            'BOARDING' => 'ABORDAJE',
            'to'       => 'a',
            'Class'    => 'Clase',
        ],
        "fr" => [
            'FLIGHT'   => 'VOL',
            'DEPART'   => 'DÉPART',
            'ARRIVAL'  => 'ARRIVÉE',
            'SEAT'     => 'SIÈGE',
            'BOARDING' => 'EMBARQUEMENT',
            'to'       => 'à',
            'Class'    => 'Classe',
        ],
    ];

    public $lang = "en";

    private $reFrom = "@aeromexico.com";
    private $reSubject = [
        "en"=> "Pase de abordar",
        "es"=> "Pase de abordar",
    ];
    private $reBody = 'Aeromexico';
    private $reBody2 = [
        "en"=> "DEPARTING",
        "es"=> "SALIDA",
        'fr'=> 'DÉPART',
    ];
    private $pdfPattern = "Aeromexico_.*\.pdf";
    private $date = null;

    public function parsePdf()
    {
        $itineraries = [];
        $text = $this->text;

        if ($t = $this->re('/( +SALIDA[^\n]+LLEGADA *)/', $text)) {
            $a = trim($t);
            $text = str_replace($t, $a, $text);
        }

        $mainTable = $this->re("#" . $this->t("FLIGHT") . "[^\n]+\n+(.*?)\n {0,10}" . $this->t("DEPART") . "#s", $text);

        $pos = [0, mb_strlen($this->re("#(.*?)\w{2}\s+\d+\s+#", $mainTable), 'UTF-8')];

        if (count($mainTable = $this->SplitCols($mainTable, $pos, false)) != 2) {
            $this->logger->info("incorrect split mainTable");

            return;
        }

        if (count($mainTable[1] = $this->SplitCols($mainTable[1], $this->colsPos($mainTable[1]))) != 2) {
            $this->logger->info("incorrect parse mainTable");

            return;
        }

        if (count($bottomTable = $this->SplitCols($this->re("#\n {0,10}" . $this->t("DEPART") . "[^\n]+\n+(.*?)\n\n#s", $text))) != 4) {
            $this->logger->info("incorrect parse bottomTable");

            return;
        }

        $it = [];
        $itsegment = [];

        $it['Kind'] = "T";

        // RecordLocator
        // TicketNumbers
        if (preg_match("#\n[^\n\S]*(?<RecordLocator>[A-Z\d]{6})\s+(?<TicketNumber>\d{10,})\s+(?:" . $this->t("Class") . " (?<BookingClass>\w+)\s+)?#", $mainTable[0], $m)) {
            $it['RecordLocator'] = $m["RecordLocator"];
            $it['TicketNumbers'] = [$m["TicketNumber"]];

            if (isset($m["BookingClass"])) {
                $itsegment['BookingClass'] = $m["BookingClass"];
            }
        }

        // TripNumber
        // Passengers
        $it['Passengers'] = [trim($this->re("#([^\n]+)#", $mainTable[0]))];

        // AccountNumbers
        $account = $this->re("#\n {0,10}AM: {0,3}(\d{5,})\s*\n#", $mainTable[0]);
        if (!empty($account)) {
            $it['AccountNumbers'][] = $account;
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

        $date = $this->normalizeDate($this->re("#\n(\d+ [^\s\d]+ \d{4}|[^\s\d]+ \d+ \d{4})\n#", $mainTable[1][0]));

        if (preg_match("#(\w{2}) (\d+)\n#", $mainTable[1][0], $m)) {
            // FlightNumber
            $itsegment['FlightNumber'] = $m[2];

            // AirlineName
            $itsegment['AirlineName'] = $m[1];
        }

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        if (preg_match("#\n[^\n\S]*(.*?) " . $this->t("to") . " (.+)#", $mainTable[0], $m)) {
            // DepName
            $itsegment['DepName'] = $m[1];

            // ArrName
            $itsegment['ArrName'] = $m[2];
        }

        // DepDate
        $itsegment['DepDate'] = strtotime($this->re("#\n" . $this->t("DEPART") . "[^\n]*\n+(.+)#", $mainTable[1][0]), $date);
        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->re("#(.+)#", $bottomTable[3]), $date);

        // DepartureTerminal
        $itsegment['DepartureTerminal'] = $this->re("#Terminal: (.+)#", $bottomTable[0]);

        // ArrivalTerminal
        $itsegment['ArrivalTerminal'] = $this->re("#Terminal: (.+)#", $bottomTable[3]);

        // Operator
        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'][] = $this->re("#\n" . $this->t("SEAT") . "[^\n]*\n+(\d{1,3}[A-Z]\b)#", $mainTable[1][1]);

        // Duration
        $itsegment['Duration'] = $this->re("#(.+)#", $bottomTable[2]);

        // Meal
        // Smoking
        // Stops

        $it['TripSegments'][] = $itsegment;

        $itineraries[] = $it;

        return $itineraries;
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

        if (isset($pdfs[0]) && ($text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]))) !== null) {
            if (stripos($text, $this->reBody) === false) {
                return false;
            }

            foreach ($this->reBody2 as $re) {
                if (stripos($text, $re) !== false) {
                    return true;
                }
            }
        } else {
            $body = $parser->getHTMLBody();

            if (stripos($body, $this->reBody) === false) {
                return false;
            }

            foreach ($this->reBody2 as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $itineraries = [];

        $type = 'html';
        $this->text = $this->http->Response['body'];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (isset($pdfs[0])) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]))) !== null) {
                $this->text = $text;
                $type = 'pdf';
            }
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (stripos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($type) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $type == 'pdf' ? $this->parsePdf() : $this->parseHtml(),
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

    private function parseHtml()
    {
        $itineraries = [];
        $it = [];
        $it['Kind'] = "T";

        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $nodes = $this->http->XPath->query("//img[ancestor::td[1]/preceding-sibling::td[1][string-length(normalize-space(.))=3] and ancestor::td[1]/following-sibling::td[1][string-length(normalize-space(.))=3]]");

        foreach ($nodes as $node) {
            $it['Passengers'][] = $this->http->FindSingleNode("./ancestor::table[1]/preceding::text()[normalize-space(.)!=''][1]", $node);
            $itsegment = [];
            $date = $this->normalizeDate($this->http->FindSingleNode("./ancestor::table[1]/following::text()[normalize-space(.)!=''][2]", $node));

            $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            $itsegment['DepDate'] = $date;
            $itsegment['DepCode'] = $this->http->FindSingleNode("./ancestor::td[1]/preceding-sibling::td[1]", $node);
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./ancestor::td[1]/following-sibling::td[1]", $node);
            $text = $this->http->FindSingleNode("./ancestor::table[1]/following::text()[normalize-space(.)!=''][1]", $node);

            if (preg_match("#(.+?)\s*to\s+(.+)#", $text, $m)) {
                $itsegment['DepName'] = $m[1];
                $itsegment['ArrName'] = $m[2];
            }

            if (0 < $this->http->XPath->query("//node()[contains(normalize-space(.), 'Aeromexico')]")->length) {
                $itsegment['AirlineName'] = 'AF';
            }
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("(./following::text()[" . $this->eq($this->t('DEPARTING')) . "][1]/ancestor::table[1]/following-sibling::table[1]//td[1]//text()[normalize-space(.)])[1]", $node, true, '/Terminal\s+(.+)/i');
            $itsegment['DepName'] = $this->http->FindSingleNode("(./following::text()[" . $this->eq($this->t('DEPARTING')) . "][1]/ancestor::table[1]/following-sibling::table[1]//td[1]//text()[normalize-space(.)])[2]", $node);
            $itsegment['Duration'] = $this->http->FindSingleNode("(./following::text()[" . $this->eq($this->t('ARRIVAL')) . "][1]/ancestor::table[1]/following-sibling::table[1]//text()[normalize-space(.)])[1]", $node);
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("(./following::text()[" . $this->eq($this->t('ARRIVAL')) . "][1]/ancestor::table[1]/following-sibling::table[1]//text()[normalize-space(.)])[last()-1]", $node), $date);
            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        return $itineraries;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        $this->http->log($instr);
        $in = [
            "#^(?<week>[^\s\d]+) (\d+) ([^\s\d,.]+)[,.]* (\d+:\d+(?:\s*[AP]M)?)$#", //THU 29 MAR 06:12
            "#^(\d+)\s+([^\d\s\.\,]+)[.,]*\s+(\d{4})$#", //29 MAR., 2018
        ];
        $out = [
            "$2 $3 %Y%, $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $instr);
        $this->http->log($str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
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

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false, $trim = true)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $s = mb_substr($row, $p, null, 'UTF-8');
                $cols[$k][] = $trim ? trim($s) : $s;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
