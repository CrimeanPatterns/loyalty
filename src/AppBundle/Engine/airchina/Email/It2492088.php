<?php

namespace AwardWallet\Engine\airchina\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class It2492088 extends \TAccountChecker
{
    public $mailFiles = "airchina/it-2492088.eml, airchina/it-13361739.eml";

    private $providerCode = '';
    private $lang = '';

    private $langDetectors = [
        'en' => ['AIRLINE PNR:', 'ORIGIN/DES'],
    ];

    private static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Air China North America') !== false
            || stripos($from, '@airchina.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Air China itinerary') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        if ($this->assignLang() === false) {
            return false;
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType'    => 'Itinerary' . ucfirst($this->lang),
            'providerCode' => $this->providerCode,
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $its = [];

        $itineraries = $this->http->XPath->query('//text()[normalize-space(.)="ORIGIN/DES"]/ancestor::table[1]');

        foreach ($itineraries as $itinerary) {
            $itFlight = $this->parseItinerary($itinerary);

            if ($itFlight === false || empty($itFlight['RecordLocator'])) {
                continue;
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

                if (!empty($itFlight['TicketNumbers'][0])) {
                    if (!empty($its[$key]['TicketNumbers'][0])) {
                        $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                        $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                    } else {
                        $its[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                    }
                }
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);

                if (!empty($itFlight['SpentAwards'])) {
                    if (!empty($its[$key]['SpentAwards'])) {
                        $patternAwards = '/^(\d[,\d ]*?)[ ]*([A-z][A-z ]*\b)$/i';

                        if (preg_match($patternAwards, $itFlight['SpentAwards'], $n) && preg_match($patternAwards, $its[$key]['SpentAwards'], $m)) {
                            if ($n[2] === $m[2]) {
                                $its[$key]['SpentAwards'] = $this->normalizePrice($n[1]) + $this->normalizePrice($m[1]) . ' ' . $n[2];
                            } else {
                                unset($its[$key]['SpentAwards']);
                            }
                        }
                    }
                }

                if (!empty($itFlight['Currency']) && $itFlight['TotalCharge'] !== null) {
                    if (!empty($its[$key]['Currency']) && $its[$key]['TotalCharge'] !== null) {
                        if ($itFlight['Currency'] === $its[$key]['Currency']) {
                            $its[$key]['TotalCharge'] += $itFlight['TotalCharge'];
                        } else {
                            unset($its[$key]['Currency'], $its[$key]['TotalCharge']);
                        }
                    }
                }

                if (!empty($itFlight['Currency']) && $itFlight['BaseFare'] !== null) {
                    if (!empty($its[$key]['Currency']) && $its[$key]['BaseFare'] !== null) {
                        if ($itFlight['Currency'] === $its[$key]['Currency']) {
                            $its[$key]['BaseFare'] += $itFlight['BaseFare'];
                        } else {
                            unset($its[$key]['Currency'], $its[$key]['BaseFare']);
                        }
                    }
                }
            } else {
                $its[] = $itFlight;
            }
        }

        foreach ($its as $key => $it) {
            $its[$key] = $this->uniqueTripSegments($it);
        }

        return $its;
    }

    private function parseItinerary($root)
    {
        $patterns = [
            'code'     => '/\b([A-Z]{3})\s*--/',
            'time'     => '/^(\d{4})$/', // 1320
            'terminal' => '/^T([A-Z\d]+)$/', // T2
        ];

        $it = [];
        $it['Kind'] = 'T';

        $xpathFragment1 = './ancestor::tr[1]/preceding-sibling::tr[normalize-space(.)][1]';

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode($xpathFragment1 . '/descendant::td[not(.//td) and contains(normalize-space(.),"AIRLINE PNR:")]', $root, true, '/^[^:]+:\s*([A-Z\d]{5,})$/');

        // Passengers
        $passenger = $this->http->FindSingleNode($xpathFragment1 . '/descendant::td[not(.//td) and contains(normalize-space(.),"NAME:")]', $root, true, '/^[^:]+:\s*(.+)$/');

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        // TicketNumbers
        $ticketNumber = $this->http->FindSingleNode($xpathFragment1 . '/descendant::td[not(.//td) and contains(normalize-space(.),"ETKT NBR:")]', $root, true, '/^[^:]+:\s*(\d[-\d\s]+\d)$/');

        if ($ticketNumber) {
            $it['TicketNumbers'] = [$ticketNumber];
        }

        // AccountNumbers
        $accountNumber = $this->http->FindSingleNode($xpathFragment1 . '/descendant::td[not(.//td) and contains(normalize-space(.),"ID NUMBER:")]', $root, true, '/^[^:]+:\s*([A-Z\d][-A-Z\d\s]+\d)$/');

        if ($accountNumber) {
            $it['AccountNumbers'] = [$accountNumber];
        }

        // ReservationDate
        $dateIssue = $this->http->FindSingleNode($xpathFragment1 . '/descendant::td[not(.//td) and contains(normalize-space(.),"DATE OF ISSUE:")]', $root, true, '/^[^:]+:\s*(.+)$/');
        $dateIssueNormal = $this->normalizeDate($dateIssue);

        if ($dateIssueNormal) {
            $it['ReservationDate'] = strtotime($dateIssueNormal);
        }

        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query("(//tr[not(.//tr) and contains(normalize-space(.),'ORIGIN/DES')])[1]/following-sibling::tr[not(.//hr) and count(./td)>3]/td[2][string-length(normalize-space(.)) > 0]/..", $root);

        foreach ($segments as $segment) {
            $seg = [];

            // DepCode
            $seg['DepCode'] = $this->http->FindSingleNode('./td[1]', $segment, true, $patterns['code']);

            // ArrCode
            $seg['ArrCode'] = $this->http->FindSingleNode('./following-sibling::tr[1]/td[1]', $segment, true, $patterns['code']);

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./td[2]', $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                if (!empty($matches['airline'])) {
                    $seg['AirlineName'] = $matches['airline'];
                }
                $seg['FlightNumber'] = $matches['flightNumber'];
            }

