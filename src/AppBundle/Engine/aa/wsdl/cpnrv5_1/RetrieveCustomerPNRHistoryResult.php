<?php

namespace CPNRV5_1;

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
     * @var PNRPassenger[]
     */
    public $PNRPassenger = null;

    /**
     * @param RetrieveCustomerPNRHistoryRequestItem $RetrieveCustomerPNRHistoryRequestItem
     * @param RetrieveCustomerPNRHistoryResponseStatus $RetrieveCustomerPNRHistoryResponseStatus
     * @param PNRTransactionBookingActivity[] $PNRTransactionBookingActivity
     * @param PNRPassenger[] $PNRPassenger
     */
    public function __construct($RetrieveCustomerPNRHistoryRequestItem, $RetrieveCustomerPNRHistoryResponseStatus, $PNRTransactionBookingActivity, $PNRPassenger)
    {
        $this->RetrieveCustomerPNRHistoryRequestItem = $RetrieveCustomerPNRHistoryRequestItem;
        $this->RetrieveCustomerPNRHistoryResponseStatus = $RetrieveCustomerPNRHistoryResponseStatus;
        $this->PNRTransactionBookingActivity = $PNRTransactionBookingActivity;
        $this->PNRPassenger = $PNRPassenger;
    }
}
