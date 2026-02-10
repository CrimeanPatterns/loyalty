<?php

namespace AwardWallet\Engine\atpi\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class NewETicketConfirmationPlain extends \TAccountChecker
{
    public $mailFiles = "atpi/it-12009347.eml, atpi/it-12009419.eml, atpi/it-12009430.eml, atpi/it-12009478.eml, atpi/it-12009482.eml";
    public $reFrom = "@atpi.com";
    public $reSubject = [
        "en"=> "New E-ticket confirmation",
    ];
    public $reBody = 'www.atpi.com';
    public $reBody2 = [
        "en"  => "FOR PASSENGER(S):",
        'en2' => 'Please see flight details',
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $text;

    private $date;

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
        $body = $parser->getPlainBody();

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
        $this->date = strtotime($parser->getDate());

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->text = $parser->getPlainBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parsePlain($itineraries);

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

    private function parsePlain(&$itineraries)
    {
        $text = $this->text;

        $rls = [];
        preg_match_all("#(\w+)\s*/\s*(\w+)#", $this->re("#(?:AIRLINE REF:|Airline Reference)\s+(.+)#", $text), $ms, PREG_SET_ORDER);

        foreach ($ms as $m) {
            if (2 === strlen($m[1])) {
                $rls[$m[1]] = $m[2];
            } else {
                $rls[$m[2]] = $m[1];
            }
        }

        if (!preg_match("#\n(\s*Flight\s+[^\n]+)\s*\n\s*-+(\s*\n.*?)\s*\n\s*\n#ms", $text, $m) && !preg_match('/\n(\s*Flight\s+[^\n]+)\s*\n\s*(\s*.+?)\n/msi', $text, $m)) {
            $this->logger->info("table not matched");

            return;
        }

        $pos = $this->rowColsPos(trim($m[1]), "\s+");
        $heads = $this->splitCols($this->re("#(.*?)(?:\n|$)#", $m[1]), $pos);

        $flights = $m[2];

        $segments = $this->split("#\n([A-Z\d]{2}\d+)#", $flights);
        $airs = [];

        foreach ($segments as $stext) {
            if (!$airline = $this->re("#^([A-Z\d]{2})\s*\d+\s#", $stext)) {
                $this->logger->info("airline not matched");

                return;
            }

            if (!isset($rls[$airline])) {
                $this->logger->info("RL not found: {$airline}");

                return;
            }
            $airs[$rls[$airline]][] = $stext;
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = array_filter(explode("\n", $this->re("#FOR PASSENGER\(S\):\s*\n\s*(.*?)\s*\n\s*-+#ms", $text)));

            if (empty($it['Passengers'])) {
                preg_match_all('/Traveller\s+(.+[MRSI]{2,4})\n/', $text, $m);
                $it['Passengers'] = $m[1];
            }

            $it['TicketNumbers'] = [];

            // AccountNumbers
            preg_match_all("#FREQUENT FLYER NUMBER:\s+\w{2}\s+(.+)#", $text, $m);
            $it['AccountNumbers'] = array_unique($m[1]);

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
                $tableValue = $this->splitCols($this->re("#(.*?)(?:\n|$)#", $stext), $pos);

                if (count($tableValue) < 8 || count($tableValue) !== count($heads)) {
                    $this->logger->info("incorrect parse table");

                    return;
                }
                $table = array_combine($heads, $tableValue);

                $date = $this->normalizeDate($table['Date']);

                $itsegment = [];

                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#^\w{2}\s*(\d+)$#", $table['Flight']);

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $table['Org'];

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $table['Term'];

                // DepDate
                $itsegment['DepDate'] = strtotime($table['Dep'], $date);

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $table['Dest'];

                // ArrivalTerminal

                // ArrDate
                $itsegment['ArrDate'] = strtotime($table['Arr'], $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#^(\w{2})\s*\d+$#", $table['Flight']);

                // Operator
                $itsegment['Operator'] = $this->re("#OPERATED BY (.+)#", $stext);

                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                if (isset($table['Class'])) {
                    $itsegment['Cabin'] = $table['Class'];
                }

                // BookingClass
                // PendingUpgradeTo
                // Seats
                if (preg_match_all("#" . $table['Flight'] . "\s+(\d{1,2}[A-Z])\s+\([A-Z]{3}-[A-Z]{3}\)#", $text, $m)) {
                    $itsegment['Seats'] = $m[1];
                }

                //				if( empty($itsegment['Seats']) && preg_match('/^\s*\d{1,3}[A-Z]\b\s*$/', $table[9]) )
                //				    $itsegment['Seats'][] = trim($table[9]);

                // Duration
                // Meal
                // Smoking
                // Stops

                // TicketNumbers
                preg_match_all("#ETICKET NUMBER:\s+(\d+)\s+" . $itsegment['AirlineName'] . "#", $text, $m);
                $it['TicketNumbers'] += $m[1];

                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr)
    {
        // $this->http->log($instr);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\s\d]+)\((?<week>[^\s\d]+)\)$#", //24APR(SU)
            "#^(\d+)([^\W\d]+)$#", //24APR
        ];
        $out = [
            "$1 $2 $year",
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                // $this->logger->info($m['week']);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        return strtotime($str);
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
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
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

    private function rowColsPos($row, $splitter = "\s{2,}")
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#" . $splitter . "#", "|", $row))));
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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
