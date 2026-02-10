<?php

namespace CPNRV3;

class PNRPassengerSeatTransactionHistory
{
    /**
     * @var string
     */
    public $TransactionBookingActivityCode = null;

    /**
     * @var PNRPassengerSeat[]
     */
    public $PNRPassengerSeat = null;

    /**
     * @param string $TransactionBookingActivityCode
     * @param PNRPassengerSeat[] $PNRPassengerSeat
     */
    public function __construct($TransactionBookingActivityCode, $PNRPassengerSeat)
    {
        $this->TransactionBookingActivityCode = $TransactionBookingActivityCode;
        $this->PNRPassengerSeat = $PNRPassengerSeat;
    }
}
