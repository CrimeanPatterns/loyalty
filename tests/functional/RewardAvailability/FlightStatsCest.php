<?php

namespace Tests\Functional\RewardAvailability;

use AppBundle\Model\Resources\RewardAvailability\Departure;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\Segment;
use AppBundle\Model\Resources\RewardAvailability\Travelers;
use AwardWallet\Common\Parsing\Solver\Helper\ALHelper;
use AwardWallet\Common\Parsing\Solver\Helper\FlightSegmentData;
use AwardWallet\Common\Parsing\Solver\Helper\FSHelper;
use Codeception\Stub;

/**
 * @backupGlobals disabled
 */
class FlightStatsCest
{

    private $providerCode;
    private $request;

    private function _before(\FunctionalTester $I)
    {
        $this->providerCode = "test" . bin2hex(random_bytes(8));
        $I->createAwProvider(null, $this->providerCode);

        eval("namespace AwardWallet\\Engine\\{$this->providerCode}\\RewardAvailability;
        
        class Parser extends \\Tests\\Functional\\RewardAvailability\\FlightStatsParser {}
        ");

        $alHelper = Stub::make(ALHelper::class, [
            'process' => Stub\Expected::atLeastOnce(function () {
                return null;
            })
        ]);
        $I->mockService('aw.solver.al_helper', $alHelper);
        $fsHelper = Stub::make(FSHelper::class, [
            'process' => Stub\Expected::atLeastOnce(function () {
                return Stub::makeEmpty(FlightSegmentData::class, ['aircraftIata' => 'A321']);
            }),
            'isFsEnabled' => true
        ]);
        $I->mockService('aw.solver.fs_helper', $fsHelper);
    }

    private function testState(\FunctionalTester $I)
    {

        $I->wantToTest("not all segments have aircraft");

        $request = (new RewardAvailabilityRequest())
            ->setProvider($this->providerCode)
            ->setUserData('{requestId: "blah"}')
            ->setArrival('JFK')
            ->setCurrency('USD')
            ->setDeparture(
                (new Departure())
                    ->setAirportCode('LAX')
                    ->setDate(new \DateTime('+1 month'))
                    ->setFlexibility(1)
            )
            ->setPassengers(
                (new Travelers())->setAdults(1)
            )
            ->setCabin('economy');

        FlightStatsParser::$aircrafts = FlightStatsParser::some;

        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        foreach ($response->getRoutesToSerialize() as $route) {
            foreach ($route->getSegmentsToSerialize() as $segment) {
                $I->assertObjectHasAttribute('aircraft', $segment);
                $I->assertNotEmpty($segment->getAircraft());
            }
        }

        $I->wantToTest("all segments have no aircraft");

        $request = (new RewardAvailabilityRequest())
            ->setProvider($this->providerCode)
            ->setUserData('{requestId: "blah"}')
            ->setArrival('JFK')
            ->setCurrency('USD')
            ->setDeparture(
                (new Departure())
                    ->setAirportCode('LAX')
                    ->setDate(new \DateTime('+1 month'))
                    ->setFlexibility(1)
            )
            ->setPassengers(
                (new Travelers())->setAdults(1)
            )
            ->setCabin('economy');


        FlightStatsParser::$aircrafts = FlightStatsParser::zero;

        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        foreach ($response->getRoutesToSerialize() as $route) {
            foreach ($route->getSegmentsToSerialize() as $segment) {
                $I->assertObjectHasAttribute('aircraft', $segment);
                $I->assertNotEmpty($segment->getAircraft());
            }
        }
    }

    private function _beforeSuite(\FunctionalTester $I)
    {
        $alHelper = Stub::make(ALHelper::class, [
            'process' => Stub\Expected::never()
        ]);
        $I->mockService('aw.solver.al_helper', $alHelper);
        $fsHelper = Stub::make(FSHelper::class, [
            'process' => Stub\Expected::never()
        ]);
        $I->mockService('aw.solver.fs_helper', $fsHelper);
    }
    private function testNoRunFs(\FunctionalTester $I)
    {
        $I->wantToTest("if all segments have aircraft - not use flightStats");

        $request = (new RewardAvailabilityRequest())
            ->setProvider($this->providerCode)
            ->setUserData('{requestId: "blah"}')
            ->setArrival('JFK')
            ->setCurrency('USD')
            ->setDeparture(
                (new Departure())
                    ->setAirportCode('LAX')
                    ->setDate(new \DateTime('+1 month'))
                    ->setFlexibility(1)
            )
            ->setPassengers(
                (new Travelers())->setAdults(1)
            )
            ->setCabin('economy');


        FlightStatsParser::$aircrafts = FlightStatsParser::all;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        foreach ($response->getRoutesToSerialize() as $route) {
            foreach ($route->getSegmentsToSerialize() as $segment) {
                $I->assertObjectHasAttribute('aircraft', $segment);
                $I->assertNotEmpty($segment->getAircraft());
            }
        }
    }

}