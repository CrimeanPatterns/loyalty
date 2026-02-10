<?php

namespace AwardWallet\Engine\fcmtravel\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-11913258.eml, fcmtravel/it-11913307.eml, fcmtravel/it-11913338.eml";
    public $reFrom = "@de.fcm.travel";
    public $reSubject = [
        "en"=> "Confirmation",
    ];
    public $reBody = 'FCM Travel Solutions';
    public $reBody2 = [
        "de"=> "Wir freuen uns, Ihnen folgende Buchung bestätigen zu können:",
    ];

    public $pdfPattern = "Update__Confirmation_[A-Z\d_]+_\d+\.\d+\.\d{4}.pdf";

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        preg_match_all("#(Flug \w{2} \d+.*?\nVon\s+.*?Stops.*?)\n\n#ms", $text, $segments);

        foreach ($segments[1] as $stext) {
            $table = $this->re("#\n(Von.+)#ms", $stext);
            $table = $this->splitCols($table, [mb_strpos($table, 'Von', 0, 'UTF-8'), mb_strpos($table, 'Nach', 0, 'UTF-8')]);

            if (count(array_filter(array_map('trim', $table))) != 2) {
                $this->logger->info("incorrect parse table");

                return;
            }

            if (!$rl = $this->re("#Airline Code:\s+\w{2}/(.+)#", $table[1])) {
                if (!$rl = $this->re("#GDS Code\s+([A-Z\d]{6}\s)#", $table[1])) {
                    $this->logger->info("RL not matched");

                    return;
                }
            }
            $airs[$rl][] = [$table, $stext];
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            preg_match_all("#\d+\. (.+)#", $this->re("#(?:Namen der Reisenden:|Name des Reisenden:)(.*?)Besteller:#ms", $text), $m);
            $it['Passengers'] = $m[1];

            // TicketNumbers
            $table = $this->splitCols($this->re("#\n([^\n]+Ticketnummer.*?)Bemerkungen zur Reservierung#ms", $text));

            foreach ($table as $col) {
                if (strpos($col, 'Ticketnummer') !== false) {
                    $it['TicketNumbers'] = array_filter(explode("\n", $col), function ($s) { return strpos($s, 'Ticketnummer') === false && !empty($s); });
                }
            }
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

            foreach ($segments as $data) {
                $table = $data[0];
                $stext = $data[1];
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#Flug \w{2} (\d+)#", $stext);

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->re("#Von\s+(.+)#", $table[0]);

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#Terminal (.+)#", $table[0]);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate(str_replace("\n", ", ", $this->re("#Abflug\s+(.*?\n.*?)(?:\s{2,}|\n)#", $table[0]))));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->re("#Nach\s+(.+)#", $table[1]);

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#Terminal (.+)#", $table[1]);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(str_replace("\n", ", ", $this->re("#Ankunft\s+(.*?\n.*?)(?:\s{2,}|\n)#", $table[1]))));

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#Flug (\w{2}) \d+#", $stext);

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->re("#Flugzeugtyp\s+(.+)#", $table[1]);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#Klasse\s+[A-Z] (.+)#", $table[1]);

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#Klasse\s+([A-Z]) #", $table[1]);

                // PendingUpgradeTo
                // Seats
                preg_match_all("# (\d{2}[A-Z]) #", $this->re("#Sitzplatz(.*?)Stops#ms", $table[0]), $m);
                $itsegment['Seats'] = $m[1];

                // Duration
                $itsegment['Duration'] = $this->re("#Dauer\s+(.+)#", $table[0]);

                // Meal
                // Smoking
                // Stops
                $itsegment['Stops'] = $this->re("#Stops\s+(.+)#", $table[0]);

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
            "#^(\d+.\d+.\d{4}, \d+:\d+) Uhr$#", //02.11.2015, 12:40 Uhr
        ];
        $out = [
            "$1",
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
