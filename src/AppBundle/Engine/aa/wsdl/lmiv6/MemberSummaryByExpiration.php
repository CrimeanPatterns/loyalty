<?php

namespace LMIV6;

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
     * @var string
     */
    public $MilesToReactivate = null;

    /**
     * @param int $ExpiringMileageQuantity
     * @param int $NonExpiringMileageQuantity
     * @param date $MileageExpirationDate
     * @param string $MilesToReactivate
     */
    public function __construct($ExpiringMileageQuantity, $NonExpiringMileageQuantity, $MileageExpirationDate, $MilesToReactivate)
    {
        $this->ExpiringMileageQuantity = $ExpiringMileageQuantity;
        $this->NonExpiringMileageQuantity = $NonExpiringMileageQuantity;
        $this->MileageExpirationDate = $MileageExpirationDate;
        $this->MilesToReactivate = $MilesToReactivate;
    }
}
