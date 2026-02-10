<?php

namespace LMIV3;

class MemberSummaryByEliteQualification
{
    /**
     * @var int
     */
    public $MileageQuantity = null;

    /**
     * @var int
     */
    public $PointsQuantity = null;

    /**
     * @var int
     */
    public $SegmentQuantity = null;

    /**
     * @param int $MileageQuantity
     * @param int $PointsQuantity
     * @param int $SegmentQuantity
     */
    public function __construct($MileageQuantity, $PointsQuantity, $SegmentQuantity)
    {
        $this->MileageQuantity = $MileageQuantity;
        $this->PointsQuantity = $PointsQuantity;
        $this->SegmentQuantity = $SegmentQuantity;
    }
}
