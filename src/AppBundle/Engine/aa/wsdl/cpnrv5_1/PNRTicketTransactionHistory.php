<?php

namespace CPNRV5_1;

class PNRTicketTransactionHistory
{
    /**
     * @var string
     */
    public $TicketTransactionBookingActivityCode = null;

    /**
     * @var PNRTicket[]
     */
    public $PNRTicket = null;

    /**
     * @param string $TicketTransactionBookingActivityCode
     * @param PNRTicket[] $PNRTicket
     */
    public function __construct($TicketTransactionBookingActivityCode, $PNRTicket)
    {
        $this->TicketTransactionBookingActivityCode = $TicketTransactionBookingActivityCode;
        $this->PNRTicket = $PNRTicket;
    }
}
