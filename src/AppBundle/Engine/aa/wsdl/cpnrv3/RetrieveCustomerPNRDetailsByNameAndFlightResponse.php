<?php

namespace CPNRV3;

include_once 'ListResponseHeaderType.php';

class RetrieveCustomerPNRDetailsByNameAndFlightResponse extends ListResponseHeaderType
{
    /**
     * @var RetrieveCustomerPNRDetailsByNameAndFlightResult[]
     */
    public $Result = null;

    /**
     * @param RetrieveCustomerPNRDetailsByNameAndFlightResult[] $Result
     */
    public function __construct($Result)
    {
        parent::__construct();
        $this->Result = $Result;
    }
}
