<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class Itinerary1 extends \TAccountChecker
{
    public const RESERVATIONDATETIME_FORMAT = 'D, M, j, Y g:i A T';
    public const DATE_FORMAT = 'D, M j Y';
    public const TIME_FORMAT = 'g:i A';
    public const CHEAP_TICKETS = 'CheapTickets';
    public $mailFiles = "cheaptickets/it-1.eml, cheaptickets/it-1604364.eml, cheaptickets/it-1605358.eml, "
            . "cheaptickets/it-1605365.eml, cheaptickets/it-1610262.eml, cheaptickets/it-1665677.eml, "
            . "cheaptickets/it-1672991.eml, cheaptickets/it-1680748.eml, cheaptickets/it-1917169.eml, "
            . "cheaptickets/it-2.eml, cheaptickets/it-3.eml, cheaptickets/it-4.eml, cheaptickets/it-5.eml,"
            . "cheaptickets/it-4294343.eml, cheaptickets/it-4378856.eml";
    public $cheapTicketsRecordLocator;
    public $itineraries = [];
    public $totalChargeStr;

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && (strcasecmp($headers["from"], "travelercare@cheaptickets.com") == 0);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return preg_match('#CheapTickets\s+record\s+locator#', $this->http->Response['body']);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->dateYear = date('Y', strtotime($parser->getHeader('date')));
        // Find air trip reservation segments
        $text = text($this->http->Response['body']);
        $airTripNodes = $this->http->XPath->query("//text()[normalize-space(.) = 'Depart']/ancestor::td[1]");
        $recordLocators = [];

        if ($airTripNodes->length > 0) {
            if (preg_match_all('#(.*)\s+record\s+locator:\s+([\w\-]+)#i', $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $name = nice($m[1]);
                    $value = nice($m[2]);

                    if ($name == $this::CHEAP_TICKETS) {
                        $this->cheapTicketsRecordLocator = $value;
                    } else {
                        $recordLocators[$name] = $value;
                    }
                }
            }
        }

        // Find hotel reservation segment
        $xpath = '//text()[contains(., "Hotel Information")]/ancestor::tr[1][not(contains(., "No hotel selected"))]/following-sibling::tr[1 and not(contains(., "PRINT THIS ENTIRE PAGE"))]';
        $hotelNodes = $this->http->XPath->query($xpath);

        // Check whether there is only one reservation and we could count total sum, or we should ignore it
        if (count($recordLocators) + $hotelNodes->length == 1) {
            $xpath = "//text()[normalize-space(.) = 'Total due at booking']/ancestor::tr[1]/td[last()]";
            $this->totalChargeStr = $this->http->FindSingleNode($xpath);
        }

        if ($airTripNodes->length > 0) {
            $this->parseAirTrip($airTripNodes, $recordLocators);
        }

        if ($hotelNodes->length > 0) {
            $node = $hotelNodes->item(0);
            $this->parseHotel($node);
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $this->itineraries,
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]cheaptickets\.com/", $from);
    }

    private function parseAirTrip($nodes, $recordLocators)
    {
        $text = text($this->http->Response['body']);

        if (preg_match_all('#Traveler\s+\d+\s+(.*)#', $text, $m)) {
            $passengers = preg_replace('#\s+#', ' ', $m[1]);
        }

        $reservationDateString = $this->http->FindPreg("/This reservation was made on ([^<\.]*)/");
        $reservationDate =
            $this->_buildDate(date_parse_from_format(self::RESERVATIONDATETIME_FORMAT,
                $reservationDateString));

        $xpath = "//strong[contains(./text(), 'Flight Confirmation')
								or contains(./text(), 'Flight Booking Request')
								or contains(./text(), 'Package Summary')]";
        $depYearString = $this->http->FindSingleNode($xpath, null, null, '/[0-9]{4}/');

        if (empty($depYearString)) {
            $depYearString = $this->dateYear;
        }

        $depDateString = "";

        foreach ($nodes as $segment) {
            $itinerary = [];

            $itinerary['Kind'] = 'T';

            $itinerary['Passengers'] = $passengers;

            if ($this->totalChargeStr !== null) {
                $itinerary['TotalCharge'] = cost($this->totalChargeStr);
                $itinerary['Currency'] = currency($this->totalChargeStr);
            }

            //$itinerary['ReservationDate'] = $reservationDate;

            $newTripSegment = [];

            $flight = $this->http->FindSingleNode("./ancestor::tr[1]/td[4]//br[1]/preceding-sibling::node()[1]", $segment);

            if (empty($flight)) {
                $flight = $this->http->FindSingleNode("./ancestor::tr[1]/td[4]/span[1]", $segment);
            }

            if (preg_match("/^\s*(.*?)\s+([0-9]+)/", $flight, $matches)) {
                $airlineName = trim($matches[1]);
                $newTripSegment['AirlineName'] = $airlineName;

                if (isset($recordLocators[$airlineName])) {
                    $itinerary['RecordLocator'] = $recordLocators[$airlineName];
                } else {
                    $itinerary['RecordLocator'] = $this->cheapTicketsRecordLocator;
                }
                $newTripSegment['FlightNumber'] = trim($matches[2]);
            }

            $xpath = "./ancestor::tr[1]/following-sibling::tr[contains(., 'Arrive') or contains(., 'Stop')]";
            $arrival = $this->http->XPath->query($xpath, $segment)->item(0);

            $depInfo = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]/td[2]", $segment);
            $arrInfo = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $arrival);

            foreach (['Dep' => $depInfo, 'Arr' => $arrInfo] as $pref => $subj) {
                $regex = '#(?P<Name>.*)\s+\((?P<Code>\w+)\)(?:\s+\|\s+(?P<Terminal>.*))?#';

                if (preg_match($regex, $subj, $m)) {
                    $newTripSegment[$pref . 'Code'] = $m['Code'];
                    $newTripSegment[$pref . 'Name'] = $m['Name'];

                    if (isset($m['Terminal'])) {
                        //$newTripSegment[$pref.'Name'] .= ' ('.$m['Terminal'].')';
                        $newTripSegment[['Dep' => 'Departure', 'Arr' => 'Arrival'][$pref] . 'Terminal'] = $m['Terminal'];
                    }
                }
            }

            $s = $this->http->FindSingleNode(
                "./ancestor::tr[1]/preceding-sibling::tr[1]/
                    td[contains(., 'Leave') or contains(., 'Return') or contains(., 'Flight')]/following-sibling::td[1]", $segment);

            if ($s !== null) {
                $depDateString = strtotime(preg_replace('/\w+,\s*/', '', $s) . ", $depYearString");
            } // else date is taken from previous trip segment

            $depTimeString = $this->http->FindSingleNode("./ancestor::node()[1]/following-sibling::tr[1]/td[1]", $segment);
            $newTripSegment['DepDate'] = strtotime($depTimeString, $depDateString);

            if (isset($lastDate) && $newTripSegment['DepDate'] < $lastDate) {
                while ($newTripSegment['DepDate'] < $lastDate) {
                    $newTripSegment['DepDate'] = strtotime('+1 year', $newTripSegment['DepDate']);
                }
            } else {
                $lastDate = $newTripSegment['DepDate'];
            }

            $arrTimeString = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $arrival);

            $newTripSegment['ArrDate'] = strtotime($arrTimeString, $newTripSegment['DepDate']);

            $depDateString = $newTripSegment['ArrDate'];

            if ($dayShift = $this->http->FindSingleNode(
                    "./ancestor::tr[1]/following-sibling::tr[6]//span[contains(./text(), 'overnight flight')]",
                    $segment,
                    null,
                    '/\(([0-9]+)\)/')) {
                $newTripSegment['ArrDate'] += $dayShift * 60 * 60 * 24;
            }

            $cabinAndAircraft =
                $this->http->FindSingleNode("./ancestor::tr[1]/td[4]//br[1]/following-sibling::node()[1]", $segment);

            if ($cabinAndAircraft) {
                [$cabin, $aircraft] = explode('|', $cabinAndAircraft);
                $newTripSegment['Aircraft'] = trim($aircraft);
                $newTripSegment['Cabin'] = trim($cabin);
            }

            $milesAndDuration =
                $this->http->FindSingleNode("./ancestor::tr[1]/td[4]//br[2]/following-sibling::node()[1]", $segment);

            if ($milesAndDuration) {
                [$miles, $duration] = explode('|', $milesAndDuration);

                if (preg_match("/[0-9,]+/", $miles, $matches)) {
                    $newTripSegment['TraveledMiles'] = (float) str_replace(',', '', $matches[0]);
                }
                $newTripSegment['Duration'] = trim($duration);
            }

            if (($m = $this->http->FindSingleNode(
                    "./ancestor::tr[1]/following-sibling::tr[4]/td[1]",
                    $segment,
                    null,
                    '/Meal: (.*)/'))
                !== null) {
                $newTripSegment['Meal'] = $m;
            }

            if (($s = $this->http->FindSingleNode(
                    "./ancestor::tr[1]/following-sibling::tr[4]/td[1]",
                    $segment,
                    null,
                    '/Seats: ([0-9]+[A-Z])/'))
                !== null) {
                $newTripSegment['Seats'] = $s;
            }

            $itinerary['TripSegments'][] = $newTripSegment;
            $this->itineraries[] = $itinerary;
        }

        $this->itineraries = uniteAirSegments($this->itineraries);
    }

    private function parseHotel($node)
    {
        $text = implode("\n", $this->http->FindNodes(".//text()", $node));
        $reservation = [];
        $reservation['Kind'] = 'R';

        $regex = '#Hotel\s+confirmation\s+for\s+room\s+held\s+under\s+(.*?)\s*:\s+([\w\-]+)#';

        if (preg_match_all($regex, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $reservation['ConfirmationNumbers'][] = nice($m[2]);
                $reservation['GuestNames'][] = nice($m[1]);
            }

            if (isset($reservation['ConfirmationNumbers']) && count($reservation['ConfirmationNumbers']) > 0) {
                $reservation['ConfirmationNumber'] = $reservation['ConfirmationNumbers'][0];
            }
        } else {
            if (preg_match_all('#(.*)\s+must\s+check\s+in\s+to\s+this\s+room#i', $text, $m)) {
                $reservation['GuestNames'] = nice($m[1]);
            }
            $reservation['ConfirmationNumber'] = re('#Hotel\s+confirmation\s+number:\s*([\w\-]+)#i', $text);
        }

        $reservation['HotelName'] = re('#\s+(.*)\s+hotel details#', $text);

        $reservation['Address'] = nice(re('#(.*)\s*Phone:.*#', $text));
        $reservation['Phone'] = nice(re('#\s+Phone:\s*([\d\+\-\(\) .]{5,})\b#', $text));
        $reservation['Fax'] = nice(re('#\s+Fax:\s*([\d\+\-\(\) .]{5,})\b#', $text));

        if (preg_match('#Room\(s\):\s+(\d+)\s+\|\s+Guest\(s\)\s+(\d+)#', $text, $m)) {
            $reservation['Rooms'] = (int) $m[1];
            $reservation['Guests'] = (int) $m[2];
        }

        $reservation['RoomType'] = re('#Room\s+description:\s+(.*)\s+#', $text);

        $regex = '#';
        $regex .= 'Check-in:\s+\w+,\s+(?P<CheckInDate>\w+\s+\d+,\s+\d+)\s+\|\s+';
        $regex .= 'Check-out:\s+\w+,\s+(?P<CheckOutDate>\w+\s+\d+,\s+\d+)\s+';
        $regex .= 'Hotel\s+check-in/check-out:\s+';
        $regex .= '(?P<CheckInHour>\d{1,2}):?(?P<CheckInMin>\d{2})\s*(?P<CheckInAmPm>am|pm)?\s+';
        $regex .= '(?P<CheckOutHour>\d{1,2}):?(?P<CheckOutMin>\d{2})\s*(?P<CheckOutAmPm>am|pm)?';
        $regex .= '#i';

        if (preg_match($regex, $text, $m)) {
            foreach (['CheckIn', 'CheckOut'] as $key) {
                $subj = $m["${key}Date"] . ', ' . $m["${key}Hour"] . ':' . $m["${key}Min"];

                if (isset($m["${key}AmPm"]) and $m["${key}AmPm"]) {
                    $subj .= $m["${key}AmPm"];
                } elseif ($m["${key}Hour"] < 9) {
                    $subj .= ' PM';
                }
                $reservation["${key}Date"] = strtotime($subj);
            }
        }

        $regex = '#((?:The\s+following\s+policies\s+apply\s+to\s+the\s+room\s+held\s+for.*?)?Cancellation:)#s';
        $cancellationPolicies = splitter($regex, $text);
        $subj = preg_replace('#\n{2,3}#', "\n", join("\n", $cancellationPolicies));
        $subj = preg_replace('#\n[^\n\S]+#', "\n", $subj);

        if (stripos($subj, 'following') === false) {
            $subj = preg_replace('#^\s*Cancellation:\s+#', '', $subj);
            $subj = preg_replace('#\s+Guarantee:\s+.*#', '', $subj);
            $subj = nice($subj);
        }
        $reservation['CancellationPolicy'] = $subj;

        if ($this->totalChargeStr) {
            $reservation['Total'] = cost($this->totalChargeStr);
            $reservation['Currency'] = currency($this->totalChargeStr);
        }

        $this->itineraries[] = $reservation;
    }

    private function _buildDate($parsedDate)
    {
        return mktime((isset($parsedDate['hour'])) ? $parsedDate['hour'] : 0, (isset($parsedDate['minute'])) ? $parsedDate['minute'] : 0, 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }
}
