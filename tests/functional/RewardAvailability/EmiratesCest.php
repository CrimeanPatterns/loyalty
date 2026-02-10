<?php

namespace Tests\Functional\RewardAvailability;

use AppBundle\Document\RaAccount;
use AppBundle\Model\Resources\RewardAvailability\Departure;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\Travelers;

/**
 * @backupGlobals disabled
 */
class EmiratesCest
{

    private $providerCode;

    public function _before(\FunctionalTester $I)
    {
        $this->providerCode = "skywards" . bin2hex(random_bytes(4));
        $I->createAwProvider(null, $this->providerCode);

        eval("namespace AwardWallet\\Engine\\{$this->providerCode}\\RewardAvailability;
        
        class Parser extends \\Tests\\Functional\\RewardAvailability\\EmiratesParser {}
        ");
    }

    public function testCookie(\FunctionalTester $I)
    {
        $I->wantToTest('check cookies');

        /** @var RaAccount $account */
        $account = $I->haveRaAccount($this->providerCode, 'login1', '');
        $answers = $account->getAnswers();
        $I->assertEquals($answers->count(), 0);

        $request = $this->getRequest();
        EmiratesParser::reset();
        EmiratesParser::$withRemember = true;
        EmiratesParser::$valueRemember = $firstRemember = '222';
        $response = $I->searchRewardAvailability($request);

        $I->assertEquals(ACCOUNT_WARNING, $response->getState());

        $account = $I->haveRaAccount($this->providerCode, 'login1', '');
        $answers = $account->getAnswers();
        $I->assertEquals($answers->count(), 2);
        $flag = false;
        $lastUpdateDate = null;
        foreach ($answers as $answer) {
            if ($answer->getQuestion() === 'remember') {
                $I->assertEquals($answer->getAnswer(), EmiratesParser::$valueRemember);
                $flag = true;
            }
            if ($answer->getQuestion() === 'lastUpdateDate') {
                $lastUpdateDate = $answer->getAnswer();
            }
        }
        $I->assertEquals($flag, EmiratesParser::$withRemember);
        $I->assertNotNull($lastUpdateDate);

        sleep(1);
        $request = $this->getRequest();
        EmiratesParser::reset();
        EmiratesParser::$withRemember = true;
        EmiratesParser::$valueRemember = '222-333';
        $response = $I->searchRewardAvailability($request);

        $I->assertEquals(ACCOUNT_WARNING, $response->getState());


        $account = $I->haveRaAccount($this->providerCode, 'login1', '');
        $answers = $account->getAnswers();
        $I->assertNotNull($answers);
        $lastUpdateDate2 = null;
        foreach ($answers as $answer) {
            if ($answer->getQuestion() === 'remember') {
                $I->assertNotEquals($answer->getAnswer(), EmiratesParser::$valueRemember);
                $I->assertEquals($answer->getAnswer(), $firstRemember);
            }
            if ($answer->getQuestion() === 'lastUpdateDate') {
                $lastUpdateDate2 = $answer->getAnswer();
            }
        }
        $I->assertNotNull($lastUpdateDate);
        $I->assertNotNull($lastUpdateDate2);
        $I->assertEquals($lastUpdateDate, $lastUpdateDate2);

        $request = $this->getRequest();
        EmiratesParser::reset();
        EmiratesParser::$withRemember = true;
        EmiratesParser::$valueRemember = '222-333';
        $response = $I->searchRewardAvailability($request, 'awardwallet', 0);

        $I->assertEquals(ACCOUNT_WARNING, $response->getState());

        $account = $I->haveRaAccount($this->providerCode, 'login1', '');
        $answers = $account->getAnswers();
        $I->assertNotNull($answers);
        $lastUpdateDate2 = null;
        foreach ($answers as $answer) {
            if ($answer->getQuestion() === 'remember') {
                $I->assertEquals($answer->getAnswer(), EmiratesParser::$valueRemember);
            }
            if ($answer->getQuestion() === 'lastUpdateDate') {
                $lastUpdateDate2 = $answer->getAnswer();
            }
        }
        $I->assertNotNull($lastUpdateDate);
        $I->assertNotNull($lastUpdateDate2);

        $I->assertGreaterThan(strtotime($lastUpdateDate), strtotime($lastUpdateDate2));
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