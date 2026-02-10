<?php

namespace CPNRV5_1;

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
     * @param string $BaseCabinClassCode
     * @param string $AircraftTypeNumericCode
     * @param SegmentStatus $SegmentStatusPreviousCode
     * @param SegmentStatus $SegmentStatusCurrentCode
     * @param Station $SegmentServiceEndCode
     * @param Station $SegmentServiceBeginCode
     * @param bool $InboundConnectionIndicator
     * @param bool $OutboundConnectionIndicator
     * @param dateTime $SegmentBeginTimestamp
     * @param dateTime $SegmentEndTimestamp
     * @param string $AircraftTypeCode
     * @param Flight $FlightBooked
     */
    public function __construct($ClassOfServiceBookedCode, $MealServiceIndicator, $BaseCabinClassCode, $AircraftTypeNumericCode, $SegmentStatusPreviousCode, $SegmentStatusCurrentCode, $SegmentServiceEndCode, $SegmentServiceBeginCode, $InboundConnectionIndicator, $OutboundConnectionIndicator, $SegmentBeginTimestamp, $SegmentEndTimestamp, $AircraftTypeCode, $FlightBooked)
    {
        parent::__construct($ClassOfServiceBookedCode, $MealServiceIndicator, $BaseCabinClassCode, $AircraftTypeNumericCode, $SegmentStatusPreviousCode, $SegmentStatusCurrentCode, $SegmentServiceEndCode, $SegmentServiceBeginCode, $InboundConnectionIndicator, $OutboundConnectionIndicator);
        $this->SegmentBeginTimestamp = $SegmentBeginTimestamp;
        $this->SegmentEndTimestamp = $SegmentEndTimestamp;
        $this->AircraftTypeCode = $AircraftTypeCode;
        $this->FlightBooked = $FlightBooked;
    }
}
