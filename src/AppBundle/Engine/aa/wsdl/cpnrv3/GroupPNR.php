<?php

namespace CPNRV3;

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
     * @param PNRTypeCodeEnum $PNRTypeCode
     * @param PNRTicket[] $PNRTicket
     * @param PNRTravelSegment[] $PNRTravelSegment
     * @param PNRSpecialServiceRequest[] $PNRSpecialServiceRequest
     * @param string $PassengerGroupName
     * @param int $PassengerQuantity
     * @param PNRPassenger[] $PNRPassenger
     * @param PNRPassengerSeat[] $PNRPassengerSeat
     */
    public function __construct($PNRIdentifier, $PNRCreateTime, $PNRCreateDate, $PNRTypeCode, $PNRTicket, $PNRTravelSegment, $PNRSpecialServiceRequest, $PassengerGroupName, $PassengerQuantity, $PNRPassenger, $PNRPassengerSeat)
    {
        parent::__construct($PNRIdentifier, $PNRCreateTime, $PNRCreateDate, $PNRTypeCode, $PNRTicket, $PNRTravelSegment, $PNRSpecialServiceRequest);
        $this->PassengerGroupName = $PassengerGroupName;
        $this->PassengerQuantity = $PassengerQuantity;
        $this->PNRPassenger = $PNRPassenger;
        $this->PNRPassengerSeat = $PNRPassengerSeat;
    }
}
