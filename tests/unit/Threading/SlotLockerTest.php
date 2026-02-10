<?php

namespace Tests\Unit\Threading;

use AppBundle\Extension\Locker;
use AppBundle\Extension\SlotLocker;
use Codeception\Stub;
use Psr\Log\LoggerInterface;

class SlotLockerTest extends \Codeception\TestCase\Test
{

    public function testAcquireOne()
    {
        $acquireCounter = 0;
        $locker = Stub::makeEmpty(Locker::class, [
            'acquire' => function(string $lockName, int $ttl) use(&$acquireCounter){
                $acquireCounter++;
                $this->assertEquals(30, $ttl);
                $this->assertStringStartsWith("ivan_", $lockName);
                return ($acquireCounter === 3);
            }
        ]);

        $slotLocker = new SlotLocker(Stub::makeEmpty(LoggerInterface::class), $locker, 30);

        $slot = $slotLocker->acquire('ivan', 5);
        $this->assertLessThanOrEqual(5, $slot->getSlotNumber());
        $this->assertGreaterThanOrEqual(1, $slot->getSlotNumber());
        $this->assertEquals(3, $acquireCounter);
    }

    public function testAcquireTwo()
    {
        $lockState = [];
        $locker = Stub::makeEmpty(Locker::class, [
            'acquire' => function(string $lockName, int $ttl) use (&$lockState) : bool {
                if (!isset($lockState[$lockName])) {
                    $lockState[$lockName] = true;
                    return true;
                }
                return false;
            },
            'isLocked' => function(string $lockName) use (&$lockState) : bool {
                return isset($lockState[$lockName]);
            }
        ]);

        $slotLocker = new SlotLocker(Stub::makeEmpty(LoggerInterface::class), $locker, 30);

        $slot1 = $slotLocker->acquire('ivan', 2);
        $slot2 = $slotLocker->acquire('ivan', 2);
        $slot3 = $slotLocker->acquire('ivan', 2);

        $this->assertLessThanOrEqual(2, $slot1->getSlotNumber());
        $this->assertGreaterThanOrEqual(1, $slot1->getSlotNumber());

        $this->assertLessThanOrEqual(2, $slot2->getSlotNumber());
        $this->assertGreaterThanOrEqual(1, $slot2->getSlotNumber());

        $this->assertNotEquals($slot1->getSlotNumber(), $slot2->getSlotNumber());

        $this->assertEmpty($slot3);

        $this->assertEquals(2, $slotLocker->total('ivan', 2));
        $this->assertEquals(2, $slotLocker->total('ivan', 3));

        unset($lockState['ivan_1']);
        $this->assertEquals(1, $slotLocker->total('ivan', 2));

        $this->assertEquals(0, $slotLocker->total('petr', 3));
    }

}