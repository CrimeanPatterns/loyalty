<?php

namespace CPNRV5_1;

include_once 'RequestHeaderType.php';

class RetrieveCustomerPNRDetailsByNameAndFlightRequest extends RequestHeaderType
{
    /**
     * @var RetrieveCustomerPNRDetailsByNameAndFlightRequestItem[]
     */
    public $RequestItem = null;

    /**
     * @param bool $IncludeInactivePNR
     * @param string $transactionId
     * @param RetrieveCustomerPNRDetailsByNameAndFlightRequestItem[] $RequestItem
     */
    public function __construct($IncludeInactivePNR, $transactionId, $RequestItem)
    {
        parent::__construct($IncludeInactivePNR, $transactionId);
        $this->RequestItem = $RequestItem;
    }
}
