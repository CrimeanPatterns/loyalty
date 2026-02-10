<?php

namespace CPNRV5_1;

include_once 'PNRLoyaltyMember.php';

class PNRBasedLoyaltyMember extends PNRLoyaltyMember
{
    /**
     * @var Airline
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
     * @param Airline $LoyaltyPNRAirlineBookedCode
     * @param SegmentStatus $SegmentStatus
     */
    public function __construct($FrequentTravelerSequenceIdentifier, $LoyaltyAccountNumber, $LoyaltyMemberMarkettingAffiliationNumericText, $FrequentTravelerProgramOwningAirlineCode, $LoyaltyMemberSegmentStatusCurrentCode, $PassengerSequenceNumber, $LoyaltyPNRAirlineBookedCode, $SegmentStatus)
    {
        parent::__construct($FrequentTravelerSequenceIdentifier, $LoyaltyAccountNumber, $LoyaltyMemberMarkettingAffiliationNumericText, $FrequentTravelerProgramOwningAirlineCode, $LoyaltyMemberSegmentStatusCurrentCode, $PassengerSequenceNumber);
        $this->LoyaltyPNRAirlineBookedCode = $LoyaltyPNRAirlineBookedCode;
        $this->SegmentStatus = $SegmentStatus;
    }
}
