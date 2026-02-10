<?php

namespace AwardWallet\Engine\fcmtravel\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-7595269.eml, fcmtravel/it-7601172.eml, fcmtravel/it-7621590.eml, fcmtravel/it-7696981.eml, fcmtravel/it-7722290.eml";

    public $reFrom = "@ke.fcm.travel";
    public $reSubject = [
        "en"=> "E TICKET",
    ];
    public $reBody = '@ke.fcm.travel';
    public $reBody2 = [
        "en"=> "Your Travel Itinerary",
    ];
    public $pdfPattern = "(E\s*)?TICKET.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $flights = mb_substr($text,
            $sp = strpos($text, "Your Travel Itinerary") + strlen("Your Travel Itinerary") - 1,
            mb_strpos($text, "Kind regards") - $sp, 'UTF-8');
        $segments = $this->split("#([^\d\s]+,\s+\d+\s+[^\d\s]+\s+\d{4}\n\s*Flight)#", $flights);

        if (count($segments) != substr_count($text, "Departs")) {
            $this->http->log("incorrect segments count");

            return;
        }
        $airs = [];

        foreach ($segments as $stext) {
            if (!$rl = $this->re("#Confirmation Number For\s+.*?\s{2,}(\w+)\n#", $stext)) {
                $this->http->log("RL not matched");

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
            $it['TripNumber'] = $this->re("#Agency Reference Number:\s+(.+)#", $text);

            if (preg_match_all("#\n\s*\*\s*(?<Passenger>.*?)\s{2,}(?<Ticket>\d+)\(Electronic\)#", $text, $m)) {
                // Passengers
                $it['Passengers'] = array_unique($m['Passenger']);

                // TicketNumbers
                $it['TicketNumbers'] = array_unique($m['Ticket']);
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
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#Date:\s+(.+)#", $text)));

            // NoItineraries
            // TripCategory

            foreach ($segments as $stext) {
                $date = strtotime($this->normalizeDate($this->re("#(\S.+)#", $stext)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#Flight\s+\w{2}(\d+)#", $stext);

                // DepCode
                // DepName
                // DepartureTerminal
                // DepDate
                // ArrCode
                // ArrName
                // ArrivalTerminal
                // ArrDate
                foreach (['Dep'=>'Departs', 'Arr'=>'Arrives'] as $pref=>$str) {
                    if (preg_match("#{$str}\s+(?<Time>.*?)\s{2,}(?<Name>.*?)\s{2,}(?<Code>[A-Z]{3})(?:[^\S\n]{2,}(?<Terminal>.*?))?\n#", $stext, $m)) {
                        $itsegment[$pref . 'Code'] = $m['Code'];
                        $itsegment[$pref . 'Name'] = $m['Name'];
                        $itsegment[$pref . 'Date'] = strtotime($m['Time'], $date);

                        if (isset($m['Terminal'])) {
                            $itsegment[($pref == 'Dep' ? 'Departure' : 'Arrival') . 'Terminal'] = $m['Terminal'];
                        }
                    }
                }

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#Flight\s+(\w{2})\d+#", $stext);

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->re("#Equipment\s+(.*?)\s{2,}#", $stext);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#Class\s+[A-Z]\s+-\s+(.*?)\s{2,}#", $stext);

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#Class\s+([A-Z])\s+-\s+.*?\s{2,}#", $stext);

                // PendingUpgradeTo
                // Seats
                // echo $stext;
                // die();
                // * MBUGUA/DALIAHMUTHONI                           7069560499434(Electronic)                        27A Window
                if (preg_match_all("#\n\s*\*\s*(?<Passenger>.*?)\s{2,}(?<Ticket>\d+)\(Electronic\)\s+(?<Seat>\d+\w)\s#", $stext, $m)) {
                    $itsegment['Seats'] = implode(", ", $m['Seat']);
                }
                // Duration
                $itsegment['Duration'] = $this->re("#Flying Time\s+(.*?)\s{2,}#", $stext);

                // Meal
                $itsegment['Meal'] = $this->re("#Meal\s+(.*?)\s{2,}#", $stext);

                // Smoking
                // Stops
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
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#", //Thursday, 20 July 2017
        ];
        $out = [
            "$1 $2 $3",
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
