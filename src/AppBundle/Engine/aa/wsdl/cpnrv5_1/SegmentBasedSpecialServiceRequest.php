<?php

namespace CPNRV5_1;

include_once 'PNRSpecialServiceRequest.php';

class SegmentBasedSpecialServiceRequest extends PNRSpecialServiceRequest
{
    /**
     * @var date
     */
    public $SegmentBeginDate = null;

    /**
     * @var string
     */
    public $ClassOfServiceSegmentBookedCode = null;

    /**
     * @var SegmentStatus
     */
    public $SegmentStatus = null;

    /**
     * @var Flight
     */
    public $Flight = null;

    /**
     * @var Station
     */
    public $SegmentEndCode = null;

    /**
     * @var Station
     */
    public $SegmentBeginCode = null;

    /**
     * @param int $SequenceIdentifier
     * @param string $Code
     * @param string $Text
     * @param int $Quantity
     * @param date $SegmentBeginDate
     * @param string $ClassOfServiceSegmentBookedCode
     * @param SegmentStatus $SegmentStatus
     * @param Flight $Flight
     * @param Station $SegmentEndCode
     * @param Station $SegmentBeginCode
     */
    public function __construct($SequenceIdentifier, $Code, $Text, $Quantity, $SegmentBeginDate, $ClassOfServiceSegmentBookedCode, $SegmentStatus, $Flight, $SegmentEndCode, $SegmentBeginCode)
    {
        parent::__construct($SequenceIdentifier, $Code, $Text, $Quantity);
        $this->SegmentBeginDate = $SegmentBeginDate;
        $this->ClassOfServiceSegmentBookedCode = $ClassOfServiceSegmentBookedCode;
        $this->SegmentStatus = $SegmentStatus;
        $this->Flight = $Flight;
        $this->SegmentEndCode = $SegmentEndCode;
        $this->SegmentBeginCode = $SegmentBeginCode;
    }
}
