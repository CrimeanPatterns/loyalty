<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 26.05.16
 * Time: 14:39
 */

namespace AppBundle\ModelConverter;

use AwardWallet\Common\Geo\GoogleGeo;
use AwardWallet\Common\Itineraries\Event;
use AwardWallet\Common\Itineraries\Itinerary;
use AwardWallet\Common\Itineraries\PersonsCollection;
use Psr\Log\LoggerInterface;

class RestaurantConverter implements ConverterInterface
{

    static public function convert(array $itinerary, array $options, GoogleGeo $googleGeo, LoggerInterface $logger){
        $result = new Event($logger);
        $result->providerDetails = ProviderDetailsConverter::createFromOptions(
            $itinerary['ConfNo'], $options, $itinerary, $logger
        );

        $addr = ArrayVal($itinerary, 'Address');
        $result->address = AddressConverter::createObjFromText($addr, null, $googleGeo, $logger);

        $result->eventName = $itinerary['Name'];
        $result->startDateTime = date(Itinerary::ISO_DATE_FORMAT, $itinerary['StartDate']);
        if(!empty($itinerary['EndDate']))
            $result->endDateTime = date(Itinerary::ISO_DATE_FORMAT, $itinerary['EndDate']);

        if(isset($itinerary['Phone']))
            $result->phone = $itinerary['Phone'];
        if(isset($itinerary['Fax']))
            $result->fax = $itinerary['Fax'];
        if(isset($itinerary['EventType']))
            $result->eventType = $itinerary['EventType'];

        if(!empty($itinerary['Guests']))
            $result->guestCount = $itinerary['Guests'];

        $result->totalPrice = TotalConverter::convert($itinerary, $logger);


        if(isset($itinerary['DinerName'])){
            $result->guests = new PersonsCollection($logger);
            $person = $result->guests->add();
            $person->fullName = $itinerary['DinerName'];
        }

        return $result;
    }

}