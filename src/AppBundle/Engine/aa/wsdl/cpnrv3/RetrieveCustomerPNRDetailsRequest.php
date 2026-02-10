<?php

namespace CPNRV3;

include_once 'RequestHeaderType.php';

class RetrieveCustomerPNRDetailsRequest extends RequestHeaderType
{
    /**
     * @var RetrieveCustomerPNRDetailsRequestItem[]
     */
    public $RetrieveCustomerPNRDetailsRequestItem = null;

    /**
     * @param RetrieveCustomerPNRDetailsRequestItem[] $RetrieveCustomerPNRDetailsRequestItem
     */
    public function __construct($RetrieveCustomerPNRDetailsRequestItem)
    {
        parent::__construct();
        $this->RetrieveCustomerPNRDetailsRequestItem = $RetrieveCustomerPNRDetailsRequestItem;
    }
}
