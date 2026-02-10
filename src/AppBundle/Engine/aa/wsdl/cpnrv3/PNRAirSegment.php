<?php

namespace CPNRV3;

include_once 'PNRTravelSegment.php';

class PNRAirSegment extends PNRTravelSegment
{
    /**
     * @var string
     */
    public $ClassOfServiceBookedCode = null;

    /**
     * @var bool
     */
    public $MealServiceIndicator = null;

    /**
     * @var BaseCabinClassCodeEnum
     */
    public $BaseCabinClassCode = null;

    /**
     * @var string
     */
    public $AircraftTypeNumericCode = null;

    /**
     * @var SegmentStatus
     */
    public $SegmentStatusPreviousCode = null;

    /**
     * @var SegmentStatus
     */
    public $SegmentStatusCurrentCode = null;

    /**
     * @var Station
     */
    public $SegmentServiceEndCode = null;

    /**
     * @var Station
     */
    public $SegmentServiceBeginCode = null;

    /**
     * @param int $SegmentSequenceIdentifier
     * @param SegmentTypeCodeEnum $SegmentTypeCode
     * @param int $SegmentPassengerQuantity
     * @param string $ClassOfServiceBookedCode
     * @param bool $MealServiceIndicator
     * @param BaseCabinClassCodeEnum $BaseCabinClassCode
     * @param string $AircraftTypeNumericCode
     * @param SegmentStatus $SegmentStatusPreviousCode
     * @param SegmentStatus $SegmentStatusCurrentCode
     * @param Station $SegmentServiceEndCode
     * @param Station $SegmentServiceBeginCode
     */
    public function __construct($SegmentSequenceIdentifier, $SegmentTypeCode, $SegmentPassengerQuantity, $ClassOfServiceBookedCode, $MealServiceIndicator, $BaseCabinClassCode, $AircraftTypeNumericCode, $SegmentStatusPreviousCode, $SegmentStatusCurrentCode, $SegmentServiceEndCode, $SegmentServiceBeginCode)
    {
        parent::__construct($SegmentSequenceIdentifier, $SegmentTypeCode, $SegmentPassengerQuantity);
        $this->ClassOfServiceBookedCode = $ClassOfServiceBookedCode;
        $this->MealServiceIndicator = $MealServiceIndicator;
        $this->BaseCabinClassCode = $BaseCabinClassCode;
        $this->AircraftTypeNumericCode = $AircraftTypeNumericCode;
        $this->SegmentStatusPreviousCode = $SegmentStatusPreviousCode;
        $this->SegmentStatusCurrentCode = $SegmentStatusCurrentCode;
        $this->SegmentServiceEndCode = $SegmentServiceEndCode;
        $this->SegmentServiceBeginCode = $SegmentServiceBeginCode;
    }
}
