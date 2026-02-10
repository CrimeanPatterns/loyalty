<?php

namespace Tests\Unit;
use AppBundle\Model\Resources\Answer;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use Doctrine\Common\Persistence\ObjectRepository;

/**
 * @backupGlobals disabled
 */
class BrowserStateTest extends \Tests\Unit\BaseWorkerTestClass
{

    public function testState(){
        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('Checker.BrowserState')
                ->setLogin2('Test1')
                ->setPassword('g5f4'.rand(1000,9999).'_q');

        $response = new CheckAccountResponse();
        $response
            ->setRequestid(bin2hex(random_bytes(10)))
            ->setRequestdate(new \DateTime());
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_QUESTION, $response->getState());
        $this->assertNotNull($response->getBrowserstate());
        $this->assertNull($response->getBalance());
        $browserState = $response->getBrowserstate();

        $answer = new Answer();
        $answer->setQuestion("Why?")
               ->setAnswer("Because!");

        $request->setAnswers([$answer]);
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_INVALID_PASSWORD, $response->getState());
        $this->assertEquals("Missing step", $response->getMessage());

        $request->setBrowserstate($browserState);
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request->setLogin2('Test2'), $response, $this->row);
        // browser state will be discarded because Login have been changed
        // see CheckAccountExecutor::decodeBrowserState
        $this->assertEquals("Missing step", $response->getMessage());
        $this->assertEquals(ACCOUNT_INVALID_PASSWORD, $response->getState());

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request->setLogin2('Test1'), $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $this->assertEquals(198, $response->getBalance());
    }

}