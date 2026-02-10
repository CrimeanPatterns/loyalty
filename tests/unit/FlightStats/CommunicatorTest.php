<?php


namespace unit\FlightStats;


use AwardWallet\Common\FlightStats\Cache;
use AwardWallet\Common\FlightStats\Communicator;
use AwardWallet\Common\FlightStats\CommunicatorCallException;
use AwardWallet\Common\FlightStats\Schedule;
use AwardWallet\Common\FlightStats\ScheduleAppendix;
use Codeception\Module\Symfony;
use Codeception\Specify;
use Codeception\Test\Unit;
use Codeception\Util\Stub;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @backupGlobals disabled
 */
class CommunicatorTest extends Unit
{
    use Specify;

    /**
     * @var \HttpDriverInterface
     */
    private $httpDriver;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var \Memcached
     */
    private $memcachedEmpty;
    /**
     * @var \Memcached
     */
    private $memcachedHit;
    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;
    /**
     * @var string
     */
    private $apiId;
    /**
     * @var string
     */
    private $apiKey;
    /**
     * @var Communicator
     */
    private $communicator;


    protected function setUp() : void
    {
        $this->httpDriver = Stub::makeEmpty(\HttpDriverInterface::class, ['request' => new \HttpDriverResponse('response text')]);
        $this->logger = Stub::makeEmpty(LoggerInterface::class);
        $this->serializer = Stub::makeEmpty(SerializerInterface::class, ['deserialize' => function ($arg, $class) {
            $result = Stub::makeEmpty($class);
            $result->_original = $arg;
            return $result;
        }]);
        $this->memcachedEmpty = @Stub::makeEmpty(\Memcached::class, ['get' => false]);
        $this->memcachedHit = @Stub::makeEmpty(\Memcached::class, ['get' => 'cached response']);
        $this->eventDispatcher = Stub::makeEmpty(EventDispatcher::class);
        $this->apiId = 'testID';
        $this->apiKey = 'testKEY';
        $this->communicator = new Communicator($this->httpDriver, $this->logger, $this->serializer, $this->memcachedEmpty, $this->eventDispatcher, Stub::make(Cache::class), $this->apiId, $this->apiKey);
    }

    public function testGetScheduleByRouteAndDate()
    {
        $this->specifyConfig()->shallowClone('communicator');
        //Не клонируемый
        $this->specifyConfig()->ignore('memcachedEmpty', 'memcachedHit');
        $from = 'AAA';
        $to = 'BBB';
        $date = date('Y-m-d\TH:i:s');

        $this->specify('No cache, getting new value', function () use ($from, $to, $date) {
            $schedule = $this->communicator->getScheduleByRouteAndDate($from, $to, $date);
            verify($schedule->_original)->equals("response text");
        });

        $this->specify('Cache hit, return from cache', function () use ($from, $to, $date) {
            $this->communicator = new Communicator($this->httpDriver, $this->logger, $this->serializer, $this->memcachedHit, $this->eventDispatcher, Stub::make(Cache::class), $this->apiId, $this->apiKey);
            $schedule = $this->communicator->getScheduleByRouteAndDate($from, $to, $date);
            verify($schedule->_original)->equals('cached response');
        });

        $this->specify('curl error with HttpDriver, should return null', function () use ($from, $to, $date) {
            $response = new \HttpDriverResponse('response text');
            $response->errorCode = 1;
            /** @var \HttpDriverInterface $httpDriver */
            $httpDriver = Stub::makeEmpty(\HttpDriverInterface::class, ['request' => $response]);
            $this->communicator = new Communicator($httpDriver, $this->logger, $this->serializer, $this->memcachedEmpty, $this->eventDispatcher, Stub::make(Cache::class), $this->apiId, $this->apiKey);
            $schedule = $this->communicator->getScheduleByRouteAndDate($from, $to, $date);
            verify($schedule)->null();
        });

        $this->specify('Response HTTP code >= 400, should return null', function () use ($from, $to, $date) {
            $response = new \HttpDriverResponse('response text');
            $response->httpCode = 404;
            /** @var \HttpDriverInterface $httpDriver */
            $httpDriver = Stub::makeEmpty(\HttpDriverInterface::class, ['request' => $response]);
            $this->communicator = new Communicator($httpDriver, $this->logger, $this->serializer, $this->memcachedEmpty, $this->eventDispatcher, Stub::make(Cache::class), $this->apiId, $this->apiKey);
            $schedule = $this->communicator->getScheduleByRouteAndDate($from, $to, $date);
            verify($schedule)->null();
        });
    }

}