<?php

namespace LMIV6;

class MemberSummaryByEliteQualification
{
    /**
     * @var string
     */
    public $MileageQuantity = null;

    /**
     * @var int
     */
    public $PointsQuantity = null;

    /**
     * @var string
     */
    public $SegmentQuantity = null;

    /**
     * @var string
     */
    public $DollarQuantity = null;

    /**
     * @param string $MileageQuantity
     * @param int $PointsQuantity
     * @param string $SegmentQuantity
     * @param string $DollarQuantity
     */
    public function __construct($MileageQuantity, $PointsQuantity, $SegmentQuantity, $DollarQuantity)
    {
        $this->MileageQuantity = $MileageQuantity;
        $this->PointsQuantity = $PointsQuantity;
        $this->SegmentQuantity = $SegmentQuantity;
        $this->DollarQuantity = $DollarQuantity;
    }
}
