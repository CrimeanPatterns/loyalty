<?php

namespace CPNRV5_1;

include_once 'PNRHostAirSegment.php';

class OAOperatingAirSegment extends PNRHostAirSegment
{
    /**
     * @var string
     */
    public $OperatingAirlinePNRIdentifier = null;

    /**
     * @var Flight
     */
    public $FlightOperated = null;

    /**
     * @param Flight $FlightMarketed
     * @param string $OperatingAirlinePNRIdentifier
     * @param Flight $FlightOperated
     */
    public function __construct($FlightMarketed, $OperatingAirlinePNRIdentifier, $FlightOperated)
    {
        parent::__construct($FlightMarketed);
        $this->OperatingAirlinePNRIdentifier = $OperatingAirlinePNRIdentifier;
        $this->FlightOperated = $FlightOperated;
    }
}
