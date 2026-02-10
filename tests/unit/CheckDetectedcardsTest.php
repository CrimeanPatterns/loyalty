<?php

namespace Tests\Unit;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\DetectedCard;

/**
 * @backupGlobals disabled
 */
class CheckDetectedcardsTest extends \Tests\Unit\BaseWorkerTestClass
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

        $cards = $response->getDetectedcards();
        $this->assertNotEmpty(count($cards));
        $this->assertInstanceOf(DetectedCard::class, $cards[0]);
    }

}
