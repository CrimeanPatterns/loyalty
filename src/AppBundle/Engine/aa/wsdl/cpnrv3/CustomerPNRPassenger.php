<?php

namespace CPNRV3;

class CustomerPNRPassenger
{
    /**
     * @var CustomerIndividual
     */
    public $CustomerIndividual = null;

    /**
     * @param CustomerIndividual $CustomerIndividual
     */
    public function __construct($CustomerIndividual)
    {
        $this->CustomerIndividual = $CustomerIndividual;
    }
}
