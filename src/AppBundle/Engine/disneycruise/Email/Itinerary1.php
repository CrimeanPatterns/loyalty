<?php

namespace AwardWallet\Engine\disneycruise\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "disneycruise/it-1.eml, disneycruise/it-1593463.eml, disneycruise/it-3294498.eml, disneycruise/it-16463358.eml, disneycruise/it-32073350.eml";

    private $guestNames = [];
    private $patterns = [
        'dateAirport' => '/(?<date>\d{1,2}-[^\d\W]{3,}-\d{2,4})\s*(?<name>.+)/u', // Sun 14-Oct-2018 Salt Lake City, UT
        'time'        => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?', // 4:19PM
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $this->checkMails($headers["from"]);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]disneycruise\.com/", $from)
            || preg_match("/@familyvacations-disneycruise\.com/", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Your Disney cruise has been successfully booked') !== false
            || stripos($body, 'Thank you for reserving the following activities for your Disney Cruise') !== false
            || $this->checkMails($body);
    }

    public function checkMails($input = '')
    {
        if (stripos($input, 'disneycruise.com') === false) {
            return false;
        }

        // this one is slow on large emails
        preg_match('/[\.@].*disneycruise\.com/ims', $input, $match);

        return (isset($match[0])) ? true : false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        // HOTEL
        $hotelBlocks = $this->http->XPath->query('//tr[ ./*[1][normalize-space(.)="Hotel Name"] and ./*[3][normalize-space(.)="Check-Out"] ]');

        if ($hotelBlocks->length > 0) {
            $itsHotel = $this->parseHotels($hotelBlocks->item(0));

            if (count($itsHotel)) {
                $its = array_merge($its, $itsHotel);
            }
        }

        // CRUISE
        $cruises = $this->http->XPath->query('//tr[not(.//tr) and normalize-space(.)="Cruise Details"]');

        if ($cruises->length > 0) {
            $its[] = $this->parseCruise();
        }

        // AIR (it-16463358)
        $flightBlocks = $this->http->XPath->query('//tr[not(.//tr) and normalize-space(.)="Flight Schedule"]');

        if ($flightBlocks->length > 0) {
            $itsFlight = $this->parseFlights($flightBlocks->item(0));

            if (count($itsFlight)) {
                $its = array_merge($its, $itsFlight);
            }
        }

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => $its,
            ],
        ];
    }

    private function parseHotels($root)
    {
        $its = [];

        $reservationNo = $this->http->FindSingleNode('//text()[normalize-space(.)="Reservation #:"]/following::text()[normalize-space(.)][1]', $hotel, true, '/^[A-Z\d]{5,}$/');

        $hotels = $this->http->XPath->query('./ancestor-or-self::tr[ ./following-sibling::tr[normalize-space(.)] ][1]/following-sibling::tr[normalize-space(.)]/descendant::tr[./*[5]]', $root);

        foreach ($hotels as $hotel) {
            $it = [];
            $it['Kind'] = 'R';

            if ($reservationNo) {
                $it['TripNumber'] = $reservationNo;
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }

            $it['HotelName'] = $this->http->FindSingleNode('./*[1]', $hotel);

            $it['CheckInDate'] = strtotime($this->http->FindSingleNode('./*[2]', $hotel));
            $it['CheckOutDate'] = strtotime($this->http->FindSingleNode('./*[3]', $hotel));

            $it['Rooms'] = $this->http->FindSingleNode('./*[5]', $hotel, true, '/^\d{1,3}$/');

            $it['RoomTypeDescription'] = $this->http->FindSingleNode('./following::tr[normalize-space(.)][1]/descendant::*[normalize-space(.)="Room Description:"]/following-sibling::*[1]', $hotel);

            // TODO: rewrite parser on object
            // example: it-32073350.eml

            $its[] = $it;
        }

        return $its;
    }

    private function parseCruise()
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['TripCategory'] = TRIP_CATEGORY_CRUISE;

        // RecordLocator
        $reservationNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Reservation #')]/ancestor::td[1]/following-sibling::td[1]");

        if (!$reservationNumber) {
            $reservationNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Reservation #')]", null, true, '/Reservation #\s*:\s*([A-Z\d]{5,})$/');
        }

        if ($reservationNumber) {
            $it['RecordLocator'] = $reservationNumber;
        }

        // ShipName
        $ship = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'Ship:')]/ancestor::td[1]/following-sibling::td[1]");

        if (!$ship) {
            $ship = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Ship:')]", null, true, '/Ship:\s*(.+)/s');
        }

        if ($ship) {
            $it['ShipName'] = $ship;
        }

        $names = $this->http->FindNodes("//*[normalize-space(text()) = 'Guest Name']/ancestor-or-self::*[name()='td' or name()='th'][1]/following-sibling::*[contains(., 'Address')]/ancestor::tr[2]/following-sibling::tr//tr[./td[2]]/td[1]");

        if (count($names)) {
            $it['Passengers'] = $names;
        }

        $total = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Total Due')]/following-sibling::td[string-length(text()) > 0]");

        if ($total) {
            $it['TotalCharge'] = cost($total);
            $it['Currency'] = currency($total);
        }

        $segments = [];
        $arrivalDeparture = $this->http->XPath->query("//*[normalize-space(text()) = 'Ashore']/ancestor-or-self::*[name()='td' or name()='th'][1]/following-sibling::*[contains(., 'Onboard')]/ancestor::tr[2]/following-sibling::tr//tr[./td[2]]");

        foreach ($arrivalDeparture as $adField) {
            $date = $this->http->FindSingleNode("./td[1]", $adField);
            $date .= ' ' . $this->http->FindSingleNode("./td[2]", $adField);
            $depData = $this->http->FindSingleNode("./td[5]", $adField);

            if (!empty($depData)) {
                $depData = $date . ' ' . $this->http->FindSingleNode("./td[5]", $adField);
            }

            $arrDate = $this->http->FindSingleNode("./td[4]", $adField);

            if (!empty($arrDate)) {
                $arrDate = $date . ' ' . $this->http->FindSingleNode("./td[4]", $adField);
            }
            $port = $this->http->FindSingleNode("./td[3]", $adField);

            $segments[] = [
                'DepDate' => strtotime($depData),
                'ArrDate' => strtotime($arrDate),
                'Port'    => $port,
            ];
        }

        if (count($segments)) {
            $converter = new \CruiseSegmentsConverter();
            $it['TripSegments'] = $converter->Convert($segments);
        } else { // it-16463358
            $seg = [];
            $seg['ArrName'] = $seg['DepName'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Cruise Itinerary:')]", null, true, '/Cruise Itinerary:.+ from (.+)/s');
            $seg['DepDate'] = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Embark Date:')]", null, true, '/Embark Date:\s*(.+)/'));
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Debark Date:')]", null, true, '/Debark Date:\s*(.+)/'));
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function parseFlights($node)
    {
        $its = [];

        $roots = $this->http->XPath->query('./ancestor::*[contains(normalize-space(.),"Airline Confirmation:")][1]', $node);

        if ($roots->length === 0) {
            $this->logger->debug('flight content not found!');

            return [];
        }
        $root = $roots->item(0);

        $this->guestNames = $this->http->FindNodes('./descendant::text()[normalize-space(.)="Guest Name"]/ancestor::tr[ ./following-sibling::tr[normalize-space(.)] ][1]/following-sibling::tr[normalize-space(.)]/descendant::td[ not(./preceding-sibling::td) and ./following-sibling::td ][1]', $root);

        $flightSegments = $this->http->XPath->query('./descendant::text()[normalize-space(.)="Departs"]/ancestor::tr[ ./descendant::text()[normalize-space(.)="Arrives"] and ./following-sibling::tr[normalize-space(.)] ][1]/following-sibling::tr[normalize-space(.)]', $root);

        foreach ($flightSegments as $flightSegment) {
            $itFlight = $this->parseFlight($flightSegment);

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
                $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
            } else {
                $its[] = $itFlight;
            }
        }

        return $its;
    }

    private function parseFlight($root)
    {
        $it = [];
        $it['Kind'] = 'T';

        // Passengers
        if (!empty($this->guestNames[0])) {
            $it['Passengers'] = $this->guestNames;
        }

        // TripSegments
        $it['TripSegments'] = [];

        $seg = [];

        $xpathFragment1 = './descendant::tr[count(./td)=2 or count(./td)=3][1]';

        $xpathFragmentDep = $xpathFragment1 . '/td[1]/descendant::tr[not(.//tr) and normalize-space(.)][1]';

        // DepName
        // DepDate
        $dateDep = '';
        $dateAirportDep = $this->http->FindSingleNode($xpathFragmentDep . '/td[normalize-space(.)][1]', $root);

        if (preg_match($this->patterns['dateAirport'], $dateAirportDep, $matches)) {
            $dateDep = $matches[1];
            $seg['DepName'] = $matches[2];
        }
        $timeDep = $this->http->FindSingleNode($xpathFragmentDep . '/td[normalize-space(.)][2]', $root, true, '/^(' . $this->patterns['time'] . ')$/');

        if ($dateDep && $timeDep) {
            $seg['DepDate'] = strtotime($dateDep . ', ' . $timeDep);
        }

        $xpathFragmentArr = $xpathFragment1 . '/td[position()>1 and last()]/descendant::tr[not(.//tr) and normalize-space(.)][1]';

        // ArrName
        // ArrDate
        $dateArr = '';
        $dateAirportArr = $this->http->FindSingleNode($xpathFragmentArr . '/td[normalize-space(.)][1]', $root);

        if (preg_match($this->patterns['dateAirport'], $dateAirportArr, $matches)) {
            $dateArr = $matches[1];
            $seg['ArrName'] = $matches[2];
        }
        $timeArr = $this->http->FindSingleNode($xpathFragmentArr . '/td[normalize-space(.)][2]', $root, true, '/^(' . $this->patterns['time'] . ')$/');

        if ($dateArr && $timeArr) {
            $seg['ArrDate'] = strtotime($dateArr . ', ' . $timeArr);
        }

        // AirlineName
        // FlightNumber
        $flight = $this->http->FindSingleNode($xpathFragmentDep . '/following::text()[string-length(normalize-space(.))>2][1]', $root);

        if (preg_match('/^(?<airline>.+?)\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
            $seg['AirlineName'] = $matches['airline'];
            $seg['FlightNumber'] = $matches['flightNumber'];
        }

        // Stops
        $stopTexts = $this->http->FindNodes("./descendant::text()[contains(normalize-space(.), 'Stop')]", $root, '/^(\d{1,3})\s*Stops?$/i');

        if (count($stopTexts) === 1 && $stopTexts[0] !== null) {
            $seg['Stops'] = $stopTexts[0];
        }

        // Operator
        $seg['Operator'] = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.), 'Operated By:')]", $root, true, '/Operated By:\s*(.+)/');

        // Aircraft
        $seg['Aircraft'] = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.), 'Aircraft Type:')]", $root, true, '/Aircraft Type:\s*(.+)/');

        // DepCode
        // ArrCode
        if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['DepName']) && !empty($seg['ArrName'])) {
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        $it['TripSegments'][] = $seg;

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.), 'Airline Confirmation:')]", $root, true, '/Airline Confirmation:\s*([A-Z\d]{5,})$/');

        return $it;
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
}
