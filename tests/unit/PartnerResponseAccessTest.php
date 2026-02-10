<?php

namespace Tests\Unit;
use AppBundle\Document\CheckAccount;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use Doctrine\Common\Persistence\ObjectRepository;
use Helper\CustomDb;
use JMS\Serializer\ArrayTransformerInterface;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;

/**
 * @backupGlobals disabled
 */
class PartnerResponseAccessTest extends \Tests\Unit\BaseWorkerTestClass
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
            ->setLogin('Checker.Throttled')
            ->setUserData('{requestId: "'.$this->requestId.'"}');

        $this->response = (new CheckAccountResponse())->setRequestid($this->requestId)
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

    public function testDebugInfoEnabled()
    {
        $partner = $this->partner . '_' . bin2hex(random_bytes(3));
        /** @var CustomDb $db */
        $db = $this->getModule('\Helper\CustomDb');
        $db->haveInDatabase("Partner", ["Login" => $partner, "ReturnHiddenProperties"  => 0, "CanDebug" => 1, "Pass" => "xxx"]);

        $this->row->setPartner($partner);
        $this->getCheckAccountWorker(null, null, $this->repo)->execute($this->row);
        $this->assertNotNull($this->response->getDebuginfo());
    }

    public function testDebugInfoDisabled()
    {
        $this->getCheckAccountWorker(null, null, $this->repo)->execute($this->row);
        $this->assertNull($this->response->getDebuginfo());
    }

}