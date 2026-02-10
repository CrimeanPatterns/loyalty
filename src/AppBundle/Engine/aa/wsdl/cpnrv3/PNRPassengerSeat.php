<?php

namespace CPNRV3;

class PNRPassengerSeat
{
    /**
     * @var int
     */
    public $PassengerSeatSequenceIdentifier = null;

    /**
     * @var string
     */
    public $SeatRowIdentifier = null;

    /**
     * @var string
     */
    public $RowLetterIdentifier = null;

    /**
     * @var date
     */
    public $FlightLegBeginDate = null;

    /**
     * @var Station
     */
    public $FlightLegServiceEndCode = null;

    /**
     * @var Station
     */
    public $FlightLegServiceBeginCode = null;

    /**
     * @var Flight
     */
    public $SeatFlightBooked = null;

    /**
     * @var string
     */
    public $SeatCharacteristicCode1 = null;

    /**
     * @var string
     */
    public $SeatCharacteristicCode2 = null;

    /**
     * @var string
     */
    public $ClassOfServiceBookedCode = null;

    /**
     * @var SegmentStatus
     */
    public $SeatSegmentStatusCurrentCode = null;

    /**
     * @var SegmentStatus
     */
    public $SeatSegmentStatusPreviousCode = null;

    /**
     * @param int $PassengerSeatSequenceIdentifier
     * @param string $SeatRowIdentifier
     * @param string $RowLetterIdentifier
     * @param date $FlightLegBeginDate
     * @param Station $FlightLegServiceEndCode
     * @param Station $FlightLegServiceBeginCode
     * @param Flight $SeatFlightBooked
     * @param string $SeatCharacteristicCode1
     * @param string $SeatCharacteristicCode2
     * @param string $ClassOfServiceBookedCode
     * @param SegmentStatus $SeatSegmentStatusCurrentCode
     * @param SegmentStatus $SeatSegmentStatusPreviousCode
     */
    public function __construct($PassengerSeatSequenceIdentifier, $SeatRowIdentifier, $RowLetterIdentifier, $FlightLegBeginDate, $FlightLegServiceEndCode, $FlightLegServiceBeginCode, $SeatFlightBooked, $SeatCharacteristicCode1, $SeatCharacteristicCode2, $ClassOfServiceBookedCode, $SeatSegmentStatusCurrentCode, $SeatSegmentStatusPreviousCode)
    {
        $this->PassengerSeatSequenceIdentifier = $PassengerSeatSequenceIdentifier;
        $this->SeatRowIdentifier = $SeatRowIdentifier;
        $this->RowLetterIdentifier = $RowLetterIdentifier;
        $this->FlightLegBeginDate = $FlightLegBeginDate;
        $this->FlightLegServiceEndCode = $FlightLegServiceEndCode;
        $this->FlightLegServiceBeginCode = $FlightLegServiceBeginCode;
        $this->SeatFlightBooked = $SeatFlightBooked;
        $this->SeatCharacteristicCode1 = $SeatCharacteristicCode1;
        $this->SeatCharacteristicCode2 = $SeatCharacteristicCode2;
        $this->ClassOfServiceBookedCode = $ClassOfServiceBookedCode;
        $this->SeatSegmentStatusCurrentCode = $SeatSegmentStatusCurrentCode;
        $this->SeatSegmentStatusPreviousCode = $SeatSegmentStatusPreviousCode;
    }
}
