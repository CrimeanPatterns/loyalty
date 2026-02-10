<?php

namespace Tests\Unit;

use AppBundle\Document\BaseDocument;
use AppBundle\Document\CheckAccount;
use AppBundle\Extension\HistoryProcessor;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\SendToAW;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Extension\Watchdog;
use AppBundle\Service\ApiValidator;
use AppBundle\Service\BrowserStateFactory;
use AppBundle\Service\EngineStatus;
use AppBundle\Service\Otc\Cache;
use AppBundle\Worker\CheckExecutor\CheckAccountExecutor;
use AppBundle\Worker\CheckExecutor\CheckConfirmationExecutor;
use AppBundle\Worker\CheckExecutor\RaHotelExecutor;
use AppBundle\Worker\CheckExecutor\RegisterAccountExecutor;
use AppBundle\Worker\CheckExecutor\RewardAvailabilityExecutor;
use AwardWallet\Common\Airport\AirportTime;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use AwardWallet\Common\Parsing\Solver\Helper\FlightHelper;
use AwardWallet\Common\Parsing\Solver\Helper\FSHelper;
use AwardWallet\Common\Parsing\Filter\ItinerariesFilter;
use AwardWallet\Common\Parsing\Solver\MasterSolver;
use AwardWallet\ExtensionWorker\ClientFactory;
use AwardWallet\ExtensionWorker\ParserFactory;
use AwardWallet\ExtensionWorker\ParserRunner;
use AwardWallet\ExtensionWorker\ProviderInfoFactory;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Symfony\Component\EventDispatcher\EventDispatcher;

class BaseWorkerTestClass extends BaseTestClass {

    /** @var  string */
    protected $aesKey;
    /** @var ApiValidator */
    protected $validatorMock;
    /** @var BaseDocument */
    protected $row;

    public function _before() {
        parent::_before();
        $this->aesKey = $this->container->getParameter('aes_key_local_browser_state');
        $this->row = new CheckAccount();
        $this->row->setId(bin2hex(random_bytes(10)));
        $this->row->setPartner($this->partner);
        $this->row->setApiVersion(1);

        $this->validatorMock = $this->getCustomMock(ApiValidator::class);
        $this->validatorMock->method('validate')->willReturn([]);
    }

    public function _after() {
        $this->aesKey = null;
        $this->validatorMock = null;
        $this->row = null;
        parent::_after();
    }

    protected function getCheckAccountWorker($logger = null, $manager = null, $repo = null, $s3Client = null, $sender = null, $mqProducer = null, $memcached = null, $connection = null, $factory = null, $awsUtil = null, $currencyConverter = null, $shared_connection = null, $tc = null){

        if (!isset($memcached)) {
            $memcached = @$this->getCustomMock(\Memcached::class);
            $memcached
                ->method('get')
                ->willReturn(false)
            ;
        }
        if (!isset($tc)) {
            $tc = @$this->getCustomMock(TimeCommunicator::class);
            $tc
                ->method('getCurrentTime')
                ->willReturn(time())
            ;
        }

        $worker = new CheckAccountExecutor(
            isset($logger)   ? $logger   : $this->getCustomMock(Logger::class),
            isset($connection)   ? $connection   : $this->connection,
            isset($shared_connection)   ? $shared_connection   : $this->shared_connection,
            isset($manager)  ? $manager  : $this->getCustomMock(DocumentManager::class),
            $this->loader,
            $factory ?? $this->container->get('aw.checker_factory'),
            isset($s3Client) ? $s3Client : $this->getCustomMock(S3Custom::class),
            $this->getCustomMock(Producer::class),
            isset($sender) ? $sender : $this->getCustomMock(MQSender::class),
            $this->serializer,
            isset($mqProducer) ? $mqProducer : $this->getCustomMock(Producer::class),
            $memcached, // suppress Declaration of Mock_MemcachedService_02926df7::get should be compatible with Memcached::get (php bug)
            $this->aesKey,
            $this->validatorMock,
            $this->getCustomMock(ItinerariesFilter::class),
            $this->getMasterSolver(),
            $this->getCustomMock(Watchdog::class),
            $this->getCustomMock(EventDispatcher::class),
            $awsUtil ?? $this->getCustomMock(Util::class),
            $currencyConverter ?? $this->getCustomMock(CurrencyConverter::class),
            $tc,
            $this->getCustomMock(ParserFactory::class),
            $this->getCustomMock(ParserRunner::class),
            $this->getCustomMock(ClientFactory::class),
            $this->getCustomMock(ProviderInfoFactory::class),
        );
        $worker->setMongoRepo(isset($repo) ? $repo : $this->getCustomMock(ObjectRepository::class));
        $worker->setHistoryProcessor($this->getCustomMock(HistoryProcessor::class));

        return $worker;
    }

