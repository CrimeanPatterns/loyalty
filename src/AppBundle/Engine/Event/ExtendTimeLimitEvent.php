<?php

namespace AppBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class ExtendTimeLimitEvent extends Event
{

    const NAME = 'aw.extend_time_limit';
    /**
     * @var int
     */
    private $time;

    public function __construct(int $time)
    {

        $this->time = $time;
    }

    /**
     * @return int
     */
    public function getTime(): int
    {
        return $this->time;
    }


}