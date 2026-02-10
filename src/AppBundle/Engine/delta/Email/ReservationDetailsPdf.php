<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationDetailsPdf extends \TAccountChecker
{
    public $mailFiles = "delta/it-7304037.eml, delta/it-7304198.eml, delta/it-7304322.eml";

    public $reFrom = "delta";
    public $reSubject = [
        "en"=> "Names and flights recap",
    ];
    public $reBody = 'Delta';
    public $reBody2 = [
        "en"=> "DETAILS OF YOUR RESERVATION",
    ];
    public $pdfPattern = "recapTransportNoms_\d+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $refs = $this->split("#(Group reference\s+\d+\s*:\s*\w+)#", $text);

        foreach ($refs as $reftext) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $this->re("#Group reference\s+\d+\s*:\s*(\w+)#", $reftext);

            // TripNumber
            // Passengers
            // $it['Passengers'] = array_filter([$this->re("#".$this->opt($this->t("Passenger"))."\s+".$this->opt($this->t("Document"))."\n(\S+)#", $text)]);

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            if (count($refs) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->re("#Total amount including ticketing fees\s+([\d\s\,\.]+)\s+[A-Z]{3}#", $text));

                // BaseFare
                // Currency
                $it['Currency'] = $this->re("#Total amount including ticketing fees\s+[\d\s\,\.]+\s+([A-Z]{3})#", $text);
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory
            $flights = "\n" . mb_substr($reftext,
                $sp = strpos($reftext, "Arrival time") + strlen("Arrival time") - 1,
                mb_strpos($reftext, "Passengers") - $sp, 'UTF-8');
            $segments = $this->split("#\n(\s*\w{2}\s+\d+\s+)#", $flights);

            foreach ($segments as $stext) {
                $stext = preg_replace("#^\s*\n#", "", $stext);
                $table = $this->SplitCols($stext);

                if (count($table) < 7) {
                    $this->http->log("incorrect table parse");

                    return;
                }

                $date = strtotime($this->normalizeDate(trim($table[2])));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\w{2}\s+(\d+)#", $table[0]);

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = implode(" ", array_filter(explode("\n", $table[3])));

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate(trim($table[5])), $date);

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = implode(" ", array_filter(explode("\n", $table[4])));

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate(trim($table[6])), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#(\w{2})\s+\d+#", $table[0]);

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                $itsegment['BookingClass'] = trim($table[1]);

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
            'emailType'  => 'ReservationDetailsPdf' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => [
                    "Amount"   => $this->amount($this->re("#Total amount including ticketing fees\s+([\d\s\,\.]+)\s+[A-Z]{3}#", $this->text)),
                    "Currency" => $this->re("#Total amount including ticketing fees\s+[\d\s\,\.]+\s+([A-Z]{3})#", $this->text),
                ],
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