    protected function getRewardAvailabilityWorker($logger = null, $manager = null, $repo = null, $s3Client = null, $sender = null, $mqProducer = null, $memcached = null, $connection = null, $factory = null, $awsUtil = null, $currencyConverter = null, $bsFactory = null, $airportTime = null, $shared_connection = null, $tc = null):RewardAvailabilityExecutor
    {

        if (!isset($memcached)) {
            $memcached = @$this->getCustomMock(\Memcached::class);
            $memcached
                ->method('get')
                ->willReturn(false)
            ;
        }
        if (!isset($tc)) {
            $tc = @$this->getCustomMock(TimeCommunicator::class);
            $tc
                ->method('getCurrentTime')
                ->willReturn(time())
            ;
        }

        $worker = new RewardAvailabilityExecutor(
            isset($logger)   ? $logger   : $this->getCustomMock(Logger::class),
            isset($connection)   ? $connection   : $this->connection,
            isset($shared_connection)   ? $shared_connection   : $this->shared_connection,
            isset($manager)  ? $manager  : $this->getCustomMock(DocumentManager::class),
            $this->loader,
            $factory ?? $this->container->get('aw.checker_factory'),
            isset($s3Client) ? $s3Client : $this->getCustomMock(S3Custom::class),
            $this->getCustomMock(Producer::class),
            isset($sender) ? $sender : $this->getCustomMock(MQSender::class),
            $this->serializer,
            isset($mqProducer) ? $mqProducer : $this->getCustomMock(Producer::class),
            $memcached, // suppress Declaration of Mock_MemcachedService_02926df7::get should be compatible with Memcached::get (php bug)
            $this->aesKey,
            $this->validatorMock,
            $this->getCustomMock(ItinerariesFilter::class),
            $this->getMasterSolver(),
            $this->getCustomMock(Watchdog::class),
            $this->getCustomMock(EventDispatcher::class),
            $awsUtil ?? $this->getCustomMock(Util::class),
            $currencyConverter ?? $this->getCustomMock(CurrencyConverter::class),
            $tc,
            $bsFactory ?? $this->getCustomMock(BrowserStateFactory::class),
            $airportTime ?? $this->getCustomMock(AirportTime::class),
            $this->getCustomMock(Cache::class),
            0,
            $this->getCustomMock(FSHelper::class),
            $this->getCustomMock(FlightHelper::class),
            $this->getCustomMock(SendToAW::class),
            'awardwallet',
            $this->getCustomMock(ParserFactory::class),
            $this->getCustomMock(ParserRunner::class),
            $this->getCustomMock(ClientFactory::class),
            $this->getCustomMock(ProviderInfoFactory::class),
        );
        $worker->setMongoRepo(isset($repo) ? $repo : $this->getCustomMock(ObjectRepository::class));
        $worker->setHistoryProcessor($this->getCustomMock(HistoryProcessor::class));

        return $worker;
    }

    protected function getRegisterAccountWorker($logger = null, $manager = null, $repo = null, $s3Client = null, $sender = null, $mqProducer = null, $memcached = null, $connection = null, $factory = null, $awsUtil = null, $currencyConverter = null, $bsFactory = null, $airportTime = null, $shared_connection = null):RegisterAccountExecutor
    {

        if (!isset($memcached)) {
            $memcached = @$this->getCustomMock(\Memcached::class);
            $memcached
                ->method('get')
                ->willReturn(false)
            ;
        }
        $tc = @$this->getCustomMock(TimeCommunicator::class);
        $tc
            ->method('getCurrentTime')
            ->willReturn(time())
        ;

        $worker = new RegisterAccountExecutor(
            isset($logger)   ? $logger   : $this->getCustomMock(Logger::class),
            isset($connection)   ? $connection   : $this->connection,
            isset($shared_connection)   ? $shared_connection   : $this->shared_connection,
            isset($manager)  ? $manager  : $this->getCustomMock(DocumentManager::class),
            $this->loader,
            $factory ?? $this->container->get('aw.checker_factory'),
            isset($s3Client) ? $s3Client : $this->getCustomMock(S3Custom::class),
            $this->getCustomMock(Producer::class),
            isset($sender) ? $sender : $this->getCustomMock(MQSender::class),
            $this->serializer,
            isset($mqProducer) ? $mqProducer : $this->getCustomMock(Producer::class),
            $memcached, // suppress Declaration of Mock_MemcachedService_02926df7::get should be compatible with Memcached::get (php bug)
            $this->aesKey,
            $this->validatorMock,
            $this->getCustomMock(ItinerariesFilter::class),
            $this->getMasterSolver(),
            $this->getCustomMock(Watchdog::class),
            $this->getCustomMock(EventDispatcher::class),
            $awsUtil ?? $this->getCustomMock(Util::class),
            $currencyConverter ?? $this->getCustomMock(CurrencyConverter::class),
            $tc,
            $this->getCustomMock(ParserFactory::class),
            $this->getCustomMock(ParserRunner::class),
            $this->getCustomMock(ClientFactory::class),
            $this->getCustomMock(Cache::class),
            0,
            'awardwallet',
            $this->getCustomMock(ProviderInfoFactory::class),
        );
        $worker->setMongoRepo(isset($repo) ? $repo : $this->getCustomMock(ObjectRepository::class));
        $worker->setHistoryProcessor($this->getCustomMock(HistoryProcessor::class));

        return $worker;
    }

