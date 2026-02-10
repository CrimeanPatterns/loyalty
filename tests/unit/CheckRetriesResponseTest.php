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
class CheckRetriesResponseTest extends BaseWorkerTestClass
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
        $this->serializer->expects($this->exactly(3))
                         ->method('deserialize')
                         ->will($this->onConsecutiveCalls($this->response, $this->request));
        $this->serializer->method('serialize')
                         ->willReturnCallback(function($data, $format) use($serializer){
                             return $serializer->serialize($data, $format);
                         });

        $this->row = (new CheckAccount())->setPartner($this->partner)
                                         ->setResponse(json_decode($this->serializedResponse, true))
                                         ->setRequest(json_decode($this->serializedRequest, true))
                                         ->setApiVersion(2);

        unset($serializer);
    }

    public function _after()
    {
        unset($this->request, $this->response, $this->serializer, $this->row, $this->serializedRequest, $this->serializedResponse);
        parent::_after();
    }

    public function testLastRetryWithCustomError()
    {
        $this->request->setLogin2(null);
        $this->row->setRetries(1);
        $this->getCheckAccountWorker(null, null, $this->repo)->execute($this->row);
        $this->assertEquals(ACCOUNT_LOCKOUT, $this->response->getState());
        $this->assertEquals($this->request->getUserData(), $this->response->getUserdata());
        $this->assertEmpty($this->response->getBalance());
    }

    public function testLastRetryWithBalance()
    {
        $this->request->setLogin2('-ubl');
        $this->row->setRetries(1);
        $this->getCheckAccountWorker(null, null, $this->repo)->execute($this->row);
        $this->assertEquals(ACCOUNT_ENGINE_ERROR, $this->response->getState());
        $this->assertEquals($this->request->getUserData(), $this->response->getUserdata());
        $this->assertEmpty($this->response->getBalance());
    }

    public function testRetryWithBalance()
    {
        $this->getCheckAccountWorker(null, null, $this->repo)->execute($this->row);
        $this->assertEquals(ACCOUNT_UNCHECKED, $this->response->getState());
    }

}