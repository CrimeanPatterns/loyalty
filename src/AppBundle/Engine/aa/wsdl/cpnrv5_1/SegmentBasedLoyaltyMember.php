<?php

namespace CPNRV5_1;

include_once 'PNRLoyaltyMember.php';

class SegmentBasedLoyaltyMember extends PNRLoyaltyMember
{
    /**
     * @var Station
     */
    public $LoyaltySegmentServiceBeginCode = null;

    /**
     * @var Station
     */
    public $LoyaltySegmentServiceEndCode = null;

    /**
     * @var date
     */
    public $LoyaltySegmentBeginDate = null;

    /**
     * @var Flight
     */
    public $LoyaltyPNRAirlineBookedCode = null;

    /**
     * @var SegmentStatus
     */
    public $SegmentStatus = null;

    /**
     * @param int $FrequentTravelerSequenceIdentifier
     * @param string $LoyaltyAccountNumber
     * @param string $LoyaltyMemberMarkettingAffiliationNumericText
     * @param Airline $FrequentTravelerProgramOwningAirlineCode
     * @param SegmentStatus $LoyaltyMemberSegmentStatusCurrentCode
     * @param int $PassengerSequenceNumber
     * @param Station $LoyaltySegmentServiceBeginCode
     * @param Station $LoyaltySegmentServiceEndCode
     * @param date $LoyaltySegmentBeginDate
     * @param Flight $LoyaltyPNRAirlineBookedCode
     * @param SegmentStatus $SegmentStatus
     */
    public function __construct($FrequentTravelerSequenceIdentifier, $LoyaltyAccountNumber, $LoyaltyMemberMarkettingAffiliationNumericText, $FrequentTravelerProgramOwningAirlineCode, $LoyaltyMemberSegmentStatusCurrentCode, $PassengerSequenceNumber, $LoyaltySegmentServiceBeginCode, $LoyaltySegmentServiceEndCode, $LoyaltySegmentBeginDate, $LoyaltyPNRAirlineBookedCode, $SegmentStatus)
    {
        parent::__construct($FrequentTravelerSequenceIdentifier, $LoyaltyAccountNumber, $LoyaltyMemberMarkettingAffiliationNumericText, $FrequentTravelerProgramOwningAirlineCode, $LoyaltyMemberSegmentStatusCurrentCode, $PassengerSequenceNumber);
        $this->LoyaltySegmentServiceBeginCode = $LoyaltySegmentServiceBeginCode;
        $this->LoyaltySegmentServiceEndCode = $LoyaltySegmentServiceEndCode;
        $this->LoyaltySegmentBeginDate = $LoyaltySegmentBeginDate;
        $this->LoyaltyPNRAirlineBookedCode = $LoyaltyPNRAirlineBookedCode;
        $this->SegmentStatus = $SegmentStatus;
    }
}
