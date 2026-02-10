<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "expedia/it-12074556.eml, expedia/it-31678730.eml, expedia/it-34696726.eml, expedia/it-48908990.eml, expedia/it-52220364.eml";

    public $lang = '';

    public static $dict = [
        'en' => [],
    ];

    protected $langDetectors = [
        'en' => ['Total travel time:'],
    ];

    private static $provs = [
        'expedia',
        'travelocity',
    ];

    private $patterns = [
        'time' => '\d{1,2}[:]+\d{2}(?:[ ]*[AaPp][Mm])?', // 11:45 PM
    ];
    /** @var \HttpBrowser */
    private $pdf;

    // Standard Methods

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if (false === stripos($textPdf, 'expedia') && false === stripos($textPdf, 'Travelocity')) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $this->pdf = clone $this->http;

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $pdfComplex = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
            $this->pdf->SetEmailBody($pdfComplex);

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $textPdfFull .= $textPdf;
            }
        }

        if (!$textPdfFull) {
            return false;
        }

        return $this->parsePdf($textPdfFull);
    }

    public static function getEmailProviders()
    {
        return self::$provs;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parsePdf($textPdf)
    {
        $result = [
            'emailType' => 'FlightConfirmationPdf' . ucfirst($this->lang),
        ];

        $provider = 'expedia';

        foreach (self::$provs as $prov) {
            if (false !== stripos($textPdf, $prov)) {
                $provider = $prov;

                break;
            }
        }

        if (false === stripos($provider, 'expedia')) {
            $result['providerCode'] = strtolower($provider);
        }

        $its = [];

        $currency = null;
        $amount = null;

        $textPdf = preg_replace("#\n\s*https://www\.expedia\.com/.+\d+/\d+\s+[\d/]+[ ]+Itinerary:.+#", "\n", $textPdf);

        // Itinerary # 5339721628252
        $itineraries = $this->splitText($textPdf, '/(.+\bItinerary[ ]*#[ ]*\d+)/', true);

        foreach ($itineraries as $textItinerary) {
            $its = $this->parseItinerary($textItinerary, $its);

            if (preg_match('/[ ]+Total Price[: ]+(\D+)(\d[,.\d ]*)$/mi', $textItinerary, $matches)
                || preg_match('/[ ]+Total Price[: ]*\n.*[ ]{10,}([^\d\s]+)[ ]*(\d[,.\d ]*)\n/mi', $textItinerary, $matches)
                || preg_match('/[ ]+Total:[ ]+([^\d\s]+)[ ]*(\d[,.\d ]*)\n/mi', $textItinerary, $matches)
            ) {
                // Total Price SG$207.15
                $matches[1] = $this->currency(trim($matches[1]));
                $matches[2] = (float) $this->normalizeAmount($matches[2]);

                if (!empty($currency) && $amount !== null) {
                    if ($matches[1] === $currency) {
                        $amount += $matches[2];
                    } else {
                        unset($currency, $amount);
                    }
                } else {
                    $currency = $this->currency($matches[1]);
                    $amount = $matches[2];
                }
            }
        }

        $result['parsedData']['Itineraries'] = $its;

        // Currency
        // Amount
        if (!empty($currency)) {
            $result['parsedData']['TotalCharge']['Currency'] = $this->currency($currency);
            $result['parsedData']['TotalCharge']['Amount'] = $amount;
        }

        return $result;
    }

    private function parseItinerary($textItinerary, $its)
    {
        if (preg_match('/\bItinerary \#[ ]?([A-Z\d]{5,})\s+/m', $textItinerary, $matches)) {
            $tripNumber = $matches[1];
        }

        // Singapore (SIN) → Kuching (KCH)
        $routes = $this->splitText($textItinerary, '/(.+\([A-Z]{3}\)[ ]*→[ ]*.+\([A-Z]{3}\))/', true);

        foreach ($routes as $textRoute) {
            $itFlights = $this->parseRoute($textRoute);

            foreach ($itFlights as $itFlight) {
                if ($itFlight === false || empty($itFlight['RecordLocator'])) {
                    continue;
                }

                if (!empty($tripNumber)) {
                    $itFlight['TripNumber'] = $tripNumber;
                }

                if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                    if (!empty($itFlight['Passengers'][0])) {
                        if (!empty($its[$key]['Passengers'][0])) {
                            $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                            $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                        } else {
                            $its[$key]['Passengers'] = $itFlight['Passengers'];
                        }
                    }

                    if (!empty($itFlight['AccountNumbers'][0])) {
                        if (!empty($its[$key]['AccountNumbers'][0])) {
                            $its[$key]['AccountNumbers'] = array_merge($its[$key]['AccountNumbers'], $itFlight['AccountNumbers']);
                            $its[$key]['AccountNumbers'] = array_unique($its[$key]['AccountNumbers']);
                        } else {
                            $its[$key]['AccountNumbers'] = $itFlight['AccountNumbers'];
                        }
                    }
                    $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
                } else {
                    $its[] = $itFlight;
                }
            }
        }

        return $its;
    }

    private function parseRoute($textRoute)
    {
        $its = [];

        // need examples with child
        $paxNodes = $this->pdf->XPath->query("//p[normalize-space()='Adult']/preceding-sibling::p[1] | //p[normalize-space()!='Adult' and contains(.,'Adult') and not(contains(.,'Traveler') or contains(.,'Traveller'))]");

        foreach ($paxNodes as $rootPax) {
            $passengers[] = implode(" ",
                $this->pdf->FindNodes(".//text()[normalize-space()!=''][not(normalize-space()='Adult')]", $rootPax));
        }

        if (isset($passengers) && preg_match_all('/^[ ]*([[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]])[ ]{2,}No frequent (?:flyer|ﬂyer)/imu', $textRoute, $travellerMatches)) {
            $passengers = $travellerMatches[1];
        }

        if (preg_match_all('/Ticket #(?: .*)\n+.*[ ]{3,}(\d{3}[- ]*\d{5,}[- ]*\d{1,2})(?:[ ]{3,}|\n)/', $textRoute, $m)) {
            $tickets = $m[1];
            // need examples with child
            $accs = $this->pdf->FindNodes("//p[{$this->contains($tickets)}]/preceding-sibling::p[not(contains(normalize-space(),'Ticket') or contains(normalize-space(),'details'))][1][not(contains(.,'No frequent flyer') or contains(.,'Adult'))]");
//            $accs = $this->pdf->FindNodes("//p[normalize-space()='Adult']/following-sibling::p[1][not(contains(.,'No frequent flyer'))] | //p[normalize-space()!='Adult' and contains(.,'Adult') and not(contains(.,'Traveler') or contains(.,'Traveller'))]/following-sibling::p[1][not(contains(.,'No frequent flyer'))]");
        }

//        if( empty($passengers) ){
//            $paxText = $this->findCutSection($textRoute, 'Traveler Information', 'Seat assignments');
//        }

        $tablePos = [0];

        if (preg_match('/^(.+[ ]{2})Price Summary$/m', $textRoute, $matches)) {
            $tablePos[1] = mb_strlen($matches[1]);
        }

        if (preg_match('/^(.+[ ]{2})Additional Flight Services$/m', $textRoute, $matches)) {
            $column0Width = mb_strlen($matches[1]);

            if (!empty($tablePos[1]) && $column0Width < $tablePos[1]) {
                $tablePos[1] = $column0Width;
            }
        }
        $table = $this->splitCols($textRoute, $tablePos);

        if (count($table) !== 2) {
            $table = [$textRoute];
        }

        $patternSegments = '/'
            . '(.+Total travel time:[\s\S]+)\s+'
            . 'Airline Rules & Regulations'
            . '/i';

        if (preg_match($patternSegments, $table[0], $matches)) {
            $textSegments = $matches[1];
        } else {
            return null;
        }

        $segmentsParts = $this->splitText($textSegments, '/^(.+Total travel time:.*)/m', true); // it-48908990.eml

        foreach ($segmentsParts as $sPart) {
            $date = null;

            if (preg_match('/^[ ]*(.{6,}?)(?:[ ]{2,}| - )/', $sPart, $m)) {
                $date = strtotime($this->normalizeDate($m[1]));
            }

            $patternSegment = '/'
                . '(?:(?<duration>\d[\dmh ]+)\n[^\n]+\s+(?<miles>\d[,\d]+ mi)\n)?'
                . '(?<p2>(?<p1>.+)(?<depCode>[A-Z]{3})[ ]+(?<timeDep>' . $this->patterns['time'] . ')[ ]+)(?<arrCode>[A-Z]{3})[ ]+(?<timeArr>' . $this->patterns['time'] . ')(?<nextDay>.*)\n+'
                . '(?<terminals>(.*\n+){0,5}?)(?:^[ ]*(\(Arrives on (?<dateArr>[\s\S]+?)\))\s*)?'
                . '^[ ]*(?<airline>\S.+)[ ]+(?<flightNumber>\d{1,5})(?:\s*Operated by ([^\n]+))?\n+'
                . '^[ ]*(?<cabin>.+)[ ]+\((?<class>[A-Z]{1,2})\)[ ]+\|[ ]+(?:Seat (?<seats>\d[A-Z\d, ]*[A-Z]))?'
                . '/m';

            preg_match_all($patternSegment, $sPart, $segmentMatches, PREG_SET_ORDER);

            if (0 === count($segmentMatches)) {
                $this->logger->debug('Pattern segment does not work!');

                return $its;
            }

            foreach ($segmentMatches as $matches) {
                $it = [];
                $it['Kind'] = 'T';

                // Passengers
                if (isset($passengers) && !empty($passengers)) {
                    $it['Passengers'] = $passengers;
                }

                // TicketNumbers
                if (isset($tickets)) {
                    $it['TicketNumbers'] = $tickets;
                }

                // AccountNumbers
                if (isset($accs)) {
                    $it['AccountNumbers'] = $accs;
                }

                // TripSegments
                $it['TripSegments'] = [];

                $seg = [];

                // DepCode
                // ArrCode
                $seg['DepCode'] = $matches['depCode'];
                $seg['ArrCode'] = $matches['arrCode'];

                if (preg_match('/Terminal (?<dep>\w{0,4})[ ]{3,}Terminal (?<arr>\w{0,4})\s+/', $matches['terminals'], $m)) {
                    $seg['DepartureTerminal'] = $m['dep'];
                    $seg['ArrivalTerminal'] = $m['arr'];
                } elseif (preg_match_all('/^(.+)Terminal (\w{0,4})([ ]{3,}|$)/m', $matches['terminals'], $m) && count($m[0]) <= 2) {
                    if (count($m[0]) === 2) {
                        if (strlen($m[1][0]) < strlen($m[1][1])) {
                            $seg['DepartureTerminal'] = $m[2][0];
                            $seg['ArrivalTerminal'] = $m[2][1];
                        } else {
                            $seg['DepartureTerminal'] = $m[2][1];
                            $seg['ArrivalTerminal'] = $m[2][0];
                        }
                    } else {
                        if (strlen($m[1][0]) < strlen($matches['p1']) + 5) {
                            $seg['DepartureTerminal'] = $m[2][0];
                        } elseif (strlen($m[1][0]) < strlen($matches['p2']) + 5) {
                            $seg['ArrivalTerminal'] = $m[2][0];
                        }
                    }
                }

                // AirlineName
                // FlightNumber
                $seg['AirlineName'] = $matches['airline'];
                $seg['FlightNumber'] = $matches['flightNumber'];

                // Cabin
                // BookingClass
                $seg['Cabin'] = $matches['cabin'];
                $seg['BookingClass'] = $matches['class'];

                // DepDate
                // ArrDate
                if ($date) {
                    $seg['DepDate'] = strtotime($matches['timeDep'], $date);
                    $seg['ArrDate'] = strtotime($matches['timeArr'], $date);

                    if (isset($matches['nextDay']) && preg_match("/([\-\+]\s*\d+) days?/i", $matches['nextDay'], $m)) {
                        $seg['ArrDate'] = strtotime($m[1] . ' days', $seg['ArrDate']);
                        $date = $seg['ArrDate'];
                    }

                    if (isset($matches['dateArr']) && ($dateArr = strtotime(preg_replace("/\s+/", ' ', $matches['dateArr'])))) {
                        $seg['ArrDate'] = strtotime($matches['timeDep'], $dateArr);
                        $date = $dateArr;
                    }
                }

                if (isset($matches['duration']) && !empty($matches['duration'])) {
                    $seg['Duration'] = $matches['duration'];
                }

                if (isset($matches['miles']) && !empty($matches['miles'])) {
                    $seg['TraveledMiles'] = $matches['miles'];
                }

                if (!empty($matches['seats'])) {
                    $seg['Seats'] = preg_split('/\s*,\s*/', $matches['seats']);
                }
                $it['TripSegments'][] = $seg;

                // RecordLocator
                if (preg_match('/' . preg_quote($seg['AirlineName'], '/') . '[ ]*([A-Z\d]{5,})$/m', $textRoute, $m)) {
                    $it['RecordLocator'] = $m[1];
                } elseif (preg_match('/[ ]+[Ee]xpedia\.com(?:\.sg|\.au)?[ ]*([A-Z\d]{5,})$/m', $textRoute, $m)
                    || preg_match('/[ ]+[Ee]xpedia\.com(?:\.sg|\.au)?[ ]*Booking[ ]*([A-Z\d]{5,})$/m', $textRoute, $m)
                ) {
                    // Expedia.com.sg L148HV3WP
                    $it['RecordLocator'] = $m[1];
                } else {
                    $it['RecordLocator'] = CONFNO_UNKNOWN;
                }

                $its[] = $it;
            }
        }

        return $its;
    }

    private function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    /**
     * function returns the key from `$array` in which `$pnr` was found, otherwise `false`.
     *
     * @param string $pnr
     * @param array $array
     *
     * @return int|bool
     */
    private function recordLocatorInArray($pnr, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $pnr) {
                    return $key;
                }
            }

            if ($value['Kind'] === 'R') {
                if ($value['ConfirmationNumber'] === $pnr) {
                    return $key;
                }
            }
        }

        return false;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $string): ?string
    {
        if (preg_match('/^(\d{1,2})\s+([^\d\W]{3,})[.,\s]+(\d{4})/', $string, $matches)) { // 19 Apr,2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (preg_match('/^([^\d\W]{3,})\s+(\d{1,2})[,.\s]+(\d{4})/', $string, $matches)) { // Aug 15, 2018
            $day = $matches[2];
            $month = $matches[1];
            $year = $matches[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . ($year ? '.' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function assignLang(?string $text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
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

    private function currency(string $s): string
    {
        $sym = [
            '$C' => 'CAD',
            '€'  => 'EUR',
            'R$' => 'BRL',
            'C$' => 'CAD',
            'SG$'=> 'SGD',
            'HK$'=> 'HKD',
            'AU$'=> 'AUD',
            '$'  => 'USD',
            '£'  => 'GBP',
            'kr' => 'NOK',
            'RM' => 'MYR',
            '฿'  => 'THB',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = preg_replace("#([,.\d ]+)#", '', $s);

        foreach ($sym as $f=> $r) {
            if ($s === $f) {
                return $r;
            }
        }

        return $s;
    }

    private function rowColsPos($row): array
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

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
