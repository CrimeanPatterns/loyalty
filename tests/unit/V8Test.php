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
class V8Test extends \Tests\Unit\BaseWorkerTestClass
{

    public function testChecked(){
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('Checker.V8')
                ->setPassword('g5f4'.rand(1000,9999).'_q');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
    }

}