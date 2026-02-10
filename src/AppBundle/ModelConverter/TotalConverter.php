<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 26.05.16
 * Time: 15:02
 */

namespace AppBundle\ModelConverter;

use AwardWallet\Common\Itineraries\FeesCollection;
use AwardWallet\Common\Itineraries\TotalPrice;
use Psr\Log\LoggerInterface;

class TotalConverter
{
    static public function convert(array $itinerary, LoggerInterface $logger){
        $map = [
            'TotalCharge' => 'total', // trip
            'Total' => 'total', // reservation
            'Currency' => 'currencyCode',
            'BaseFare' => 'cost', // trip
            'Cost' => 'cost', // reservation
            'Tax' => 'tax', // trip
            'TotalTaxAmount' => 'tax', // rental
            'Taxes' => 'tax', // reservation
            'Rate' => 'rate', // reservation
            'RateType' => 'rateType', // reservation
            'SpentAwards' => 'spentAwards',
            'Discount' => 'discount',
        ];
        $totalPrice = new TotalPrice($logger);
        foreach ($map as $fromKey => $toKey) {
            if (empty($itinerary[$fromKey]))
                continue;

            $totalPrice->$toKey = $itinerary[$fromKey];
        }

        if(isset($itinerary['Fees'])){
            $totalPrice->fees = new FeesCollection($logger);
            foreach($itinerary['Fees'] as $row){
                if(isset($row['Key']) and $row['Key'] == 'More')
                    continue;
                $equip = $totalPrice->fees->add();
                $equip->name = $row['Name'];
                $equip->charge = $row['Charge'];
            }
        }

        return $totalPrice;
    }

}