<?php

namespace Tests\Unit;
use AppBundle\Extension\MemcachedService;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Extension\Watchdog;
use AppBundle\Model\Resources\ChangePasswordRequest;
use AppBundle\Model\Resources\ChangePasswordResponse;
use AppBundle\Worker\CheckExecutor\ChangePasswordExecutor;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use AwardWallet\Common\Parsing\Filter\ItinerariesFilter;
use AwardWallet\ExtensionWorker\ClientFactory;
use AwardWallet\ExtensionWorker\ParserFactory;
use AwardWallet\ExtensionWorker\ParserRunner;
use AwardWallet\ExtensionWorker\ProviderInfoFactory;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @backupGlobals disabled
 */
class ChangePasswordExecutorTest extends \Tests\Unit\BaseWorkerTestClass
{

    protected function getCheckAccountWorker($logger = null, $manager = null, $repo = null, $s3Client = null, $sender = null, $mqProducer = null, $memcached = null, $connection = null, $factory = null, $shared_connection = null){
        $tc = @$this->getCustomMock(TimeCommunicator::class);
        $tc
            ->method('getCurrentTime')
            ->willReturn(time())
        ;
        $worker = new ChangePasswordExecutor(
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
            isset($memcached) ? $memcached : @$this->getCustomMock(MemcachedService::class), // suppress Declaration of Mock_MemcachedService_02926df7::get should be compatible with Memcached::get (php bug)
            $this->aesKey,
            $this->validatorMock,
            $this->getCustomMock(ItinerariesFilter::class),
            $this->getMasterSolver(),
            $this->getCustomMock(Watchdog::class),
            $this->getCustomMock(EventDispatcher::class),
            $this->createMock(Util::class),
            $this->createMock(CurrencyConverter::class),
            $tc,
            $this->getCustomMock(ParserFactory::class),
            $this->getCustomMock(ParserRunner::class),
            $this->getCustomMock(ClientFactory::class),
            $this->getCustomMock(ProviderInfoFactory::class),
        );
        $worker->setMongoRepo(isset($repo) ? $repo : $this->getCustomMock(ObjectRepository::class));

        return $worker;
    }

    public function testSuccess(){
        $request = new ChangePasswordRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('Checker.ChangePassword')
                ->setLogin2('-s')
                ->setPassword('g5f4'.rand(1000,9999).'_q')
                ->setNewPassword('a10u'.rand(1000,9999).'_q');

        $response = new ChangePasswordResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
    }

    public function testUnavailableNewPassword(){
        $request = new ChangePasswordRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('Checker.ChangePassword')
                ->setLogin2('-s')
                ->setPassword('g5f4'.rand(1000,9999).'_q')
                ->setNewPassword('');

        $response = new ChangePasswordResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_INVALID_USER_INPUT, $response->getState());
    }

    public function testParserFalse(){
        $request = new ChangePasswordRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('Checker.ChangePassword')
                ->setLogin2('-f')
                ->setPassword('g5f4'.rand(1000,9999).'_q')
                ->setNewPassword('a10u'.rand(1000,9999).'_q');

        $response = new ChangePasswordResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
    }

    public function testParserCheckException(){
        $request = new ChangePasswordRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setLogin('Checker.ChangePassword')
                ->setLogin2('-e')
                ->setPassword('g5f4'.rand(1000,9999).'_q')
                ->setNewPassword('a10u'.rand(1000,9999).'_q');

        $response = new ChangePasswordResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()->processRequest($request, $response, $this->row);
        $this->assertEquals(ACCOUNT_PROVIDER_ERROR, $response->getState());
    }

}