<?php


namespace Tests\Unit;


use AppBundle\Document\BaseDocument;
use AppBundle\Document\RaAccount;
use AppBundle\Document\RewardAvailability;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\Departure;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Model\Resources\RewardAvailability\Travelers;
use AppBundle\Repository\RaAccountRepository;
use AppBundle\Service\ApiValidator;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\NullLogger;

class RewardAvailabilityWorkerTest extends BaseWorkerTestClass
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
    private $depDate;

    public function _before()
    {
        parent::_before();
        $this->depDate = date("Y-m-d", strtotime('+1 month'));
        $this->requestId = 'someId_' . time() . rand(10000, 99999);
        $this->request = (new RewardAvailabilityRequest())
            ->setProvider('testprovider')
            ->setUserData('{requestId: "' . $this->requestId . '"}')
            ->setArrival('JFK')
            ->setCurrency('USD')
            ->setDeparture(
                (new Departure())
                    ->setAirportCode('LAX')
                    ->setDate(new \DateTime($this->depDate))
                    ->setFlexibility(1)
            )
            ->setPassengers(
                (new Travelers())->setAdults(1)
            )
            ->setCabin('economy');

        $this->row = (new RewardAvailability())
            ->setPartner($this->partner)
            ->setApiVersion(2);

        $this->repo = $this->getCustomMock(ObjectRepository::class);
    }

    public function testSuccess()
    {
        $this->response = (new RewardAvailabilityResponse())
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
                    $this->assertTrue($row instanceof RewardAvailability);
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

        $this->getRewardAvailabilityWorker(null, $this->getMongoManagerMock(true), $this->repo, null, $mqSenderMock)->execute($this->row);
        self::assertEquals(ACCOUNT_CHECKED, $this->response->getState());
        self::assertNull($this->response->getDebugInfo());
    }

    public function testTimeout()
    {
        $this->response = (new RewardAvailabilityResponse())
            ->setRequestid($this->requestId)
            ->setState(ACCOUNT_UNCHECKED)
            ->setRequestdate(new \DateTime());

        $this->request->setTimeout(1);
        $this->row
            ->setRequest($this->request)
            ->setResponse($this->response);

        $mqSenderMock = $this->getCustomMock(MQSender::class);
        $mqSenderMock->expects(self::any())
            ->method('dumpPartnerStatistic')
            ->willReturnCallback(
                function ($method, LoyaltyRequestInterface $request, BaseDocument $row, $callbackRetries, $callbackSent)
                {
                    $this->assertTrue($row instanceof RewardAvailability);
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
        $tc = @$this->getCustomMock(TimeCommunicator::class);
        $tc
            ->method('getCurrentTime')
            ->willReturn(time()+5)
        ;
        $this->getRewardAvailabilityWorker(null, $this->getMongoManagerMock(false), $this->repo, null, $mqSenderMock, null,null,null,null,null,null,null,null,null, $tc)->execute($this->row);
        self::assertEquals(ACCOUNT_TIMEOUT, $this->response->getState());
        self::assertNull($this->response->getDebugInfo());
    }

    public function testZeroTimeout()
    {
        $this->response = (new RewardAvailabilityResponse())
            ->setRequestid($this->requestId)
            ->setState(ACCOUNT_UNCHECKED)
            ->setRequestdate(new \DateTime());

        $this->request->setTimeout(0);
        $this->row
            ->setRequest($this->request)
            ->setResponse($this->response);

        $mqSenderMock = $this->getCustomMock(MQSender::class);
        $mqSenderMock->expects(self::any())
            ->method('dumpPartnerStatistic')
            ->willReturnCallback(
                function ($method, LoyaltyRequestInterface $request, BaseDocument $row, $callbackRetries, $callbackSent)
                {
                    $this->assertTrue($row instanceof RewardAvailability);
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
        $tc = @$this->getCustomMock(TimeCommunicator::class);
        $tc
            ->method('getCurrentTime')
            ->willReturn(time()+5)
        ;
        $this->getRewardAvailabilityWorker(null, $this->getMongoManagerMock(true), $this->repo, null, $mqSenderMock, null,null,null,null,null,null,null,null,null, $tc)->execute($this->row);
        self::assertEquals(ACCOUNT_CHECKED, $this->response->getState());
        self::assertNull($this->response->getDebugInfo());
    }

    public function testWarningOnExchangeRate()
    {
        $this->response = (new RewardAvailabilityResponse())
            ->setRequestid($this->requestId)
            ->setState(ACCOUNT_UNCHECKED)
            ->setRequestdate(new \DateTime());
        $this->row
            ->setRequest($this->request)
            ->setResponse($this->response);

        $checkerMock = $this->createCheckerMock();

        $checkerFactory = $this->getCustomMock(CheckerFactory::class);
        $checkerFactory
            ->expects(self::any())
            ->method('getRewardAvailabilityChecker')
            ->willReturn($checkerMock);

        $currencyConverterMock = $this->getCustomMock(CurrencyConverter::class);
        $currencyConverterMock
            ->expects(self::once())
            ->method('getExchangeRate')
            ->willReturn(null);

        $this->getRewardAvailabilityWorker(null, $this->getMongoManagerMock(false), $this->repo, null, null, null, null, null, $checkerFactory, null,
            $currencyConverterMock)
            ->execute($this->row);
        self::assertEquals(ACCOUNT_WARNING, $this->response->getState());
    }

    private function createCheckerMock()
    {
        $checkerResponse = [
            'routes' => [
                [
                    'distance' => null,
                    'num_stops' => 0,
                    'times' => ['flight' => '11:20', 'layover' => '00:00',],
                    'redemptions' => ['miles' => 55000, 'program' => 'british'],
                    'payments' => ['currency' => 'ZZZ', 'taxes' => 5.6, 'fees' => null],
                    'connections' => [
                        [
                            'departure' => [
                                'date' => $this->depDate . ' 15:30',
                                'airport' => 'LHR',
                                'terminal' => "A",
                            ],
                            'arrival' => [
                                'date' => $this->depDate . ' 16:30',
                                'airport' => 'CDG',
                                'terminal' => 1,
                            ],
                            'meal' => 'Dinner',
                            'cabin' => 'business',
                            'fare_class' => 'HN',
                            'flight' => ['BA0269',],
                            'airline' => 'BA',
                            'operator' => 'BA',
                            'distance' => null,
                            'aircraft' => 'Boeing 787 jet',
                            'times' => ['flight' => '11:20', 'layover' => '00:00',],
                        ],
                    ],
                    'tickets' => null,
                    'award_type' => 'Standard Reward',
                ]
            ]
        ];

        $checkerMock = $this->createMock(\TAccountChecker::class);
        $checkerMock
            ->expects(self::any())
            ->method('ParseRewardAvailability')
            ->willReturn($checkerResponse);
        $checkerMock
            ->expects(self::any())
            ->method('Check')
            ->willReturnCallback(function () use ($checkerMock) {
                call_user_func($checkerMock->onLoggedIn);
            });
        $checkerMock->ErrorCode = ACCOUNT_UNCHECKED;
        $checkerMock->DebugInfo = 'should not be in response';
        $checkerMock->logger = new NullLogger();
        $checkerMock->AccountFields = [
            'Partner' => $this->partner,
            'ProviderCode' => 'testprovider',
            'Method' => 'reward-availability'
        ];
        return $checkerMock;
    }

    private function getMongoManagerMock($checked)
    {
        $id = 'testaccid';
        $acc = new RaAccount('testemail', 'testlogin', 'testpass', 'email');
        $acc->setId($id);

        $repoMock = $this->createMock(RaAccountRepository::class);
        if ($checked) {
            $repoMock
                ->expects($this->once())
                ->method('findBestAccount')
                ->with('testprovider', false)
                ->willReturn($acc);
            $repoMock
                ->expects($this->once())
                ->method('find')
                ->with($id)
                ->willReturn($acc);
            $repoMock
                ->expects($this->once())
                ->method('decodePassword')
                ->with($acc->getPass())
                ->willReturnArgument(0);
        } else {
            $repoMock->expects($this->never())->method('find');
            $repoMock->expects($this->never())->method('decodePassword');
        }

        $managerMock = $this->createMock(DocumentManager::class);
        $managerMock
            ->expects($this->any())
            ->method('getRepository')
            ->with(RaAccount::class)
            ->willReturn($repoMock);
        return $managerMock;
    }
}