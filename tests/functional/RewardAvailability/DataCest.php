<?php

namespace Tests\Functional\RewardAvailability;

use AppBundle\Model\Resources\RewardAvailability\Departure;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\Segment;
use AppBundle\Model\Resources\RewardAvailability\Travelers;
use AwardWallet\Common\Parsing\Solver\Helper\FlightSegmentData;
use AwardWallet\Common\Parsing\Solver\Helper\FSHelper;
use Codeception\Stub;

/**
 * @backupGlobals disabled
 */
class DataCest
{

    private $providerCode;
    private $request;

    public function _before(\FunctionalTester $I)
    {
        $this->providerCode = "test" . bin2hex(random_bytes(8));
        $I->createAwProvider(null, $this->providerCode);

        eval("namespace AwardWallet\\Engine\\{$this->providerCode}\\RewardAvailability;
        
        class Parser extends \\Tests\\Functional\\RewardAvailability\\DataParser {}
        ");
    }

    public function testStateAndDate(\FunctionalTester $I)
    {
        $I->wantToTest('error when empty and no warning');
        $request = $this->getRequest();
        DataParser::$checkState = DataParser::noWarningEmpty;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());

        $I->wantToTest('not empty and warning');
        $request = $this->getRequest();
        DataParser::$checkState = DataParser::warningNotEmpty;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $routes = $response->getRoutesToSerialize();
        $I->assertNotEmpty($routes);

        $I->wantToTest('empty and warning');
        $request = $this->getRequest();
        DataParser::$checkState = DataParser::warningEmpty;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $routes = $response->getRoutesToSerialize();
        $I->assertEmpty($routes);

        $I->wantToTest('error with wrong Data');
        $request = $this->getRequest();
        DataParser::$checkState = DataParser::wrongData;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());

        $I->wantToTest('error with wrong Dates');
        $request = $this->getRequest();
        DataParser::$checkState = DataParser::wrongDatesSkip;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertEquals(1, count($response->getRoutesToSerialize()));

        $I->wantToTest('error with wrong miles');
        $request = $this->getRequest();
        DataParser::$checkState = DataParser::wrongMiles;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());

        $I->wantToTest('check correct Date');
        $request = $this->getRequest();
        DataParser::$checkState = DataParser::checkDates;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());

        $I->wantToTest('check parseMode');
        $request = $this->getRequest();
        DataParser::$checkState = DataParser::checkDates;
        DataParser::$checkParseMode = true;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertCount(1, $response->getRoutesToSerialize());
    }

    private function getRequest(){
        return (new RewardAvailabilityRequest())
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
    }
}