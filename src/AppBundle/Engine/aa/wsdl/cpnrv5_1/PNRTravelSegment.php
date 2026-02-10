<?php

namespace CPNRV5_1;

class PNRTravelSegment
{
    /**
     * @var int
     */
    public $SegmentSequenceIdentifier = null;

    /**
     * @var string
     */
    public $SegmentTypeCode = null;

    /**
     * @var int
     */
    public $SegmentPassengerQuantity = null;

    /**
     * @param int $SegmentSequenceIdentifier
     * @param string $SegmentTypeCode
     * @param int $SegmentPassengerQuantity
     */
    public function __construct($SegmentSequenceIdentifier, $SegmentTypeCode, $SegmentPassengerQuantity)
    {
        $this->SegmentSequenceIdentifier = $SegmentSequenceIdentifier;
        $this->SegmentTypeCode = $SegmentTypeCode;
        $this->SegmentPassengerQuantity = $SegmentPassengerQuantity;
    }
}
