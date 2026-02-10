<?php

namespace CPNRV3;

include_once 'RequestHeaderType.php';

class RetrieveCustomerPNRHistoryRequest extends RequestHeaderType
{
    /**
     * @var PNRHistoryRetrieveList[]
     */
    public $PNRHistoryRetrieveList = null;

    /**
     * @var RetrieveCustomerPNRHistoryRequestItem[]
     */
    public $RetrieveCustomerPNRHistoryRequestItem = null;

    /**
     * @param PNRHistoryRetrieveList[] $PNRHistoryRetrieveList
     * @param RetrieveCustomerPNRHistoryRequestItem[] $RetrieveCustomerPNRHistoryRequestItem
     */
    public function __construct($PNRHistoryRetrieveList, $RetrieveCustomerPNRHistoryRequestItem)
    {
        parent::__construct();
        $this->PNRHistoryRetrieveList = $PNRHistoryRetrieveList;
        $this->RetrieveCustomerPNRHistoryRequestItem = $RetrieveCustomerPNRHistoryRequestItem;
    }
}
