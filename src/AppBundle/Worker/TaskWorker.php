<?php

namespace AppBundle\Worker;

use PhpAmqpLib\Message\AMQPMessage;
use Monolog\Logger;

abstract class TaskWorker {

    const MAX_MESSAGE_SIZE = 10000000;

    /** @var Logger */
    protected $logger;

    /** @var string */
    protected $name;

    protected $executeTimeLimit = 300;
    private int $memoryLimit;

    public function __construct(Logger $logger, int $memoryLimit) {
        $this->logger = $logger;
        $this->name = substr(basename(str_replace('\\', '/', get_class($this))), 0, -6);
        $this->memoryLimit = $memoryLimit;
    }

    protected function unserialize(AMQPMessage $msg){
        $size = strlen($msg->body);
        if($size > self::MAX_MESSAGE_SIZE)
            throw new SkipException("message too big: $size bytes");

        $this->logger->debug("received message, $size bytes");

        $request = unserialize($msg->body);
        if (false === $request) {
            throw new SkipException("can't unserialize this message, skipping");
        }
        return $request;
    }

    protected function checkLimits(){
        if (defined('PHPUNIT_LOYALTY')) {
            return;
        }

        $usedMemory = memory_get_usage(true);
        $this->logger->debug("memory usage: " . $usedMemory);
        if($usedMemory > $this->memoryLimit){
            $this->logger->notice("memory limit hit, exiting to recycle memory");
            exit();
        }

    }

    public function logProcessor(array $record){
        $record['extra']['worker'] = $this->name;
        return $record;
    }

    /**
     * @return int
     */
    public function getExecuteTimeLimit()
    {
        return $this->executeTimeLimit;
    }

    /**
     * @param int $executeTimeLimit
     */
    public function setExecuteTimeLimit($executeTimeLimit)
    {
        $this->executeTimeLimit = $executeTimeLimit;
    }

}