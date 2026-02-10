<?php

namespace CPNRV3;

include_once 'ListResponseHeaderType.php';

class RetrieveCustomerPNRDetailsResponse extends ListResponseHeaderType
{
    /**
     * @var RetrieveCustomerPNRDetailsResult[]
     */
    public $RetrieveCustomerPNRDetailsResult = null;

    /**
     * @param RetrieveCustomerPNRDetailsResult[] $RetrieveCustomerPNRDetailsResult
     */
    public function __construct($RetrieveCustomerPNRDetailsResult)
    {
        parent::__construct();
        $this->RetrieveCustomerPNRDetailsResult = $RetrieveCustomerPNRDetailsResult;
    }
}
