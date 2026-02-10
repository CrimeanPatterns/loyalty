<?php

namespace AwardWallet\Engine\egencia\Email;

class TravelPdf2015En extends \TAccountChecker
{
    public $mailFiles = "egencia/it-3312063.eml, egencia/it-6298169.eml, egencia/it-6298230.eml";

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        return strpos($body, 'Egencia') !== false && (
                strpos($body, 'TRAVEL CONFIRMATION') !== false
                || strpos($body, 'OPPDATERT REISEBEKREFTELSE') !== false
                );
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@viaegencia.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Travel confirmation:') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@viaegencia') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate(), false);
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('[A-Z\d]{5,6}\.pdf');

        if (empty($pdfs)) {
            return;
        }

        $pdf = $parser->getAttachmentBody(array_shift($pdfs));
        $text = $this->strCut(\PDF::convertToText($pdf), null, 'Agency ');

        if (strpos($text, 'Airline:') != false) {
            // WED 07JAN 13:30   TG955
            $flights = $this->splitter('/(\d+[A-Z]{3} \d+:\d+\s+[A-Z\d]{2}\d+)/s', $text);
            $itineraries[] = $this->parseAir($text, $flights);
            $itineraries = $this->groupBySegments($itineraries);
        }

        if (strpos($text, 'Hotel') != false) {
            // TUE 21JUN        Hotel
            $hotels = $this->splitter('/([A-Z]{3} \d+[A-Z]{3}\s+Hotel)/s', $text);

            foreach ($hotels as $hotel) {
                $itineraries[] = $this->parseHotel($hotel);
            }
        }

        $result = [
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
            'emailType' => 'TravelPdf2015En',
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    protected function parseHotel($text)
    {
        $i = ['Kind' => 'R'];
        $i += $this->matchSubpattern('/(?<CheckInDate>[A-Z]{3} \d+[A-Z]{3})\s+Hotel\s+(?<HotelName>.+?)\s{3,}(?<Status>\w+)\s+'
                . '(?<CheckOutDate>[A-Z]{3} \d+[A-Z]{3})\s+(?<Address>.+?)'
                . 'Phone:(?<Phone>[+\d\s()-]+)\n/s', $text);

        $i['ConfirmationNumber'] = $this->match('/Confirmation number:\s*(\w+)\n/', $text);
        $i['RoomType'] = $this->match('/Room type:\s*(.+?)\n/', $text);
        $i['CancellationPolicy'] = $this->match('/Cancellation policy:\s*(.+?)\n/', $text);
        $i['GuestNames'][] = $this->match('/Reservation name:\s*(.+?)\n/', $text);

        if (isset($i['CheckInDate'])) {
            $i['CheckInDate'] = strtotime($i['CheckInDate'], $this->date);
            $i['CheckOutDate'] = strtotime($i['CheckOutDate'], $i['CheckInDate']);
        }
        // Total rate: 1790.00 NOK
        if (preg_match('/Total rate:\s+([\d.,]+)\s*([A-Z]{3})/', $text, $matches)) {
            $i['Total'] = (float) $matches[1];
            $i['Currency'] = $matches[2];
        }

        return $i;
    }

    /**
     * TODO: Beta!
     *
     * @version v1.2
     *
     * @param type $reservations
     *
     * @return array
     */
    protected function groupBySegments($reservations)
    {
        $newReservations = [];

        foreach ($reservations as $reservation) {
            $newSegments = [];

            foreach ($reservation['TripSegments'] as $segment) {
                if (empty($segment['RecordLocator']) && isset($reservation['TripNumber'])) {
                    // when there is no locator in the segment
                    $newSegments[$reservation['TripNumber']][] = $segment;
                } elseif (isset($segment['RecordLocator'])) {
                    $r = $segment['RecordLocator'];
                    unset($segment['RecordLocator']);
                    $newSegments[$r][] = $segment;
                }
            }

            foreach ($newSegments as $key => $segment) {
                $reservation['RecordLocator'] = $key;
                $reservation['TripSegments'] = $segment;
                $newReservations[] = $reservation;
            }
        }

        return $newReservations;
    }

    private function parseAir($text, $flights)
    {
        $result = ['Kind' => 'T'];

        if (preg_match_all('#\b([A-Z]+/[A-Z\s]+)\s*\n\s*Frequent Flyer:\s*(\w+)\b#', $text, $matches)) {
            $result['Passengers'] = $matches[1];
            $result['AccountNumbers'] = $matches[2];
        }

        foreach ($flights as $flight) {
            $i = ['DepCode' => TRIP_CODE_UNKNOWN, 'ArrCode' => TRIP_CODE_UNKNOWN];
            $i += $this->matchSubpattern('/^(?<DepDate>\d+[A-Z]{3}) (?<DepTime>\d+:\d+)\s+'
                    . '(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)\s+'
                    . '(?<DepName>[A-Z\s-]+)\s+\w+\s+(?<ArrDate>[A-Z]{3} \d+[A-Z]{3}) (?<ArrTime>\d+:\d+)\s+'
                    . '(?<ArrName>[A-Z\s-]+)\s+Confirmation number:\s*(?<RecordLocator>[A-Z\d]{5,6})/', $flight);

            if (isset($i['DepDate'])) {
                $i['DepDate'] = strtotime($i['DepDate'] . ', ' . $i['DepTime'], $this->date);
                $i['ArrDate'] = strtotime($i['ArrDate'] . ', ' . $i['ArrTime'], $i['DepDate']);
                unset($i['DepTime'], $i['ArrTime']);
            }

            $i['Duration'] = $this->match('/Duration:\s*(\d+:\d+)\b/', $flight);
            $i += $this->matchSubpattern('/Fare type:\s*(?<Cabin>\w+)\s*\((?<BookingClass>[A-Z])\)/', $flight);

            $i['Meal'] = $this->match('/Meal:(.+?)\n/', $flight);

            $result['TripSegments'][] = $i;
        }

        return $result;
    }

    /**
     * TODO: The experimental method.
     * If several groupings need to be used
     * Named subpatterns not accept the syntax (?<Name>) and (?'Name').
     *
     * @version v0.1
     *
     * @param type $pattern
     * @param type $text
     *
     * @return type
     */
    private function matchSubpattern($pattern, $text)
    {
        if (preg_match($pattern, $text, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_int($key)) {
                    unset($matches[$key]);
                }
            }

            if (!empty($matches)) {
                return array_map([$this, 'normalizeText'], $matches);
            }
        }

        return [];
    }

    private function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        } elseif ($allMatches) {
            return [];
        }
    }

    private function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    private function splitter($pattern, $text)
    {
        $result = [];

        $array = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*?)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    private function strCut($input, $searchStart, $searchFinish = null)
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
}
