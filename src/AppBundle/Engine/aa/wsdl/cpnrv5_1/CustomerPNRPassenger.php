<?php

namespace CPNRV5_1;

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
