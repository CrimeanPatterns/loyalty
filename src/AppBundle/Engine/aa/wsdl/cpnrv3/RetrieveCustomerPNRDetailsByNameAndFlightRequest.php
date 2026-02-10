<?php

namespace CPNRV3;

include_once 'RequestHeaderType.php';

class RetrieveCustomerPNRDetailsByNameAndFlightRequest extends RequestHeaderType
{
    /**
     * @var RetrieveCustomerPNRDetailsByNameAndFlightRequestItem[]
     */
    public $RequestItem = null;

    /**
     * @param RetrieveCustomerPNRDetailsByNameAndFlightRequestItem[] $RequestItem
     */
    public function __construct($RequestItem)
    {
        parent::__construct();
        $this->RequestItem = $RequestItem;
    }
}
