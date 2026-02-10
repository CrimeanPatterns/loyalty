<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Engine\MonthTranslate;

class NotificationFor extends \TAccountChecker
{
    public $mailFiles = "egencia/it-6469542.eml, egencia/it-6572737.eml, egencia/it-8699873.eml";

    public $reFrom = '@customercare.egencia.com';
    public $reSubject = [
        'en' => 'notification for',
    ];
    public $reBody = 'Egencia';
    public $reBody2 = [
        'en' => 'FLIGHT SUMMARY',
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [],
    ];

    public function parsePlain(&$itineraries)
    {
        // Chicago (ORD), 14-Nov-17 at  9:20 PM Terminal: 5 INTERNATIONAL
        $patterns['airportInfo'] = '(?<airport>.+),\s*(?<date>\d{1,2}-[^-,.\d\s]{3,}-\d{2})\s+at\s+(?<time>\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)(?:\s*Terminal:\s*(?<terminal>[A-z\d ]+))?';
        $patterns['nameCode'] = '/(.{2,})\s+\(([A-Z]{3})\)\s*$/';

        $text = $this->http->Response['body'];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        if (!$it['RecordLocator'] = $this->re("#" . $this->opt($this->t("Airline ticket number\(s\):")) . "\s+([A-Z]{5,})#", $text)) {
            $it['RecordLocator'] = $this->re("#" . $this->opt($this->t("Egencia booking ID:")) . "\s+(\w+)#", $text);
        }

        // Passengers
        preg_match_all("#([^\n]+)\nAsiento:#", $text, $Passengers);
        $it['Passengers'] = array_filter([trim($this->re("#" . $this->opt($this->t("Traveler:")) . "\s+(.+)#", $text))]);

        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#" . $this->opt($this->t("Total Cost:")) . "\s+(.+)#", $text));

        // Currency
        $it['Currency'] = $this->currency($this->re("#" . $this->opt($this->t("Total Cost:")) . "\s+(.+)#", $text));

        $segments = $this->split("#(" . $this->t("Depart:") . "[^\n]+?\s*" . $this->t("Arrive:") . "[^\n]+?\s*" . $this->t("Flight:") . ".+?)#ms", $text);
        $test = substr_count($text, $this->t("Aircraft:"));

        if (count($segments) !== $test) {
            return false;
        }

        foreach ($segments as $stext) {
            $itsegment = [];

            // AirlineName
            // FlightNumber
            if (preg_match('/' . $this->t("Flight:") . '\s*(.{2,})\s+(\d+)/i', $stext, $matches)) {
                $itsegment['AirlineName'] = $matches[1];
                $itsegment['FlightNumber'] = $matches[2];
            }

            // DepName
            // DepCode
            // DepDate
            // DepartureTerminal
            if (preg_match('/' . $this->t("Depart:") . '\s*' . $patterns['airportInfo'] . '/i', $stext, $matches)) {
                if (preg_match($patterns['nameCode'], $matches['airport'], $m)) {
                    $itsegment['DepName'] = $m[1];
                    $itsegment['DepCode'] = $m[2];
                } else {
                    $itsegment['DepName'] = $matches['airport'];
                }

                if ($dateDep = $this->normalizeDate($matches['date'])) {
                    $itsegment['DepDate'] = strtotime($dateDep . ', ' . $matches['time']);
                }

                if (!empty($matches['terminal'])) {
                    $itsegment['DepartureTerminal'] = $matches['terminal'];
                }
            }

            // DepCode
            if ((!isset($itsegment['DepCode']) || !$itsegment['DepCode']) && $itsegment['DepName']) {
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // ArrName
            // ArrCode
            // ArrDate
            // ArrivalTerminal
            if (preg_match('/' . $this->t("Arrive:") . '\s*' . $patterns['airportInfo'] . '/i', $stext, $matches)) {
                if (preg_match($patterns['nameCode'], $matches['airport'], $m)) {
                    $itsegment['ArrName'] = $m[1];
                    $itsegment['ArrCode'] = $m[2];
                } else {
                    $itsegment['ArrName'] = $matches['airport'];
                }

                if ($dateArr = $this->normalizeDate($matches['date'])) {
                    $itsegment['ArrDate'] = strtotime($dateArr . ', ' . $matches['time']);
                }

                if (!empty($matches['terminal'])) {
                    $itsegment['ArrivalTerminal'] = $matches['terminal'];
                }
            }

            // ArrCode
            if ((!isset($itsegment['ArrCode']) || !$itsegment['ArrCode']) && $itsegment['ArrName']) {
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = trim($this->re("#" . $this->t("Aircraft:") . "\s+(.+)#", $stext));

            // TraveledMiles
            $itsegment['TraveledMiles'] = trim($this->re("#" . $this->t("Distance:") . "\s+(.+)#", $stext));

            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = trim($this->re("#(\S+)\s+" . $this->t("Class") . "\s+\(\w\)#", $stext));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\S+\s+" . $this->t("Class") . "\s+\((\w)\)#", $stext);

            // PendingUpgradeTo
            // Seats
            $seat = $this->re('/' . $this->t("Seat:") . '\s*(\d{1,2}[A-Z])/', $stext);

            if ($seat) {
                $itsegment['Seats'] = [$seat];
            }

            // Duration
            $itsegment['Duration'] = trim($this->re("#" . $this->t("Duration:") . "\s+(.+)#", $stext));

            // Meal
            $itsegment['Meal'] = trim($this->re("#" . $this->t("Meal Service:") . "\s+(.+)#", $stext));

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
        if (strpos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->SetEmailBody($parser->getPlainBody());

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response['body'], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = [];
        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => 'NotificationFor' . ucfirst($this->lang),
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

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
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

    private function normalizeDate($string)
    {
        if (preg_match('/^\s*(\d{1,2})-([^-,.\d\s]{3,})-(\d{2})/', $string, $matches)) { // 14-Nov-17
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
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
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_replace('/([.$*)|(\/])/', '\\\\$1', $s); }, $field)) . ')';
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
}
