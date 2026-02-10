<?php

namespace CPNRV5_1;

class CustomerIndividual
{
    /**
     * @var CUPIDType
     */
    public $CustomerIdentifier = null;

    /**
     * @param CUPIDType $CustomerIdentifier
     */
    public function __construct($CustomerIdentifier)
    {
        $this->CustomerIdentifier = $CustomerIdentifier;
    }
}
