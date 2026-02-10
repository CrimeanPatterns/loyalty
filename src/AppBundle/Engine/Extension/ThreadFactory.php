<?php

namespace AppBundle\Extension;

use Psr\Log\LoggerInterface;

class ThreadFactory
{

    public const TTL = 300;
    public const BACKGROUND_PARTNER = 'awardwallet';
    private const MAX_BACKGROUND_THREADS = 500;

    /**
     * @var SlotLockerFactory
     */
    private $slotLockerFactory;
    /**
     * @var PartnerSource
     */
    private $partnerSource;
    /**
     * @var Locker
     */
    private $locker;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(SlotLockerFactory $slotLockerFactory, PartnerSource $partnerSource, Locker $locker, LoggerInterface $logger)
    {
        $this->slotLockerFactory = $slotLockerFactory;
        $this->partnerSource = $partnerSource;
        $this->locker = $locker;
        $this->logger = $logger;
    }

    public function create(string $partner = null) : Thread
    {
        $result = null;

        if ($partner !== self::BACKGROUND_PARTNER) {
            $result = $this->createAnyPartnerThread($partner);
        }

        if ($result === null) {
            $result = $this->createAwardwalletThread();
        }

        return $result;
    }

    public function update(Thread $thread, string $partner = null) : Thread
    {
        if ($thread->getPartner() === self::BACKGROUND_PARTNER && $partner !== self::BACKGROUND_PARTNER) {
            $result = $this->createAnyPartnerThread();
            if ($result) {
                $this->logger->info("switching from background to partner {$result->getPartner()}");
                $thread->stop();
                return $result;
            }
        }

        // partner thread
        if (!$thread->keep()) {
            $this->logger->info("thread lost lock for {$thread->getPartner()}");
            $thread->stop();
            return $this->create();
        }

        return $thread;
    }

    public function removeByPid(string $partner, int $pid)
    {
        $partners = $this->partnerSource->getPartners();
        if (!isset($partners[$partner])){
            return;
        }
        $this->slotLockerFactory->getSlotLocker($pid)->releaseMySlots($partner, $partners[$partner]);
    }

    /**
     * returns array with active thread usage ["partner1" => 5, "partner2" => 1, ...
     * @return array
     */
    public function getStats() : array
    {
        $partners = $this->partnerSource->getPartners();
        $result = [];
        foreach ($partners as $partner => $maxThreads) {
            $result[$partner] = $this->slotLockerFactory->getSlotLocker(getmypid())->total($partner, $maxThreads);
        }
        $result[self::BACKGROUND_PARTNER] = $this->slotLockerFactory->getSlotLocker(getmypid())->total(self::BACKGROUND_PARTNER, self::MAX_BACKGROUND_THREADS);
        return $result;
    }

    private function createAnyPartnerThread(string $onlyPartner = null) : ?Thread
    {
        $partners = $this->partnerSource->getPartners();
        if ($onlyPartner !== null){
            $keys = [$onlyPartner];
        } else {
            $keys = array_keys($partners);
            shuffle($keys);
        }
        foreach ($keys as $partner) {
            $threads = $partners[$partner];
            $slot = $this->slotLockerFactory->getSlotLocker(getmypid())->acquire($partner, $threads);
            if ($slot) {
                return $this->createPartnerThread($partner, $slot);
            }
        }

        return null;
    }

    private function createPartnerThread(string $partner, Slot $slot) : Thread
    {
        $lockName = "dedicated_" . $partner;
        $dedicated = $this->locker->acquire($lockName, self::TTL);

        $this->logger->info("created thread for {$partner}", ["dedicated" => $dedicated]);

        return new Thread(
            $partner,
            $dedicated,
            // keepDelegate
            function() use ($slot, $partner, $dedicated, $lockName){
                return
                    $slot->keep(self::TTL)
                    && $slot->getSlotNumber() <= $this->maxThreads($partner)
                    && (!$dedicated || $this->locker->keep($lockName, self::TTL));
            },
            // stopDelegate
            function () use ($slot, $dedicated, $lockName, $partner) {
                $this->logger->info("stopped thread for {$partner}");
                if ($dedicated) {
                    $this->logger->info("unlocked dedicated thread for {$partner}");
                    $this->locker->release($lockName);
                }
                $slot->release();
            }
        );
    }

    private function createAwardwalletThread() : Thread
    {
        return $this->createPartnerThread(self::BACKGROUND_PARTNER, $this->slotLockerFactory->getSlotLocker(getmypid())->acquire(self::BACKGROUND_PARTNER, self::MAX_BACKGROUND_THREADS));
    }

    private function maxThreads(string $partner) : int
    {
        if ($partner === self::BACKGROUND_PARTNER) {
            return self::MAX_BACKGROUND_THREADS;
        }

        $partners = $this->partnerSource->getPartners();
        if (count($partners) === 1 && array_key_exists($partner, $partners)) {
            return self::MAX_BACKGROUND_THREADS;
        }

        return $this->partnerSource->getPartners()[$partner] ?? 0;
    }

}