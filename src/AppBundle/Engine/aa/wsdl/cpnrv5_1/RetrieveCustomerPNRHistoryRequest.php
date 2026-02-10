<?php

namespace CPNRV5_1;

include_once 'RequestHeaderType.php';

class RetrieveCustomerPNRHistoryRequest extends RequestHeaderType
{
    /**
     * @var string[]
     */
    public $PNRHistoryRetrieveList = null;

    /**
     * @var RetrieveCustomerPNRHistoryRequestItem[]
     */
    public $RetrieveCustomerPNRHistoryRequestItem = null;

    /**
     * @var PNRHistorySegmentFilter
     */
    public $PNRHistorySegmentFilter = null;

    /**
     * @param bool $IncludeInactivePNR
     * @param string $transactionId
     * @param string[] $PNRHistoryRetrieveList
     * @param RetrieveCustomerPNRHistoryRequestItem[] $RetrieveCustomerPNRHistoryRequestItem
     * @param PNRHistorySegmentFilter $PNRHistorySegmentFilter
     */
    public function __construct($IncludeInactivePNR, $transactionId, $PNRHistoryRetrieveList, $RetrieveCustomerPNRHistoryRequestItem, $PNRHistorySegmentFilter)
    {
        parent::__construct($IncludeInactivePNR, $transactionId);
        $this->PNRHistoryRetrieveList = $PNRHistoryRetrieveList;
        $this->RetrieveCustomerPNRHistoryRequestItem = $RetrieveCustomerPNRHistoryRequestItem;
        $this->PNRHistorySegmentFilter = $PNRHistorySegmentFilter;
    }
}
