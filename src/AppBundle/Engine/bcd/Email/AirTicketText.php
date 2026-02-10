<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\bcd\Email;

class AirTicketText extends \TAccountChecker
{
    public $mailFiles = "bcd/it-5513471.eml, bcd/it-5576738.eml, bcd/it-5576766.eml";

    private $detectBody = 'BCD Travel acts only as an agent for the airlines, hotels, bus companies, railroads, tour operators, cruise lines, car rental companies, and other';

    private $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getHTMLBody();
        $nbsp = chr(194) . chr(160);
        $text = str_replace($nbsp, ' ', $text);

        if (stripos($text, '<br>') !== false) {
            $text = str_replace('<br>', '', $text);
        }
        $this->parseEmail($text);

        return [
            'parsedData' => ['Itineraries' => $this->result],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (is_string($this->detectBody) && stripos($body, $this->detectBody) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@bcdtravel.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, '@bcdtravel.com') !== false;
    }

    private function parseEmail($text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        if (strpos($text, 'HOTEL -') !== false && strpos($text, 'HOTEL RATE') !== false) {
            $hotel = $this->cutText('HOTEL -', 'HOTEL RATE', $text);
            $this->parseHotel($hotel);
        }

        $recLoc = $this->cutText('Information for Trip Locator', 'Passengers', $text);

        if (!empty($recLoc) && preg_match('/\b([A-Z0-9]{6,8})\b/', $recLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        $segments = $this->cutText('Passengers', 'FOR INFORMATION ON TRAVEL REQUIREMENTS', $text);

        if (empty($segments)) {
            $this->logger->info('Segments not found by text with \'start = Passengers\' and \'end = FOR INFORMATION ON TRAVEL REQUIREMENTS\'');

            return false;
        }

        if (stripos($segments, 'AIR - ') === false) {
            $this->logger->info('Delimeter not found!');

            return false;
        }
        $segments = explode('AIR - ', $segments);
        array_shift($segments);

        $status = $this->cutText('Status', 'Meal', $segments[0]);

        if (!empty($status) && preg_match('/Status[:]?\s+(\w+)/', $status, $m)) {
            $it['Status'] = $m[1];
        }

        foreach ($segments as $segment) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $infoFlight = substr($segment, 0, stripos($segment, 'Depart'));
            $re = '/Flight\s+(?<AName>[A-Z]{2})\s?(?<FNum>\d+)\s+(?<Cabin>\w+)/si';

            if (!empty($recLoc) && preg_match($re, $infoFlight, $m)) {
                $seg['AirlineName'] = $m['AName'];
                $seg['FlightNumber'] = $m['FNum'];
                $seg['Cabin'] = $m['Cabin'];
            }

            $depInfo = $this->cutText('Depart', 'Arrive', $segment);
//            $reDepArr = '/(?:Depart|Arrive):\s+(?<Name>.+?)\s+(?:terminal (?<Term>[a-z0-9]{1,3})\s+(?<Name1>.+)|)\s+(?<Time>\d{1,2}:\d{2} (?:pm|am))\s+\w+, (?<Month>\w+) (?<Day>\d+) (?<Year>\d{4})/si';
            $reDepArr = '/(?:Depart|Arrive):\s+(?<Name>.+?)\s+(?:terminal (?<Term>[^\n]+)\s+(?<Name1>.+)|)\s+(?<Time>\d{1,2}:\d{2} (?:pm|am))\s+\w+, (?<Month>\w+) (?<Day>\d+) (?<Year>\d{4})/si';
            $this->logger->debug($depInfo);
            $this->logger->debug($reDepArr);

            if (!empty($depInfo) && preg_match($reDepArr, $depInfo, $m)) {
                $seg['DepName'] = preg_replace("#\s*\n\s*#", ", ", trim($m['Name'] . ' ' . $m['Name1']));
                $seg['DepartureTerminal'] = (isset($m['Term'])) ? trim($m['Term']) : null;
                $seg['DepDate'] = strtotime($m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'] . ', ' . $m['Time']);
            }

            $arrInfo = $this->cutText('Arrive', 'Duration', $segment);

            if (!empty($arrInfo) && preg_match($reDepArr, $arrInfo, $m)) {
                $seg['ArrName'] = preg_replace("#\s*\n\s*#", ", ", trim($m['Name'] . ' ' . $m['Name1']));
                $seg['ArrivalTerminal'] = trim($m['Term']);
                $seg['ArrDate'] = strtotime($m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'] . ', ' . $m['Time']);
            }

            $duration = $this->cutText('Duration', 'Status', $segment);

            if (!empty($duration) && preg_match('/Duration:\s+(\d+ \D+ \d+ [a-z\(\)]+)/i', $duration, $m)) {
                $seg['Duration'] = $m[1];
            }

            $aircraft = $this->cutText('Equipment', 'Seat', $segment);

            if (!empty($aircraft) && preg_match('/Equipment:\s+(.+)/', $aircraft, $m)) {
                $seg['Aircraft'] = trim($m[1]);
            }

            $miles = $this->cutText('Distance', 'CO2 Emissions', $segment);

            if (!empty($aircraft) && preg_match('/Distance:\s+(.+)/', $miles, $m)) {
                $seg['TraveledMiles'] = trim($m[1]);
            }

            $seats = $this->cutText('Seat', 'Distance', $segment);

            if (!empty($seats) && preg_match_all('/([a-z0-9]{1,4})\s+\w+\s+-\s+(.+)/i', $seats, $m, PREG_PATTERN_ORDER)) {
                $seg['Seats'] = $m[1];
                $it['Passengers'] = $m[2];
            }

            if (isset($seg['DepDate']) && isset($seg['ArrDate']) && isset($seg['FlightNumber'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return $this->result[] = $it;
    }

    private function parseHotel($text)
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $it */
        $it = ['Kind' => 'R'];

        $hotelName = $this->cutText('HOTEL', 'Address', $text);

        if (!empty($hotelName) && preg_match('/\w+, \w+ \d+ \d+\s+[\-]+\s+(.+)/', $hotelName, $m)) {
            $it['HotelName'] = trim($m[1]);
        }

        $address = $this->cutText('Address', 'Tel', $text);

        if (!empty($address) && preg_match('/:\s+(.+)/s', $address, $m)) {
            $it['Address'] = preg_replace('/\s+/', ' ', $m[1]);
        }

        $tel = $this->cutText('Tel', 'Fax', $text);

        if (!empty($tel) && preg_match('/:\s+([0-9\-]+)/', $tel, $m)) {
            $it['Phone'] = $m[1];
        }

        $fax = $this->cutText('Fax', 'Check In/Check Out', $text);

        if (!empty($fax) && preg_match('/:\d+([0-9\-]+)/', $fax, $m)) {
            $it['Fax'] = $m[1];
        }

        $checkInOutDate = $this->cutText('Check In/Check Out', 'Status', $text);

        if (!empty($checkInOutDate) && preg_match('/(?<In>\w+, \w+ \d+ \d+)\s+-\s+(?<Out>\w+, \w+ \d+ \d+)/', $checkInOutDate, $m)) {
            $it['CheckInDate'] = strtotime($m[1]);
            $it['CheckOutDate'] = strtotime($m[2]);
        }

        $status = $this->cutText('Status', 'Number of Persons', $text);

        if (!empty($status) && preg_match('/:\s+(\w+)/', $status, $m)) {
            $it['Status'] = $m[1];
        }

        $persons = $this->cutText('Number of Persons', 'Number of Rooms', $text);

        if (!empty($persons) && preg_match('/:\s+(\d+)/', $persons, $m)) {
            $it['Guests'] = $m[1];
        }

        $rooms = $this->cutText('Number of Rooms', 'Number of Nights', $text);

        if (!empty($rooms) && preg_match('/:\s+(\d+)/', $rooms, $m)) {
            $it['Rooms'] = $m[1];
        }

        $rate = $this->cutText('Rate per night', 'Guaranteed', $text);

        if (!empty($rate) && preg_match('/:\s+(\w+\s+[\d\.]+)/', $rate, $m)) {
            $it['Rate'] = $m[1];
        }

        $confirmation = $this->cutText('Confirmation', 'Cancellation Policy', $text);

        if (!empty($confirmation) && preg_match('/:\s+(\d+)/', $confirmation, $m)) {
            $it['ConfirmationNumber'] = $m[1];
        }

        return $this->result[] = $it;
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = strstr(strstr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
    }
}
