<?php

namespace AwardWallet\Engine\airpanama\Email;

use AwardWallet\Engine\MonthTranslate;

class ItineraryPlain extends \TAccountChecker
{
    public $mailFiles = "airpanama/it-8599386.eml, mileageplus/it-8016899.eml, mileageplus/it-8049898.eml";
    public $reFrom = "airpanama.com";
    public $reSubject = [
        "en"=> "ITINERARY",
    ];
    public $reBody = 'AIR PANAMA';
    public $reBody2 = [
        "es"=> "LOCALIZADOR DE RESERVA",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = strip_tags(preg_replace("#<br[^>]*>#", "\n", $this->text));

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#LOCALIZADOR DE RESERVA\s+\w{2}/(.+)#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->re("#\n\s*([A-Z]+/[A-Z]+)\s{2,}#", $text)];

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
        $it['ReservationDate'] = $this->date = strtotime($this->normalizeDate($this->re("#FECHA\s+(.+)#", $text)), false);

        // NoItineraries
        // TripCategory
        $pos = $this->TableHeadPos($this->re("#LLEGADA\n([^\n]+)#", $text));
        $flights = $this->re("#LLEGADA\n[^\n]+\n(.*?)\s+RECORD LOCATOR TIME LIMIT#ms", $text);
        $segments = explode("\n\n", $flights);

        foreach ($segments as $stext) {
            $table = $this->splitCols($this->re("#[^\n]+\n([^\n]+\n[^\n]+)#", $stext), $pos);

            if (count($table) != 5) {
                $this->http->log("incorrect parse table");

                return;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#-\s+\w{2}\s+(\d+)\n#", $stext);

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = str_replace("\n", " ", $table[1]);

            // DepartureTerminal

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(trim($table[0]) . ', ' . trim($table[3])));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = str_replace("\n", " ", $table[2]);

            // ArrivalTerminal

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#(.*?)(?:\s{2,}|$)#", str_replace("\n", ", ", trim($table[4])))));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#-\s+(\w{2})\s+\d+\n#", $stext);

            // Operator
            // Aircraft
            $itsegment['AirlineName'] = $this->re("#TIPO DE EQUIPO\s+\((.*?)\)#", $stext);

            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $this->re("#RESERVA CONFIRMADA\s+(\w)\n#", $stext);

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
        $this->text = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

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

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\s\d]+), (\d{2})(\d{2})$#", //19SEP, 1900
            "#^(\d{2})(\d{2}), (\d+)([^\s\d]+)$#", //2000, 19SEP
        ];
        $out = [
            "$1 $2 $year, $3:$4",
            "$3 $4 $year, $1:$2",
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

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s+#", "|", $row))));
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
}
