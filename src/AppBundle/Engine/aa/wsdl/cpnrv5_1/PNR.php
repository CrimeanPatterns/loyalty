<?php

namespace CPNRV5_1;

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
     * @var int
     */
    public $PNRTypeCode = null;

    /**
     * @var int
     */
    public $PNRSequenceID = null;

    /**
     * @var string
     */
    public $PNRParentIdentifier = null;

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
     * @var bool
     */
    public $Active = null;

    /**
     * @var PNRPhones
     */
    public $PNRPhones = null;

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
     */
    public function __construct($PNRIdentifier, $PNRCreateTime, $PNRCreateDate, $PNRTypeCode, $PNRSequenceID, $PNRParentIdentifier, $PNRTicket, $PNRTravelSegment, $PNRSpecialServiceRequest, $Active, $PNRPhones)
    {
        $this->PNRIdentifier = $PNRIdentifier;
        $this->PNRCreateTime = $PNRCreateTime;
        $this->PNRCreateDate = $PNRCreateDate;
        $this->PNRTypeCode = $PNRTypeCode;
        $this->PNRSequenceID = $PNRSequenceID;
        $this->PNRParentIdentifier = $PNRParentIdentifier;
        $this->PNRTicket = $PNRTicket;
        $this->PNRTravelSegment = $PNRTravelSegment;
        $this->PNRSpecialServiceRequest = $PNRSpecialServiceRequest;
        $this->Active = $Active;
        $this->PNRPhones = $PNRPhones;
    }
}
