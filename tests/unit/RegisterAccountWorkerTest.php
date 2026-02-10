<?php


namespace Tests\Unit;


use AppBundle\Document\BaseDocument;
use AppBundle\Document\RaAccount;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RewardAvailability;
use AppBundle\Extension\MQSender;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountResponse;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Repository\RaAccountRepository;
use AppBundle\Service\ApiValidator;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentManager;

class RegisterAccountWorkerTest extends BaseWorkerTestClass
{

    /** @var string */
    private $requestId;
    /** @var RewardAvailabilityRequest */
    private $request;
    /** @var RewardAvailabilityResponse */
    private $response;
    /** @var ObjectRepository */
    private $repo;
    /** @var RewardAvailability */
    protected $row;

    public function _before()
    {
        parent::_before();
        $this->requestId = 'someId_' . time() . rand(10000, 99999);
        $this->request = (new RegisterAccountRequest())
            ->setProvider('testprovider')
            ->setUserData('{requestId: "' . $this->requestId . '"}')
            ->setFields(['FirstName'=>'Success', 'Email'=>'bla@bla.com', 'Password'=>'pass'.rand(100,999)])
;
        $this->row = (new RegisterAccount())
            ->setPartner($this->partner)
            ->setApiVersion(2);;

        $this->repo = $this->getCustomMock(ObjectRepository::class);
        $this->repo->expects(self::never())
            ->method('find')
            ->with($this->requestId)
            ->willReturn($this->row);
    }

    public function testSuccess()
    {
        $this->response = (new RegisterAccountResponse())
            ->setRequestid($this->requestId)
            ->setRequestdate(new \DateTime())
            ->setState(ACCOUNT_UNCHECKED);

        $this->row
            ->setRequest($this->request)
            ->setResponse($this->response);

        $mqSenderMock = $this->getCustomMock(MQSender::class);
        $mqSenderMock->expects(self::any())
            ->method('dumpPartnerStatistic')
            ->willReturnCallback(
                function ($method, LoyaltyRequestInterface $request, BaseDocument $row, $callbackRetries, $callbackSent)
                {
                    $this->assertTrue($row instanceof RegisterAccount);
                    $this->assertEquals($row->getResponse()->getRequestId(), $this->requestId);
                }
            );

        $this->validatorMock = $this->getCustomMock(ApiValidator::class);

        $this->getRegisterAccountWorker(null, $this->getMongoManagerMock(), $this->repo, null, $mqSenderMock)->execute($this->row);
        self::assertEquals(ACCOUNT_CHECKED, $this->response->getState());
        self::assertNull($this->response->getDebugInfo());
        self::assertNotEmpty($this->response->getMessage());
    }

    public function testTimeout()
    {
        $this->response = (new RegisterAccountResponse())
            ->setRequestid($this->requestId)
            ->setRequestdate(new \DateTime())
            ->setState(ACCOUNT_UNCHECKED);

        $this->request
            ->setFields(['FirstName'=>'Timeout', 'Email'=>'bla@bla.com', 'Password'=>'pass'.rand(100,999)]);

        $this->row
            ->setRequest($this->request)
            ->setResponse($this->response);

        $mqSenderMock = $this->getCustomMock(MQSender::class);
        $mqSenderMock->expects(self::any())
            ->method('dumpPartnerStatistic')
            ->willReturnCallback(
                function ($method, LoyaltyRequestInterface $request, BaseDocument $row, $callbackRetries, $callbackSent)
                {
                    $this->assertTrue($row instanceof RegisterAccount);
                    $this->assertEquals($row->getResponse()->getRequestId(), $this->requestId);
                }
            );

        $this->validatorMock = $this->getCustomMock(ApiValidator::class);

        $this->getRegisterAccountWorker(null, $this->getMongoManagerMock(), $this->repo, null, $mqSenderMock)->execute($this->row);
        self::assertEquals(ACCOUNT_INVALID_USER_INPUT, $this->response->getState());
        self::assertNull($this->response->getDebugInfo());
    }

    private function getMongoManagerMock()
    {
        $id = 'testaccid';
        $acc = new RaAccount('testemail', 'testlogin', 'testpass', 'email');
        $acc->setId($id);

        $repoMock = $this->createMock(RaAccountRepository::class);
        $repoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['provider' => 'testprovider', 'email' => 'bla@bla.com'])
            ->willReturn(null);
        $repoMock
            ->expects($this->any())
            ->method('find')
            ->with($id)
            ->willReturn($acc);
        $repoMock
            ->expects($this->any())
            ->method('encodePassword')
            ->willReturnArgument(0);
        $managerMock = $this->createMock(DocumentManager::class);
        $managerMock
            ->expects($this->any())
            ->method('getRepository')
            ->with(RaAccount::class)
            ->willReturn($repoMock);
        return $managerMock;
    }

}