    protected function getRaHotelWorker($logger = null, $manager = null, $repo = null, $s3Client = null, $sender = null, $mqProducer = null, $memcached = null, $connection = null, $factory = null, $awsUtil = null, $currencyConverter = null, $bsFactory = null, $airportTime = null, $shared_connection = null):RaHotelExecutor
    {

        if (!isset($memcached)) {
            $memcached = @$this->getCustomMock(\Memcached::class);
            $memcached
                ->method('get')
                ->willReturn(false)
            ;
        }
        $tc = @$this->getCustomMock(TimeCommunicator::class);
        $tc
            ->method('getCurrentTime')
            ->willReturn(time());

        $worker = new RaHotelExecutor(
            isset($logger)   ? $logger   : $this->getCustomMock(Logger::class),
            isset($connection)   ? $connection   : $this->connection,
            isset($shared_connection)   ? $shared_connection   : $this->shared_connection,
            isset($manager)  ? $manager  : $this->getCustomMock(DocumentManager::class),
            $this->loader,
            $factory ?? $this->container->get('aw.checker_factory'),
            isset($s3Client) ? $s3Client : $this->getCustomMock(S3Custom::class),
            $this->getCustomMock(Producer::class),
            isset($sender) ? $sender : $this->getCustomMock(MQSender::class),
            $this->serializer,
            isset($mqProducer) ? $mqProducer : $this->getCustomMock(Producer::class),
            $memcached, // suppress Declaration of Mock_MemcachedService_02926df7::get should be compatible with Memcached::get (php bug)
            $this->aesKey,
            $this->validatorMock,
            $this->getCustomMock(ItinerariesFilter::class),
            $this->getMasterSolver(),
            $this->getCustomMock(Watchdog::class),
            $this->getCustomMock(EventDispatcher::class),
            $awsUtil ?? $this->getCustomMock(Util::class),
            $currencyConverter ?? $this->getCustomMock(CurrencyConverter::class),
            $tc,
            $bsFactory ?? $this->getCustomMock(BrowserStateFactory::class),
            $airportTime ?? $this->getCustomMock(AirportTime::class),
            $this->getCustomMock(Cache::class),
            0,
            $this->getCustomMock(FSHelper::class),
            $this->getCustomMock(FlightHelper::class),
            $this->getCustomMock(SendToAW::class),
            'awardwallet',
            $this->getCustomMock(ParserFactory::class),
            $this->getCustomMock(ParserRunner::class),
            $this->getCustomMock(ClientFactory::class),
            $this->getCustomMock(ProviderInfoFactory::class),
        );
        $worker->setMongoRepo(isset($repo) ? $repo : $this->getCustomMock(ObjectRepository::class));
        $worker->setHistoryProcessor($this->getCustomMock(HistoryProcessor::class));

        return $worker;
    }

    protected function getCheckConfirmationWorker($logger = null, $manager = null, $repo = null, $s3Client = null, $sender = null, $mqProducer = null, $memcached = null){
        $tc = @$this->getCustomMock(TimeCommunicator::class);
        $tc
            ->method('getCurrentTime')
            ->willReturn(time());
        $worker = new CheckConfirmationExecutor(
            isset($logger)   ? $logger   : $this->getCustomMock(Logger::class),
            $this->connection, $this->shared_connection,
            isset($manager)  ? $manager  : $this->getCustomMock(DocumentManager::class),
            $this->loader,
            $this->container->get('aw.checker_factory'),
            isset($s3Client) ? $s3Client : $this->getCustomMock(S3Custom::class),
            $this->getCustomMock(Producer::class),
            isset($sender) ? $sender : $this->getCustomMock(MQSender::class),
            $this->serializer,
            isset($mqProducer) ? $mqProducer : $this->getCustomMock(Producer::class),
            isset($memcached) ? $memcached : $this->getCustomMock(\Memcached::class),
            $this->aesKey,
            $this->validatorMock,
            $this->getCustomMock(ItinerariesFilter::class),
            $this->getMasterSolver(),
            $this->getCustomMock(Watchdog::class),
            $this->getCustomMock(EventDispatcher::class),
            $this->getCustomMock(Util::class),
            $this->getCustomMock(CurrencyConverter::class),
            $tc
        );
        $worker->setMongoRepo(isset($repo) ? $repo : $this->getCustomMock(ObjectRepository::class));

        return $worker;
    }

    protected function getMasterSolver()
    {
        return $this->getCustomMock(MasterSolver::class);
    }

    protected function getEngineStatus()
    {
        $mock = $this->getCustomMock(EngineStatus::class);
        $mock->method('isFresh')->willReturn(true);

        return $mock;
    }
}
