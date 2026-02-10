<?php

namespace LMIV3;

class MemberSummaryByExpiration
{
    /**
     * @var int
     */
    public $ExpiringMileageQuantity = null;

    /**
     * @var int
     */
    public $NonExpiringMileageQuantity = null;

    /**
     * @var date
     */
    public $MileageExpirationDate = null;

    /**
     * @param int $ExpiringMileageQuantity
     * @param int $NonExpiringMileageQuantity
     * @param date $MileageExpirationDate
     */
    public function __construct($ExpiringMileageQuantity, $NonExpiringMileageQuantity, $MileageExpirationDate)
    {
        $this->ExpiringMileageQuantity = $ExpiringMileageQuantity;
        $this->NonExpiringMileageQuantity = $NonExpiringMileageQuantity;
        $this->MileageExpirationDate = $MileageExpirationDate;
    }
}
