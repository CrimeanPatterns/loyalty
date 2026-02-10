<?php

namespace CPNRV3;

class PNR
{
    /**
     * @var string
     */
    public $PNRIdentifier = null;

    /**
     * @var time
     */
    public $PNRCreateTime = null;

    /**
     * @var date
     */
    public $PNRCreateDate = null;

    /**
     * @var PNRTypeCodeEnum
     */
    public $PNRTypeCode = null;

    /**
     * @var PNRTicket[]
     */
    public $PNRTicket = null;

    /**
     * @var PNRTravelSegment[]
     */
    public $PNRTravelSegment = null;

    /**
     * @var PNRSpecialServiceRequest[]
     */
    public $PNRSpecialServiceRequest = null;

    /**
     * @param string $PNRIdentifier
     * @param time $PNRCreateTime
     * @param date $PNRCreateDate
     * @param PNRTypeCodeEnum $PNRTypeCode
     * @param PNRTicket[] $PNRTicket
     * @param PNRTravelSegment[] $PNRTravelSegment
     * @param PNRSpecialServiceRequest[] $PNRSpecialServiceRequest
     */
    public function __construct($PNRIdentifier, $PNRCreateTime, $PNRCreateDate, $PNRTypeCode, $PNRTicket, $PNRTravelSegment, $PNRSpecialServiceRequest)
    {
        $this->PNRIdentifier = $PNRIdentifier;
        $this->PNRCreateTime = $PNRCreateTime;
        $this->PNRCreateDate = $PNRCreateDate;
        $this->PNRTypeCode = $PNRTypeCode;
        $this->PNRTicket = $PNRTicket;
        $this->PNRTravelSegment = $PNRTravelSegment;
        $this->PNRSpecialServiceRequest = $PNRSpecialServiceRequest;
    }
}
