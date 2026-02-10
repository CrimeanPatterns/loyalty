<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Engine\MonthTranslate;

class TravelItineraryPlain extends \TAccountChecker
{
    public $mailFiles = "bcd/it-8728154.eml, bcd/it-8821475.eml";
    public $reFrom = "@bcdtravel.com";
    public $reSubject = [
        "en" => "Travel Itinerary for",
    ];
    public $reBody = 'BCD Travel';
    public $reBody2 = [
        "en" => "Travel Summary",
    ];
    public $text;
    public $date;
    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "";

    public function parsePlain(&$itineraries)
    {
        $text = $this->text;

        $mainTable = $this->re("#\n([^\n]*Flight\/Vendor.*?)\n\n#ms", $text);

        if (strpos($mainTable, "\n") === false) {
            $mainTable = $this->re("#\n([^\n]*Flight\/Vendor.*?)\n\n\n#ms", $text);
            $mainTable = str_replace("\n\n", "\n", $mainTable);
        }
        $rows = explode("\n", $mainTable);
        $pos = $this->tableHeadPos(array_shift($rows));
        $mainInfo = [];
        $table = [];

        foreach ($rows as $row) {
            $table = $this->splitCols($row, $pos);

            if (count($table) != 6) {
                $this->http->Log("incorrect parse table");

                return;
            }
            $mainInfo[trim($table[2])] = $table;
        }

        $segments = $this->split("#\n([^\n]+- Agency Record Locator)#", $text);
        $airs = [];

        foreach ($segments as $stext) {
            $type = $this->re("#^\s*(.+?)\s+- #", $stext);
            //$type = $this->re("#^(.*?) - #", $stext);
            switch ($type) {
                case 'AIR':
                    if (!$rl = $this->re("#Status:.*Record Locator:\s+\*?(\w+)#", $stext)) {
                        $this->http->Log("RL not matched");

                        return;
                    }
                    $airs[$rl][] = $stext;

                    break;

                case 'Travel Summary':
                    break;

                    break;

                default:
                    $this->http->Log("unknown segment type {$type}");

                    return;
            }
        }

        foreach ($airs as $rl => $segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->re("#Agency Record Locator\s+(\w+)#", $text);

            // Passengers
            $it['Passengers'] = array_filter([trim($this->re("#Traveler\s*\n([^\n]+)#", $text))]);

            // TicketNumbers
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

            foreach ($segments as $stext) {
                $date = strtotime($this->normalizeDate($this->re("#-\s+(.*?)(?:\s*-\s+|\n|\s{2,})#", $stext)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#Flight\s+\w{2}(\d+)#", $stext);

                // DepName
                $itsegment['DepName'] = $this->re("#Depart:\s+(.*?)(?:,|\n)#", $stext);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#Depart:.*Terminal\s+(.+)#", $stext);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#Depart:.*?(\d+:\d+[^\n]+)#ms", $stext)), $date);

                // ArrName
                $itsegment['ArrName'] = $this->re("#Arrive:\s+(.*?)(?:,|\n)#", $stext);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#Arrive:.*Terminal\s+(.+)#", $stext);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#Arrive:.*?(\d+:\d+[^\n]+)#ms", $stext)), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#Flight\s+(\w{2})\d+#", $stext);

                // Operator
                $itsegment['Operator'] = $this->re("#Operated By:\s+(.+)#", $stext);

                // Aircraft
                $itsegment['Aircraft'] = $this->re("#Equipment:\s+(.+)#", $stext);

                // TraveledMiles
                $itsegment['TraveledMiles'] = $this->re("#Distance:\s+(.+)#", $stext);

                // AwardMiles
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = array_filter([$this->re("#Seat:\s+(\d+\w)#", $stext)]);

                // Duration
                $itsegment['Duration'] = $this->re("#Duration:\s+(.*?)(?:\s+Non-stop|\s+with\s+|\n)#", $stext);

                // Meal
                $itsegment['Meal'] = $this->re("#Meal:\s+(.+)#", $stext);

                // Smoking
                // Stops
                if (!$itsegment['Stops'] = $this->re("#Duration:\s+.*\s+(Non-stop)#", $stext)) {
                    if (!$itsegment['Stops'] = $this->re("#Duration:\s+.*\s+with\s+(.+)#", $stext)) {
                        $itsegment['Stops'] = $this->re("#Stop\(s\):\s+(.+)#", $stext);
                    }
                }

                if (isset($mainInfo[$itsegment['AirlineName'] . ' ' . $itsegment['FlightNumber']])) {
                    $info = $mainInfo[$itsegment['AirlineName'] . ' ' . $itsegment['FlightNumber']];

                    // DepCode
                    if (isset($table[1])) {
                        $itsegment['DepCode'] = $this->re("#([A-Z]{3})-[A-Z]{3}#", $table[1]);
                    }

                    // ArrCode
                    if (isset($table[1])) {
                        $itsegment['ArrCode'] = $this->re("#[A-Z]{3}-([A-Z]{3})#", $table[1]);
                    }

                    // Cabin
                    if (isset($table[5])) {
                        $itsegment['Cabin'] = $this->re("#(.*?) / \w#", $table[5]);
                    }

                    // BookingClass
                    if (isset($table[5])) {
                        $itsegment['BookingClass'] = $this->re("#.*? / (\w)#", $table[5]);
                    }
                }

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
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
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
        $this->text = $parser->getPlainBody();

        if (empty($this->text)) {
            $this->text = $this->htmlToText($parser->getHTMLBody());
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => 'TravelItineraryPlain' . ucfirst($this->lang),
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

    protected function htmlToText($string)
    {
        $NBSP = chr(194) . chr(160);
        $string = str_replace($NBSP, ' ', html_entity_decode($string));
        $string = preg_replace('/<[^>]+>/', "\n", $string);

        return $string;
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
        // $this->http->Log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^([^\s\d]+)\s+(\d+),\s+(\d+:\d+)\s+([ap])\.m\.$#", //August 23, 7:50 p.m.
        ];
        $out = [
            "$2 $1 $year, $3 $4m",
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
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
}
