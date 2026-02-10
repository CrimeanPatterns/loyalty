<?php

namespace CPNRV3;

include_once 'PNRSpecialServiceRequest.php';

class PNRBasedSpecialServiceRequest extends PNRSpecialServiceRequest
{
    /**
     * @var date
     */
    public $SegmentBeginDate = null;

    /**
     * @var string
     */
    public $ClassOfServicePNRBookedCode = null;

    /**
     * @var SegmentStatus
     */
    public $SegmentStatus = null;

    /**
     * @var Flight
     */
    public $FlightBooked = null;

    /**
     * @param int $SequenceIdentifier
     * @param string $Code
     * @param string $Text
     * @param int $Quantity
     * @param date $SegmentBeginDate
     * @param string $ClassOfServicePNRBookedCode
     * @param SegmentStatus $SegmentStatus
     * @param Flight $FlightBooked
     */
    public function __construct($SequenceIdentifier, $Code, $Text, $Quantity, $SegmentBeginDate, $ClassOfServicePNRBookedCode, $SegmentStatus, $FlightBooked)
    {
        parent::__construct($SequenceIdentifier, $Code, $Text, $Quantity);
        $this->SegmentBeginDate = $SegmentBeginDate;
        $this->ClassOfServicePNRBookedCode = $ClassOfServicePNRBookedCode;
        $this->SegmentStatus = $SegmentStatus;
        $this->FlightBooked = $FlightBooked;
    }
}
