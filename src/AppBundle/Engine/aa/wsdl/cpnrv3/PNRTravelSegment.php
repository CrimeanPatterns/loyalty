<?php

namespace CPNRV3;

class PNRTravelSegment
{
    /**
     * @var int
     */
    public $SegmentSequenceIdentifier = null;

    /**
     * @var SegmentTypeCodeEnum
     */
    public $SegmentTypeCode = null;

    /**
     * @var int
     */
    public $SegmentPassengerQuantity = null;

    /**
     * @param int $SegmentSequenceIdentifier
     * @param SegmentTypeCodeEnum $SegmentTypeCode
     * @param int $SegmentPassengerQuantity
     */
    public function __construct($SegmentSequenceIdentifier, $SegmentTypeCode, $SegmentPassengerQuantity)
    {
        $this->SegmentSequenceIdentifier = $SegmentSequenceIdentifier;
        $this->SegmentTypeCode = $SegmentTypeCode;
        $this->SegmentPassengerQuantity = $SegmentPassengerQuantity;
    }
}
