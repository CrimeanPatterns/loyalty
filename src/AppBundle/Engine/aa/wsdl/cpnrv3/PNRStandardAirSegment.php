<?php

namespace CPNRV3;

include_once 'PNRAirSegment.php';

class PNRStandardAirSegment extends PNRAirSegment
{
    /**
     * @var dateTime
     */
    public $SegmentBeginTimestamp = null;

    /**
     * @var dateTime
     */
    public $SegmentEndTimestamp = null;

    /**
     * @var string
     */
    public $AircraftTypeCode = null;

    /**
     * @var Flight
     */
    public $FlightBooked = null;

    /**
     * @param string $ClassOfServiceBookedCode
     * @param bool $MealServiceIndicator
     * @param BaseCabinClassCodeEnum $BaseCabinClassCode
     * @param string $AircraftTypeNumericCode
     * @param SegmentStatus $SegmentStatusPreviousCode
     * @param SegmentStatus $SegmentStatusCurrentCode
     * @param Station $SegmentServiceEndCode
     * @param Station $SegmentServiceBeginCode
     * @param dateTime $SegmentBeginTimestamp
     * @param dateTime $SegmentEndTimestamp
     * @param string $AircraftTypeCode
     * @param Flight $FlightBooked
     */
    public function __construct($ClassOfServiceBookedCode, $MealServiceIndicator, $BaseCabinClassCode, $AircraftTypeNumericCode, $SegmentStatusPreviousCode, $SegmentStatusCurrentCode, $SegmentServiceEndCode, $SegmentServiceBeginCode, $SegmentBeginTimestamp, $SegmentEndTimestamp, $AircraftTypeCode, $FlightBooked)
    {
        parent::__construct($ClassOfServiceBookedCode, $MealServiceIndicator, $BaseCabinClassCode, $AircraftTypeNumericCode, $SegmentStatusPreviousCode, $SegmentStatusCurrentCode, $SegmentServiceEndCode, $SegmentServiceBeginCode);
        $this->SegmentBeginTimestamp = $SegmentBeginTimestamp;
        $this->SegmentEndTimestamp = $SegmentEndTimestamp;
        $this->AircraftTypeCode = $AircraftTypeCode;
        $this->FlightBooked = $FlightBooked;
    }
}
