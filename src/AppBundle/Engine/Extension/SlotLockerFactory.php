<?php

namespace AppBundle\Extension;

use Psr\Log\LoggerInterface;

class SlotLockerFactory
{

    /**
     * @var \Memcached
     */
    private $memcached;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger, \Memcached $memcached)
    {
        $this->memcached = $memcached;
        $this->logger = $logger;
    }

    public function getSlotLocker(int $pid)
    {
        return new SlotLocker($this->logger, new Locker($this->memcached, null, $pid), 300);
    }

}