<?php

namespace AppBundle\Extension;

class ThreadStatsInfo
{

    /**
     * @var array
     */
    private $byPartner;
    /**
     * @var int
     */
    private $total;
    /**
     * @var int
     */
    private $free;

    public function __construct(array $byPartner, int $total, int $free)
    {
        $this->byPartner = $byPartner;
        $this->total = $total;
        $this->free = $free;
    }

    /**
     * @return array
     */
    public function getByPartner(): array
    {
        return $this->byPartner;
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return int
     */
    public function getFree(): int
    {
        return $this->free;
    }

}