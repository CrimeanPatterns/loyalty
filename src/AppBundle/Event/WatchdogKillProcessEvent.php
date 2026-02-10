<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 27/12/2017
 * Time: 17:10
 */

namespace AppBundle\Event;


use Symfony\Component\EventDispatcher\Event;

class WatchdogKillProcessEvent extends Event
{

    const NAME = 'aw.watchdog.kill_process';
    /** @var int */
    private $startTime;
    /** @var array */
    private $context;

    public function __construct(int $startTime, array $context = [])
    {
        $this->startTime = $startTime;
        $this->context = $context;
    }

    /**
     * @return int
     */
    public function getStartTime(): int
    {
        return $this->startTime;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

}