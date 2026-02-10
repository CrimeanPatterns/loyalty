<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 26.05.16
 * Time: 14:33
 */

namespace AppBundle\ModelConverter;


use AwardWallet\Common\Geo\GoogleGeo;
use AwardWallet\Common\Itineraries\Cruise;
use AwardWallet\Common\Itineraries\CruiseDetails;
use AwardWallet\Common\Itineraries\Flight;
use AwardWallet\Common\Itineraries\FlightPoint;
use AwardWallet\Common\Itineraries\FlightSegment;
use AwardWallet\Common\Itineraries\Itinerary;
use AwardWallet\Common\Itineraries\PersonsCollection;
use AwardWallet\Common\Itineraries\SegmentsCollection;
use AwardWallet\Common\Itineraries\Transport;
use Psr\Log\LoggerInterface;

class TripConverter implements ConverterInterface
{

    const TRIP_CATEGORY_TYPE = [
        TRIP_CATEGORY_BUS => 'bus',
        TRIP_CATEGORY_TRAIN => 'train',
        TRIP_CATEGORY_FERRY => 'ferry',
        TRIP_CATEGORY_CRUISE => 'ship',
        TRIP_CATEGORY_AIR => 'aircraft',
        TRIP_CATEGORY_TRANSFER => 'transport',
    ];

    static public function convert(array $itinerary, array $options, GoogleGeo $googleGeo, LoggerInterface $logger){
        $props = array("AirlineName", "Operator", "Aircraft", "TraveledMiles", "Cabin", "BookingClass", "Duration", "Meal", "Smoking", "Stops", "FlightNumber", "PendingUpgradeTo");
        $tripCategory = ArrayVal($itinerary, 'TripCategory', TRIP_CATEGORY_AIR);
        if ($tripCategory == TRIP_CATEGORY_CRUISE) {
            $result = new Cruise($logger);
            $props = [];

            $details = new CruiseDetails($logger);
            $mapping = [
                'ShipName' => 'ship',
                'ShipCode' => 'shipCode',
                'CruiseName' => 'description',
                'RoomClass' => 'class',
                'RoomNumber' => 'room',
                'Deck' => 'deck',
            ];
            foreach($mapping as $field => $property)
                if (!empty($itinerary[$field]))
                    $details->$property = $itinerary[$field];

            $result->cruiseDetails = $details;;
        }
        else
            $result = new Flight($logger);

        if (in_array($tripCategory, [TRIP_CATEGORY_BUS, TRIP_CATEGORY_TRAIN, TRIP_CATEGORY_FERRY, TRIP_CATEGORY_TRANSFER])) {
            $result->type = 'transportation';
            $props = ["TraveledMiles", "FlightNumber", "Cabin", "BookingClass", "Duration", "Meal", "Smoking", "Stops", "PendingUpgradeTo"];
        }

        $result->providerDetails = ProviderDetailsConverter::createFromOptions(
            $itinerary['RecordLocator'], $options, $itinerary, $logger
        );

        if (!empty($itinerary['Passengers'])) {
            $result->travelers = new PersonsCollection($logger);
            if (!is_array($itinerary['Passengers']))
                $itinerary['Passengers'] = explode(', ', $itinerary['Passengers']);
            foreach ($itinerary['Passengers'] as $name){
                $person = $result->travelers->add();
                $person->fullName = $name;
            }
        }
        if (!empty($itinerary['TicketNumbers']) && is_array($itinerary['TicketNumbers'])) {
            $tickets = array_filter($itinerary['TicketNumbers'], function($ticket){
                return is_string($ticket) && strlen($ticket) < 50;
            });
            if (count($tickets) > 0)
                $result->ticketNumbers = array_values($tickets);
        }

        $result->totalPrice = TotalConverter::convert($itinerary, $logger);

        if (!empty($itinerary['Status']))
            $result->providerDetails->status = $itinerary['Status'];

        if (isset($itinerary['TripSegments'])){
            $result->segments = new SegmentsCollection($logger);
            foreach ($itinerary['TripSegments'] as $segment){
                $result->segments[] = self::convertSegment($segment, $props, $tripCategory, $googleGeo, $logger);
            }
        }

        return $result;
    }

