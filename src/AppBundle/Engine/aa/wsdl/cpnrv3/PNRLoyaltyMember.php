<?php

namespace CPNRV3;

class PNRLoyaltyMember
{
    /**
     * @var int
     */
    public $FrequentTravelerSequenceIdentifier = null;

    /**
     * @var string
     */
    public $LoyaltyAccountNumber = null;

    /**
     * @var string
     */
    public $LoyaltyMemberMarkettingAffiliationNumericText = null;

    /**
     * @var Airline
     */
    public $FrequentTravelerProgramOwningAirlineCode = null;

    /**
     * @var SegmentStatus
     */
    public $LoyaltyMemberSegmentStatusCurrentCode = null;

    /**
     * @var int
     */
    public $PassengerSequenceNumber = null;

    /**
     * @param int $FrequentTravelerSequenceIdentifier
     * @param string $LoyaltyAccountNumber
     * @param string $LoyaltyMemberMarkettingAffiliationNumericText
     * @param Airline $FrequentTravelerProgramOwningAirlineCode
     * @param SegmentStatus $LoyaltyMemberSegmentStatusCurrentCode
     * @param int $PassengerSequenceNumber
     */
    public function __construct($FrequentTravelerSequenceIdentifier, $LoyaltyAccountNumber, $LoyaltyMemberMarkettingAffiliationNumericText, $FrequentTravelerProgramOwningAirlineCode, $LoyaltyMemberSegmentStatusCurrentCode, $PassengerSequenceNumber)
    {
        $this->FrequentTravelerSequenceIdentifier = $FrequentTravelerSequenceIdentifier;
        $this->LoyaltyAccountNumber = $LoyaltyAccountNumber;
        $this->LoyaltyMemberMarkettingAffiliationNumericText = $LoyaltyMemberMarkettingAffiliationNumericText;
        $this->FrequentTravelerProgramOwningAirlineCode = $FrequentTravelerProgramOwningAirlineCode;
        $this->LoyaltyMemberSegmentStatusCurrentCode = $LoyaltyMemberSegmentStatusCurrentCode;
        $this->PassengerSequenceNumber = $PassengerSequenceNumber;
    }
}
