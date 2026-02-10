<?php

namespace CPNRV3;

class RetrieveCustomerPNRDetailsRequestItem
{
    /**
     * @var PNRID
     */
    public $PNRID = null;

    /**
     * @param PNRID $PNRID
     */
    public function __construct($PNRID)
    {
        $this->PNRID = $PNRID;
    }
}
