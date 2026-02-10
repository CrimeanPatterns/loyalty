<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 26.05.16
 * Time: 14:42
 */

namespace AppBundle\ModelConverter;

use AwardWallet\Common\Geo\GoogleGeo;
use AwardWallet\Common\Itineraries\HotelReservation;
use AwardWallet\Common\Itineraries\Itinerary;
use AwardWallet\Common\Itineraries\PersonsCollection;
use AwardWallet\Common\Itineraries\RoomsCollection;
use Psr\Log\LoggerInterface;

class ReservationConverter implements ConverterInterface
{

    static public function convert(array $itinerary, array $options, GoogleGeo $googleGeo, LoggerInterface $logger) {
        $result = new HotelReservation($logger);
        $result->providerDetails = ProviderDetailsConverter::createFromOptions(
            $itinerary['ConfirmationNumber'], $options, $itinerary, $logger
        );

        $category = ArrayVal($itinerary, "HotelCategory", HOTEL_CATEGORY_STAY);
        if (in_array($category, [HOTEL_CATEGORY_SHOP]))
            $result->type = "hotelShop";

        $addr = ArrayVal($itinerary, 'Address');
        $result->address = AddressConverter::createObjFromText($addr, null, $googleGeo, $logger);
        if (isset($itinerary["DetailedAddress"])) {
            foreach ([
                         "AddressLine" => "addressLine",
                         "City" => "city",
                         "StateProv" => "stateName",
                         "Country" => "countryName",
                     ] as $key => $field) {
                if (isset($itinerary["DetailedAddress"][$key]) && !isset($result->address->$field))
                    $result->address->$field = $itinerary["DetailedAddress"][$key];
            }
        }

        $result->hotelName = $itinerary['HotelName'];
        if (isset($itinerary['CheckInDate']) && $itinerary['CheckInDate'] !== MISSING_DATE)
            $result->checkInDate = date(Itinerary::ISO_DATE_FORMAT, $itinerary['CheckInDate']);
        if (isset($itinerary['CheckOutDate']) && $itinerary['CheckOutDate'] !== MISSING_DATE)
            $result->checkOutDate = date(Itinerary::ISO_DATE_FORMAT, $itinerary['CheckOutDate']);

        if (isset($itinerary['Phone']))
            $result->phone = $itinerary['Phone'];
        if (isset($itinerary['Fax']))
            $result->fax = $itinerary['Fax'];

        if (!empty($itinerary['Guests']))
            $result->guestCount = $itinerary['Guests'];
        if (isset($itinerary['Kids']))
            $result->kidsCount = $itinerary['Kids'];
        if (!empty($itinerary['Rooms']))
            $result->roomsCount = $itinerary['Rooms'];

        if (!empty($itinerary['CancellationPolicy']))
            $result->cancellationPolicy = $itinerary['CancellationPolicy'];

        $result->totalPrice = TotalConverter::convert($itinerary, $logger);

        if (isset($itinerary['RoomType'])) {
            $roomTypes = explode('|', $itinerary['RoomType']);
            if (isset($itinerary['RoomTypeDescription']))
                $roomDescr = explode('|', $itinerary['RoomTypeDescription']);
            $result->rooms = new RoomsCollection($logger);
            foreach ($roomTypes as $i => $type) {
                $room = $result->rooms->add();
                $room->type = $type;
                if (!empty($roomDescr) && count($roomDescr) == count($roomTypes))
                    $room->description = $roomDescr[$i];
            }
        }

        if (isset($itinerary['GuestNames'])) {
            if (is_array($itinerary['GuestNames']))
                $guests = $itinerary['GuestNames'];
            else
                $guests = explode(', ', $itinerary['GuestNames']);
            $result->guests = new PersonsCollection($logger);
            foreach ($guests as $name) {
                $person = $result->guests->add();
                $person->fullName = $name;
            }
        }

        return $result;
    }

}