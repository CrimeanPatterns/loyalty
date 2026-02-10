<?php

namespace CPNRV5_1;

include_once 'PNR.php';

class GroupPNR extends PNR
{
    /**
     * @var string
     */
    public $PassengerGroupName = null;

    /**
     * @var int
     */
    public $PassengerQuantity = null;

    /**
     * @var PNRPassenger[]
     */
    public $PNRPassenger = null;

    /**
     * @var PNRPassengerSeat[]
     */
    public $PNRPassengerSeat = null;

    /**
     * @param string $PNRIdentifier
     * @param time $PNRCreateTime
     * @param date $PNRCreateDate
     * @param int $PNRTypeCode
     * @param int $PNRSequenceID
     * @param string $PNRParentIdentifier
     * @param PNRTicket[] $PNRTicket
     * @param PNRTravelSegment[] $PNRTravelSegment
     * @param PNRSpecialServiceRequest[] $PNRSpecialServiceRequest
     * @param bool $Active
     * @param PNRPhones $PNRPhones
     * @param string $PassengerGroupName
     * @param int $PassengerQuantity
     * @param PNRPassenger[] $PNRPassenger
     * @param PNRPassengerSeat[] $PNRPassengerSeat
     */
    public function __construct($PNRIdentifier, $PNRCreateTime, $PNRCreateDate, $PNRTypeCode, $PNRSequenceID, $PNRParentIdentifier, $PNRTicket, $PNRTravelSegment, $PNRSpecialServiceRequest, $Active, $PNRPhones, $PassengerGroupName, $PassengerQuantity, $PNRPassenger, $PNRPassengerSeat)
    {
        parent::__construct($PNRIdentifier, $PNRCreateTime, $PNRCreateDate, $PNRTypeCode, $PNRSequenceID, $PNRParentIdentifier, $PNRTicket, $PNRTravelSegment, $PNRSpecialServiceRequest, $Active, $PNRPhones);
        $this->PassengerGroupName = $PassengerGroupName;
        $this->PassengerQuantity = $PassengerQuantity;
        $this->PNRPassenger = $PNRPassenger;
        $this->PNRPassengerSeat = $PNRPassengerSeat;
    }
}
