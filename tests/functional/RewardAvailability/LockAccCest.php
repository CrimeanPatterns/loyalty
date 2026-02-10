<?php

namespace Tests\Functional\RewardAvailability;

use AppBundle\Document\RewardAvailability;
use AppBundle\Model\Resources\RewardAvailability\Departure;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Model\Resources\RewardAvailability\Segment;
use AppBundle\Model\Resources\RewardAvailability\Travelers;
use AppBundle\Worker\CheckExecutor\RewardAvailabilityExecutor;
use AwardWallet\Common\Parsing\Solver\Helper\FlightSegmentData;
use AwardWallet\Common\Parsing\Solver\Helper\FSHelper;
use Codeception\Module\Symfony;
use Codeception\Stub;
use Codeception\Test\Cest;
use Codeception\Test\Unit;
use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * @backupGlobals disabled
 */
class LockAccCest
{

    private $providerCode;
    private $request;

    public function _before(\FunctionalTester $I)
    {
        $this->providerCode = "testprovider" . bin2hex(random_bytes(4));
        $I->createAwProvider(null, $this->providerCode, ['RewardAvailabilityLockAccount' => 1]);

        eval("namespace AwardWallet\\Engine\\{$this->providerCode}\\RewardAvailability;
        
        class Parser extends \\Tests\\Functional\\RewardAvailability\\LockAccParser {}
        ");
    }

    public function testLockAndGoOn(\FunctionalTester $I)
    {
        $I->wantToTest('Lock account and send to parse');
        $I->haveRaAccount($this->providerCode, 'login1', '', '-8 min', true);
        $lock = $I->checkRaAccountLock($this->providerCode, 'login1');
        $I->assertEquals(true, $lock);
        $request = $this->getRequest();
        LockAccParser::reset();
        $response = $I->searchRewardAvailability($request);

        $I->assertEquals(ACCOUNT_UNCHECKED, $response->getState());
        $I->assertEquals(0, LockAccParser::$numRun);
        $I->assertStringContainsString('all RA-accounts', $response->getDebugInfo());

        $I->wantToTest('unLock account and send to parse');
        $I->haveRaAccount($this->providerCode, 'login1', '', '-10 min', false);
        $response = $I->runSearchRewardAvailabilityById($response->getRequestId());
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $I->assertEquals(0, LockAccParser::$numRun);

        $request = $this->getRequest();
        $response = $I->searchRewardAvailability($request);

        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $lock = $I->checkRaAccountLock($this->providerCode, 'login1');
        $I->assertEquals(false, $lock);
    }

    public function testLockByProvider(\FunctionalTester $I){
        $I->wantToTest('Lock account and send to parse');
        $I->haveRaAccount($this->providerCode, 'lockAccount', '', '-10 min', false, 3);
        $request = $this->getRequest();
        LockAccParser::reset();
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_UNCHECKED, $response->getState());
        $I->assertEquals(0, LockAccParser::$numRun);
        $I->assertEquals('lockAccount', LockAccParser::$usedLogin);

        $I->wantToTest('parse with other unLock account and send to parse');
        $I->haveRaAccount($this->providerCode, 'goodAccount', '', '-10 min', false);
        $response = $I->runSearchRewardAvailabilityById($response->getRequestId());
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $I->assertEquals(1, LockAccParser::$numRun);
        $I->assertEquals('goodAccount', LockAccParser::$usedLogin);

        $I->wantToTest('parse when all accounts locked');
        $I->haveRaAccount($this->providerCode, 'lockAccount', '', '-10 min', false, 3);
        $I->haveRaAccount($this->providerCode, 'goodAccount', '', '-10 min', false, 3);
        $request = $this->getRequest();
        LockAccParser::reset();
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_UNCHECKED, $response->getState());
        $response = $I->runSearchRewardAvailabilityById($response->getRequestId());
        $I->assertEquals(ACCOUNT_TIMEOUT, $response->getState());
    }

    public function testLockAndPreventLockout(\FunctionalTester $I){
        $I->wantToTest('Lock account and send to parse');
        $I->haveRaAccount($this->providerCode, 'prevLockAccount', '', '-10 min', false, 1);
        $I->haveRaAccount($this->providerCode, 'prevLockAccount1', '', '-10 min', false, 1);
        $request = $this->getRequest();
        LockAccParser::reset();
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(ACCOUNT_UNCHECKED, $response->getState());
        $I->assertEquals(0, LockAccParser::$numRun);
        $I->assertEquals('prevLockAccount', LockAccParser::$usedLogin);

        $account = $I->getRaAccount(['provider' => $this->providerCode, 'login' => 'prevLockAccount']);
        $response = $I->runSearchRewardAvailabilityById($response->getRequestId());

        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $I->assertEquals(1, LockAccParser::$numRun);
        $I->assertEquals('prevLockAccount1', LockAccParser::$usedLogin);
    }

    private function getRequest()
    {
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