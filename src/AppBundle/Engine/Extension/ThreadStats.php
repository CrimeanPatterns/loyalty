<?php

namespace AppBundle\Extension;

use AppBundle\Document\Thread;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;

class ThreadStats
{

    /**
     * @var DocumentManager
     */
    private $manager;
    private LoggerInterface $logger;

    public function __construct(
        DocumentManager $manager,
        LoggerInterface $logger
    )
    {
        $this->manager = $manager;
        $this->logger = $logger;
    }

    public function register(string $host, int $pid) : Thread
    {
        $thread = $this->manager->getRepository('AppBundle:Thread')->findOneBy(['hostName' => $host, 'pid' => $pid]);

        if ($thread === null) {
            $thread = new Thread($host, $pid, true);
            $this->manager->persist($thread);
        } else {
            $thread->stillActive();
        }

        $this->manager->flush($thread);

        return $thread;
    }

    public function update(Thread $thread) : void
    {
        $thread->stillActive();
        $this->manager->flush($thread);
    }

    public function remove(Thread $thread)
    {
        $this->manager->remove($thread);
        $this->manager->flush($thread);
    }

    public function getStats() : ThreadStatsInfo
    {
        $threads = $this->manager->getRepository('AppBundle:Thread')->findAll();

        $total = 0;
        $free = 0;
        $partners = [];

        foreach ($threads as $thread){
            if ($thread->isExpired()){
                $this->manager->remove($thread);
                $this->manager->flush($thread);
            }
            else {
                $total++;

                if ($thread->getPartner() !== null) {
                    if (!isset($partners[$thread->getPartner()])) {
                        $partners[$thread->getPartner()] = 1;
                    } else {
                        $partners[$thread->getPartner()]++;
                    }
                }
                else {
                    $this->logger->info("no partner for thread {$thread->getHostName()} {$thread->getPid()}");
                }

                if ($thread->isFree()) {
                    $free++;
                }
            }
        }

        return new ThreadStatsInfo($partners, $total, $free);
    }

}