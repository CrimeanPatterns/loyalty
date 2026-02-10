<?php

namespace CPNRV5_1;

include_once 'PNR.php';

class NonGroupPNR extends PNR
{
    /**
     * @var PNRPassenger[]
     */
    public $PNRPassenger = null;

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
     * @param PNRPassenger[] $PNRPassenger
     */
    public function __construct($PNRIdentifier, $PNRCreateTime, $PNRCreateDate, $PNRTypeCode, $PNRSequenceID, $PNRParentIdentifier, $PNRTicket, $PNRTravelSegment, $PNRSpecialServiceRequest, $Active, $PNRPhones, $PNRPassenger)
    {
        parent::__construct($PNRIdentifier, $PNRCreateTime, $PNRCreateDate, $PNRTypeCode, $PNRSequenceID, $PNRParentIdentifier, $PNRTicket, $PNRTravelSegment, $PNRSpecialServiceRequest, $Active, $PNRPhones);
        $this->PNRPassenger = $PNRPassenger;
    }
}
