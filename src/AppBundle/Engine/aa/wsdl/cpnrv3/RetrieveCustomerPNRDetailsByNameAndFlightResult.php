<?php

namespace CPNRV3;

class RetrieveCustomerPNRDetailsByNameAndFlightResult
{
    /**
     * @var RetrieveCustomerPNRDetailsByNameAndFlightResponseStatus
     */
    public $ResponseStatus = null;

    /**
     * @var RetrieveCustomerPNRDetailsByNameAndFlightRequestItem
     */
    public $RequestItem = null;

    /**
     * @var PNR[]
     */
    public $PNR = null;

    /**
     * @param RetrieveCustomerPNRDetailsByNameAndFlightResponseStatus $ResponseStatus
     * @param RetrieveCustomerPNRDetailsByNameAndFlightRequestItem $RequestItem
     * @param PNR[] $PNR
     */
    public function __construct($ResponseStatus, $RequestItem, $PNR)
    {
        $this->ResponseStatus = $ResponseStatus;
        $this->RequestItem = $RequestItem;
        $this->PNR = $PNR;
    }
}
