<?php

namespace CPNRV5_1;

class PNRLoyaltyMemberTransactionHistory
{
    /**
     * @var string
     */
    public $TransactionBookingActivityCode = null;

    /**
     * @var PNRLoyaltyMember[]
     */
    public $PNRLoyaltyMember = null;

    /**
     * @param string $TransactionBookingActivityCode
     * @param PNRLoyaltyMember[] $PNRLoyaltyMember
     */
    public function __construct($TransactionBookingActivityCode, $PNRLoyaltyMember)
    {
        $this->TransactionBookingActivityCode = $TransactionBookingActivityCode;
        $this->PNRLoyaltyMember = $PNRLoyaltyMember;
    }
}
