<?php

namespace CPNRV3;

class RetrieveCustomerPNRHistoryResult
{
    /**
     * @var RetrieveCustomerPNRHistoryRequestItem
     */
    public $RetrieveCustomerPNRHistoryRequestItem = null;

    /**
     * @var RetrieveCustomerPNRHistoryResponseStatus
     */
    public $RetrieveCustomerPNRHistoryResponseStatus = null;

    /**
     * @var PNRTransactionBookingActivity[]
     */
    public $PNRTransactionBookingActivity = null;

    /**
     * @param RetrieveCustomerPNRHistoryRequestItem $RetrieveCustomerPNRHistoryRequestItem
     * @param RetrieveCustomerPNRHistoryResponseStatus $RetrieveCustomerPNRHistoryResponseStatus
     * @param PNRTransactionBookingActivity[] $PNRTransactionBookingActivity
     */
    public function __construct($RetrieveCustomerPNRHistoryRequestItem, $RetrieveCustomerPNRHistoryResponseStatus, $PNRTransactionBookingActivity)
    {
        $this->RetrieveCustomerPNRHistoryRequestItem = $RetrieveCustomerPNRHistoryRequestItem;
        $this->RetrieveCustomerPNRHistoryResponseStatus = $RetrieveCustomerPNRHistoryResponseStatus;
        $this->PNRTransactionBookingActivity = $PNRTransactionBookingActivity;
    }
}
