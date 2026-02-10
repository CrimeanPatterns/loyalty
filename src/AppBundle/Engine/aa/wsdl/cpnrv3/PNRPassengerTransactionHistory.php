<?php

namespace CPNRV3;

class PNRPassengerTransactionHistory
{
    /**
     * @var string
     */
    public $TransactionBookingActivity = null;

    /**
     * @var PNRPassenger[]
     */
    public $PNRPassenger = null;

    /**
     * @var GroupPNR[]
     */
    public $GroupPNR = null;

    /**
     * @param string $TransactionBookingActivity
     * @param PNRPassenger[] $PNRPassenger
     * @param GroupPNR[] $GroupPNR
     */
    public function __construct($TransactionBookingActivity, $PNRPassenger, $GroupPNR)
    {
        $this->TransactionBookingActivity = $TransactionBookingActivity;
        $this->PNRPassenger = $PNRPassenger;
        $this->GroupPNR = $GroupPNR;
    }
}
