<?php

namespace CPNRV3;

class PNRTravelSegmentTransactionHistory
{
    /**
     * @var string
     */
    public $SegmentTransactionBookingActivityCode = null;

    /**
     * @var PNRTravelSegment[]
     */
    public $PNRTravelSegment = null;

    /**
     * @param string $SegmentTransactionBookingActivityCode
     * @param PNRTravelSegment[] $PNRTravelSegment
     */
    public function __construct($SegmentTransactionBookingActivityCode, $PNRTravelSegment)
    {
        $this->SegmentTransactionBookingActivityCode = $SegmentTransactionBookingActivityCode;
        $this->PNRTravelSegment = $PNRTravelSegment;
    }
}
