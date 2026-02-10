<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 01/12/2016
 * Time: 16:15
 */

namespace AppBundle\Extension;

use AppBundle\Document\BaseDocument;
use AppBundle\Document\CheckAccount;
use AppBundle\Extension\MQMessages\CallbackRequest;
use Doctrine\DBAL\Connection;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\Producer;

class CallbackPackageProcessor
{

    /** @var Logger */
    private $logger;
    /** @var Connection */
    private $connection;
    /** @var Producer */
    private $callbackProducer;
    /** @var MongoCommunicator */
    private $mongoCommunicator;
    /** @var \Memcached */
    private $memcached;
    /** @var TimeCommunicator */
    private $time;
    /** @var int */
    private $numTestIterations; /* only for test. Num run() method iterations for test */
    /** @var string */
    private $memcachedValue;

    /*
     * $storage EXAMPLE:
     * [
     *      'awardwallet' => [
     *          'StartCollectPackageTime' => 1482386926, // TIMESTAMP first package item put
     *          'Package' => ['57a2390d5d82591213e1f354', '57fe21065d8259b542c07c8e', ...] // requestIds list
     *      ],
     *      'traxo' => [
     *          'StartCollectPackageTime' => 1482386999,
     *          'Package' => [...]
     *      ]
     * ];
     */
    private $storage = [];

    /*
     * $settings EXAMPLE:
     * [
     *      'awardwallet' => [
     *          'CacheTime' => 1482386926, // TIMESTAMP last settings update from MySQL table `Partner`
     *          'PacketPriority' => 5,
     *          'PacketDelay' => 5
     *      ],
     *      'traxo' => [
     *          'CacheTime' => 1482386995,
     *          'PacketPriority' => 10,
     *          'PacketDelay' => 15
     *      ]
     * ];
     */
    private $settings = [];

    const PACKAGE_MAX_SIZE = 20;
    const PARTNER_SETTINGS_CACHE_TIME = 60;
    const WAITING_TIMEOUT = 3;
    const PROCESSOR_KEY_NAME = 'LOYALTY_CALLBACK_PACKAGE_PROCESSOR';
    const PROCESSOR_TTL = 180;

    public function __construct(Logger $logger, Connection $connection, MongoCommunicator $mongoCommunicator, Producer $callbackProducer, \Memcached $memcached, TimeCommunicator $time, $numTestIterations = 0)
    {
        $this->logger = $logger;
        $this->connection = $connection;
        $this->callbackProducer = $callbackProducer;
        $this->mongoCommunicator = $mongoCommunicator;
        $this->memcached = $memcached;
        $this->time = $time;
        $this->numTestIterations = $numTestIterations;

        $this->memcachedValue = gethostname().'_'.getmypid();
    }

    /** @return bool */
    public function canProcess()
    {
        return $this->memcached->add(self::PROCESSOR_KEY_NAME, $this->memcachedValue, self::PROCESSOR_TTL);
    }

    public function stopProcess()
    {
        $info = $this->memcached->get(self::PROCESSOR_KEY_NAME, null, \Memcached::GET_EXTENDED);
        if (!empty($info) && $this->memcachedValue == $info['value']) {
            $this->memcached->cas($info['cas'], self::PROCESSOR_KEY_NAME, $info['value'], $this->time->getCurrentTime() - 3600);
        }
        $this->logger->notice("Process {$this->memcachedValue} stopped!");
    }

    public function run()
    {
        $i = 0; // count iterations
        while (true) {
            $i++;
            /* break when is using test mode after `numTestIterations` iteration */
            if ($this->numTestIterations !== 0 && $this->numTestIterations < $i) {
                break;
            }

            $break = true;
            $info = $this->memcached->get(self::PROCESSOR_KEY_NAME, null, \Memcached::GET_EXTENDED);
            if (!empty($info) && $this->memcachedValue == $info['value']) {
                $break = !$this->memcached->cas($info['cas'], self::PROCESSOR_KEY_NAME, $info['value'], self::PROCESSOR_TTL);
            }

            if ($break) {
                break;
            }

            $this->logger->info("building callbacks");
            $this->buildCallbacks();
            $this->processStorage();
            $this->time->sleep(self::WAITING_TIMEOUT);
        }
    }

