<?php


namespace Tests\Unit;


use AppBundle\Document\BaseDocument;
use AppBundle\Document\RaAccount;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RewardAvailability;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\MQSender;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\Departure;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelResponse;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Model\Resources\RewardAvailability\Travelers;
use AppBundle\Repository\RaAccountRepository;
use AppBundle\Service\ApiValidator;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\NullLogger;

class RaHotelWorkerTest extends BaseWorkerTestClass
{

    /** @var string */
    private $requestId;
    /** @var RaHotelRequest */
    private $request;
    /** @var RaHotelResponse */
    private $response;
    /** @var ObjectRepository */
    private $repo;
    /** @var RaHotel */
    protected $row;
    private $checkIn;
    private $checkOut;

    public function _before()
    {
        parent::_before();
        $this->checkIn = date("Y-m-d", strtotime('+1 month'));
        $this->checkOut = date("Y-m-d", strtotime('+1 day', strtotime($this->checkIn)));
        $this->requestId = 'someId_' . time() . rand(10000, 99999);
        $this->request = (new RaHotelRequest())
            ->setProvider('testprovider')
            ->setUserData('{requestId: "' . $this->requestId . '"}')
            ->setCheckInDate(new \DateTime($this->checkIn))
            ->setCheckOutDate(new \DateTime($this->checkOut))
            ->setDestination('')
            ->setNumberOfAdults(2)
            ->setNumberOfKids(1)
            ->setNumberOfRooms(2)
            ->setPriority(5);

        $this->row = (new RaHotel())
            ->setPartner($this->partner)
            ->setApiVersion(2)
            ->setRequest($this->request)
        ;
    }

    public function testSuccess()
    {
        $this->response = (new RaHotelResponse())
            ->setRequestid($this->requestId)
            ->setState(ACCOUNT_UNCHECKED)
            ->setRequestdate(new \DateTime());

        $this->row
            ->setRequest($this->request)
            ->setResponse($this->response);

        $mqSenderMock = $this->getCustomMock(MQSender::class);
        $mqSenderMock->expects(self::any())
            ->method('dumpPartnerStatistic')
            ->willReturnCallback(
                function ($method, LoyaltyRequestInterface $request, BaseDocument $row, $callbackRetries, $callbackSent)
                {
                    $this->assertTrue($row instanceof RaHotel);
                    $this->assertEquals($row->getResponse()->getRequestId(), $this->requestId);
                }
            );

        $this->validatorMock = $this->getCustomMock(ApiValidator::class);
        $this->validatorMock->expects(self::any())
            ->method('validate')
            ->willReturnCallback(
                function (\stdClass $response, string $definitionName, int $apiVersion, bool $isHiddenVersion = false) {
                    $this->assertEquals($definitionName, basename(str_replace('\\', '/', get_class($this->response))));
                    $this->assertTrue($isHiddenVersion);
                    $this->assertEquals(2, $apiVersion);
                    return [];
                }
            );

        $this->getRaHotelWorker(null, $this->getMongoManagerMock(true), $this->repo, null, $mqSenderMock)->execute($this->row);
        self::assertEquals(ACCOUNT_CHECKED, $this->response->getState());
        self::assertNull($this->response->getDebugInfo());
    }

    private function getMongoManagerMock($checked)
    {
        $id = 'testaccid';
        $acc = new RaAccount('testemail', 'testlogin', 'testpass', 'email');
        $acc->setId($id);

        $repoMock = $this->createMock(RaAccountRepository::class);
        $repoMock
            ->expects($this->once())
            ->method('findBestAccount')
            ->with('testprovider', false)
            ->willReturn($acc);
        if ($checked) {
            $repoMock
                ->expects($this->once())
                ->method('find')
                ->with($id)
                ->willReturn($acc);
        }
        $repoMock
            ->expects($this->once())
            ->method('decodePassword')
            ->with($acc->getPass())
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