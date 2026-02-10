<?php

namespace AwardWallet\Engine\expedia\Email;

class NewAirTrip extends \TAccountChecker
{
    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers['subject']) && stripos($headers['subject'], 'expedia') !== false)
            || (isset($headers['from']) && stripos($headers['from'], '@expedia') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response['body'], '@expedia') !== false
               && stripos($parser->getPlainBody(), 'Flight') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'expedia') !== false;
    }

    /**
     * @example expedia/it-2.eml
     * @example expedia/it-34.eml
     * @example expedia/it-35.eml
     * @example expedia/it-36.eml
     * @example expedia/it-37.eml
     * @example expedia/it-38.eml
     * @example expedia/it-39.eml
     */
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->XPath->registerNamespace('php', 'http://php.net/xpath');
        $this->http->XPath->registerPhpFunctions(['stripos', 'CleanXMLValue']);
        $http = $this->http;
        $xpath = $http->XPath;
        /** @var \AwardWallet\ItineraryArrays\AirTrip $segment */
        $result = [];
        // parse Flights
        $passengers = array_values(array_filter($http->FindNodes("//*[(contains(text(), 'Traveler') or contains(text(), 'Traveller')) and contains(text(), ' name(s):')]/ancestor::tr[1]/following-sibling::tr[1]//tr/td[position()=last()]"), 'strlen'));

        if (empty($passengers)) {
            $passengers = [$http->FindSingleNode('//*[contains(text(), "Main contact:")]', null, true, '/Main\s*contact:\s*(.+)/ims')];
        }
        $flightSegmentsNodes = $xpath->query('//img[contains(@src, "flight_icon")]/ancestor::table[2]');

        foreach ($flightSegmentsNodes as $flightSegmentNode) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $segment */
            $segment = [];
            $segment['FlightNumber'] = $http->FindSingleNode('.//*[contains(text(), "Flight Number:")]', $flightSegmentNode, false, '/^Flight Number:\s+?(.+)$/ims');
            $segment['Duration'] = $http->FindSingleNode('.//*[contains(text(), "Duration")]', $flightSegmentNode, false, '/^Duration:?\s+?(.+)$/ims');
            $recordLocator = $http->FindSingleNode('.//*[contains(text(), "Confirmation Code:")]', $flightSegmentNode, false, '/^Confirmation Code:\s+(\w+)$/ims');
            $segment['AirlineName'] = $http->FindSingleNode('.//*[contains(text(), "Confirmation Code:")]
                /ancestor::tr[1]
                    /preceding-sibling::tr[
                        not(contains(string(), "Check-in")) and
                        string-length(php:functionString("CleanXMLValue", string())) > 0
                    ]
            ', $flightSegmentNode);

            if (empty($segment['AirlineName'])) {
                $segment['AirlineName'] = $http->FindSingleNode('.//*[contains(text(), "Operated By")]', $flightSegmentNode, true, '/Operated By\s*(.+)/ims');
            }

            foreach ([['Dep', 'Depart', 2], ['Arr', 'Arrive', 6]] as $varNames) {
                [$Dep, $Depart, $dateIdx] = $varNames;
                // Rome (FCO)
                if (preg_match('/^(.+)\s+?\((\w+)\)?$/ims', $http->FindSingleNode(".//*[contains(text(), '{$Depart}')]/following::text()[normalize-space()][1]", $flightSegmentNode), $matches)) {
                    $segment["{$Dep}Name"] = $matches[1];
                    $segment["{$Dep}Code"] = $matches[2];
                }

                $segment["{$Dep}Date"] = strtotime(str_replace(',', '',
                    $http->FindSingleNode(".//*[contains(text(), '{$Depart}')]/ancestor::tr[1]/following-sibling::tr[4]/td[{$dateIdx}]", $flightSegmentNode) . ' ' .
                    $http->FindSingleNode(".//*[contains(text(), '{$Depart}')]/ancestor::tr[1]/following-sibling::tr[2]/td[{$dateIdx}]", $flightSegmentNode)
                ));
            }

            // 12A, 34B, Economy/Coach Class, Airbus A123
            // Seat Unassigned, Economy/Coach Class, Avro RJ85
            if (preg_match('/^((Seat Unassigned)|((\d+[a-z]+(, )?)+)),\s+?([^,]+) Class, (.+)$/ims', $http->FindSingleNode('.//*[contains(text(), " Class, ")]', $flightSegmentNode), $matches)) {
                if (!empty($matches[3])) {
                    $segment['Seats'] = $matches[3];
                }
                $segment['Cabin'] = $matches[6];
                $segment['Aircraft'] = $matches[7];
            }

            if (isset($result[$recordLocator])) {
                $result[$recordLocator]['TripSegments'][] = $segment;
            } else {
                $result[$recordLocator] = [
                    'Kind'          => 'T',
                    'RecordLocator' => $recordLocator,
                    'Passengers'    => $passengers,
                    'TripSegments'  => [
                        $segment,
                    ],
                ];
            }
        }
        $itineraries = [];

        foreach ($result as $recordLocator => $itinerary) {
            if (empty($recordLocator)) {
                $itinerary['RecordLocator'] = TRIP_CODE_UNKNOWN;
            }
            $itineraries[] = $itinerary;
        }

        // Dmitry Vinokurov: toggled off parser as it duplicates functions of emailMultiItineraryChecker.php
//        return ['parsedData' => [
//            'Itineraries' => $itineraries,
//        ]];
    }
}
