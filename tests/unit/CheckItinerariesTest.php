<?php

namespace Tests\Unit;

use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\ProvidersHelper;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\V2\CheckAccountResponse as V2Response;
use AwardWallet\Common\Itineraries\Cancelled;
use AwardWallet\Common\Itineraries\CarRental;
use AwardWallet\Common\Itineraries\Event;
use AwardWallet\Common\Itineraries\Flight;
use AwardWallet\Common\Itineraries\HotelReservation;
use JMS\Serializer\Serializer;

/**
 * @backupGlobals disabled
 */
class CheckItinerariesTest extends \Tests\Unit\BaseWorkerTestClass
{

    protected function getRequest()
    {
        $request = new CheckAccountRequest();
        return $request->setProvider('testprovider')
            ->setUserid('SomeID')
            ->setParseitineraries(true)
            ->setPassword('g5f4' . rand(1000, 9999) . '_q');
    }

    public function testNoItineraries()
    {
        $request = $this->getRequest()->setLogin('itmaster.no.trle');
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEmpty($response->getItineraries());
        $this->assertEquals(true, $response->getNoitineraries());
    }

    public function testUnknownItineraries()
    {
        $request = $this->getRequest()->setLogin('balance.random');
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEmpty($response->getItineraries());
        $this->assertEquals(0, count($response->getItineraries()));
    }

    public function testCancelItinerary()
    {
        $request = $this->getRequest()->setLogin('Itineraries.Cancelled');
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $worker = $this->getCheckAccountWorker();
        $worker->processRequest($request, $response, $this->row);
        /** @var Serializer $serialiser */
        $serialiser = $this->container->get('jms_serializer');
        $response = $serialiser->serialize($response, 'json');
        /** @var CheckAccountResponse $result */
        $result = $serialiser->deserialize($response, CheckAccountResponse::class, 'json');
        $this->assertEquals(ACCOUNT_CHECKED, $result->getState());
        $this->assertInstanceOf(Flight::class, $result->getItineraries()[0]);
        $this->assertInstanceOf(Cancelled::class, $result->getItineraries()[1]);
    }

    /**
     * @dataProvider dataCheckItineraries
     */
    public function testItineraryItems($login, $cls, $apiVersion)
    {
        codecept_debug("\nLogin: {$login}\nItinerary Class: {$cls}");
        $request = $this->getRequest()->setLogin($login);
        $this->row->setApiVersion($apiVersion);

        if (2 === $apiVersion) {
            $response = new \AppBundle\Model\Resources\V2\CheckAccountResponse();
        } else {
            $response = new CheckAccountResponse();
        }

        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()
            ->processRequest($request, $response, $this->row);

        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $itinerary = $response->getItineraries()[0];
        $this->assertInstanceOf($cls, $itinerary);

        /** @var Serializer $serialiser */
        $serialiser = $this->container->get('jms_serializer');
        $res = $serialiser->serialize($response, 'json');
    }

    public function dataCheckItineraries()
    {
        return [
            ['future.reservation', HotelReservation::class, 1],
            ['future.rental', CarRental::class, 1],
            ['future.restaurant', Event::class, 1],
            ['future.trip', Flight::class, 1],
            ['future.reservation', \AwardWallet\Schema\Itineraries\HotelReservation::class, 2],
            ['future.rental', \AwardWallet\Schema\Itineraries\CarRental::class, 2],
            ['future.restaurant', \AwardWallet\Schema\Itineraries\Event::class, 2],
            ['future.trip', \AwardWallet\Schema\Itineraries\Flight::class, 2],
        ];
    }

    public function testNameFromCode()
    {
        $request = $this->getRequest()->setLogin('TripSegment.AirlineName');
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        //Get by IATA, but use the active one
        $this->assertSame('Air Link', $response->getItineraries()[0]->segments[0]->airlineName);
        //Get by ICAO, but only an inactive airline is available
        $this->assertSame('American Airlines', $response->getItineraries()[0]->segments[1]->airlineName);
        //Just a normal airline name
        $this->assertSame('Normal Airline Name', $response->getItineraries()[0]->segments[2]->airlineName);
    }

    /**
     * @dataProvider dataResponseItWarning
     */
    public function testReponseItWarning($canCheckIt, $warningExists)
    {
        $provider = 'testprov' . rand(1000, 9999);
        /** @var \Helper\Aw $aw */
        $aw = $this->getModule('\Helper\Aw');
        $aw->createAwProvider(null, $provider, ['CanCheckItinerary' => $canCheckIt]);

        $request = $this->getRequest()->setProvider($provider)->setLogin('someTestLogin');
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals($warningExists, !empty($response->getWarnings()));
        $this->assertEquals($warningExists,
            in_array(ProvidersHelper::DO_NOT_SUPPORT_ITINERARIES_WARNING, $response->getWarnings() ?? []));
    }

    public function dataResponseItWarning()
    {
        return [
            [0, true],
            [1, false]
        ];
    }

    public function testParserV2Itineraries()
    {
        $request = $this->getRequest()->setLogin('Itineraries.V2Example');

        $response = new V2Response();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->row->setApiVersion(2);

        $this->getCheckAccountWorker()
            ->processRequest($request, $response, $this->row);

        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        /** @var Serializer $serialiser */
        $serialiser = $this->container->get('jms_serializer');
        $serialized = $serialiser->serialize($response, 'json');
        $result = json_decode($serialized, true);
        $this->assertCount(9, $result['itineraries']);
    }

    protected function getMasterSolver()
    {
        return $this->container->get('aw.solver.master');
    }

    /**
     * @dataProvider dataParseIts
     */
    public function testParserItineraries($parseIts)
    {
        /** @var \Helper\Aw $aw */
        $aw = $this->getModule('\Helper\Aw');

        $factory = $this->container->get('aw.checker_factory');

        $checkerFactoryMock = $aw->getMock(CheckerFactory::class);
        $checkerFactoryMock->expects($this->once())
            ->method('getAccountChecker')
            ->willReturnCallback(function ($sProviderCode, array $accountInfo = null) use ($factory, &$checker) {
                $checker = $factory->getAccountChecker($sProviderCode, $accountInfo);
                return $checker;
            });

        $request = $this->getRequest()->setLogin('future.trip')->setParseitineraries($parseIts);
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));

        $worker = $this->getCheckAccountWorker(null, null, null, null, null, null, null, null, $checkerFactoryMock)
            ->processRequest($request, $response, $this->row);

        $this->assertEquals($checker->ParseIts, $parseIts);
    }

    public function dataParseIts()
    {
        return [
            [true],
            [false]
        ];
    }
}
