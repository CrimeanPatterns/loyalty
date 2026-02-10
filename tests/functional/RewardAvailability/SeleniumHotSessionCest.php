<?php

namespace Tests\Functional\RewardAvailability;

use AppBundle\Document\RaAccount;
use AppBundle\Model\Resources\RewardAvailability\Departure;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\Travelers;
use Codeception\Stub;
use Codeception\Stub\Expected;

/**
 * @backupGlobals disabled
 */
class SeleniumHotSessionCest
{

    public function testSessions(\FunctionalTester $I)
    {
        $providerCode = "test" . bin2hex(random_bytes(8));
        $I->createAwProvider(null, $providerCode);
        eval("namespace AwardWallet\\Engine\\{$providerCode}\\RewardAvailability;
        
        class Parser extends \\Tests\\Functional\\RewardAvailability\\SeleniumHotSessionParser {}
        ");

        $request = $this->getRequest($providerCode);

        SeleniumHotSessionParser::reset();
        SeleniumHotSessionParser::$provider = $providerCode;

//        $I->wantToTest('run selenium and save hot');
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(1, SeleniumHotSessionParser::$numRun);
        $I->assertEquals('no hot', SeleniumHotSessionParser::$message);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        $I->assertNotNull($session);
        $lastDate = $session->getLastUseDate();
        $startDate = $session->getStartDate();

        sleep(1);
//        $I->wantToTest('got hot selenium and check LastDate');
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(2, SeleniumHotSessionParser::$numRun);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $I->assertEquals('got hot', SeleniumHotSessionParser::$message);
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        $I->assertNotNull($session);
        $I->assertLessThan($session->getLastUseDate()->getTimestamp(),$lastDate->getTimestamp());
        $I->assertEquals($session->getStartDate()->getTimestamp(),$startDate->getTimestamp());

//        $I->wantToTest('got hot selenium and delete it after UE');
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(3, SeleniumHotSessionParser::$numRun);
        $I->assertEquals('got hot', SeleniumHotSessionParser::$message);
        $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        $I->assertNull($session);

//        $I->wantToTest('run selenium and save hot');
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(4, SeleniumHotSessionParser::$numRun);
        $I->assertEquals('no hot', SeleniumHotSessionParser::$message);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        $I->assertNotNull($session);

//        $I->wantToTest('got hot selenium and not saving it');
        SeleniumHotSessionParser::$saveSession = false;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(5, SeleniumHotSessionParser::$numRun);
        $I->assertEquals('got hot', SeleniumHotSessionParser::$message);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        $I->assertNull($session);
    }

    public function testSessionsWithAccounts(\FunctionalTester $I){
        $providerCode = "testacc" . bin2hex(random_bytes(5));
        $I->createAwProvider(null, $providerCode, ['RewardAvailabilityLockAccount' => 1]);

        eval("namespace AwardWallet\\Engine\\{$providerCode}\\RewardAvailability;
        
        class Parser extends \\Tests\\Functional\\RewardAvailability\\SeleniumHotSessionParser {}
        ");

        $request = $this->getRequest($providerCode);

        /** @var RaAccount $account */
        $account = $I->haveRaAccount($providerCode, 'login1', '', '-10 min');

        SeleniumHotSessionParser::reset();
        SeleniumHotSessionParser::$provider = $providerCode;
        SeleniumHotSessionParser::$accountKey = $account->getId();

//        $I->wantToTest('run selenium and save hot');
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(1, SeleniumHotSessionParser::$numRun);
        $I->assertEquals('no hot', SeleniumHotSessionParser::$message);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        $I->assertNotNull($session);
        $lastDate = $session->getLastUseDate();
        $startDate = $session->getStartDate();

        sleep(1);
//        $I->wantToTest('got hot selenium and check Account');
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(2, SeleniumHotSessionParser::$numRun);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $I->assertEquals('got hot', SeleniumHotSessionParser::$message);
        $I->assertEquals($account->getId(), SeleniumHotSessionParser::$accountKey);
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        $I->assertNotNull($session);
        $I->assertLessThan($session->getLastUseDate()->getTimestamp(),$lastDate->getTimestamp());
        $I->assertEquals($session->getStartDate()->getTimestamp(),$startDate->getTimestamp());

        // аккаунт залочен, должен взять новый акк и создать сессию
        $account2 = $I->haveRaAccount($providerCode, 'login2', '', '-10 min');
        $account = $I->haveRaAccount($providerCode, 'login1', '', '-1 min', true);
        SeleniumHotSessionParser::$numRun = 1;
//        $I->wantToTest('got hot selenium and check Account');
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(2, SeleniumHotSessionParser::$numRun);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $I->assertEquals('no hot', SeleniumHotSessionParser::$message);
        $I->assertEquals($account2->getId(), SeleniumHotSessionParser::$accountKey);

        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, $account2->getId());
        $I->assertNotNull($session);

//        $I->wantToTest('got hot selenium and delete it after UE');
        $account = $I->haveRaAccount($providerCode, 'login1', '', '-1 min', false);
        foreach ([$account,$account2] as $acc) {
            SeleniumHotSessionParser::$numRun = 2;
            SeleniumHotSessionParser::$accountKey = $acc->getId();
            $response = $I->searchRewardAvailability($request);
            $I->assertEquals(3, SeleniumHotSessionParser::$numRun);
            $I->assertEquals('got hot', SeleniumHotSessionParser::$message);
            $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
        }
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, $account->getId());
        $I->assertNull($session);
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, $account2->getId());
        $I->assertNull($session);
    }

    public function testBackgroundCheck(\FunctionalTester $I)
    {
        $providerCode = "test" . bin2hex(random_bytes(8));
        $I->createAwProvider(null, $providerCode);
        eval("namespace AwardWallet\\Engine\\{$providerCode}\\RewardAvailability;
        
        class Parser extends \\Tests\\Functional\\RewardAvailability\\SeleniumHotSessionParser {}
        ");

        $request = $this->getRequest($providerCode);
        $request->setUserData('{requestId: "testBackgroundCheck"}');
        $request->setPriority(1);

        SeleniumHotSessionParser::reset();
        SeleniumHotSessionParser::$provider = $providerCode;

//        $I->wantToTest('run priority1: run selenium and not save hot');
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(1, SeleniumHotSessionParser::$numRun);
        $I->assertEquals('no hot', SeleniumHotSessionParser::$message);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        $I->assertNull($session);

//        $I->wantToTest('run priority 9: no hot selenium and save hot');
        $request->setPriority(9);
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(2, SeleniumHotSessionParser::$numRun);
        $I->assertEquals('no hot', SeleniumHotSessionParser::$message);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        // 1 session left
        $I->assertNotNull($session);

//        $I->wantToTest('run priority 1: no hot selenium and not save');
        $request->setPriority(1);
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(3, SeleniumHotSessionParser::$numRun);
        $I->assertEquals('no hot', SeleniumHotSessionParser::$message);
        $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        $I->assertNotNull($session);

//        $I->wantToTest('run priority 9: close get and close last hot');
        $request->setPriority(9);
        SeleniumHotSessionParser::$numRun = 2;
        $response = $I->searchRewardAvailability($request);
        $I->assertEquals(3, SeleniumHotSessionParser::$numRun);
        $I->assertEquals('got hot', SeleniumHotSessionParser::$message);
        $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
        $session = $I->getHotSession(SeleniumHotSessionParser::$provider, SeleniumHotSessionParser::$prefix, SeleniumHotSessionParser::$accountKey);
        // 0 session left
        $I->assertNull($session);
    }

    private function getRequest($provider){
        return (new RewardAvailabilityRequest())
            ->setProvider($provider)
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