    /**
     * @param array $segment
     * @param array $props
     * @param int $tripCategory
     * @param GoogleGeo $googleGeo
     * @param LoggerInterface $logger
     * @return FlightSegment
     */
    static public function convertSegment(array $segment, array $props, $tripCategory, GoogleGeo $googleGeo, LoggerInterface $logger) {
        $result = new FlightSegment($logger);
        $result->departure = self::convertPoint($segment, "Dep", $tripCategory, $googleGeo, $logger);
        $result->arrival = self::convertPoint($segment, "Arr", $tripCategory, $googleGeo, $logger);
        if (!empty($segment['Seats'])) {
            if (is_array($segment['Seats']))
                $result->seats = $segment['Seats'];
            else
                $result->seats = explode(", ", $segment['Seats']);
        }
        if (isset($segment["FlightNumber"]) && $segment["FlightNumber"] === FLIGHT_NUMBER_UNKNOWN)
            unset($segment["FlightNumber"]);

        if (isset($segment["FlightNumber"]) && $tripCategory !== TRIP_CATEGORY_AIR){
            $result->scheduleNumber = $segment["FlightNumber"];
            unset($segment["FlightNumber"]);
        }

        foreach ($props as $key){
            if (isset($segment[$key]) && trim($segment[$key]) != '') {
                $property = lcfirst($key);
                $result->$property = $segment[$key];
            }
        }

        if ($tripCategory != TRIP_CATEGORY_AIR && $tripCategory != TRIP_CATEGORY_CRUISE) {
            $tripTypes = self::TRIP_CATEGORY_TYPE;
            $transport = new Transport($logger);
            $transport->type = $tripTypes[$tripCategory];
            if (!empty($segment['Type']))
                $transport->name = $segment['Type'];
            if (!empty($segment['Vehicle']))
                $transport->vehicleClass = $segment['Vehicle'];

            $result->transport = $transport;
        }
        return $result;
    }

    /**
     * @param array $segment
     * @param string $prefix
     * @param int $tripCategory
     * @param GoogleGeo $googleGeo
     * @param LoggerInterface $logger
     * @return FlightPoint
     */
    static public function convertPoint(array $segment, $prefix, $tripCategory, GoogleGeo $googleGeo, LoggerInterface $logger) {
        $code = isset($segment[$prefix . 'Code']) && $segment[$prefix . 'Code'] !== TRIP_CODE_UNKNOWN ? $segment[$prefix . 'Code'] : null;

        $point = new FlightPoint($logger);
        $point->airportCode = !empty($code) && $tripCategory === TRIP_CATEGORY_AIR ? $code : null;
        $point->stationCode = !empty($code) && $tripCategory !== TRIP_CATEGORY_AIR ? $code : null;

        $point->name = isset($segment[$prefix . 'Name']) ? $segment[$prefix . 'Name'] : null;

        if (!isset($segment[$prefix . 'Date']) || $segment[$prefix . 'Date'] == MISSING_DATE)
            $point->localDateTime = null;
        else
            $point->localDateTime = date(Itinerary::ISO_DATE_FORMAT, $segment[$prefix . 'Date']);

		if ($tripCategory === TRIP_CATEGORY_AIR)
			$googleGeo->getGeoCoder()->LookupAirPort($point->airportCode, $fields);
		else
            $fields['Name'] = $point->name;

        if (isset($fields['Name'])) {
            $point->address = AddressConverter::createObjFromText(
                !empty($segment[$prefix . 'Address']) ? $segment[$prefix . 'Address'] : $fields['Name'],
                $point->airportCode,
                $googleGeo, $logger
            );
        }

        $terminalPrefix = [
            'Arr' => 'Arrival',
            'Dep' => 'Departure'
        ];
        if(isset($segment[$terminalPrefix[$prefix] . 'Terminal']))
            $point->terminal = $segment[$terminalPrefix[$prefix] . 'Terminal'];

        return $point;
    }

}