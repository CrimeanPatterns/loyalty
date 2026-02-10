<?php

namespace AwardWallet\Engine\egencia\Email;

class AirTicketPlainText extends \TAccountChecker
{
    public $mailFiles = "egencia/it-5639198.eml";

    private $subjects = [
        'en' => ['booking confirmation'],
    ];

    private $traveler = '';
    private $itineraryNumber = '';
    private $itineraries = [];
    private $PNRs = [];

    private $patterns = [
        'date' => '\d{1,2}-[^-,.\d\s]{3,}-\d{2,4}',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $textBody = $parser->getPlainBody();

        if ($this->parseEmail($textBody) === false) {
            return false;
        }

        return [
            'parsedData' => ['Itineraries' => $this->itineraries],
            'emailType'  => 'AirTicketText',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $textBody = $parser->getPlainBody();

        if (strpos($textBody, 'trip with Egencia') === false && stripos($textBody, 'www.egencia.com') === false && stripos($textBody, '@customercare.egencia.com') === false && stripos($textBody, 'Thank you for choosing Egencia') === false) {
            return false;
        }

        if (strpos($textBody, 'FLIGHT SUMMARY') !== false || strpos($textBody, 'CAR SUMMARY') !== false || strpos($textBody, 'HOTEL SUMMARY') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/Egencia\s+booking\s+confirmation/i', $headers['subject'])) {
            return true;
        }

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@customercare.egencia.com') !== false;
    }

    private function parseEmail($text)
    {
        if (preg_match('/[=]+\[[ ]*Egencia[ ]*\][=]+\s+^\s*(.+)$\s+Itinerary[ ]+#:[ ]*([A-Z\d]{5,})\s*$/mi', $text, $matches)) {
            $this->traveler = trim($matches[1]);
            $this->itineraryNumber = $matches[2];
        }

        // --[ Sunday 29-Oct-2017 ]---
        $dateSegments = $this->splitText($text, '/^\s*[-]+\[[ ]*[^-,.\d\s]{2,}[ ]+(' . $this->patterns['date'] . ')[ ]*\][-]+/m', true);

        foreach ($dateSegments as $dateSegment) {
            if ($this->parseDateSegment($dateSegment) === false) {
                return false;
            }
        }

        return true;
    }

    private function parseDateSegment($text)
    {
        if (preg_match('/^(' . $this->patterns['date'] . ')/', $text, $matches)) {
            $date = $matches[1];
        } else {
            return false;
        }

        $travelSegments = $this->splitText($text, '/^[ ]*(.*(?:FLIGHT SUMMARY|CAR SUMMARY|HOTEL SUMMARY))\s*$/m', true);

        foreach ($travelSegments as $travelSegment) {
            if (preg_match('/^\s*Depart:/mi', $travelSegment)) {
                $itFlights = $this->parseFlights($travelSegment, $date);

                foreach ($itFlights as $itFlight) {
                    if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $this->itineraries)) !== false) {
                        $this->itineraries[$key]['Passengers'] = array_merge($this->itineraries[$key]['Passengers'], $itFlight['Passengers']);
                        $this->itineraries[$key]['Passengers'] = array_unique($this->itineraries[$key]['Passengers']);

                        if (!empty($itFlight['TicketNumbers'][0])) {
                            if (!empty($this->itineraries[$key]['TicketNumbers'][0])) {
                                $this->itineraries[$key]['TicketNumbers'] = array_merge($this->itineraries[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                                $this->itineraries[$key]['TicketNumbers'] = array_unique($this->itineraries[$key]['TicketNumbers']);
                            } else {
                                $this->itineraries[$key]['TicketNumbers'] = $itFlight['TicketNumbers'];
                            }
                        }
                        $this->itineraries[$key]['TripSegments'] = array_merge($this->itineraries[$key]['TripSegments'], $itFlight['TripSegments']);
                    } else {
                        $this->itineraries[] = $itFlight;
                    }
                }
            } elseif (preg_match('/^\s*Pick-up location:/mi', $travelSegment)) {
                $this->itineraries[] = $this->parseCar($travelSegment, $date);
            } elseif (preg_match('/^\s*Check in:/mi', $travelSegment)) {
                $this->itineraries[] = $this->parseHotel($travelSegment, $date);
            }
        }

        foreach ($this->itineraries as $key => $it) {
            $this->itineraries[$key] = $this->uniqueTripSegments($it);
        }

        return true;
    }

