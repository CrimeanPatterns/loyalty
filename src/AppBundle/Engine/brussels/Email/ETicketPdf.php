<?php

namespace AwardWallet\Engine\brussels\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "brussels/it-12121069.eml, brussels/it-7281325.eml";

    public $reFrom = "noreply@brusselsairlines.com";
    public $reSubject = [
        "fr" => "Brussels Airlines enregistrement en ligne",
    ];
    public $reBody = 'brusselsairlines.com';
    public $reBody2 = [
        "en" => "Departure",
    ];
    public $pdfPattern = ".+\.pdf";

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
        if (preg_match_all("#" . $this->opt($this->t("PASSENGER NAME:")) . "[ ]+(.+?)(?: {2,}|\n)#", $text, $m)) {
            $it['Passengers'] = array_unique($m[1]);
        }

        // TicketNumbers
        if (preg_match_all("#" . $this->opt($this->t("E-TICKET NUMBER:")) . "[ ]+(\d+)#ms", $text, $m)) {
            $it['TicketNumbers'] = array_unique($m[1]);
        }

        // AccountNumbers
        if (preg_match_all("#" . $this->opt($this->t("Frequent Flyer No.:")) . "[ ]+([A-Z\d\-]+)#ms", $text, $m)) {
            $it['AccountNumbers'] = array_unique($m[1]);
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
        preg_match_all("#\n\s*From.*?Seat\s*\n[^\n]+#ms", $text, $segments);

        foreach ($segments[0] as $stext) {
            $tables = $this->split("#\n\s*(From|Date|Gate)#", $stext);

            foreach ($tables as &$table) {
                if (preg_match("#((.*?)\n.+)#ms", $table, $m)) {
                    $pos = $this->TableHeadPos($m[2]);
                    $table = $this->splitCols($m[1], $pos);
                } else {
                    $this->logger->info("incorrect parse table rows");

                    return;
                }
            }

            if (count($tables) < 3) {
                $this->logger->info("incorrect tables count");

                return;
            }

            if (count($tables[0]) < 3 || count($tables[1]) < 3 || count($tables[2]) < 3) {
                $this->logger->info("incorrect columns count");

                return;
            }

            $date = strtotime($this->normalizeDate(trim($this->re("/^.+\n\s*(.+)/", $tables[1][0]))));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#\n\w{2}\s+(\d+)#", $tables[0][2]);

            // DepCode
            $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $tables[0][0]);

            // DepName
            $itsegment['DepName'] = $this->re("#\n(.*?)\s*\([A-Z]{3}\)#", $tables[0][0]);

            // DepartureTerminal
            $terminal = $this->re('/Terminal\s+(\S.*)/', $tables[1][2]);

            if (!empty($terminal)) {
                $itsegment['DepartureTerminal'] = $terminal;
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeTime(trim($this->re("/^.+\n\s*(.+)/", $tables[1][1]))), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $tables[0][1]);

            // ArrName
            $itsegment['ArrName'] = $this->re("#\n(.*?)\s*\([A-Z]{3}\)#", $tables[0][1]);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#(\w{2})\s+\d+#", $tables[0][2]);

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $cabin = $this->re('/Class of Travel\s+(\S.*)/', $tables[1][3] ?? '');

            if (empty($cabin)) {
                $cabin = $this->re('/Class of Travel\s+(\S.*)/', $tables[1][2]);
            }
            $itsegment['Cabin'] = $cabin;

            if ($itsegment['Cabin'] == 'CHECK') {
                unset($itsegment['Cabin']);
            }

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $seat = $this->re('/Seat\s+(\d{1,3}[A-Z])\b/', $tables[2][2]);

            if (empty($seat)) {
                $seat = $this->re('/Seat\s+(\d{1,3}[A-Z])\b/', $tables[2][3]);
            }
            $itsegment['Seats'][] = $seat;

            // Duration
            // Meal
            // Smoking
            // Stops

            $finded = false;

            if (empty($it['TripSegments'])) {
                $it['TripSegments'][] = $itsegment;

                continue;
            }

            foreach ($it['TripSegments'] as $key => $value) {
                if (isset($itsegment['AirlineName']) && $itsegment['AirlineName'] == $value['AirlineName']
                        && isset($itsegment['FlightNumber']) && $itsegment['FlightNumber'] == $value['FlightNumber']
                        && isset($itsegment['DepDate']) && $itsegment['DepDate'] == $value['DepDate']) {
                    $it['TripSegments'][$key]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $itsegment['Seats'])));
                    $finded = true;
                }
            }

            if ($finded == false) {
                $it['TripSegments'][] = $itsegment;
            }
        }
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

        if (stripos($text, $this->reBody) === false && stripos($text, 'brusseslairlines.com') === false) {
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

        $classParts = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($classParts) . ucfirst($this->lang),
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
            "#^(\d+)([^\d\s]+)(\d{2})$#", //04MAR17
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

    private function normalizeTime($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d{1,2})(\d{2})$#", // 1020
        ];
        $out = [
            "$1:$2",
        ];
        $str = preg_replace($in, $out, $str);

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

        return implode("|", array_map('preg_quote', $field));
    }
}
