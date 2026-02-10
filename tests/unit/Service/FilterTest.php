<?php


namespace Tests\Unit\Service;

use AwardWallet\Common\API\Filter\Filter;
use AwardWallet\Schema\Itineraries\Flight;
use Codeception\TestCase\Test;
use JMS\Serializer\Serializer;

class FilterTest extends Test
{

    public function testCheckFlightDates()
    {
        /** @var Serializer $jms */
        $jms = $this->getModule('Symfony')->grabService('jms_serializer');
        $json = '{"providerInfo":{"code":"aeroflot","name":"Aeroflot"},"segments":[{"departure":{"airportCode":"PEE","name":"Perm International Airport","localDateTime":"2030-01-01T14:00:00","address":{"text":"PEE","city":"Perm","countryName":"Russian Federation","countryCode":"RU","lat":57.920026,"lng":56.019179,"timezone":18000,"timezoneId":"Asia\/Yekaterinburg"}},"arrival":{"airportCode":"DME","name":"Moscow Domodedovo Airport","localDateTime":"2030-01-01T16:00:00","address":{"text":"DME","city":"Moscow","countryName":"Russian Federation","countryCode":"RU","lat":55.414566,"lng":37.899494,"timezone":10800,"timezoneId":"Europe\/Moscow"}},"marketingCarrier":{"airline":{"name":"Aeroflot","iata":"SU","icao":"AFL"},"flightNumber":"123","confirmationNumber":"AABBCC"},"calculatedTraveledMiles":705,"calculatedDuration":240,"flightStatsMethodUsed":"ScheduleByFlight"}],"type":"flight"}';
        /** @var Flight $it */
        $it = $jms->deserialize($json, Flight::class, 'json');
        $this->assertEquals(1, count((new Filter())->filter([$it])));
        $it->segments[0]->arrival->localDateTime = '2030-01-01T12:00:00';
        $this->assertEquals(0, count((new Filter())->filter([$it])));
    }

}