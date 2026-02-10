<?php
namespace AppBundle\ModelConverter;
use AwardWallet\Common\Geo\GoogleGeo;
use AwardWallet\Common\Itineraries\Address;
use Psr\Log\LoggerInterface;

/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 26.05.16
 * Time: 14:24
 */
class AddressConverter
{

    static public function createObjFromText($text, $airportCode = null, GoogleGeo $googleGeo, LoggerInterface $logger){
        $object = new Address($logger);
        $object->text = $text;
        // remove duplicate city entries (City, 1234 US City)
        if (preg_match('/^([^\s,]+)(.+)$/', $text, $m) && strlen($m[1]) > 4 && stripos($m[2], $m[1]) !== false)
            $text = trim($m[2], ', ');
        // gonna see how this works out (update: it didnt)
        //if (!isset($airportCode) && preg_match('/^(?<code>[A-Z]{3})\b/', $text, $matches))
        //	$airportCode = $matches['code'];
        if($googleGeo->GoogleGeoTagLimitOk() && (!empty($airportCode) || !empty($text))){
            $detailedAddress = $googleGeo->FindGeoTag(empty($airportCode) ? $text : $airportCode);
            if(isset($detailedAddress['Lat'])){
                $object->lat = round($detailedAddress['Lat'], 7);
                $object->lng = round($detailedAddress['Lng'], 7);
                if (isset($detailedAddress['City']))
                    $object->city = $detailedAddress['City'];
                if (isset($detailedAddress['AddressLine']))
                    $object->addressLine = $detailedAddress['AddressLine'];
                if (isset($detailedAddress['State']))
                    $object->stateName = $detailedAddress['State'];
                if (isset($detailedAddress['Country']))
                    $object->countryName = $detailedAddress['Country'];
                if(!empty($detailedAddress['PostalCode']))
                    $object->postalCode = $detailedAddress['PostalCode'];
                if (isset($detailedAddress['TimeZone']))
                    $object->timezone = intval($detailedAddress['TimeZone']);
                // extract addressLine from weird addressed like 'Via Vittorio Bragadin Snc Fiumicino, Italy'
                if (empty($object->addressLine))
                    self::parseAddressLine($object);
            }
        }

        return $object;
    }

    static protected function parseAddressLine(Address &$object) {
        $pos = null;
        foreach (["city", "stateName", "countryName"] as $field)
            if (!empty($object->$field)) {
                $find = strpos($object->text, $object->$field);
                if ($find !== false && (!isset($pos) || $pos > $find))
                    $pos = $find;
            }
        if (isset($pos))
            $line = trim(substr($object->text, 0, $pos));
        if (!empty($line))
            $object->addressLine = $line;
    }

}