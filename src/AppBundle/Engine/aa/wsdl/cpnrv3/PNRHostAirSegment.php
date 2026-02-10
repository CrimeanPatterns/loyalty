<?php

namespace CPNRV3;

include_once 'PNRStandardAirSegment.php';

class PNRHostAirSegment extends PNRStandardAirSegment
{
    /**
     * @var Flight
     */
    public $FlightMarketed = null;

    /**
     * @param dateTime $SegmentBeginTimestamp
     * @param dateTime $SegmentEndTimestamp
     * @param string $AircraftTypeCode
     * @param Flight $FlightBooked
     * @param Flight $FlightMarketed
     */
    public function __construct($SegmentBeginTimestamp, $SegmentEndTimestamp, $AircraftTypeCode, $FlightBooked, $FlightMarketed)
    {
        parent::__construct($SegmentBeginTimestamp, $SegmentEndTimestamp, $AircraftTypeCode, $FlightBooked);
        $this->FlightMarketed = $FlightMarketed;
    }
}