            // BookingClass
            $class = $this->http->FindSingleNode('./td[3]', $segment, true, '/^([A-Z]{1,2})$/');

            if ($class) {
                $seg['BookingClass'] = $class;
            }

            $date = 0;
            $dateText = $this->http->FindSingleNode('./td[4]', $segment);

            if (!empty($it['ReservationDate']) && $dateText) {
                $date = EmailDateHelper::parseDateRelative($dateText, $it['ReservationDate']);
            }

            // DepDate
            $timeDep = $this->http->FindSingleNode('./td[5]', $segment, true, $patterns['time']);

            if ($date && $timeDep) {
                $seg['DepDate'] = strtotime($timeDep, $date);
            }

            // ArrDate
            $timeArr = $this->http->FindSingleNode('./td[5]/following-sibling::td[normalize-space(.)][1]', $segment, true, $patterns['time']);

            if ($date && $timeArr) {
                $seg['ArrDate'] = strtotime($timeArr, $date);
            } elseif (!empty($seg['DepDate'])) {
                $seg['ArrDate'] = MISSING_DATE;
            }

            // DepartureTerminal
            // ArrivalTerminal
            if ($this->http->FindSingleNode('./preceding-sibling::tr/td[10][1]', $segment) === 'TERMINAL') {
                $terminalDep = $this->http->FindSingleNode('./td[10]', $segment, true, $patterns['terminal']);

                if ($terminalDep) {
                    $seg['DepartureTerminal'] = $terminalDep;
                }
                $terminalArr = $this->http->FindSingleNode('./td[11]', $segment, true, $patterns['terminal']);

                if ($terminalArr) {
                    $seg['ArrivalTerminal'] = $terminalArr;
                }
            }

            $it['TripSegments'][] = $seg;
        }

        $xpathFragment2 = './ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]';

        // Currency
        // TotalCharge
        // BaseFare
        $totalPayment = $this->http->FindSingleNode($xpathFragment2 . '/descendant::td[not(.//td) and starts-with(normalize-space(.),"TOTAL:")]', $root);

        if (preg_match('/^[^:]+:\s*(?<currency>[A-Z]{3})\s*(?<charge>\d[,.\d]*)$/', $totalPayment, $matches)) {
            $it['Currency'] = $matches['currency'];
            $it['TotalCharge'] = (float) $this->normalizePrice($matches['charge']);
            $fare = $this->http->FindSingleNode($xpathFragment2 . '/descendant::td[not(.//td) and starts-with(normalize-space(.),"FARE:")]', $root);

            if (preg_match('/^[^:]+:\s*' . preg_quote($matches['currency'], '/') . '\s*(?<charge>\d[,.\d]*)$/', $fare, $m)) {
                $it['BaseFare'] = (float) $this->normalizePrice($m['charge']);
            }
        }

        return $it;
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

    private function normalizeDate($string = '')
    {
        if (preg_match('/^(\d{1,2})[.\s]*([^\d\W]{3,})[.\s]*(\d{2})$/u', $string, $matches)) { // 28JUN14
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . ($year ? '.' . $year : '');
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

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);           // 11 507.00    ->    11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string); // 2,790        ->    2790    |    4.100,00    ->    4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);  // 18800,00     ->    18800.00

        return $string;
    }

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

    private function assignProvider($headers)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"@airchina.com") or contains(normalize-space(.),"www.airchina.com")]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.airchina.com")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'airchina';

            return true;
        }

        $condition1 = stripos($headers['from'], 'Ctrip English Flight Support') !== false || stripos($headers['from'], '@ctrip.com') !== false;

        if ($condition1) {
            $this->providerCode = 'ctrip';

            return true;
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['airchina', 'ctrip'];
    }

    private function assignLang()
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

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }
}
