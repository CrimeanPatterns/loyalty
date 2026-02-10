<?php

namespace CPNRV3;

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
     * @param PNRTypeCodeEnum $PNRTypeCode
     * @param PNRTicket[] $PNRTicket
     * @param PNRTravelSegment[] $PNRTravelSegment
     * @param PNRSpecialServiceRequest[] $PNRSpecialServiceRequest
     * @param PNRPassenger[] $PNRPassenger
     */
    public function __construct($PNRIdentifier, $PNRCreateTime, $PNRCreateDate, $PNRTypeCode, $PNRTicket, $PNRTravelSegment, $PNRSpecialServiceRequest, $PNRPassenger)
    {
        parent::__construct($PNRIdentifier, $PNRCreateTime, $PNRCreateDate, $PNRTypeCode, $PNRTicket, $PNRTravelSegment, $PNRSpecialServiceRequest);
        $this->PNRPassenger = $PNRPassenger;
    }
}