    private function parseFlights($text, $date)
    {
        // Total: $639.94 (Out of Policy)
        if (preg_match('/^\s*Total:([^\d]+)(\d[,.\d]*)/m', $text, $matches)) {
            $currency = trim($matches[1]);
            $totalCharge = $this->normalizePrice($matches[2]);
        }

        // JetBlue Airways Confirmation code: OJWJQC
        if (preg_match('/^\s*(.+)[Cc]onfirmation [Cc]ode:\s*([A-Z\d]{5,})/m', $text, $matches)) {
            $this->PNRs[trim($matches[1])] = $matches[2];
        }

        // Ticket #: 2797017041815
        if (preg_match('/^\s*Ticket #:\s*([\d\s]{5,})/m', $text, $matches)) {
            $ticketNumber = trim($matches[1]);
        }

        $its = [];

        $patterns['terminal'] = '[A-z\d\s]*(?:Terminal|terminal|TERMINAL)[A-z\d\s]*)';
        $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])';
        $patterns['segments'] = '/'
            . '^\s*(?<airline>.+),\s+(?<flightNumber>\d+)\s+-\s+Status\s*$' // JetBlue Airways, 1094  - Status
            . '\s+^.+$' // URL
            . '\s+^\s*Depart:\s*(?<nameDep>.+)\s+\((?<codeDep>[A-Z]{3})\)(?:,\s*(?<terminalDep>' . $patterns['terminal'] . ')?,\s+(?<timeDep>' . $patterns['time'] . ')' // Depart: New York (JFK), Terminal 5,  9:13 pm
            . '\s+^\s*Arrive:\s*(?<nameArr>.+)\s+\((?<codeArr>[A-Z]{3})\)(?:,\s*(?<terminalArr>' . $patterns['terminal'] . ')?,\s+(?<timeArr>' . $patterns['time'] . ')(?:\s*[+]\s*\d+\s+day)?' // Arrive: Austin (AUS), 12:34 am +1 day
            . '\s+^\s*(?:Seat\s*(?<seat>\d{1,2}[A-Z])\s*,|.+\.)\s*(?<cabin>[^,.\n]+)\((?<bookingClass>[A-Z]{1,2})\)(?:,\s+(?<meal>[^,]{4,}))?,\s+(?<duration>\d{1,2}hr\s*\d{1,2}mn)(?:,\s+(?<aircraft>.{5,}))?' // Seat 20E, Economy/Coach Class (K), Food For Purchase, 5hr 5mn, Boeing 737-900
            . '/m';
        preg_match_all($patterns['segments'], $text, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $matches) {
            $it = [];
            $it['Kind'] = 'T';

            if (!empty($this->traveler)) {
                $it['Passengers'] = [$this->traveler];
            }

            if (!empty($this->itineraryNumber)) {
                $it['TripNumber'] = $this->itineraryNumber;
            }

            if (isset($currency) && $currency) {
                $it['Currency'] = $currency;
                $it['TotalCharge'] = $totalCharge;
            }

            $airline = trim($matches['airline']);

            if (!empty($this->PNRs[$airline])) {
                $it['RecordLocator'] = $this->PNRs[$airline];
            } else {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }

            if (isset($ticketNumber) && $ticketNumber) {
                $it['TicketNumbers'] = [$ticketNumber];
            }

            $it['TripSegments'] = [];

            $seg = [];

            $seg['AirlineName'] = $airline;
            $seg['FlightNumber'] = $matches['flightNumber'];

            $seg['DepName'] = $matches['nameDep'];
            $seg['DepCode'] = $matches['codeDep'];

            if (!empty($matches['terminalDep'])) {
                $seg['DepartureTerminal'] = $matches['terminalDep'];
            }

            $seg['ArrName'] = $matches['nameArr'];
            $seg['ArrCode'] = $matches['codeArr'];

            if (!empty($matches['terminalArr'])) {
                $seg['ArrivalTerminal'] = $matches['terminalArr'];
            }

            if ($date) {
                $seg['DepDate'] = strtotime($date . ', ' . $matches['timeDep']);
                $seg['ArrDate'] = strtotime($date . ', ' . $matches['timeArr']);
            }

            if (!empty($matches['seat'])) {
                $seg['Seats'] = [$matches['seat']];
            }

            $seg['Cabin'] = trim($matches['cabin']);
            $seg['BookingClass'] = $matches['bookingClass'];

            if (!empty($matches['meal'])) {
                $seg['Meal'] = $matches['meal'];
            }

            $seg['Duration'] = $matches['duration'];

            if (!empty($matches['aircraft'])) {
                $seg['Aircraft'] = trim($matches['aircraft']);
            }

            $it['TripSegments'][] = $seg;

            $its[] = $it;
        }

        return $its;
    }

    private function parseCar($text, $date)
    {
        $it = [];
        $it['Kind'] = 'L';

        if (!empty($this->traveler)) {
            $it['RenterName'] = $this->traveler;
        }

        if (!empty($this->itineraryNumber)) {
            $it['TripNumber'] = $this->itineraryNumber;
        }

        if (preg_match('/^(.+)CAR SUMMARY/', $text, $matches)) {
            $it['RentalCompany'] = $matches[1];
        }

        if (preg_match('/^\s*(Confirmed)\s*$/mi', $text, $matches)) {
            $it['Status'] = $matches[1];
        }

        // Base rate: $120.50
        if (preg_match('/^\s*Base rate:([^\d]+)(\d[,.\d]*)/m', $text, $matches)) {
            $it['Currency'] = trim($matches[1]);
            $it['BaseFare'] = $this->normalizePrice($matches[2]);
        }

        if (preg_match('/Booking confirmation number:\s*([A-Z\d]{5,})/', $text, $matches)) {
            $it['Number'] = $matches[1];
        }

        if (preg_match('/^\s*Pick-up date & time:\s*(.+)/m', $text, $matches)) {
            $it['PickupDatetime'] = strtotime($matches[1]);
        }

        if (preg_match('/^\s*Pick-up location:\s*(.+)/m', $text, $matches)) {
            $it['PickupLocation'] = $matches[1];
        }

        // Drop-off location: New York, NY (JFK-John F. Kennedy Intl.), DL  flight 456, 23-Oct-17 11:25 AM
        if (preg_match('/^\s*Drop-off location:\s*(.+)/m', $text, $matches)) {
            if (preg_match('/(.+),\s*(\d{1,2}-[^-,.\d\s]{3,}-\d{2,4}\s*\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\s*$/', $matches[1], $m)) {
                $it['DropoffLocation'] = $m[1];
                $it['DropoffDatetime'] = strtotime($m[2]);
            } else {
                $it['DropoffLocation'] = $matches[1];
                $it['DropoffDatetime'] = MISSING_DATE;
            }
        }

        return $it;
    }

    private function parseHotel($text, $date)
    {
        $it = [];
        $it['Kind'] = 'R';

        if (!empty($this->traveler)) {
            $it['GuestNames'] = [$this->traveler];
        }

        if (!empty($this->itineraryNumber)) {
            $it['TripNumber'] = $this->itineraryNumber;
        }

        if (preg_match('/^\s*(Reserved)\s*$/mi', $text, $matches)) {
            $it['Status'] = $matches[1];
        }

        // Total: $1,085.60
        if (preg_match('/^\s*Total:([^\d]+)(\d[,.\d]*)/m', $text, $matches)) {
            $it['Currency'] = trim($matches[1]);
            $it['Total'] = $this->normalizePrice($matches[2]);
        }

        if (preg_match('/Confirmation number:\s*([A-Z\d]{5,})/', $text, $matches)) {
            $it['ConfirmationNumber'] = $matches[1];
        }

        if (preg_match('/^.+Confirmation number:.+$\s+^\s*(.+)$\s+^\s*(.+)$\s+^\s*([A-z].+[A-z])\s*$/m', $text, $matches)) {
            $it['HotelName'] = $matches[1];
            $it['Address'] = trim($matches[2]) . ', ' . $matches[3];
        } elseif (preg_match('/^\s*Total:.+$\s+^\s*(.+)$\s+^\s*(.+)$\s+^\s*([A-z].+[A-z])\s*$/m', $text, $matches)) {
            $it['HotelName'] = $matches[1];
            $it['Address'] = trim($matches[2]) . ', ' . $matches[3];

            if (empty($it['ConfirmationNumber'])) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }
        }

        // 1 (203) 358-8400
        if (preg_match('/^\s*(\+?\d[- \d)(]{7,}\d{3})/m', $text, $matches)) {
            $it['Phone'] = $matches[1];
        }

        // Fax 1 (203) 358-8872
        if (preg_match('/Fax\s+(\+?\d[- \d)(]{7,}\d{3})\s*$/m', $text, $matches)) {
            $it['Fax'] = $matches[1];
        }

        // Traditional Room. Requests: 1 KING BED, Non-Smoking
        if (preg_match('/^\s*(.+)Requests:\s*(.+)$/m', $text, $matches)) {
            $it['RoomType'] = trim($matches[1], ';,. ');
            $it['RoomTypeDescription'] = $matches[2];
        }

        if (preg_match('/^\s*Check in:\s*(.+)/m', $text, $matches)) {
            $it['CheckInDate'] = strtotime($matches[1]);
        }

        if (preg_match('/^\s*Check out:\s*(.+)/m', $text, $matches)) {
            $it['CheckOutDate'] = strtotime($matches[1]);
        }

        // CancellationPolicy

        return $it;
    }

    private function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function uniqueTripSegments($it)
    {
        if ($it['Kind'] !== 'T') {
            return $it;
        }
        $uniqueSegments = [];

        foreach ($it['TripSegments'] as $segment) {
            foreach ($uniqueSegments as $key => $uniqueSegment) {
                if ($segment['FlightNumber'] === $uniqueSegment['FlightNumber'] && $segment['DepDate'] === $uniqueSegment['DepDate']) {
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

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
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
}
