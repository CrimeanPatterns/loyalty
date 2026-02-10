<?php

namespace Tests\Unit\Threading;

use AppBundle\Extension\Locker;
use AppBundle\Extension\PartnerSource;
use AppBundle\Extension\Slot;
use AppBundle\Extension\SlotLocker;
use AppBundle\Extension\SlotLockerFactory;
use AppBundle\Extension\Thread;
use AppBundle\Extension\ThreadFactory;
use Codeception\Stub;
use Psr\Log\LoggerInterface;

class ThreadFactoryTest extends \Codeception\TestCase\Test
{

    public function testCreatePartner()
    {
        $slotLocker = Stub::makeEmpty(SlotLocker::class, [
            'acquire' => function($prefix, $max) : ?Slot {
                if ($prefix === 'ivan') {
                    return new Slot('ivan', 3, Stub::makeEmpty(Locker::class, [], $this));
                }
                return null;
            }
        ], $this);

        $slotLockerFactory = Stub::makeEmpty(SlotLockerFactory::class, [
            'getSlotLocker' => $slotLocker
        ]);

        $partnerSource = Stub::makeEmpty(PartnerSource::class, [
            'getPartners' => ['ivan' => 3, 'petr' => 5]
        ], $this);

        $factory = new ThreadFactory($slotLockerFactory, $partnerSource, Stub::makeEmpty(Locker::class, [], $this), Stub::makeEmpty(LoggerInterface::class));

        $thread = $factory->create();
        $this->assertEquals('ivan', $thread->getPartner());
        $this->assertFalse($thread->isDedicated());
    }

    public function testCreateAwardwallet()
    {
        $slotLocker = Stub::makeEmpty(SlotLocker::class, [
            'acquire' => function($prefix, $max) : ?Slot {
                if ($prefix === 'awardwallet') {
                    return new Slot('awardwallet', 3, Stub::makeEmpty(Locker::class, [], $this));
                }
                return null;
            }
        ], $this);

        $slotLockerFactory = Stub::makeEmpty(SlotLockerFactory::class, [
            'getSlotLocker' => $slotLocker
        ]);

        $partnerSource = Stub::makeEmpty(PartnerSource::class, [
            'getPartners' => ['ivan' => 3, 'petr' => 5]
        ], $this);

        $factory = new ThreadFactory($slotLockerFactory, $partnerSource, Stub::makeEmpty(Locker::class, [], $this), Stub::makeEmpty(LoggerInterface::class));

        $thread = $factory->create();
        $this->assertEquals('awardwallet', $thread->getPartner());
        $this->assertFalse($thread->isDedicated());
    }

    public function testUpdatePartnerToAwardwallet()
    {
        $oldThread = Stub::makeEmpty(Thread::class, [
            'getPartner' => Stub\Expected::atLeastOnce('ivan'),
            'stop' => Stub\Expected::once(),
        ], $this);

        $slotLocker = Stub::makeEmpty(SlotLocker::class, [
            'acquire' => function($prefix, $max) : ?Slot {
                if ($prefix === 'awardwallet') {
                    return new Slot('awardwallet', 3, Stub::makeEmpty(Locker::class, [], $this));
                }
                return null;
            }
        ], $this);

        $slotLockerFactory = Stub::makeEmpty(SlotLockerFactory::class, [
            'getSlotLocker' => $slotLocker
        ]);

        $partnerSource = Stub::makeEmpty(PartnerSource::class, [
            'getPartners' => ['ivan' => 3, 'petr' => 5]
        ], $this);

        $factory = new ThreadFactory($slotLockerFactory, $partnerSource, Stub::makeEmpty(Locker::class, [], $this), Stub::makeEmpty(LoggerInterface::class));

        $newThread = $factory->update($oldThread);
        $this->assertEquals('awardwallet', $newThread->getPartner());
    }

    public function testUpdateAwardwalletToPartner()
    {
        $oldThread = Stub::makeEmpty(Thread::class, [
            'getPartner' => Stub\Expected::atLeastOnce('awardwallet'),
            'stop' => Stub\Expected::once(),
        ], $this);

        $slotLocker = Stub::makeEmpty(SlotLocker::class, [
            'acquire' => function($prefix, $max) : ?Slot {
                if ($prefix === 'ivan') {
                    return new Slot('ivan', 3, Stub::makeEmpty(Locker::class, [], $this));
                }
                return null;
            }
        ], $this);

        $slotLockerFactory = Stub::makeEmpty(SlotLockerFactory::class, [
            'getSlotLocker' => $slotLocker
        ]);

        $partnerSource = Stub::makeEmpty(PartnerSource::class, [
            'getPartners' => ['ivan' => 3, 'petr' => 5]
        ], $this);

        $factory = new ThreadFactory($slotLockerFactory, $partnerSource, Stub::makeEmpty(Locker::class, [], $this), Stub::makeEmpty(LoggerInterface::class));

        $newThread = $factory->update($oldThread);
        $this->assertEquals('ivan', $newThread->getPartner());
    }

    public function testEmptyUpdateAwardwallet()
    {
        $oldThread = Stub::makeEmpty(Thread::class, [
            'getPartner' => Stub\Expected::atLeastOnce('awardwallet'),
            'keep' => true,
        ], $this);

        $slotLocker = Stub::makeEmpty(SlotLocker::class, [
            'acquire' => function($prefix, $max) : ?Slot {
                return null;
            }
        ], $this);

        $slotLockerFactory = Stub::makeEmpty(SlotLockerFactory::class, [
            'getSlotLocker' => $slotLocker
        ]);

        $partnerSource = Stub::makeEmpty(PartnerSource::class, [
            'getPartners' => ['ivan' => 3, 'petr' => 5]
        ], $this);

        $factory = new ThreadFactory($slotLockerFactory, $partnerSource, Stub::makeEmpty(Locker::class, [], $this), Stub::makeEmpty(LoggerInterface::class));

        $newThread = $factory->update($oldThread);
        $this->assertEquals($newThread->getPartner(), $oldThread->getPartner());
    }

    public function testEmptyUpdatePartner()
    {
        $oldThread = Stub::makeEmpty(Thread::class, [
            'getPartner' => Stub\Expected::atLeastOnce('ivan'),
            'keep' => true,
        ], $this);

        $slotLocker = Stub::makeEmpty(SlotLocker::class, [
            'acquire' => true
        ], $this);

        $slotLockerFactory = Stub::makeEmpty(SlotLockerFactory::class, [
            'getSlotLocker' => $slotLocker
        ]);

        $partnerSource = Stub::makeEmpty(PartnerSource::class, [
            'getPartners' => ['ivan' => 3, 'petr' => 5]
        ], $this);

        $factory = new ThreadFactory($slotLockerFactory, $partnerSource, Stub::makeEmpty(Locker::class, [], $this), Stub::makeEmpty(LoggerInterface::class));

        $newThread = $factory->update($oldThread);
        $this->assertEquals($newThread->getPartner(), $oldThread->getPartner());
    }

}