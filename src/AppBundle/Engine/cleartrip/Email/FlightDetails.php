<?php

namespace AwardWallet\Engine\cleartrip\Email;

class FlightDetails extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-10489139.eml, cleartrip/it-10489133.eml";

    protected $lang = '';

    protected $langDetectors = [
        'en' => ['Departure:'],
    ];
    protected static $dict = [
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cleartrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        return stripos($headers['subject'], 'Revised flight details for Trip') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }

        $textBody = str_replace([chr(194) . chr(160), '&nbsp;'], ' ', $textBody);

        if (stripos($textBody, 'Thank you for choosing Cleartrip') === false && stripos($textBody, 'Cleartrip Travel Service') === false && stripos($textBody, '@cleartrip.com') === false && stripos($textBody, 'www.cleartrip.com') === false) {
            return false;
        }

        return $this->assignLang($textBody);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }

        $textBody = str_replace([chr(194) . chr(160), '&nbsp;'], ' ', $textBody);

        if ($this->assignLang($textBody) === false) {
            return false;
        }

        $it = $this->parseEmail($textBody);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'FlightDetails_' . $this->lang,
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

    protected function parseEmail($text)
    {
        $start = strpos($text, 'This email is');
        $end = strrpos($text, 'Thank you for choosing', $start);

        if ($start === false || $end === false) {
            return false;
        }
        $text = substr($text, $start, $end - $start);

        $patterns = [
            'time'     => '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?', // 05:35 AM
            'date'     => '\d{1,2}\s+[^,.\d\s]{3,}\s+\d{2,4}', // 17 Jun 2016
            'terminal' => '(?:Terminal|TERMINAL)[ ]+(?<terminal>[A-Z\d ]+)', // Terminal 1B
        ];

        $it = [];
        $it['Kind'] = 'T';

        if (preg_match('/your flight booking under Trip Id[>\s]*(\d{5,})\./i', $text, $matches)) {
            $it['TripNumber'] = $matches[1];
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $it['TripSegments'] = [];
        $segments = $this->splitText($text, '/^[>\s]*(.*[A-Z\d]{2}-\d+.*[>\s]+Departure[ ]*:)/m', true);

        if (count($segments) === 0) {
            return false;
        }

        foreach ($segments as $segmentText) {
            $seg = [];

            $segmentParts = preg_split('/Arrival[ ]*:/', $segmentText);

            if (count($segmentParts) !== 2) {
                return false;
            }

            // Etihad Airways EY-5205 Economy class Operated by G3
            if (preg_match('/([A-Z\d]{2})-(\d+)(.+)?/', $segmentParts[0], $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];

                if (preg_match('/(.+)Operated by[>\s]*(.+)/i', $matches[3], $m)) {
                    $seg['Cabin'] = str_replace('class', '', $m[1]);
                    $seg['Operator'] = $m[2];
                } elseif (!empty($matches[3])) {
                    $seg['Cabin'] = str_replace('class', '', $matches[3]);
                }
            }

            // Departure: GRU 22:05 17 Jun 2016 Guarulhos, Sao Paulo, Terminal 3
            if (preg_match('/Departure[ ]*:\s*(?<code>[A-Z]{3})\s+(?<time>' . $patterns['time'] . ')\s+(?<date>' . $patterns['date'] . ')(?<other>[^:]+)/', $segmentParts[0], $matches)) {
                $seg['DepCode'] = $matches['code'];
                $seg['DepDate'] = strtotime($matches['date'] . ', ' . $matches['time']);

                if (preg_match('/' . $patterns['terminal'] . '$/m', $matches['other'], $m)) {
                    $seg['DepartureTerminal'] = $m['terminal'];
                }
            }

            // 02:50 (+1 day) BOM 19 Jun 2016 Mumbai, India | Chhatrapati Shivaji BOM | Terminal 2
            if (preg_match('/(?<time>' . $patterns['time'] . ')(\s*\([+]\d+[ ]*[days]+\))?\s+(?<code>[A-Z]{3})\s+(?<date>' . $patterns['date'] . ')(?<other>[^:]+)/', $segmentParts[1], $matches)) {
                $seg['ArrCode'] = $matches['code'];
                $seg['ArrDate'] = strtotime($matches['date'] . ', ' . $matches['time']);

                if (preg_match('/' . $patterns['terminal'] . '$/m', $matches['other'], $m)) {
                    $seg['ArrivalTerminal'] = $m['terminal'];
                }
            }

            $it['TripSegments'][] = $seg;
        }

        return $this->uniqueTripSegments($it);
    }

    protected function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
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

    protected function uniqueTripSegments($it)
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

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
