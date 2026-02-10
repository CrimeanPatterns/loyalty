<?php

namespace CPNRV5_1;

class PNRPassenger
{
    /**
     * @var int
     */
    public $PassengerSequenceIdentifier = null;

    /**
     * @var string
     */
    public $PassengerTypeCode = null;

    /**
     * @var float
     */
    public $PassengerId = null;

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
     * @param string $PassengerTypeCode
     * @param float $PassengerId
     * @param string $PNRFirstName
     * @param string $PNRLastName
     * @param PNRLoyaltyMember[] $PNRLoyaltyMember
     * @param PNRPassengerSeat[] $PNRPassengerSeat
     * @param CustomerPNRPassenger $CustomerPNRPassenger
     * @param PNRSpecialServiceRequest[] $PNRSpecialServiceRequest
     */
    public function __construct($PassengerSequenceIdentifier, $PassengerTypeCode, $PassengerId, $PNRFirstName, $PNRLastName, $PNRLoyaltyMember, $PNRPassengerSeat, $CustomerPNRPassenger, $PNRSpecialServiceRequest)
    {
        $this->PassengerSequenceIdentifier = $PassengerSequenceIdentifier;
        $this->PassengerTypeCode = $PassengerTypeCode;
        $this->PassengerId = $PassengerId;
        $this->PNRFirstName = $PNRFirstName;
        $this->PNRLastName = $PNRLastName;
        $this->PNRLoyaltyMember = $PNRLoyaltyMember;
        $this->PNRPassengerSeat = $PNRPassengerSeat;
        $this->CustomerPNRPassenger = $CustomerPNRPassenger;
        $this->PNRSpecialServiceRequest = $PNRSpecialServiceRequest;
    }
}
