<?php

namespace CPNRV3;

class PNRPassengerAirSegment
{
    /**
     * @var int
     */
    public $PassengerSequenceNumber = null;

    /**
     * @param int $PassengerSequenceNumber
     */
    public function __construct($PassengerSequenceNumber)
    {
        $this->PassengerSequenceNumber = $PassengerSequenceNumber;
    }
}
