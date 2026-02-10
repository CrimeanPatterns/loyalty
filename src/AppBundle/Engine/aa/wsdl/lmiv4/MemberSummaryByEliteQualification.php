<?php

namespace LMIV4;

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
     * @var int
     */
    public $DollarQuantity = null;

    /**
     * @param int $MileageQuantity
     * @param int $PointsQuantity
     * @param int $SegmentQuantity
     * @param int $DollarQuantity
     */
    public function __construct($MileageQuantity, $PointsQuantity, $SegmentQuantity, $DollarQuantity)
    {
        $this->MileageQuantity = $MileageQuantity;
        $this->PointsQuantity = $PointsQuantity;
        $this->SegmentQuantity = $SegmentQuantity;
        $this->DollarQuantity = $DollarQuantity;
    }
}
