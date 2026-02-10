<?php


namespace AppBundle\VersionConverter\ItineraryConverter;


use AwardWallet\Common\Itineraries\Address;
use AwardWallet\Common\Parsing\Solver\Extra\GeoData;
use AwardWallet\Schema\Parser\Common as P;

class Util
{

	const DATE_FORMAT = 'Y-m-d\TH:i:s';

    protected static $cancelledTypes = [
        P\Flight::class => 'flight',
        P\Cruise::class => 'cruise',
        P\Hotel::class => 'hotelReservation',
        P\Rental::class => 'carRental',
        P\Bus::class => 'bus',
        P\Train::class => 'train',
        P\Transfer::class => 'transfer',
        P\Event::class => 'event',
    ];

	public static function getCancelledType(P\Itinerary $parsed)
    {
        return self::$cancelledTypes[get_class($parsed)];
    }

	public static function date($time)
    {
		if (!$time)
			return null;
		return date(self::DATE_FORMAT, $time);
	}

	public static function intval($value)
    {
	    return (null !== $value) ? intval($value) : null;
    }

    public static function floatval($value)
    {
        return (null !== $value) ? floatval($value) : null;
    }

	public static function emptyAddress(array $lines)
    {
	    $a = new Address();
	    $lines = array_filter($lines);
	    if (count($lines) > 0) {
            $a->text = array_shift($lines);
            return $a;
        }
        return null;
    }

	public static function address($text, GeoData $geo) : Address
    {
		$address = new Address();
		$address->text = $text;
		$address->addressLine = $geo->address;
		$address->city = $geo->city;
		$address->stateName = $geo->state;
		$address->countryName = $geo->country;
		$address->postalCode = $geo->zip;
		$address->timezone = $geo->tz;
		$address->lat = $geo->lat;
		$address->lng = $geo->lng;
		return $address;
	}

}