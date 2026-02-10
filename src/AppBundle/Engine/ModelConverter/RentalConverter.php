<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 26.05.16
 * Time: 14:33
 */

namespace AppBundle\ModelConverter;


use AwardWallet\Common\Geo\GoogleGeo;
use AwardWallet\Common\Itineraries\Car;
use AwardWallet\Common\Itineraries\CarRental;
use AwardWallet\Common\Itineraries\CarRentalDiscountsCollection;
use AwardWallet\Common\Itineraries\CarRentalPoint;
use AwardWallet\Common\Itineraries\FeesCollection;
use AwardWallet\Common\Itineraries\Itinerary;
use AwardWallet\Common\Itineraries\Person;
use Psr\Log\LoggerInterface;

class RentalConverter implements ConverterInterface
{

    static public function convert(array $itinerary, array $options, GoogleGeo $googleGeo, LoggerInterface $logger){
        $result = new CarRental($logger);
            $result->providerDetails = ProviderDetailsConverter::createFromOptions(
                $itinerary['Number'], $options, $itinerary, $logger
            );

        $result->pickup = self::convertPoint('Pickup', $itinerary, $googleGeo, $logger);
        $result->dropoff = self::convertPoint('Dropoff', $itinerary, $googleGeo, $logger);

        $result->totalPrice = TotalConverter::convert($itinerary, $logger);

        if(isset($itinerary['RenterName'])){
            $result->driver = new Person($logger);
            $result->driver->fullName = $itinerary['RenterName'];
        }


        if(isset($itinerary['PricedEquips'])){
            $result->pricedEquipment = new FeesCollection($logger);
            foreach($itinerary['Fees'] as $row){
                if(isset($row['Key']) and $row['Key'] == 'More')
                    continue;
                $equip = $result->pricedEquipment->add();
                $equip->name = $row['Name'];
                $equip->charge = $row['Charge'];
            }
        }

        if(isset($itinerary['Discounts'])){
            $result->discounts = new CarRentalDiscountsCollection($logger);
            foreach($itinerary['Discounts'] as $row){
                $item = $result->discounts->add();
                $item->name = $row['Name'];
                $item->code = $row['Code'];
            }
        }

        if(isset($itinerary['RentalCompany']))
            $result->providerDetails->name = $itinerary['RentalCompany'];

        if(isset($itinerary['CarType']) || isset($itinerary['CarModel']) || isset($itinerary['CarImageUrl'])){
            $result->car = new Car($logger);
            if(isset($itinerary['CarType']))
                $result->car->type = $itinerary['CarType'];
            if(isset($itinerary['CarModel']))
                $result->car->model = $itinerary['CarModel'];
            if(isset($itinerary['CarImageUrl']))
                $result->car->imageUrl = $itinerary['CarImageUrl'];
        }

        if(!empty($itinerary['Status']))
            $result->providerDetails->status = $itinerary['Status'];

        return $result;
    }

    /**
     * @param $prefix
     * @param array $itinerary
     * @param GoogleGeo $googleGeo
     * @param LoggerInterface $logger
     * @return CarRentalPoint
     */
    static public function convertPoint($prefix, array $itinerary, GoogleGeo $googleGeo, LoggerInterface $logger){
        $result = new CarRentalPoint($logger);
        if (!empty($itinerary[$prefix."Location"]))
            $result->address = AddressConverter::createObjFromText($itinerary[$prefix."Location"], null, $googleGeo, $logger);

        if (!empty($itinerary[$prefix.'Datetime']) && $itinerary[$prefix.'Datetime'] !== MISSING_DATE)
            $result->localDateTime = date(Itinerary::ISO_DATE_FORMAT, $itinerary[$prefix.'Datetime']);
        if(isset($itinerary[$prefix."Hours"]))
            $result->openingHours = $itinerary[$prefix."Hours"];
        if(isset($itinerary[$prefix."Phone"]))
            $result->phone = $itinerary[$prefix."Phone"];
        if(isset($itinerary[$prefix."Fax"]))
            $result->fax = $itinerary[$prefix."Fax"];
        return $result;
    }
}