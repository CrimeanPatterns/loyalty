<?php

namespace AwardWallet\Engine\aireuropa\Email;

use AwardWallet\Engine\MonthTranslate;

class It4130695 extends \TAccountChecker
{
    public $mailFiles = "aireuropa/it-4130695.eml";

    public $reSubject = [
        "pt"=> ["LOC ", "PASSAGEIRO REACOMODADO "],
    ];

    public $langDetectors = [
        "pt"=> ["Boa tarde", "Buenas tarde"],
    ];

    public static $dictionary = [
        "pt" => [],
    ];

    public $lang = "";

    public function parseHtml(&$itineraries)
    {
        $text = implode("\n", $this->http->FindNodes("/descendant::text()[normalize-space(.)]"));
        $text = $this->re("/(?:Boa tarde|Buenas tarde)(.+)/s", $text);

        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#\d+\w+\d{2}/\d{4}Z\s+(\w+)#", $text);

        // Passengers
        $passanger = $this->re('/\b0?1\s*\.\s*(.+?)(?:\(ADT\)|\s+2)/', $text); // 1.FARES/NASSER MR

        if ($passanger) {
            $it['Passengers'] = [$passanger];
        }

        $startDate = 0;
        $startDateText = $this->re("#\s(\d+\w+\d{2}/\d{4}Z)\s+\w+#", $text);

        if ($startDateText) {
            $startDate = strtotime($this->normalizeDate($startDateText));
        }

        // TripSegments
        $it['TripSegments'] = [];

        preg_match_all("#\n\s*\d\s+(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<FlightNumber>\d+)\s+\w\s+(?<Date>\d+\w+)\s+\d\s+(?<DepCode>[A-Z]{3})(?<ArrCode>[A-Z]{3})\s+HK1\s*\d?\s+(?<DepHours>\d{2})(?<DepMins>\d{2})\s+(?<ArrHours>\d{2})(?<ArrMins>\d{2})(?<NextDate>\+\d)?#", $text, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $segment) {
            $itsegment = [];

            $date = 0;

            if ($startDate) {
                $date = strtotime($this->normalizeDate($segment["Date"]), $startDate);
            }

            // AirlineName
            $itsegment['AirlineName'] = $segment["AirlineName"];

            // FlightNumber
            $itsegment['FlightNumber'] = $segment["FlightNumber"];

            // DepCode
            $itsegment['DepCode'] = $segment["DepCode"];

            // DepDate
            if ($date) {
                $itsegment['DepDate'] = strtotime($segment["DepHours"] . ':' . $segment["DepMins"], $date);
            }

            // ArrCode
            $itsegment['ArrCode'] = $segment["ArrCode"];

            // ArrDate
            if ($date) {
                $itsegment['ArrDate'] = strtotime($segment["ArrHours"] . ':' . $segment["ArrMins"], $date);

                if (!empty($segment["NextDate"])) {
                    $itsegment['ArrDate'] = strtotime("{$segment["NextDate"]} days", $itsegment['ArrDate']);
                }
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $this->uniqueTripSegments($it);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@air-europa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers["subject"], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"@air-europa.com") or contains(.,"aireuropa.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.aireuropa.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        if ($this->assignLang() === false) {
            return false;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations' . ucfirst($this->lang),
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
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})([^\d\W]{3,})$/u', $string, $matches)) { // 14NOV
            $day = $matches[1];
            $month = $matches[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})([^\d\W]{3,})(\d{2,4})\/\w+Z$/u', $string, $matches)) { // 4NOV16/0636Z
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
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

    private function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                $condition1 = $segment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $uniqueSegment['FlightNumber'] !== FLIGHT_NUMBER_UNKNOWN && $segment['FlightNumber'] === $uniqueSegment['FlightNumber'];
                $condition2 = $segment['DepCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['DepCode'] !== TRIP_CODE_UNKNOWN && $segment['DepCode'] === $uniqueSegment['DepCode']
                    && $segment['ArrCode'] !== TRIP_CODE_UNKNOWN && $uniqueSegment['ArrCode'] !== TRIP_CODE_UNKNOWN && $segment['ArrCode'] === $uniqueSegment['ArrCode'];
                $condition3 = $segment['DepDate'] !== MISSING_DATE && $uniqueSegment['DepDate'] !== MISSING_DATE && $segment['DepDate'] === $uniqueSegment['DepDate'];

                if (($condition1 || $condition2) && $condition3) {
                    if (!empty($segment['Seats'][0])) {
                        if (!empty($uniqueSegments[$key]['Seats'][0])) {
                            $uniqueSegments[$key]['Seats'] = array_merge($uniqueSegments[$key]['Seats'], $segment['Seats']);
                            $uniqueSegments[$key]['Seats'] = array_unique($uniqueSegments[$key]['Seats']);
                        } else {
                            $uniqueSegments[$key]['Seats'] = $segment['Seats'];
                        }
                    }

                    continue 2;
                }
            }
            $uniqueSegments[] = $segment;
        }
        $it['TripSegments'] = $uniqueSegments;

        return $it;
    }
}
