<?php

namespace CPNRV3;

class PNRSpecialServiceRqstTransactionHistory
{
    /**
     * @var string
     */
    public $TransactionBookingActivityCode = null;

    /**
     * @var PNRSpecialServiceRequest[]
     */
    public $PNRSpecialServiceRequest = null;

    /**
     * @param string $TransactionBookingActivityCode
     * @param PNRSpecialServiceRequest[] $PNRSpecialServiceRequest
     */
    public function __construct($TransactionBookingActivityCode, $PNRSpecialServiceRequest)
    {
        $this->TransactionBookingActivityCode = $TransactionBookingActivityCode;
        $this->PNRSpecialServiceRequest = $PNRSpecialServiceRequest;
    }
}
