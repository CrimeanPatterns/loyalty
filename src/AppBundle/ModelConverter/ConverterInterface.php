<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 29.05.16
 * Time: 20:21
 */

namespace AppBundle\ModelConverter;

use AwardWallet\Common\Geo\GoogleGeo;
use AwardWallet\Common\Itineraries\Itinerary;
use Psr\Log\LoggerInterface;

interface ConverterInterface {

    /**
     * @param array $itinerary
     * @param array $options
     * @param GoogleGeo $googleGeo
     * @param LoggerInterface $logger
     * @return Itinerary
     */
    static public function convert(array $itinerary, array $options, GoogleGeo $googleGeo, LoggerInterface $logger);

}