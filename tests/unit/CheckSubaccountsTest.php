<?php

namespace Tests\Unit;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\Property;
use AppBundle\Model\Resources\SubAccount;

/**
 * @backupGlobals disabled
 */
class CheckSubaccountsTest extends \Tests\Unit\BaseWorkerTestClass
{

    public function testCollectSubaccounts(){
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setPassword('g5f4'.rand(1000,9999).'_q')
                ->setLogin('2.subaccounts');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        $subAccounts = $response->getSubaccounts();
        $this->assertEquals(2, count($subAccounts));
        $this->assertInstanceOf(SubAccount::class, $subAccounts[0]);
    }

    public function testSubaccountExp()
    {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
            ->setUserid('SomeID')
            ->setPassword('g5f4' . rand(1000, 9999) . '_q')
            ->setLogin('subaccount_expired');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(1, count($response->getSubaccounts()));
        /** @var SubAccount $subAcc */
        $subAcc = $response->getSubaccounts()[0];
        $this->assertNotNull($subAcc->getExpirationDate());
    }

    public function testSubaccountExpComb()
    {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
            ->setUserid('SomeID')
            ->setPassword('g5f4' . rand(1000, 9999) . '_q')
            ->setLogin('subaccount_expired_combined');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertNull($response->getSubaccounts());
        $this->assertNotNull($response->getExpirationDate());
    }

    public function testSubaccountBalances()
    {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
            ->setUserid('SomeID')
            ->setPassword('g5f4' . rand(1000, 9999) . '_q')
            ->setLogin('sub.n-a.balance');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        $subAccounts = $response->getSubaccounts();
        $this->assertEquals(2, count($subAccounts));
        $this->assertInstanceOf(SubAccount::class, $subAccounts[0]);
    }

    public function testCombineSubaccWithZeroMainBalance()
    {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
            ->setUserid('SomeID')
            ->setPassword('g5f4' . rand(1000, 9999) . '_q')
            ->setLogin('Checker.SubAccountsBalance')
            ->setLogin2('zero_main_balance');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertSame(0, $response->getBalance());
        $this->assertNotEmpty($response->getSubaccounts());
    }

    public function testCombineSubaccWithNullMainBalance()
    {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
            ->setUserid('SomeID')
            ->setPassword('g5f4' . rand(1000, 9999) . '_q')
            ->setLogin('Checker.SubAccountsBalance')
            ->setLogin2('null_main_balance');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertTrue($response->getBalance() > 0);
        $this->assertEmpty($response->getSubaccounts());
    }

    public function testCombineSubaccWithBalanceCurrency()
    {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
            ->setUserid('SomeID')
            ->setPassword('g5f4' . rand(1000, 9999) . '_q')
            ->setLogin('Checker.SubAccountsBalance')
            ->setLogin2('currency_main_balance_combine');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertTrue($response->getBalance() > 0);
        $this->assertEmpty($response->getSubaccounts());
        /** @var Property $prop */
        $prop = $response->getProperties()[0];
        $this->assertEquals('Currency', $prop->getCode());
        $this->assertEquals('CNY', $prop->getValue());
    }

    public function testSubaccWithBalanceCurrency()
    {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
            ->setUserid('SomeID')
            ->setPassword('g5f4' . rand(1000, 9999) . '_q')
            ->setLogin('Checker.SubAccountsBalance')
            ->setLogin2('currency_main_balance');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertNull($response->getBalance());
        /** @var SubAccount $subAcc */
        $subAcc = $response->getSubaccounts()[0];
        $this->assertTrue($subAcc->getBalance() > 0);
        /** @var Property $prop */
        $prop = $subAcc->getProperties()[0];
        $this->assertEquals('Currency', $prop->getCode());
        $this->assertEquals('CNY', $prop->getValue());
    }
}
