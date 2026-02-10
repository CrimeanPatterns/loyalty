<?php

namespace CPNRV3;

class PNRPassenger
{
    /**
     * @var int
     */
    public $PassengerSequenceIdentifier = null;

    /**
     * @var PassengerTypeCodeEnum
     */
    public $PassengerTypeCode = null;

    /**
     * @var string
     */
    public $PNRFirstName = null;

    /**
     * @var string
     */
    public $PNRLastName = null;

    /**
     * @var PNRLoyaltyMember[]
     */
    public $PNRLoyaltyMember = null;

    /**
     * @var PNRPassengerSeat[]
     */
    public $PNRPassengerSeat = null;

    /**
     * @var CustomerPNRPassenger
     */
    public $CustomerPNRPassenger = null;

    /**
     * @var PNRSpecialServiceRequest[]
     */
    public $PNRSpecialServiceRequest = null;

    /**
     * @param int $PassengerSequenceIdentifier
     * @param PassengerTypeCodeEnum $PassengerTypeCode
     * @param string $PNRFirstName
     * @param string $PNRLastName
     * @param PNRLoyaltyMember[] $PNRLoyaltyMember
     * @param PNRPassengerSeat[] $PNRPassengerSeat
     * @param CustomerPNRPassenger $CustomerPNRPassenger
     * @param PNRSpecialServiceRequest[] $PNRSpecialServiceRequest
     */
    public function __construct($PassengerSequenceIdentifier, $PassengerTypeCode, $PNRFirstName, $PNRLastName, $PNRLoyaltyMember, $PNRPassengerSeat, $CustomerPNRPassenger, $PNRSpecialServiceRequest)
    {
        $this->PassengerSequenceIdentifier = $PassengerSequenceIdentifier;
        $this->PassengerTypeCode = $PassengerTypeCode;
        $this->PNRFirstName = $PNRFirstName;
        $this->PNRLastName = $PNRLastName;
        $this->PNRLoyaltyMember = $PNRLoyaltyMember;
        $this->PNRPassengerSeat = $PNRPassengerSeat;
        $this->CustomerPNRPassenger = $CustomerPNRPassenger;
        $this->PNRSpecialServiceRequest = $PNRSpecialServiceRequest;
    }
}
