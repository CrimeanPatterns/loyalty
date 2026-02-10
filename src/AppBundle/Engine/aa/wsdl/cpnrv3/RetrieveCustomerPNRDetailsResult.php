<?php

namespace CPNRV3;

class RetrieveCustomerPNRDetailsResult
{
    /**
     * @var RetrieveCustomerPNRDetailsResponseStatus
     */
    public $ResponseStatus = null;

    /**
     * @var RetrieveCustomerPNRDetailsRequestItem
     */
    public $RequestItem = null;

    /**
     * @var NonGroupPNR
     */
    public $PNR = null;

    /**
     * @param RetrieveCustomerPNRDetailsResponseStatus $ResponseStatus
     * @param RetrieveCustomerPNRDetailsRequestItem $RequestItem
     * @param PNR $PNR
     */
    public function __construct($ResponseStatus, $RequestItem, $PNR)
    {
        $this->ResponseStatus = $ResponseStatus;
        $this->RequestItem = $RequestItem;
        $this->PNR = $PNR;
    }
}
