<?php

namespace Tests\Unit;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\FileStateManager;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Extension\Watchdog;
use AppBundle\Extension\WorkerState;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\Property;
use AppBundle\Worker\CheckExecutor\CheckAccountExecutor;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\Geo\GeoCoder;
use AwardWallet\Common\Geo\GoogleGeo;
use AwardWallet\Engine\Settings;
use AwardWallet\WebdriverClient\NodeFinder;
use Doctrine\DBAL\Connection;
use Helper\Aw;
use Monolog\Logger;
use Doctrine\ODM\MongoDB\DocumentManager;
use AppBundle\Extension\S3Custom;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use AppBundle\Extension\MemcachedService;
use Doctrine\Common\Persistence\ObjectRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @backupGlobals disabled
 */
class ProviderThrottlerTest extends BaseWorkerTestClass
{

    public function _before()
    {
        parent::_before();
        $throttler = new \Throttler(\Cache::getInstance()->memcached, 10, 6, 2);
        $throttler->clear("testprovider_" . gethostname());
    }

    public function testDoNotThrottleAbovePriority()
    {
        $memcached = $this->container->get('aw.memcached');
        $logger = $this->getCustomMock(Logger::class);
        $googleGeo = $this->getCustomMock(GoogleGeo::class);
        $seleniumConnector = $this->container->get('aw.selenium_connector');

        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setPriority(5)
                ->setLogin('Checker.Requests')
                ->setPassword('1');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $response2 = new CheckAccountResponse();
        $response2->setRequestid(bin2hex(random_bytes(10)));

        $connection = $this->getConnection(1);
        $factory = new CheckerFactory($logger, $connection, $googleGeo, $seleniumConnector, false, $this->getCustomMock(Util::class), $this->getCustomMock(TimeCommunicator::class), $this->getCustomMock(EventDispatcherInterface::class), new \CurlDriver($this->createMock(\Memcached::class)), $this->getCustomMock(ContainerInterface::class));

        $this->getCheckAccountWorker($logger, null, null, null, null, null, $memcached, $connection, $factory)->processRequest($request, $response, $this->row);

        $throttled = false;
        try{
            $this->getCheckAccountWorker($logger, null, null, null, null, null, $memcached, $connection, $factory)->processRequest($request, $response2, $this->row);
        } catch (\ThrottledException $e){
            codecept_debug('throttled with interval '.$e->retryInterval);
            $throttled = true;
        }

        $this->assertEquals(false, $throttled);
    }

    public function testThrottledBelowPriority()
    {
        $memcached = $this->container->get('aw.memcached');
        $logger = $this->getCustomMock(Logger::class);
        $googleGeo = $this->getCustomMock(GoogleGeo::class);
        $seleniumConnector = $this->container->get('aw.selenium_connector');

        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setPriority(2)
                ->setLogin('Checker.Requests')
                ->setPassword('1');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $response2 = new CheckAccountResponse();
        $response2->setRequestid(bin2hex(random_bytes(10)));

        $connection = $this->getConnection(1);
        $factory = new CheckerFactory($logger, $connection, $googleGeo, $seleniumConnector, false, $this->getCustomMock(Util::class), $this->getCustomMock(TimeCommunicator::class), $this->getCustomMock(EventDispatcherInterface::class), new \CurlDriver($this->createMock(\Memcached::class)), $this->getCustomMock(ContainerInterface::class));

        $this->getCheckAccountWorker($logger, null, null, null, null, null, $memcached, $connection, $factory)->processRequest($request, $response, $this->row);

        $throttled = false;
        try{
            $this->getCheckAccountWorker($logger, null, null, null, null, null, $memcached, $connection, $factory)->processRequest($request, $response2, $this->row);
        } catch (\ThrottledException $e){
            codecept_debug('throttled with interval '.$e->retryInterval);
            $throttled = true;
        }

        $this->assertEquals(true, $throttled);
    }

    public function testAccountPerMinute()
    {
        $memcached = $this->container->get('aw.memcached');
        $logger = $this->getCustomMock(Logger::class);
        $googleGeo = $this->getCustomMock(GoogleGeo::class);
        $seleniumConnector = $this->container->get('aw.selenium_connector');

        $request = new CheckAccountRequest();
        $request->setProvider('testprovider')
                ->setUserid('SomeID')
                ->setPriority(2)
                ->setLogin('Checker.Requests')
                ->setPassword('1');

        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $response2 = new CheckAccountResponse();
        $response2->setRequestid(bin2hex(random_bytes(10)));

        $connection = $this->getConnection(-2);
        $factory = new CheckerFactory($logger, $connection, $googleGeo, $seleniumConnector, false, $this->getCustomMock(Util::class), $this->getCustomMock(TimeCommunicator::class), $this->getCustomMock(EventDispatcherInterface::class), new \CurlDriver($this->createMock(\Memcached::class)), $this->getCustomMock(ContainerInterface::class));

        $this->getCheckAccountWorker($logger, null, null, null, null, null, $memcached, $connection, $factory)->processRequest($request, $response, $this->row);
        $this->getCheckAccountWorker($logger, null, null, null, null, null, $memcached, $connection, $factory)->processRequest($request, $response, $this->row);

        $throttled = false;
        try{
            $this->getCheckAccountWorker($logger, null, null, null, null, null, $memcached, $connection, $factory)->processRequest($request, $response2, $this->row);
        } catch (\ThrottledException $e){
            codecept_debug('throttled with interval '.$e->retryInterval);
            $throttled = true;
        }

        $this->assertEquals(true, $throttled);
    }

