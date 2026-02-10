<?php

namespace CPNRV3;

class PNRTransactionBookingActivity
{
    /**
     * @var int
     */
    public $TransactionSequenceIdentifier = null;

    /**
     * @var string
     */
    public $TransactionText = null;

    /**
     * @var string
     */
    public $DutyAuthorizationLevelUpdateCode = null;

    /**
     * @var dateTime
     */
    public $TransactionEndTimestamp = null;

    /**
     * @var string
     */
    public $AgentOverridePseudoCityCode = null;

    /**
     * @var string
     */
    public $AgentHomePseudoCityCode = null;

    /**
     * @var PNRTravelSegmentTransactionHistory[]
     */
    public $PNRTravelSegmentTransactionHistory = null;

    /**
     * @var PNRPassengerTransactionHistory[]
     */
    public $PNRPassengerTransactionHistory = null;

    /**
     * @var PNRPassengerSeatTransactionHistory[]
     */
    public $PNRPassengerSeatTransactionHistory = null;

    /**
     * @var PNRLoyaltyMemberTransactionHistory[]
     */
    public $PNRLoyaltyMemberTransactionHistory = null;

    /**
     * @var PNRSpecialServiceRqstTransactionHistory[]
     */
    public $PNRSpecialServiceRqstTransactionHistory = null;

    /**
     * @var PNRTicketTransactionHistory[]
     */
    public $PNRTicketTransactionHistory = null;

    /**
     * @param int $TransactionSequenceIdentifier
     * @param string $TransactionText
     * @param string $DutyAuthorizationLevelUpdateCode
     * @param dateTime $TransactionEndTimestamp
     * @param string $AgentOverridePseudoCityCode
     * @param string $AgentHomePseudoCityCode
     * @param PNRTravelSegmentTransactionHistory[] $PNRTravelSegmentTransactionHistory
     * @param PNRPassengerTransactionHistory[] $PNRPassengerTransactionHistory
     * @param PNRPassengerSeatTransactionHistory[] $PNRPassengerSeatTransactionHistory
     * @param PNRLoyaltyMemberTransactionHistory[] $PNRLoyaltyMemberTransactionHistory
     * @param PNRSpecialServiceRqstTransactionHistory[] $PNRSpecialServiceRqstTransactionHistory
     * @param PNRTicketTransactionHistory[] $PNRTicketTransactionHistory
     */
    public function __construct($TransactionSequenceIdentifier, $TransactionText, $DutyAuthorizationLevelUpdateCode, $TransactionEndTimestamp, $AgentOverridePseudoCityCode, $AgentHomePseudoCityCode, $PNRTravelSegmentTransactionHistory, $PNRPassengerTransactionHistory, $PNRPassengerSeatTransactionHistory, $PNRLoyaltyMemberTransactionHistory, $PNRSpecialServiceRqstTransactionHistory, $PNRTicketTransactionHistory)
    {
        $this->TransactionSequenceIdentifier = $TransactionSequenceIdentifier;
        $this->TransactionText = $TransactionText;
        $this->DutyAuthorizationLevelUpdateCode = $DutyAuthorizationLevelUpdateCode;
        $this->TransactionEndTimestamp = $TransactionEndTimestamp;
        $this->AgentOverridePseudoCityCode = $AgentOverridePseudoCityCode;
        $this->AgentHomePseudoCityCode = $AgentHomePseudoCityCode;
        $this->PNRTravelSegmentTransactionHistory = $PNRTravelSegmentTransactionHistory;
        $this->PNRPassengerTransactionHistory = $PNRPassengerTransactionHistory;
        $this->PNRPassengerSeatTransactionHistory = $PNRPassengerSeatTransactionHistory;
        $this->PNRLoyaltyMemberTransactionHistory = $PNRLoyaltyMemberTransactionHistory;
        $this->PNRSpecialServiceRqstTransactionHistory = $PNRSpecialServiceRqstTransactionHistory;
        $this->PNRTicketTransactionHistory = $PNRTicketTransactionHistory;
    }
}
