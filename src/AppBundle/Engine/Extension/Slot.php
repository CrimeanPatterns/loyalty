<?php

namespace AppBundle\Extension;

class Slot
{

    /**
     * @var string
     */
    private $lockName;
    /**
     * @var Locker
     */
    private $locker;
    /**
     * @var int
     */
    private $slotNumber;

    public function __construct(string $prefix, int $slotNumber, Locker $locker)
    {
        $this->lockName = $prefix . "_" . $slotNumber;
        $this->locker = $locker;
        $this->slotNumber = $slotNumber;
    }

    public function keep(int $ttl) : bool
    {
        return $this->locker->keep($this->lockName, $ttl);
    }

    public function release()
    {
        $this->locker->release($this->lockName);
    }

    public function getSlotNumber() : int
    {
        return $this->slotNumber;
    }

}