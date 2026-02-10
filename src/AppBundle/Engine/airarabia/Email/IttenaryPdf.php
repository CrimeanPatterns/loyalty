<?php

namespace AwardWallet\Engine\airarabia\Email;

use AwardWallet\Engine\MonthTranslate;

class IttenaryPdf extends \TAccountChecker
{
    public $mailFiles = "airarabia/it-6812327.eml, airarabia/it-690756176.eml";

    public static $dictionary = [
        "en" => [],
        'it' => [
            'E TICKET NUMBER'           => 'NUMERO DI',
            'ANCILLARY DETAILS'         => 'ACCESSORIE DETTAGLI',
            'Segment'                   => 'Segmento',
            'Seat'                      => 'NOTTRANSLATED',
            'PAYMENT DETAILS'           => 'NOTTRANSLATED',
            'RESERVATION NUMBER'        => 'NUMERO DI PRENOTAZIONE',
            'Passenger Name'            => 'Nome(i) del(i) passeggero(i)',
            'TOTAL'                     => 'TOTAL',
            'ORIGIN / DESTINATION'      => 'ORIGINE / DESTINAZIONE',
            'LOCAL CALL CENTER DETAILS' => 'LOCAL CALL CENTER DETAILS',
            'Duration'                  => 'Duration',
        ],
    ];

    public $lang = "en";

    private $reFrom = "reservations@airarabia.com";
    private $reSubject = [
        "en"=> "Itinerary for the Reservation",
    ];
    private $reBody = 'airarabia.com';
    private $reBody2 = [
        "en" => "RESERVATION CONFIRMED",
        'it' => 'CONFERMA DELLA PRENOTAZIONE',
    ];
    private $pdfPattern = "(?:itinerary_email_v2__\d+|[A-Z\d ]+)\.pdf";
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

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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

        // tickets & codes
        $ticketstable = $this->splitCols(preg_replace("#^\s*\n#", "", substr($text, $s = strpos($text, $this->t("E TICKET NUMBER")) + strlen($this->t("E TICKET NUMBER")), strpos($text, $this->t("ANCILLARY DETAILS")) - $s)));

        if (count($ticketstable) < 3) {
            $this->logger->debug("parse tickets table is incorrect");

            return;
        }
        $crows = explode("\n", $ticketstable[0]);
        $flrows = explode("\n", $ticketstable[1]);
        $codes = [];

        if (count($crows) == count($flrows)) {
            foreach ($crows as $i=>$row) {
                if (preg_match("#^([A-Z]{3})/([A-Z]{3})$#", $row, $c) && preg_match("#^\w{2}(\d+)$#", $flrows[$i], $f)) {
                    $codes[$f[1]] = $c;
                }
            }
        }

        // seats
        preg_match_all("#\s\w{2}(\d+)\s+(\d{1,2}\w)#", $this->re("#{$this->t('Segment')}\s+{$this->t('Seat')}(.*?){$this->t('PAYMENT DETAILS')}#ms", $text), $m, PREG_SET_ORDER);
        $seats = [];

