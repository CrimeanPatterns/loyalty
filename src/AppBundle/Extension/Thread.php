<?php

namespace AppBundle\Extension;

class Thread
{

    /**
     * @var string
     */
    private $partner;
    /**
     * @var bool
     */
    private $dedicated;
    /**
     * @var callable
     */
    private $keepDelegate;
    /**
     * @var callable
     */
    private $stopDelegate;

    public function __construct(string $partner, bool $dedicated, Callable $keepDelegate, Callable $stopDelegate)
    {
        $this->partner = $partner;
        $this->dedicated = $dedicated;
        $this->keepDelegate = $keepDelegate;
        $this->stopDelegate = $stopDelegate;
    }

    public function getPartner() : string
    {
        return $this->partner;
    }

    public function isDedicated() : bool
    {
        return $this->dedicated;
    }

    public function keep() : bool
    {
        return call_user_func($this->keepDelegate);
    }

    public function stop()
    {
        return call_user_func($this->stopDelegate);
    }

}