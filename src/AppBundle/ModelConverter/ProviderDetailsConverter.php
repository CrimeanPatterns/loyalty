<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 26.05.16
 * Time: 15:00
 */

namespace AppBundle\ModelConverter;

use AwardWallet\Common\Itineraries\ProviderDetails;
use Psr\Log\LoggerInterface;

class ProviderDetailsConverter
{

    static public function createFromOptions($confNo, array $options, array $itinerary, LoggerInterface $logger){
        $details = new ProviderDetails($logger);
        if ($confNo !== CONFNO_UNKNOWN)
            $details->confirmationNumber = $confNo;
        if(isset($options['Provider'])){
            $details->name = $options['Provider']['ProviderName'];
            $details->code = $options['Provider']['ProviderCode'];
        }
        if(isset($itinerary['ConfirmationNumbers']))
            $details->confirmationNumbers = is_array($itinerary['ConfirmationNumbers']) ? $itinerary['ConfirmationNumbers'] : explode(', ', $itinerary['ConfirmationNumbers']);
        if(isset($itinerary['ReservationDate']))
            $details->reservationDate = date('Y-m-d\TH:i:s', $itinerary['ReservationDate']);
        if(isset($itinerary['AccountNumbers']) && is_array($itinerary['AccountNumbers']))
            $itinerary['AccountNumbers'] = implode(", ", $itinerary['AccountNumbers']);

        $map = [
            'TripNumber' => 'tripNumber',
            'AccountNumbers' => 'accountNumbers',
            'Status' => 'status',
            'EarnedAwards' => 'earnedAwards',
        ];
        foreach ($map as $fromKey => $toKey)
            if (isset($itinerary[$fromKey]))
                $details->$toKey = $itinerary[$fromKey];

        return $details;
    }

}