<?php


namespace Tests\Functional\RewardAvailability;

use AppBundle\Document\RaAccount;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest;

class RegisterCest
{
    private $providerCode;

    public function _before(\FunctionalTester $I)
    {
        $this->providerCode = "test" . bin2hex(random_bytes(8));
        $I->createAwProvider(null, $this->providerCode, ['RewardAvailabilityLockAccount' => 1]);

        eval("namespace AwardWallet\\Engine\\{$this->providerCode}\\RewardAvailability;
        
        class Register extends \\Tests\\Functional\\RewardAvailability\\RegisterParser {}
        ");

        eval("namespace AwardWallet\\Engine\\{$this->providerCode};
        
        class QuestionAnalyzer extends \\Tests\\Functional\\RewardAvailability\\QuestionAnalyzer {}
        ");
    }

    public function testSucces(\FunctionalTester $I)
    {
        $I->wantToTest('running with UE');
        $request = $this->getRequest('John', 'asd@asd.com');
        RegisterParser::$checkState = RegisterParser::engineError;
        $response = $I->runRegisterAccountRA($request);
        $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());

        $I->wantToTest('running with wrong fields');
        RegisterParser::$checkState = RegisterParser::userInputError;
        $response = $I->runRegisterAccountRA($request);
        $I->assertEquals(ACCOUNT_INVALID_USER_INPUT, $response->getState());
        $I->assertEquals("wrong fields", $response->getMessage());

        $I->wantToTest('running register, save to DB');
        RegisterParser::$checkState = RegisterParser::withJsonForDB;
        $response = $I->runRegisterAccountRA($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertStringContainsString("Registration is successful! (account saved to DB", $response->getMessage());
        $account = $I->getRaAccount(['provider' => $this->providerCode, 'email' => 'asd@asd.com']);
        $I->assertEquals(RaAccount::STATE_RESERVE, $account->getState());
        $I->assertCount(3, $account->getRegisterInfo());

        $I->wantToTest('running register with same email');
        $response = $I->runRegisterAccountRA($request);
        $I->assertEquals(ACCOUNT_TIMEOUT, $response->getState());
        $I->assertStringContainsString("already has account with email ", $response->getDebugInfo());

        $I->wantToTest('running register with new email, without saving to DB');
        $request = $this->getRequest('John', 'qwe@qwe.com');
        RegisterParser::$checkState = RegisterParser::withoutJsonForDB;
        $response = $I->runRegisterAccountRA($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertStringContainsString(" (account not saved to DB)", $response->getMessage());
        $account = $I->getRaAccount(['provider' => $this->providerCode, 'email' => 'qwe@qwe.com']);
        $I->assertNull($account);

        $I->wantToTest('running register with new email, with saving to DB, set state Disable');
        $request = $this->getRequest('Johnny', 'qwed@qwed.com');
        $request->setUserData('{"state":0}');
        RegisterParser::$checkState = RegisterParser::withJsonForDB;
        $response = $I->runRegisterAccountRA($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertStringContainsString("Registration is successful! (account saved to DB", $response->getMessage());
        $account = $I->getRaAccount(['provider' => $this->providerCode, 'email' => 'qwed@qwed.com']);
        $I->assertEquals(RaAccount::STATE_DISABLED, $account->getState());

        $I->wantToTest('running register with new email, result inactive account, with saving to DB, set state Inactive');
        $request = $this->getRequest('Johnson', 'qwedson@qwed.com');
        RegisterParser::$checkState = RegisterParser::withJsonForDBInactive;
        $response = $I->runRegisterAccountRA($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertStringContainsString("Registration is successful! (account saved to DB", $response->getMessage());
        $account = $I->getRaAccount(['provider' => $this->providerCode, 'email' => 'qwedson@qwed.com']);
        $I->assertEquals(RaAccount::STATE_INACTIVE, $account->getState());

        $I->wantToTest('running register with new email, question, with saving to DB, set state Disable');
        $request = $this->getRequest('Johanna', 'qwedna@qwed.com');
        RegisterParser::$checkState = RegisterParser::question;
        $response = $I->runRegisterAccountRA($request);
        $I->assertEquals(ACCOUNT_QUESTION, $response->getState());
        $I->assertStringContainsString("Confirm your email", $response->getMessage());
        $account = $I->getRaAccount(['provider' => $this->providerCode, 'email' => 'qwedna@qwed.com']);
        $I->assertEquals(RaAccount::STATE_DISABLED, $account->getState());

        $I->wantToTest('running with throw retry');
        $request = $this->getRequest('John', 'qwe@qwe.com');
        RegisterParser::$checkState = RegisterParser::throwRetry;
        $response = $I->runRegisterAccountRA($request);
        $I->assertEquals(ACCOUNT_TIMEOUT, $response->getState());
        $I->assertEquals("Sorry, we couldn't complete your request. Try again later.", $response->getMessage());
    }

    private function getRequest($name, $email)
    {
        return (new RegisterAccountRequest())
            ->setProvider($this->providerCode)
            ->setUserData('{"requestId": "blah"}')
            ->setFields(['LastName' => $name, 'Email' => $email, 'Password' => 'ssd123@1']);
    }
}