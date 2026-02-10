<?php

namespace AwardWallet\Engine\cebu\Email;

use AwardWallet\Engine\MonthTranslate;

class ScheduleChangeAdvisory2 extends \TAccountChecker
{
    public $mailFiles = "cebu/it-12085733.eml, cebu/it-11967961.eml";

    public $reSubject = [
        'en' => ['Schedule Change Advisory'],
    ];

    public $langDetectors = [
        'en' => ['NEW FLIGHT SCHEDULE'],
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [],
    ];

    public function parseHtml()
    {
        $patterns = [
            'timeAirport'  => '/(\d{1,2}:\d{2})\s+\(\d{1,2}:\d{2}\s*[AaPp][Mm]\)\s+(.{3,})/s', // 11:15 (11:15AM) Francisco Reyes Airport
            'nameTerminal' => '/(.{3,}?)\s+Terminal\s+(\w+)/is', // Ninoy International Airport Terminal 4
        ];

        $itineraries = [];
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts("Booking reference no.:") . "]", null, true, '/:\s*([A-Z\d]{5,})/');

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq("Names of Guest/s:") . "]/../descendant::text()[normalize-space(.)][./preceding::text()[" . $this->eq("Names of Guest/s:") . "]]");

        // Status
        $segmentHeaders = $this->http->XPath->query('//text()[' . $this->starts("NEW FLIGHT SCHEDULE") . ']');

        if ($segmentHeaders->length !== 1) {
            return null;
        } else {
            $it['Status'] = 'changed';
        }

        // TripSegments
        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('./following::text()[' . $this->starts("Flight No:") . ']', $segmentHeaders->item(0));

        foreach ($segments as $root) {
            $itsegment = [];

            $date = 0;
            $dateText = $this->http->FindSingleNode('./following::text()[normalize-space(.)][position()<4][' . $this->starts("Date:") . ']', $root, true, '/:\s*(.+)/');

            if ($dateText) {
                $date = $this->normalizeDate($dateText);
            }

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('.', $root);

            if (preg_match('/\b([A-Z\d]{2})\s*(\d+)$/', $flight, $matches)) {
                $itsegment['AirlineName'] = $matches[1];
                $itsegment['FlightNumber'] = $matches[2];
            }

            $departureText = $this->http->FindSingleNode('./following::text()[normalize-space(.)][position()<5][' . $this->starts("Departure:") . ']/..', $root);

            // DepName
            // DepartureTerminal
            $timeDep = '';

            if (preg_match($patterns['timeAirport'], $departureText, $matches)) {
                $timeDep = $matches[1];

                if (preg_match($patterns['nameTerminal'], $matches[2], $m)) {
                    $itsegment['DepName'] = trim($m[1], '- ');
                    $itsegment['DepartureTerminal'] = $m[2];
                } else {
                    $itsegment['DepName'] = trim($matches[2], '- ');
                }
            }

            // DepDate
            if ($date && $timeDep) {
                $itsegment['DepDate'] = strtotime($date . ', ' . $timeDep);
            }

            $arrivalText = $this->http->FindSingleNode('./following::text()[normalize-space(.)][position()<7][' . $this->starts("Arrival:") . ']/..', $root);

            // ArrName
            // ArrivalTerminal
            $timeArr = '';

            if (preg_match($patterns['timeAirport'], $arrivalText, $matches)) {
                $timeArr = $matches[1];

                if (preg_match($patterns['nameTerminal'], $matches[2], $m)) {
                    $itsegment['ArrName'] = trim($m[1], '- ');
                    $itsegment['ArrivalTerminal'] = $m[2];
                } else {
                    $itsegment['ArrName'] = trim($matches[2], '- ');
                }
            }

            // ArrDate
            if ($date && $timeArr) {
                $itsegment['ArrDate'] = strtotime($date . ', ' . $timeArr);
            }

            // DepCode
            // ArrCode
            if (!empty($itsegment['DepName']) && !empty($itsegment['ArrName'])) {
                $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        return $itineraries;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'CEB Schedule Change') !== false
            || stripos($from, '@advisory.cebupacific5j.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Cebu Pacific team") or contains(normalize-space(.),"Cebu Pacific Team")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.cebupacificair.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() === false) {
            return false;
        }

        $this->date = strtotime($parser->getHeader('date'));

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
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

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^([^\s\d]+) (\d+), (\d{4})$#", // March 30, 2018
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
