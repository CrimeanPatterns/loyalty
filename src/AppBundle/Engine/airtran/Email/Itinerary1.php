<?php

namespace AwardWallet\Engine\airtran\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "";
    public $processors = [];
    public $reFrom = '#confirmations@airtran.com|travel-advisory@accept.airtran.com#i';
    public $reProvider = '#[@.]airtran.com#i';
    public $reSubject = "#AirTran Airways|AirTran International#";
    public $reText = null;
    public $reHtml = null;

    public function ParseConfirmationLetter()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $result = ['Kind' => 'T'];
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        $xpath->registerPhpFunctions(['stripos', 'CleanXMLValue']);

        $result['RecordLocator'] = $http->FindSingleNode('//text()[contains(., "Confirmation number:")]', null, true, '/Confirmation number:\s*(.+)/ims');
        // find passenger names between "Passengers" and "Flight Information" headings, assume that passenger name does not contain digits which in opposite address line does
        $passengers = [];
        $accountNumbers = [];
        $passengerLines = array_filter($http->FindNodes('//text()[contains(., "Passengers:") or contains(., "Passenger:")]/following::text()[count(following::text()[contains(., "Flight Information")]) = 1 and string-length(.) > 0]'), 'strlen');

        foreach ($passengerLines as $passengerLine) {
            // VASILIY KURDIN 9999999999
            if (preg_match('/^([^,0-9]+)\s*(\d+)?$/', $passengerLine, $matches)) {
                $passengers[] = trim($matches[1]);

                if (isset($matches[2])) {
                    $accountNumbers[] = $matches[2];
                }
            } else {
                // break after first non-name match
                break;
            }
        }
        $result['Origin'] = preg_replace('/\s+\d+/ims', '', end($passengerLines));

        if (!empty($passengers)) {
            $result['Passengers'] = array_filter($passengers);
        }
        $result['AccountNumbers'] = implode(', ', $accountNumbers);

        $segments = [];
        $flightHeaderNodes = $xpath->query('//text()[contains(., "Flight Information:")]/following::*[count(following::text()[contains(., "Payment Information:")]) = 1 and contains(string(), "Flight ") and not(contains(string(), "Departing"))]');

        foreach ($flightHeaderNodes as $flightHeaderIndex => $flightHeaderNode) {
            // parse header
            $segment = [];

            if (preg_match('/(.+)\s+Flight\s+(.+)/ims', CleanXMLValue($flightHeaderNode->nodeValue), $matches)) {
                $segment['DepDay'] = $matches[1];
                $segment['FlightNumber'] = $matches[2];
            }
            // get text of next node for xpath-query limits
            if ($flightHeaderNodes->item($flightHeaderIndex + 1)) {
                $flightHeaderText = CleanXMLValue($flightHeaderNodes->item($flightHeaderIndex + 1)->nodeValue);
            } else {
                $flightHeaderText = 'Payment Information:';
            }
            $flightSegmentRows = array_filter($http->FindNodes("following-sibling::node()[count(
				following::*[
					name() = '{$flightHeaderNode->tagName}' and
					contains(php:functionString('CleanXMLValue', string()), '{$flightHeaderText}')
				]
				) = 1]", $flightHeaderNode), 'strlen');

            foreach ($flightSegmentRows as $flightSegmentRow) {
                // [Non-Stop] Seat: 12C / 34C / 56A / 78A
                if (preg_match('/\[([^\]]+)\]( Seats?: (.+))?/ims', $flightSegmentRow, $matches)) {
                    if ($matches[1] === 'Non-Stop') {
                        $segment['Stops'] = 0;
                    }
                    // 12C / 34C / 56A / 78A
                    if (isset($matches[2]) && preg_match_all('/\s(\w+)\s/ims', $matches[3], $seatsMatches)) {
                        $segment['Seats'] = implode(', ', $seatsMatches[1]);
                    }
                }
                // Departing Perm, PR (PEE) at 05:50 AM
                if (preg_match('/Departing (.+)\s*\((\w+)\) at (.+)/ims', $flightSegmentRow, $matches)) {
                    $segment['DepName'] = $matches[1];
                    $segment['DepCode'] = $matches[2];
                    $segment['DepDate'] = strtotime($segment['DepDay'] . ' ' . $matches[3]);
                }
                // Departing Perm, PR (PEE) at 05:50 AM
                if (preg_match('/Arriving (.+)\s*\((\w+)\) at (.+)/ims', $flightSegmentRow, $matches)) {
                    $segment['ArrName'] = $matches[1];
                    $segment['ArrCode'] = $matches[2];
                    $segment['ArrDate'] = strtotime($segment['DepDay'] . ' ' . $matches[3]);
                }
            }

            if (!empty($segment)) {
                if (isset($segment['DepDay'])) {
                    unset($segment['DepDay']);
                }
                $segments[] = $segment;
            }
        }

        $total = $this->coalesce([
            // Total $100500.42 USD
            $http->FindSingleNode('//b[contains(text(), "Payment Information")]/following-sibling::text()[contains(., "Total")]', null, false, '/Total\s+((\S)?(\d+.\d+|\d+)(\s+\S+)?)/ims'),
            // 100500.42
            $http->FindSingleNode('//td[not(.//td) and contains(string(), "Ticket Total")]/following-sibling::td[1]'),
        ]);

        if (isset($total) && preg_match('/(Total\s+)?((\S)?(\d+.\d+|\d+)(\s+(\w+))?)/ims', $total, $matches)) {
            if (isset($matches[3]) && $matches[3] == '$') {
                $result['Currency'] = 'USD';
            }
            $result['TotalCharge'] = $matches[4];

            if (isset($matches[6])) {
                $result['Currency'] = $matches[6];
            }
        }
        $result['BaseFare'] = $http->FindSingleNode('//td[not(.//td) and contains(string(), "Air Fare")]/following-sibling::td[1]');

        if (!empty($segments)) {
            $result['TripSegments'] = $segments;
        }

        if (isset($result['Origin'])) {
            unset($result['Origin']);
        }

        return $result;
    }

    public function ParseInternationalFlightInformation()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $result = ['Kind' => 'T'];
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        $xpath->registerPhpFunctions(['stripos', 'CleanXMLValue']);
        $result['RecordLocator'] = $http->FindSingleNode("//*[contains(text(), 'Confirmation number:')]", null, false, '/Confirmation number:\s+(\w+)/ims');

        $passengers = [];
        $seats = [];
        $passengerLines = $http->FindNodes('//*[contains(text(), "Passenger(s) and Seat(s)")]/following-sibling::text()');

        foreach ($passengerLines as $passengerLine) {
            // Vasiliy Kurdin - Seats: 2D 2D
            if (preg_match('/(.+)\s+-\s+Seats:\s+(.+)/ims', $passengerLine, $matches)) {
                $passengers[] = $matches[1];
                $seats[] = explode(' ', trim($matches[2]));
            }
        }

        if (!empty($passengers)) {
            $result['Passengers'] = array_filter($passengers);
        }
        $segments = [];
        $fromNodes = $xpath->query('//text()[contains(., "From:") and following-sibling::text()[1][contains(., "To:")]]');

        foreach ($fromNodes as $id => $fromNode) {
            $segment = [];
            $baseDate = null;
            // MARCH 99, 2023, Flight 1030
            if (preg_match('/(.+),\s+Flight\s+(\w+)/ims', $http->FindSingleNode('preceding-sibling::*[contains(text(), ", Flight")][1]', $fromNode), $matches)) {
                $baseDate = $matches[1];
                $segment['FlightNumber'] = $matches[2];
            }
            // From: Perm-BlahBlah International Airport (PEE) / Departing: 830AM
            if (preg_match('|From:\s+(.+)\s+\((\w+)\)\s+/\s+Departing:\s+(.+)|ims', CleanXMLValue($fromNode->nodeValue), $matches)) {
                // 830AM
                if (preg_match('/(\d?\d)(\d\d)(\s+)?(\w+)/ims', $matches[3], $timeMatches)) {
                    $time = "{$timeMatches[1]}:{$timeMatches[2]} {$timeMatches[3]}";
                    $segment['DepDate'] = strtotime($baseDate . ' ' . $time);
                }
                $segment['DepName'] = $matches[1];
                $segment['DepCode'] = $matches[2];
            }

            if (preg_match('|To:\s+(.+)\s+\((\w+)\)\s+/\s+Arriving:\s+(.+)|ims', $http->FindSingleNode('following-sibling::text()[1][contains(., "To:")]', $fromNode), $matches)) {
                // 830AM
                if (preg_match('/(\d?\d)(\d\d)(\s+)?(\w+)/ims', $matches[3], $timeMatches)) {
                    $time = "{$timeMatches[1]}:{$timeMatches[2]} {$timeMatches[4]}";
                    $segment['ArrDate'] = strtotime($baseDate . ' ' . $time);
                }
                $segment['ArrName'] = $matches[1];
                $segment['ArrCode'] = $matches[2];
            }
            $flightSeats = [];

            foreach ($seats as $passenger) {
                if (isset($seats[$id])) {
                    $flightSeats[] = $passenger[$id];
                }
            }
            $segment['Seats'] = implode(', ', $flightSeats);

            if (!empty($segment)) {
                $segments[] = $segment;
            }
        }

        if (!empty($segments)) {
            if (isset($segment['DepDay'])) {
                unset($segment['DepDay']);
            }

            $result['TripSegments'] = $segments;
        }

        if (isset($result['Origin'])) {
            unset($result['Origin']);
        }

        return $result;
    }

    public function coalesce($values)
    {
        $filtered = array_values(array_filter($values, 'strlen'));

        if (!empty($filtered)) {
            return $filtered[0];
        } else {
            return null;
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $emailType = $this->getEmailType();
        $result = null;

        switch ($emailType) {
            case "ConfirmationLetter":
                $result = $this->ParseConfirmationLetter();

            break;

            case "InternationalFlightInformation":
                $result = $this->ParseInternationalFlightInformation();

            break;

            default:
                $result = [];

            break;
        }

        /*
        if($this->RefreshData && !empty($result['RecordLocator']) && !empty($result['PassengersArray']) && !empty($result['Origin'])){
            // get last name of the first passenger
            $nameParts = explode(' ', $result['PassengersArray'][0]);

            // "Origin LastName FirstName ConfirmationNumber"
            $errorMsg = $this->CheckConfirmationNumberInternal([
                    'ConfirmationNumber' => $result['RecordLocator'],
                    'FirstName' => strtoupper($nameParts[0]),
                    'LastName'  => strtoupper(end($nameParts)),
                    'Origin' => $this->getOriginCode($result['Origin'], $this->OriginSelect()),
                ], $itinerary);

            if($errorMsg === null && $this->checkItineraries($itinerary)){
                $result = $itinerary;
            }
        }*/

        return [
            'parsedData' => [
                'Itineraries' => [$result],
            ],
            'emailType' => $emailType,
        ];
    }

    public function getEmailType()
    {
        if ($this->http->FindPreg("/AirTran Airways Confirmation/ims") || $this->http->FindNodes('//img[contains(@src, "reservations/a-head-blue.git")]')) {
            return "ConfirmationLetter";
        }

        if ($this->http->FindPreg('/Information About Your Upcoming International Trip/ims')) {
            return "InternationalFlightInformation";
        }

        return "Undefined";
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return ((isset($this->reFrom) && isset($headers['from'])) ? preg_match($this->reFrom, $headers["from"]) : false)
                || ((isset($this->reSubject) && isset($headers['subject'])) ? preg_match($this->reSubject, $headers["subject"]) : false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return ((isset($this->reText) && $this->reText) ? preg_match($this->reText, $this->http->Response['body']) : false)
                || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false);
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    private function getOriginCode($needle, $origins)
    {
        if (empty($needle) || !is_array($origins) || empty($origins)) {
            return null;
        }
        $addressExplodeFunction = function ($name) {
            // Boston, MA (BOS)
            $parts = explode(', ', $name);
            $state = null;

            if (isset($parts[1])) {
                // MA
                $state = explode(' ', $parts[1])[0];
            }

            return [
                'FullName' => $name,
                'City'     => strtolower($parts[0]),
                'State'    => strtolower($state),
            ];
        };
        $needle = $addressExplodeFunction(strtolower($needle));
        $origins = array_map($addressExplodeFunction, $origins);
        // search by FulLName
        foreach ($origins as $code => $data) {
            if (stripos($data['FullName'], $needle['FullName']) !== false) {
                return $code;
            }
        }
        // fallback to search by state
        foreach ($origins as $code => $data) {
            if (strcasecmp($data['State'], $needle['State']) === 0) {
                return $code;
            }
        }

        return null;
    }
}
