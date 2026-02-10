<?php
namespace Tests\Functional\RewardAvailability;

use AppBundle\Document\RaAccount;
use AppBundle\Document\RewardAvailability;
use AppBundle\Model\Resources\RewardAvailability\Departure;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Model\Resources\RewardAvailability\Travelers;
use AppBundle\Worker\CheckExecutor\RewardAvailabilityExecutor;
use Doctrine\ODM\MongoDB\DocumentManager;
use Documents\Manager;

/**
 * @backupGlobals disabled
 */
class BrowserStateCest
{

    public function testState(\FunctionalTester $I)
    {
        $providerCode = "test" . bin2hex(random_bytes(8));
        $I->createAwProvider(null, $providerCode);

        eval("namespace AwardWallet\\Engine\\{$providerCode}\\RewardAvailability;
        
        class Parser extends \\Tests\\Functional\\RewardAvailability\\BrowserStateParser {}
        ");


        $request = (new RewardAvailabilityRequest())
            ->setProvider($providerCode)
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

        $I->wantToTest("create new state");
        BrowserStateParser::reset();
        $I->haveRaAccount($providerCode, 'login1', '', '-10 min');
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertEquals("login1", BrowserStateParser::$loginOnEnter);
        $I->assertNull(BrowserStateParser::$stateOnEnter);
        $I->assertNotEmpty(BrowserStateParser::$stateOnExit);
        $state1 = BrowserStateParser::$stateOnExit;

        $I->wantToTest("load existing state");
        BrowserStateParser::reset();
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertEquals("login1", BrowserStateParser::$loginOnEnter);
        $I->assertEquals($state1, BrowserStateParser::$stateOnEnter);
        $I->assertNotEmpty(BrowserStateParser::$stateOnExit);
        $state2 = BrowserStateParser::$stateOnExit;

        $I->wantToTest("do not load existing state for another account");
        $I->haveRaAccount($providerCode, 'login2', '', '-15 min');
        BrowserStateParser::reset();
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertEquals("login2", BrowserStateParser::$loginOnEnter);
        $I->assertNull(BrowserStateParser::$stateOnEnter);
        $I->assertNotEmpty(BrowserStateParser::$stateOnExit);

        $I->wantToTest("load existing state and update it");
        $I->haveRaAccount($providerCode, 'login1', '', '-20 min');
        BrowserStateParser::reset();
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertEquals("login1", BrowserStateParser::$loginOnEnter);
        $I->assertEquals($state2, BrowserStateParser::$stateOnEnter);
        $I->assertNotEmpty(BrowserStateParser::$stateOnExit);
        $state3 = BrowserStateParser::$stateOnExit;
        $I->assertNotEquals($state2, $state3);

        $I->wantToTest("browser state reset when it's bad");
        $I->haveRaAccount($providerCode, 'login1', '', '-20 min');
        BrowserStateParser::reset();
        BrowserStateParser::$checkChecker = false;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_UNCHECKED, $response->getState());
        $I->assertEquals(true, BrowserStateParser::$checkChecker);

        $I->haveRaAccount($providerCode, 'login1', '', '-20 min');
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertEquals("login1", BrowserStateParser::$loginOnEnter);
        $I->assertNull(BrowserStateParser::$stateOnEnter);
        $I->assertNotEmpty(BrowserStateParser::$stateOnExit);
    }


}