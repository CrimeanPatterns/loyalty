<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Engine\MonthTranslate;

class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "@airasia.com";
    public $reSubject = [
        "en"=> "Itinerary",
    ];
    public $reBody = 'AirAsia';
    public $reBody2 = [
        "en"=> "FLIGHT DETAILS",
    ];

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
        $it['RecordLocator'] = $this->re("#" . $this->opt($this->t("Booking number:")) . "\s+([A-Z\d]+)\s#ms", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = [];
        $seats = [];

        $ptext = substr($text, $s = strpos($text, "GUEST DETAILS") + strlen("GUEST DETAILS"), strpos($text, "PAYMENT DETAILS") - $s);
        $pseg = $this->split("#(Flight \d+:.*?\n)#", $ptext);

        foreach ($pseg as $pstext) {
            $cols = $this->splitCols(preg_replace("#^\s*\n#", "", substr($pstext, strpos($pstext, "\n"))));
            $it['Passengers'][] = trim(str_replace("\n", " ", $cols[0]));

            if (preg_match_all("#Seat\s+-\s+(\d+\w)#", $pstext, $m)) {
                $seats[$this->re("#Flight\s+(\d+):#", $pstext)] = $m[1];
            }
        }

        $it['Passengers'] = array_unique($it['Passengers']);

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->re("#Total amount\s+([\d\.]+)\s+[A-Z]{3}#", $text);

        // BaseFare
        // Currency
        $it['Currency'] = $this->re("#Total amount\s+[\d\.]+\s+([A-Z]{3})#", $text);

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#" . $this->opt($this->t("Booking date:")) . "\s+(.+)#", $text)));

        // NoItineraries
        // TripCategory

        $flights = substr($text, $s = strpos($text, "FLIGHT DETAILS") + strlen("FLIGHT DETAILS"), strpos($text, "GUEST DETAILS") - $s);
        $segments = $this->split("#(Flight \d+:.*?\n)#", $flights);

        foreach ($segments as $stext) {
            $ttext = preg_replace("#^\s*\n#", "", substr($stext, strpos($stext, "\n")));
            $pos = $this->TableHeadPos(explode("\n", $ttext)[0]);
            $pos = array_merge([0], $pos);
            $table = $this->splitCols(preg_replace("#^\s*\n#", "", substr($stext, strpos($stext, "\n"))), $pos);

            if (count($table) < 3) {
                $this->http->log("incorrect table parse");

                return;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#\w{2}\s+(\d+)#", $table[0]);

            // DepCode
            $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $table[1]);

            // DepName
            $itsegment['DepName'] = $this->re("#\([A-Z]{3}\)\n(.*?)\n#", $table[1]);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(trim(str_replace("\n", " ", $this->re("#\([A-Z]{3}\)\n.*?\n(.*?\n.*?)\n#ms", $table[1])))));

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#ms", $table[2]);

            // ArrName
            $itsegment['ArrName'] = $this->re("#\([A-Z]{3}\)\n(.*?)\n#", $table[2]);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate(trim(str_replace("\n", " ", $this->re("#\([A-Z]{3}\)\n.*?\n(.*?\n.*?)\n#ms", $table[2])))));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#(\w{2})\s+\d+#", $table[0]);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            if (isset($seats[$this->re("#Flight\s+(\d+):#", $stext)])) {
                $itsegment['Seats'] = implode(", ", $seats[$this->re("#Flight\s+(\d+):#", $stext)]);
            }

            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
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
        $pdfs = $parser->searchAttachmentByName('\([A-Z]+\)\s+[A-Z\d]+\s+-\s+[A-Z\s]+\.pdf|[A-Z\d]+_[A-Z\d]+.pdf');

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

        $pdfs = $parser->searchAttachmentByName('\([A-Z]+\)\s+[A-Z\d]+\s+-\s+[A-Z\s]+\.pdf|[A-Z\d]+_[A-Z\d]+.pdf');

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
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
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
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s+(\d{1,2})(\d{2})\s+hrs\s+\(\d+:\d+[AP]M\)$#", //Thu 25 May 2017 1715 hrs (5:15PM)
        ];
        $out = [
            "$1 $2 $3, $4:$5",
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
}
