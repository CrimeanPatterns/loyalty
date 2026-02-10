<?php

namespace CPNRV3;

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
     * @param BaseCabinClassCodeEnum $BaseCabinClassCode
     * @param string $AircraftTypeNumericCode
     * @param SegmentStatus $SegmentStatusPreviousCode
     * @param SegmentStatus $SegmentStatusCurrentCode
     * @param Station $SegmentServiceEndCode
     * @param Station $SegmentServiceBeginCode
     * @param date $SegmentBeginTimestamp
     * @param Airline $AirlineBookedCode
     */
    public function __construct($ClassOfServiceBookedCode, $MealServiceIndicator, $BaseCabinClassCode, $AircraftTypeNumericCode, $SegmentStatusPreviousCode, $SegmentStatusCurrentCode, $SegmentServiceEndCode, $SegmentServiceBeginCode, $SegmentBeginTimestamp, $AirlineBookedCode)
    {
        parent::__construct($ClassOfServiceBookedCode, $MealServiceIndicator, $BaseCabinClassCode, $AircraftTypeNumericCode, $SegmentStatusPreviousCode, $SegmentStatusCurrentCode, $SegmentServiceEndCode, $SegmentServiceBeginCode);
        $this->SegmentBeginTimestamp = $SegmentBeginTimestamp;
        $this->AirlineBookedCode = $AirlineBookedCode;
    }
}
