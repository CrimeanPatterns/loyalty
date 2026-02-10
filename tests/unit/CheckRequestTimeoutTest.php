<?php
namespace Tests\Unit;

use AppBundle\Document\CheckAccount;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use Doctrine\Common\Persistence\ObjectRepository;
use JMS\Serializer\SerializerInterface;

/**
 * @backupGlobals disabled
 */
class CheckRequestTimeoutTest extends BaseWorkerTestClass
{

    /** @var string */
    private $requestId;
    /** @var CheckAccountRequest */
    private $request;
    /** @var CheckAccountResponse */
    private $response;
    /** @var string */
    private $serializedRequest;
    /** @var string */
    private $serializedResponse;
    /** @var ObjectRepository */
    private $repo;

    public function _before()
    {
        parent::_before();
        $this->requestId = 'someId_'.time().rand(10000,99999);
        $this->request = (new CheckAccountRequest())->setTimeout(120)
                                                    ->setUserId('MyUserID')
                                                    ->setProvider('testprovider')
                                                    ->setLogin('balance.random')
                                                    ->setUserData('{requestId: "'.$this->requestId.'"}');

        $this->response = (new CheckAccountResponse())->setRequestid($this->requestId)
                                                      ->setRequestdate(new \DateTime())
                                                      ->setState(ACCOUNT_UNCHECKED);

        $serializer = $this->container->get('jms_serializer');
        $this->serializedRequest = $serializer->serialize($this->request, 'json');
        $this->serializedResponse = $serializer->serialize($this->response, 'json');

        $this->serializer = $this->getCustomMock(SerializerInterface::class);
        $this->serializer->method('serialize')
                         ->willReturnCallback(function($data, $format) use($serializer){
                             return $serializer->serialize($data, $format);
                         });

        $this->row = (new CheckAccount())->setPartner($this->partner)
                                         ->setResponse(json_decode($this->serializedResponse, true))
                                         ->setRequest(json_decode($this->serializedRequest, true))
                                         ->setApiVersion(2);

        $this->repo = $this->getCustomMock(ObjectRepository::class);

        unset($serializer);
    }

    public function _after()
    {
        $this->assertEquals($this->request->getUserData(), $this->response->getUserdata());
        unset($this->request, $this->response, $this->serializer, $this->row, $this->serializedRequest, $this->serializedResponse);
        parent::_after();
    }

    public function testTimeout()
    {
        $this->serializer->expects($this->exactly(2))
            ->method('deserialize')
            ->will($this->onConsecutiveCalls($this->response, $this->request));
        $this->row->setQueuedate(new \DateTime('-5 minute'));
        $this->getCheckAccountWorker(null, null, $this->repo)->execute($this->row);
        $this->assertEquals(ACCOUNT_TIMEOUT, $this->response->getState());
    }

    public function testSuccessBeforeTimeout()
    {
        $this->serializer->expects($this->exactly(3))
            ->method('deserialize')
            ->will($this->onConsecutiveCalls($this->response, $this->request));
        $this->row->setQueuedate(new \DateTime('now'));
        $this->getCheckAccountWorker(null, null, $this->repo)->execute($this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $this->response->getState());
    }

}