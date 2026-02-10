<?php

namespace CPNRV5_1;

include_once 'PNRStandardAirSegment.php';

class PNROtherAirSegment extends PNRStandardAirSegment
{
    /**
     * @param dateTime $SegmentBeginTimestamp
     * @param dateTime $SegmentEndTimestamp
     * @param string $AircraftTypeCode
     * @param Flight $FlightBooked
     */
    public function __construct($SegmentBeginTimestamp, $SegmentEndTimestamp, $AircraftTypeCode, $FlightBooked)
    {
        parent::__construct($SegmentBeginTimestamp, $SegmentEndTimestamp, $AircraftTypeCode, $FlightBooked);
    }
}
