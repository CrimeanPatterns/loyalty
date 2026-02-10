<?php

namespace CPNRV3;

include_once 'ListResponseHeaderType.php';

class RetrieveCustomerPNRHistoryResponse extends ListResponseHeaderType
{
    /**
     * @var RetrieveCustomerPNRHistoryResult[]
     */
    public $RetrieveCustomerPNRHistoryResult = null;

    /**
     * @param RetrieveCustomerPNRHistoryResult[] $RetrieveCustomerPNRHistoryResult
     */
    public function __construct($RetrieveCustomerPNRHistoryResult)
    {
        parent::__construct();
        $this->RetrieveCustomerPNRHistoryResult = $RetrieveCustomerPNRHistoryResult;
    }
}
