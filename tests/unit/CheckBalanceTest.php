<?php

namespace Tests\Unit;
use AppBundle\Document\CheckAccount;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\Property;
use AppBundle\Model\Resources\SubAccount;
use AppBundle\Model\Resources\UserData;

/**
 * @backupGlobals disabled
 */
class CheckBalanceTest extends \Tests\Unit\BaseWorkerTestClass
{

    public function testChecked(){
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('balance.point')
                ->setPassword('g5f4'.rand(1000,9999).'_q');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
    }

    public function testProperties(){
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('elite.complex')
                ->setPassword('g5f4'.rand(1000,9999).'_q');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(6, count($response->getProperties()));
        $this->assertInstanceOf(Property::class, $response->getProperties()[0]);
    }

    public function testAllowHtmlPropertiesAw(){
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('sub.chase.freedom')
                ->setPassword('g5f4'.rand(1000,9999).'_q')
                ->setUserData(json_encode(["accountId"=>9999]));

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, (new CheckAccount())->setPartner("awardwallet"));
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(1, count($response->getSubaccounts()));

        /** @var SubAccount $subAcc */
        $subAcc = $response->getSubaccounts()[0];
        $checked = false;
        /** @var Property $property */
        foreach ($subAcc->getProperties() as $property){
            if($property->getCode() === "CashBack"){
                $this->assertEquals(true, strpos($property->getValue(), '<a target=') !== false);
                $checked = true;
            }
        }
        $this->assertEquals(true, $checked);
    }

    public function testInvisibleToPartnerProperty()
    {
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('sub.chase.freedom')
                ->setPassword('g5f4'.rand(1000,9999).'_q');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(1, count($response->getSubaccounts()));

        /** @var SubAccount $subAcc */
        $subAcc = $response->getSubaccounts()[0];
        $checked = false;
        /** @var Property $property */
        foreach ($subAcc->getProperties() as $property)
            if($property->getCode() === "CashBack")
                $checked = true;

        $this->assertEquals(false, $checked);
    }

    public function testCancelCheck(){
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setPriority(2)
                ->setLogin('cancel.check')
                ->setPassword('g5f4'.rand(1000,9999).'_q')
                ->setUserData(json_encode((new UserData())->setAccountId(123)));

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, (new CheckAccount())->setPartner("awardwallet"));
        $this->assertEquals(ACCOUNT_UNCHECKED, $response->getState());
        $this->assertEquals("Cancelled", $response->getMessage());
    }

}