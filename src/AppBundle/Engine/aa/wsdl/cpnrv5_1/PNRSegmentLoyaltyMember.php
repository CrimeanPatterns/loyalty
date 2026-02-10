<?php

namespace CPNRV5_1;

class PNRSegmentLoyaltyMember
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