    protected function getConnection($rpm)
    {
        $mock = $this->getCustomMock(Connection::class);
        $mock->method('executeQuery')->willReturnCallback(function($query, $params = [], $types = [], $qcp = null) use($rpm) {
            if(stripos($query, 'SELECT ProviderID, DisplayName, ShortName, ProgramName, Engine as ProviderEngine, Code, RequestsPerMinute') !== false)
                return $this->connection->executeQuery("SELECT ProviderID, DisplayName, ShortName, ProgramName, Engine as ProviderEngine, Code, $rpm As RequestsPerMinute, 1 as CanCheckItinerary from Provider where code = 'testprovider'");
            if(stripos($query, 'SELECT ThrottleBelowPriority') !== false)
                return $this->connection->executeQuery("SELECT 5 As ThrottleBelowPriority");

            return $this->connection->executeQuery($query, $params, $types, $qcp);
        });
        return $mock;
    }

    public function testPerMinuteWithSelenium()
    {
        $initMethods = [["UseSelenium"]];
        /** @var Aw $aw */
        $aw = $this->getModule('\Helper\Aw');
        $providerCode = "rp" . bin2hex(random_bytes(9));
        $aw->createAwProvider(null, $providerCode, ['RequestsPerMinute' => -1], [
            'InitBrowser' => function () use ($initMethods) {
                /** @var \TAccountChecker $this */
                parent::InitBrowser();
                foreach ($initMethods as $methodAndArguments) {
                    $method = array_shift($methodAndArguments);
                    call_user_func_array([$this, $method], $methodAndArguments);
                }
            },

            'Parse' => function () {
                $this->http->GetURL('http://localhost/1');
                $this->http->GetURL('http://localhost/2');
                $this->http->GetURL('http://localhost/3');
                $this->SetBalance(100);
            }
        ], [], [\SeleniumCheckerHelper::class]);

        $request = new CheckAccountRequest();
        $request->setProvider($providerCode)
            ->setUserid('SomeID')
            ->setLogin('Some')
            ->setPassword('Some');

        $server = new \SeleniumServer("fake", 4444);
        $this->container->set("aw.selenium_finder", new \SeleniumArrayFinder([$server]));

        $nodeFinder = $this->createMock(NodeFinder::class);
        $nodeFinder
            ->expects($this->atMost(1))
            ->method('getNode')
            ->willReturn("fake");
        $aw->mockService(NodeFinder::class, $nodeFinder);

        $manage = $this->createMock(\WebDriverOptions::class);
        $manage
            ->expects($this->once())
            ->method('getCookies')
            ->willReturn([]);

        $webDriver = $this
            ->getMockBuilder(\RemoteWebDriver::class)
            ->disableOriginalConstructor()
            ->getMock();
        $webDriver
            ->expects($this->once())
            ->method('quit');
        $webDriver
            ->expects($this->once())
            ->method('manage')
            ->willReturn($manage);
        $webDriver
            ->method('getCurrentURL')
            ->willReturn('http://some.url/path');
        $webDriver
            ->method('executeAsyncScript')
            ->willReturn([]);

        $seleniumStarter = $this
            ->getMockBuilder(\SeleniumStarter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $seleniumStarter
            ->expects($this->exactly(1))
            ->method('createSession')->willReturnCallback(function (
                \SeleniumServer $server,
                \SeleniumFinderRequest $finderRequest
            ) use ($webDriver) {
                return new \SeleniumConnection($webDriver, "fake", "fake", 4444, '/wd/hub', "/tmp/fake",
                    $finderRequest->getBrowser(), $finderRequest->getVersion(), [\SeleniumStarter::CONTEXT_BROWSER_FAMILY => $finderRequest->getBrowser(), \SeleniumStarter::CONTEXT_BROWSER_VERSION => $finderRequest->getVersion()]);
            });

        $this->container->set("aw.selenium_starter", $seleniumStarter);
        $this->container->get("aw.selenium_connector")->setPauseBetweenNewSessions(0);

        $response = $aw->checkAccount($request);
        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());

        try {
            $aw->checkAccount($request);
        } catch (\ThrottledException $e) {
            codecept_debug('throttled with interval ' . $e->retryInterval);
            $throttled = true;
        }

        $this->assertEquals(true, $throttled);
    }
}