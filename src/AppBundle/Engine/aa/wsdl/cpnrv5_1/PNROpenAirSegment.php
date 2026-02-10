<?php

namespace CPNRV5_1;

include_once 'PNRAirSegment.php';

class PNROpenAirSegment extends PNRAirSegment
{
    /**
     * @var date
     */
    public $SegmentBeginTimestamp = null;

    /**
     * @var Airline
     */
    public $AirlineBookedCode = null;

    /**
     * @param string $ClassOfServiceBookedCode
     * @param bool $MealServiceIndicator
     * @param string $BaseCabinClassCode
     * @param string $AircraftTypeNumericCode
     * @param SegmentStatus $SegmentStatusPreviousCode
     * @param SegmentStatus $SegmentStatusCurrentCode
     * @param Station $SegmentServiceEndCode
     * @param Station $SegmentServiceBeginCode
     * @param bool $InboundConnectionIndicator
     * @param bool $OutboundConnectionIndicator
     * @param date $SegmentBeginTimestamp
     * @param Airline $AirlineBookedCode
     */
    public function __construct($ClassOfServiceBookedCode, $MealServiceIndicator, $BaseCabinClassCode, $AircraftTypeNumericCode, $SegmentStatusPreviousCode, $SegmentStatusCurrentCode, $SegmentServiceEndCode, $SegmentServiceBeginCode, $InboundConnectionIndicator, $OutboundConnectionIndicator, $SegmentBeginTimestamp, $AirlineBookedCode)
    {
        parent::__construct($ClassOfServiceBookedCode, $MealServiceIndicator, $BaseCabinClassCode, $AircraftTypeNumericCode, $SegmentStatusPreviousCode, $SegmentStatusCurrentCode, $SegmentServiceEndCode, $SegmentServiceBeginCode, $InboundConnectionIndicator, $OutboundConnectionIndicator);
        $this->SegmentBeginTimestamp = $SegmentBeginTimestamp;
        $this->AirlineBookedCode = $AirlineBookedCode;
    }
}
