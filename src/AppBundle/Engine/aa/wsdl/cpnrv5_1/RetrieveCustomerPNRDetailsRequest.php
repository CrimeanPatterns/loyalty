<?php

namespace CPNRV5_1;

include_once 'RequestHeaderType.php';

class RetrieveCustomerPNRDetailsRequest extends RequestHeaderType
{
    /**
     * @var RetrieveCustomerPNRDetailsRequestItem[]
     */
    public $RetrieveCustomerPNRDetailsRequestItem = null;

    /**
     * @param bool $IncludeInactivePNR
     * @param string $transactionId
     * @param RetrieveCustomerPNRDetailsRequestItem[] $RetrieveCustomerPNRDetailsRequestItem
     */
    public function __construct($IncludeInactivePNR, $transactionId, $RetrieveCustomerPNRDetailsRequestItem)
    {
        parent::__construct($IncludeInactivePNR, $transactionId);
        $this->RetrieveCustomerPNRDetailsRequestItem = $RetrieveCustomerPNRDetailsRequestItem;
    }
}
