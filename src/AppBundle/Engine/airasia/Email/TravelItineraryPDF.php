<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Engine\MonthTranslate;

class TravelItineraryPDF extends \TAccountChecker
{
    public $mailFiles = "airasia/it-10736932.eml, airasia/it-29557418.eml, airasia/it-32571364.eml";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $reFrom = "@airasia.com";
    private $reSubject = [
        "en"=> "Travel Itinerary",
    ];
    private $reBody = 'AirAsia';
    private $reBody2 = [
        "en"=> "Travel Itinerary",
    ];
    private $pdfPattern = ".*\.pdf";
    private $text;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false && strpos($headers["subject"], $this->reBody) === false) {
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

        if (stripos($text, $this->reBody) === false && strpos($parser->getSubject(), $this->reBody) === false) {
            if (stripos($text, 'All times shown are local') !== false || stripos($text, 'All guests (except infants) are allowed to carry on board 2 pieces of cabin baggage') !== false) {
                $flight = stristr($text, 'FLIGHT DETAILS');

                if (empty($flight) || !preg_match("#FLIGHT DETAILS\n\s*Flight 1:#i", $flight)) {
                    return false;
                }
            } else {
                return false;
            }
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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }

        foreach ($pdfs as $pdf) {
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
        }

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

    private function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $text = preg_replace("#\n\s*http://webitin.airasia.com/Itinerary.+\d+/\d+\s*(?:\n|$)#", "\n", $text);

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Booking number:[ ]+([A-Z\d]{5,})#", $text);

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->re("#Booking number:?\s*\n(?:.*\n)?.{50,} ([A-Z\d]{5,})\s+#", $text);
        }

        // TripNumber
        // Passengers
        $seats = [];
        $passengers = $this->re("#GUEST DETAILS\n(.*?)(?:\n\n\n|PAYMENT DETAILS)#ms", $text);

        if (empty($passengers)) {
            $passengers = strstr($text, 'GUEST DETAILS');
        }

        if (empty($passengers)) {
            $passengers = strstr($text, 'ADD­ONS');
        }

        if (empty($passengers)) {
            $passengers = strstr($text, 'ADD-ONS');
        }

        if (empty($passengers)) {
            $passengers = "GUEST DETAILS\n" . $this->re('/(Flight \d{1,2}:.+\n[ ]+[Msr]+[ ]+[A-Z ]+[\s\S]+)/', $text);
        }

        $arrSeats = $this->split('/^(Flight \d{1,2}\: .+?\s{2,}.+)/m', $passengers);

        if (empty($arrSeats)) {
            $arrSeats = $this->split("#^(.+?\s+to\s+.+)#m", $passengers);
        }

        $passengers = preg_replace("#^\s*(?:GUEST DETAILS|ADD­ONS)\s*\n#", "\n", $passengers);
        $passengers = preg_replace("#\n\s*Flight \d+:.+#", "\n", $passengers);
        $passengers = $this->splitCols($passengers, $this->colsPos($passengers, 10));

        foreach ($arrSeats as $v) {
            if (preg_match("#^(.+?)\s+to\s+(.+)#", $v, $m)) {
                if (preg_match_all("#Seat\s*\-\s*\b(\d+\w)\b#", $v, $s)) {
                    $seats[trim($m[1]) . ' to ' . trim($m[2])] = $s[1];
                }
            } elseif (preg_match('/^Flight \d{1,2}\: (.+?)\s{2,}(.+)/', $v, $m)) {
                if (preg_match_all("#Standard seat\s*\-\s*\b(\d+\w)\b#", $v, $s)) {
                    $seats[trim($m[1]) . ' to ' . trim($m[2])] = $s[1];
                }
            }
        }

        if (0 === count($seats) && preg_match_all('/seat\s+-\s+([A-Z\d]{1,5})/i', end($passengers), $m)) {
            $seats = $m[1];
        }

        if ((count($passengers) != 2) && (count($passengers) != 3) && (count($passengers) != 4) && (count($passengers) != 5)) {
            $this->logger->info("Incorrect parse passengers table");

            return;
        }

        $it['Passengers'] = array_values(
            array_unique(
                array_map(function ($s) { return $this->re("#^\s*(?:Ms |Mr |Child )?(.+)#", str_replace("\n", " ", $s)); },
                    array_filter(
                        array_map('trim', explode("\n\n", $passengers[0])),
                        function ($s) {
                            return !empty($s)
                                && stripos($s, 'Flight') === false && stripos($s, ' to ') === false && stripos($s, 'All guests') === false && stripos($s, 'ADD-ONS') === false && stripos($s, 'Total') === false
                                && stripos($s, 'Balance') === false && stripos($s, 'Airport') === false && stripos($s, 'AirAsia') === false
                                ;
                        }
                            )
                )
            )
        );

        // TicketNumbers
        // AccountNumbers
        // Cancelled

        // TotalCharge
        // Currency
        $total = $this->re("#Total amount[ ]+(.+)#", $text);

        if (!empty($total) && preg_match("#^\s*(\d[\d\.]*)\s+([A-Z]{3})\s*$#", $total, $m)) {
            $it['TotalCharge'] = $m[1];
            $it['Currency'] = $m[2];
        }

        // BaseFare
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#Booking date:\s+(.+)#", $text)));

        // NoItineraries
        // TripCategory

        $segments = $this->split("#(?:^|\n)([^\n\S]*(?:Flight \d+:|Transit))#", $this->re("#FLIGHT DETAILS\n(.*?)(?:GUEST DETAILS|ADD­ONS|ADD-ONS)#msi", $text));

        if (empty($segments)) {
            $segments = $this->split("#(?:^|\n)([^\n\S]*(?:Flight \d+:|Transit))#", $this->re("#FLIGHT DETAILS\n(.*?)(?:GUEST DETAILS|ADD­ONS|ADD-ONS|\n\n\n)#msi", $text));
        }

        foreach ($segments as $stext) {
            $table = $this->re("#(?:Flight \d+:|Transit)[^\n]+\n\n*(.*?)(?:\n\n|$)#s", $stext);

            $table = $this->splitCols($table, $this->colsPos($table));

            if (count($table) != 3) {
                $this->logger->info("Incorrect parse segment table");

                return;
            }
            $dep = array_values(array_filter(explode("\n", $table[1])));
            $arr = array_values(array_filter(explode("\n", $table[2])));

            if (count($dep) < 4 || count($arr) < 4) {
                $this->logger->info("Incorrect dep/arr column");

                return;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#\n(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\n#", $table[0]);

            // DepCode
            $itsegment['DepCode'] = $this->re("#^.*? \(([A-Z]{3})\)$#", $dep[0]);

            // DepName
            $itsegment['DepName'] = $this->re("#^(.*?) \([A-Z]{3}\)$#", $dep[0]);

            // DepartureTerminal
            $airport = '';

            for ($i = 1; $i < count($dep) - 2; $i++) {
                $airport .= $dep[$i] . " ";
            }
            $depTerm = trim($this->re("#\((.*?)\)\s*$#", $airport));

            if (strlen($depTerm) === 2 && strpos($depTerm, 'T') === 0) {
                $itsegment['DepartureTerminal'] = substr($depTerm, 1, 1);
            } elseif (!empty($depTerm)) {
                $itsegment['DepartureTerminal'] = $depTerm;
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($dep[count($arr) - 2] . ', ' . $dep[count($arr) - 1]));

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#^.*? \(([A-Z]{3})\)$#", $arr[0]);

            // ArrName
            $itsegment['ArrName'] = $this->re("#^(.*?) \([A-Z]{3}\)$#", $arr[0]);

            // ArrivalTerminal
            $airport = '';

            for ($i = 1; $i < count($arr) - 2; $i++) {
                $airport .= $arr[$i] . " ";
            }
            $arrTerm = trim($this->re("#\((.*?)\)\s*$#", $airport));

            if (strlen($arrTerm) === 2 && strpos($arrTerm, 'T') === 0) {
                $itsegment['ArrivalTerminal'] = substr($arrTerm, 1, 1);
            } elseif (!empty($arrTerm)) {
                $itsegment['ArrivalTerminal'] = $arrTerm;
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($arr[count($arr) - 2] . ', ' . $arr[count($arr) - 1]));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#\n([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+\n#", $table[0]);

            if (count($seats) > 0 && isset($seats[trim($itsegment['DepName']) . ' to ' . trim($itsegment['ArrName'])])) {
                $itsegment['Seats'] = $seats[trim($itsegment['DepName']) . ' to ' . trim($itsegment['ArrName'])];
            } elseif (0 < count($seats)) {
                $itsegment['Seats'] = $seats;
            }
            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
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
        //		$year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4}), \d{2}\d{2} hrs \((\d+:\d+[AP]M)\)$#", //Sun 01 Apr 2018, 1940 hrs (7:40PM)
        ];
        $out = [
            "$1, $2",
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
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