    /**
     * collecting callback packages for each partner
     */
    private function buildCallbacks()
    {
        $excludeIds = [];
        foreach ($this->storage as $partner => $data) {
            $excludeIds = array_merge($excludeIds, array_map(function(BaseDocument $document){ return $document->getId(); }, $data['Package']));
        }

        $rows = $this->mongoCommunicator->getPackageCallbacks($excludeIds);
        if (empty($rows)) {
            return;
        }

        /** @var CheckAccount $row */
        foreach ($rows as $row) {
            $partner = $row->getPartner();
            if (!isset($this->storage[$partner])) {
                $this->storage[$partner] = ['Package' => [], 'StartCollectPackageTime' => $this->time->getCurrentTime()];
            }

            if (isset($this->storage[$partner]['Package'][$row->getId()])) {
                continue;
            }

            $this->addRequest($row, $partner);
            if (count($this->storage[$partner]['Package']) >= self::PACKAGE_MAX_SIZE) {
                $this->createCallbackTask($partner);
            }
        }
    }

    private function processStorage()
    {
        if (empty($this->storage)) {
            return;
        }

        foreach ($this->storage as $partner => $data) {
            $delay = $this->getPartnerSettings($partner)['PacketDelay'];
            $startTime = $data['StartCollectPackageTime'];
            if ($delay + $startTime < $this->time->getCurrentTime()) {
                $this->createCallbackTask($partner);
            }
        }
    }

    /**
     * Create RabbitMQ callback message
     * @param string $partner
     */
    private function createCallbackTask($partner)
    {
        if (empty($this->storage[$partner]['Package'])) {
            return;
        }

        $byUrl = [];
        foreach ($this->storage[$partner]['Package'] as $document) {
            /** @var BaseDocument $document */
            if ($document->getRequest() === null || count($document->getRequest()) === 0) {
                $this->logger->notice('Trying to access array offset on value of type null', ['requestId' => $document->getId()]);
            } else {
                $url = $document->getRequest()['callbackUrl'];
            }

            if (!isset($byUrl[$url])) {
                $byUrl[$url] = [];
            }
            $byUrl[$url][] = $document->getId();
        }
        ksort($byUrl); // for tests

        foreach ($byUrl as $url => $package) {
            $settings = $this->getPartnerSettings($partner);
            $priority = $settings['PacketPriority'];

            $callback = new CallbackRequest();
            $callback->setMethod('account')
                ->setPartner($partner)
                ->setPriority($priority)
                ->setIds($package);

            $this->callbackProducer->publish(serialize($callback), '', ['priority' => $priority]);
            $this->mongoCommunicator->updatePackageCallbacksIsSent($package);
            $this->logger->info('Callback package created',
                ['callbackPackage' => $package, 'packageSize' => count($package), 'partner' => $partner, "callbackUrl" => $url]);
        }

        unset($this->storage[$partner]);
    }

    /**
     * Get partner callback settings from MySQL or valid cache
     * @param string $partner
     * @return bool|mixed
     */
    private function getPartnerSettings($partner)
    {
        if (isset($this->settings[$partner]) && $this->settings[$partner]['CacheTime'] + self::PARTNER_SETTINGS_CACHE_TIME > $this->time->getCurrentTime()) {
            return $this->settings[$partner];
        }

        $sql = <<<SQL
              SELECT PacketPriority, PacketDelay FROM Partner
              WHERE Login = :PARTNER
SQL;
        $result = $this->connection->executeQuery($sql, [':PARTNER' => $partner])->fetch();
        if (!$result) {
            $this->logger->critical('Unavailable partner '.$partner);
            return false;
        }

        $this->settings[$partner] = array_merge($result, ['CacheTime' => $this->time->getCurrentTime()]);
        return $this->settings[$partner];
    }

    private function addRequest(BaseDocument $document, $partner)
    {
        $this->storage[$partner]['Package'][$document->getId()] = $document;
        $this->mongoCommunicator->setCallbackQueuedRow($document->getId());
    }
}