        foreach ($m as $srow) {
            $seats[$srow[1]][] = $srow[2];
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#{$this->t('RESERVATION NUMBER')}(?: \(PNR\))?\s+(\w+)#", $text);

        // TripNumber
        // Passengers
        $pax = addcslashes($this->t('Passenger Name'), '/()');
        $n = $this->re("#{$pax}[^\n]+\n+(.*?)\n +{$this->t('TOTAL')}#ms", $text);
        $n = preg_replace(['/\s*Passport No\. \-\s*/', '/\s+(?:Data Di Nascita|Date Of Birth)\s+.+/'], ['   ', '   '], $n);
        $n = preg_replace('/\n *PASSENGER CONTACT DETAILS\s+[\s\S]+/', '', $n);
        $n = preg_replace('/\n *AGENT DETAILS\s+[\s\S]+/', '', $n);
        $n = preg_replace('/^( {20}.*\n)+/', '', $n);

        if (preg_match('/([A-Z ]+)([ ]*\n[ ]+)/', $n, $m)) {
            // too general condition
            // $n = str_replace($m[1] . $m[2], $m[1] . '   ', $n);
        }

        $passtable = $this->splitCols($n);

        $otherType = false;

        if (count($passtable) < 5) {
            $this->http->log("parse passengers table is incorrect");
            $otherType = true;
        }

        if (!$otherType) {
            $it['Passengers'] = array_filter(explode("\n", $passtable[0]));
        } elseif ($otherType && 1 === count($passtable)) {
            $it['Passengers'] = array_filter(array_map(function ($p) { return trim(preg_replace('/Passport No\s*\.\s*\-\s*/', '', $p)); }, explode("\n", $passtable[0])));
        } else {
            $it['Passengers'] = array_map(function ($p) { return trim(preg_replace('/Passport No\s*\.\s*\-\s*/', '', $p)); }, array_filter($passtable));
        }
        // Child. MSTR ZAIN SALEH
        $it['Passengers'] = preg_replace("/^\s*(Child\.|Baby\.|Ребенок |Enfant |Bébé )\s*/", '', $it['Passengers']);
        $it['Passengers'] = preg_replace("/^\s*((MS|MRS|MR|DR|MISS|MSTR) )/", '', $it['Passengers']);

        // TicketNumbers
        $it['TicketNumbers'] = array_unique(array_filter(array_map(function ($s) { return preg_match("#^(\d+)/\d#", $s, $m) ? $m[1] : null; }, explode("\n", $ticketstable[2]))));

        if (0 === count($it['TicketNumbers']) && isset($ticketstable[3])) {
            $it['TicketNumbers'] = array_values(array_unique(array_filter(array_map(function ($s) { return preg_match("#\b(\d+)/\d#", $s, $m) ? $m[1] : null; }, explode("\n", $ticketstable[3])))));
        }

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->re("#{$this->t('TOTAL')}\s+([\d\.\,]+)\s+[A-Z]{3}\n#", $text);

        // BaseFare
        // Currency
        $it['Currency'] = $this->re("#{$this->t('TOTAL')}\s+[\d\.\,]+\s+([A-Z]{3})\n#", $text);

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $origin = addcslashes($this->t('ORIGIN / DESTINATION'), '/');
        $local = $this->t('LOCAL CALL CENTER DETAILS');

        $flights = $this->re("#(?:{$origin}|TRAVEL SEGMENTS\s*)[^\n]+\n(.*?)(?:{$local}|{$pax})#ms", $text);

        $str = $this->re('/(.+Duration\s*\:\s*.+Aircraft\s*:\s*.+Transit\s*\:\s*.+Remarks.+)/i', $flights);
        $a[0] = 0;
        $a = array_merge($a, $this->rowColsPos($str));
        $last = end($a);

        $segments = $this->split("#(.*?(?:Duration|Aircraft)[^\n]+)#ms", $flights);
        //		$pos = [0, 45, 80, 110, 149, 175];
        if ('it' === $this->lang) {
            $a[] = $last + 13;
        } else {
            $a[] = $last + 26;
        }

        $pos = $a;

        foreach ($segments as $stext) {
            $stext = preg_replace(['/(\s+O\n\n)/', '/(Remarks: -)\s*.+/'], ['', '$1'], $stext);

            $table = $this->SplitCols(preg_replace("#^\s*\n#", "", $stext), $pos);

            if (count($table) < 6) {
                $this->http->log("incorrect table parse");

                return;
            }
            $names = array_merge([], array_filter(explode("\n", $table[1])));
            $dates = array_merge([], array_filter(array_map(function ($d) { return trim(str_ireplace('ARRIVAL', '', $d)); }, explode("\n", $table[2]))));

            if (count($names) < 2 || count($dates) < 2) {
                $this->http->log("incorrect rows count");

                return;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#(?:\n|^)\w{2}(\d+)\n#ms", $table[0]);

            // DepCode
            if (isset($codes[$itsegment['FlightNumber']])) {
                $itsegment['DepCode'] = $codes[$itsegment['FlightNumber']][1];
            } else {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // DepName
            $itsegment['DepName'] = $this->re("#(.*?)(\s+-\s+Terminal|\s+-\s+T\w+\s*$|$)#", $names[0]);

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->re("#\s+-\s+(?:Terminal\s+|T)(\w+)\s*$#", $names[0]);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($dates[0]));

            // ArrCode
            if (isset($codes[$itsegment['FlightNumber']])) {
                $itsegment['ArrCode'] = $codes[$itsegment['FlightNumber']][2];
            } else {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // ArrName
            $itsegment['ArrName'] = $this->re("#(.*?)(\s+-\s+Terminal|\s+-\s+T\w+\s*$|$)#", $names[1]);

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->re("#\s+-\s+(?:Terminal\s+|T)(\w+)\s*$#", $names[1]);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($dates[1]));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#(?:\n|^)(\w{2})\d+\n#ms", $table[0]);

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->re("#Aircraft\s*\:\s*(.+)#", $table[2]);

            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#([^\n]+)\s+\n[A-Z]\n#ms", $table[4]);

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\n([A-Z])\n#", $table[4]);

            // PendingUpgradeTo
            // Seats
            if (isset($seats[$itsegment['FlightNumber']])) {
                $itsegment['Seats'] = implode(", ", $seats[$itsegment['FlightNumber']]);
            }

            // Duration
            $itsegment['Duration'] = $this->re("#{$this->t('Duration')}:\s+(.+)#", $table[1]);

            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace(["#\s{2,}#", '/(.+Aircraft\s*\:\s*.+)[ ]+(Transit\s*\:\s*[ ]{1,}.+)/'], ["|", '$1|$2'], $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
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
            "#^[^\d\s]+, (\d+ [^\d\s]+ \d{4})\s+(\d+:\d+)$#i", //Sun, 08 Jan 2017 13:35
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
