<?php

namespace AwardWallet\Engine\flynas\Email;

use AwardWallet\Engine\MonthTranslate;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "flynas/it-6606785.eml, flynas/it-6610070.eml";

    public $reSubject = [
        'en' => ['flynas Booking Confirmation'],
    ];
    public $reBody = 'flynas';
    public $langDetectors = [
        'en' => ['Flight Number'],
    ];

    public static $dictionary = [
        'en' => [],
    ];

    public $lang = '';

    public function parseHtml(&$itineraries)
    {
        $patterns = [
            'nameCode' => '/^(.{2,}?)\s*\(([A-Z]{3})\)(?:[^)(]+|$)/s', // RIYADH (RUH) الرياض
            'terminal' => '/^(Terminal\s*[A-Z\d][A-Z\d\s]*\b|[A-Z\d][A-Z\d\s]*Terminal)/i', // Terminal 3 - صالة 3
            'charge'   => '/^(\d[,.\d\s]*\b)$/', // 1,043.00
        ];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Booking Reference");

        // ReservationDate
        $bookingDate = $this->http->FindSingleNode('//*[contains(normalize-space(.),"Booking Date") and not(.//*)]', null, true, '/^[^:]+:\s*(.{4,})$/s');

        if ($bookingDate) {
            $it['ReservationDate'] = strtotime($this->normalizeDate($bookingDate));
        }

        // TripSegments
        $it['TripSegments'] = [];
        $xpath = "//text()[" . $this->starts("Departing") . "]/ancestor::tr[1]/following-sibling::tr";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($segments as $root) {
            $itsegment = [];

            // AirlineName
            // FlightNumber
            // Seats
            $flight = $this->http->FindSingleNode('./td[1]/descendant::text()[normalize-space(.)][1]', $root);

            if (preg_match('/^([A-Z\d]{2})-(\d+)$/', $flight, $matches)) {
                $itsegment['AirlineName'] = $matches[1];
                $itsegment['FlightNumber'] = $matches[2];

                $seats = $this->http->FindNodes('//text()[' . $this->starts("Pax Name") . ']/ancestor::tr[1]/following-sibling::tr/descendant::td[contains(normalize-space(.),"' . $flight . '") and not(.//td)]/following-sibling::td[normalize-space(.)][1]', null, '/^(\d{1,2}[A-Z])$/');
                $seatValues = array_values(array_filter($seats));

                if (!empty($seatValues[0])) {
                    $itsegment['Seats'] = $seatValues;
                }
            }

            // DepName
            // DepCode
            $airportDep = $this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][1]', $root);

            if (preg_match($patterns['nameCode'], $airportDep, $matches)) {
                $itsegment['DepName'] = $matches[1];
                $itsegment['DepCode'] = $matches[2];
            } elseif ($airportDep) {
                $itsegment['DepName'] = $airportDep;
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            // DepartureTerminal
            $terminalDep = $this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][2][contains(normalize-space(.),"Terminal")]', $root, true, $patterns['terminal']);

            if ($terminalDep) {
                $itsegment['DepartureTerminal'] = str_ireplace('Terminal', '', $terminalDep);
            }

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][last()]', $root)));

            // ArrName
            // ArrCode
            $airportArr = $this->http->FindSingleNode('./td[3]/descendant::text()[normalize-space(.)][1]', $root);

            if (preg_match($patterns['nameCode'], $airportArr, $matches)) {
                $itsegment['ArrName'] = $matches[1];
                $itsegment['ArrCode'] = $matches[2];
            } elseif ($airportArr) {
                $itsegment['ArrName'] = $airportArr;
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            // ArrivalTerminal
            $terminalArr = $this->http->FindSingleNode('./td[3]/descendant::text()[normalize-space(.)][2][contains(normalize-space(.),"Terminal")]', $root, true, $patterns['terminal']);

            if ($terminalArr) {
                $itsegment['ArrivalTerminal'] = str_ireplace('Terminal', '', $terminalArr);
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode('./td[3]/descendant::text()[normalize-space(.)][last()]', $root)));

            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][2]", $root);

            $it['TripSegments'][] = $itsegment;
        }

        // Passengers
        $it['Passengers'] = $this->http->FindNodes('//text()[' . $this->starts("Pax Name") . ']/ancestor::tr[1]/following-sibling::tr[./td[7]]/td[1]');

        // Currency
        $it['Currency'] = $this->http->FindSingleNode('//text()[' . $this->contains("Total Price:") . ']/following::text()[normalize-space(.)][1]', null, true, '/^([^\d]+)$/');

        if (!empty($it['Currency'])) {
            // TotalCharge
            $totalCharge = $this->http->FindSingleNode('//text()[' . $this->contains("Total Price:") . ']/following::text()[normalize-space(.)][2]', null, true, $patterns['charge']);

            if ($totalCharge) {
                $it['TotalCharge'] = $this->normalizePrice($totalCharge);
            }

            // BaseFare
            if ($this->http->FindSingleNode('//text()[' . $this->contains("Fare Price:") . ']/following::text()[normalize-space(.)][1]') === $it['Currency']) {
                $baseFare = $this->http->FindSingleNode('//text()[' . $this->contains("Fare Price:") . ']/following::text()[normalize-space(.)][2]', null, true, $patterns['charge']);

                if ($baseFare) {
                    $it['BaseFare'] = $this->normalizePrice($baseFare);
                }
            }
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flynas.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
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

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        if ($this->assignLang() === false) {
            return false;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

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

    protected function assignLang(): bool
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

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

//    protected function eq($field): string
//    {
//        $field = (array)$field;
//        if (count($field) === 0) return 'false';
//        return '(' . implode(' or ', array_map(function($s){ return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
//    }

    protected function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->starts($field);

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
        $in = [
            '/^[^-,.\d\s\/]{2,}\s+(\d{1,2})-([^-,.\d\s\/]{3,})-(\d{2,4})\s*-?\s*(\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AaPp][Mm])?)$/', // Mon 14-Dec-2015 - 07:15    |    Sun 13-Dec-2015 11:04:20 AM
        ];
        $out = [
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/\d{1,2}\s+([^-,.\d\s\/]{3,})\s+\d{2,4}/', $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
