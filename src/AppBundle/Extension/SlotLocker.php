<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 04/04/2018
 * Time: 12:27
 */

namespace AppBundle\Extension;


use Psr\Log\LoggerInterface;

class SlotLocker
{

    /** @var LoggerInterface */
    private $logger;
    /** @var Locker */
    private $locker;
    /**
     * @var int
     */
    private $ttl;

    public function __construct(LoggerInterface $logger, Locker $locker, int $ttl)
    {
        $this->logger = $logger;
        $this->locker = $locker;
        $this->ttl = $ttl;
    }

    public function acquire(string $prefix, int $maxSlots) : ?Slot
    {
        if ($maxSlots < 1) {
            return null;
        }

        $pool = range(1, $maxSlots);
        shuffle($pool);

        foreach ($pool as $slot) {
            $lockName = $this->getLockName($prefix, $slot);
            $added = $this->locker->acquire($lockName, $this->ttl);
            if ($added) {
                return new Slot($prefix, $slot, $this->locker);
            }
        }

        return null;
    }

    public function total(string $prefix, int $max)
    {
        $count = 0;
        for ($i = 1; $i <= $max; $i++) {
            $value = $this->locker->isLocked($this->getLockName($prefix, $i));
            if ($value !== false) {
                $count++;
            }
        }

        return $count;
    }

    public function releaseMySlots(string $prefix, int $max)
    {
        for ($i = 1; $i <= $max; $i++) {
            // will release only if locked by my pid
            $this->locker->release($this->getLockName($prefix, $i));
        }
    }

    public function release(string $prefix, int $slotNumber)
    {
        $this->locker->release($this->getLockName($prefix, $slotNumber));
    }

    private function getLockName(string $prefix, int $slot)
    {
        return $prefix . '_' . $slot;
    }

}