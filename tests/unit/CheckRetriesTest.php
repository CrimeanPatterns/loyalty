<?php

namespace Tests\Unit;
use AppBundle\Document\CheckAccount;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AwardWallet\Common\Itineraries\ItinerariesCollection;
use Doctrine\Common\Persistence\ObjectRepository;
use JMS\Serializer\SerializerInterface;

/**
 * @backupGlobals disabled
 */
class CheckRetriesTest extends \Tests\Unit\BaseWorkerTestClass
{

    protected function getRequest(){
        $request = new CheckAccountRequest();
        return $request->setProvider('testprovider')
                       ->setUserid('SomeID');
    }

        public function testRetry()
    {
        $request = $this->getRequest()->setLogin('Checker.Retry');
        $response = new CheckAccountResponse();
        $response
            ->setRequestid(bin2hex(random_bytes(10)))
            ->setRequestdate(new \DateTime());
        $this->expectException(\ThrottledException::class);
        $this->getCheckAccountWorker()
             ->processRequest($request, $response, $this->row);

        /** @var \ThrottledException $e */
        $e = $this->getExpectedException();
        $this->assertEquals(20, $e->retryInterval);
        $this->assertEquals(ACCOUNT_UNCHECKED, $response->getState());
    }

    public function testRetryExceeded()
    {
        $request = $this->getRequest()->setLogin('Checker.Retry');
        $response = new CheckAccountResponse();
        $response
            ->setRequestid(bin2hex(random_bytes(10)))
            ->setRequestdate(new \DateTime());
        $this->row->setRetries(1);
        $this->getCheckAccountWorker()
             ->processRequest($request, $response, $this->row);

        $this->assertEquals(ACCOUNT_LOCKOUT, $response->getState());
    }

    public function testRetryUnknownError()
    {
        $request = $this->getRequest()->setLogin('Checker.Retry')->setLogin2("-u");
        $response = new CheckAccountResponse();
        $response
            ->setRequestid(bin2hex(random_bytes(10)))
            ->setRequestdate(new \DateTime());
        $this->row->setRetries(1);
        $this->getCheckAccountWorker()
             ->processRequest($request, $response, $this->row);

        $this->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
    }

    public function testCancel()
    {
        $request = $this->getRequest()->setLogin('Checker.Cancel');
        $response = new CheckAccountResponse();
        $response
            ->setRequestid(bin2hex(random_bytes(10)))
            ->setRequestdate(new \DateTime());
        $this->getCheckAccountWorker()
             ->processRequest($request, $response, $this->row);

        $this->assertEquals(ACCOUNT_UNCHECKED, $response->getState());
        $this->assertEquals("Cancelled", $response->getMessage());
    }

    public function testKilled()
    {
        $this->requestId = 'someId_'.time().rand(10000,99999);
        $this->request = (new CheckAccountRequest())->setTimeout(120)
            ->setUserId('MyUserID')
            ->setProvider('testprovider')
            ->setLogin('Checker.Retry')
            ->setLogin2('-ub')
            ->setRetries(1)
            ->setPriority(9)
            ->setUserData('{requestId: "'.$this->requestId.'"}');

        $this->response = (new CheckAccountResponse())
            ->setRequestid($this->requestId)
            ->setRequestdate(new \DateTime())
            ->setState(ACCOUNT_UNCHECKED);

        $serializer = $this->container->get('jms_serializer');
        $this->serializedRequest = $serializer->serialize($this->request, 'json');
        $this->serializedResponse = $serializer->serialize($this->response, 'json');

        $this->serializer = $this->getCustomMock(SerializerInterface::class);
        $this->serializer->expects($this->exactly(2))
            ->method('deserialize')
            ->will($this->onConsecutiveCalls($this->response, $this->request));
        $this->serializer->method('serialize')
            ->willReturnCallback(function($data, $format) use($serializer){
                return $serializer->serialize($data, $format);
            });

        $this->row = (new CheckAccount())->setPartner($this->partner)
            ->setResponse(json_decode($this->serializedResponse, true))
            ->setRequest(json_decode($this->serializedRequest, true))
            ->setApiVersion(2)
            ->setKilled();

        $this->repo = $this->getCustomMock(ObjectRepository::class);
        $this->repo->expects($this->never())
            ->method('find')
            ->with($this->requestId)
            ->willReturn($this->row);

        unset($serializer);

        $this->getCheckAccountWorker(null, null, $this->repo)->execute($this->row);
        $this->assertEquals(ACCOUNT_TIMEOUT, $this->response->getState());

        unset($this->request, $this->response, $this->serializer, $this->row, $this->serializedRequest, $this->serializedResponse);
    }

}
