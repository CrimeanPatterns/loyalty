<?php

namespace CPNRV3;

include_once 'PNRTravelSegment.php';

class PNRUnkownSegment extends PNRTravelSegment
{
    /**
     * @param int $SegmentSequenceIdentifier
     * @param SegmentTypeCodeEnum $SegmentTypeCode
     * @param int $SegmentPassengerQuantity
     */
    public function __construct($SegmentSequenceIdentifier, $SegmentTypeCode, $SegmentPassengerQuantity)
    {
        parent::__construct($SegmentSequenceIdentifier, $SegmentTypeCode, $SegmentPassengerQuantity);
    }
}
