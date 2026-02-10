<?php

namespace LMIV3;

class MemberSummaryDetail
{
    /**
     * @var int
     */
    public $PrizeEligibleMileageQuantity = null;

    /**
     * @var int
     */
    public $BaseMileageQuantity = null;

    /**
     * @var int
     */
    public $BonusMileageQuantity = null;

    /**
     * @var LoyaltyProgramTimePeriod
     */
    public $LoyaltyProgramTimePeriod = null;

    /**
     * @var MemberSummaryByEliteQualification
     */
    public $MemberSummaryByEliteQualification = null;

    /**
     * @var MemberSummaryByExpiration
     */
    public $MemberSummaryByExpiration = null;

    /**
     * @param int $PrizeEligibleMileageQuantity
     * @param int $BaseMileageQuantity
     * @param int $BonusMileageQuantity
     * @param LoyaltyProgramTimePeriod $LoyaltyProgramTimePeriod
     * @param MemberSummaryByEliteQualification $MemberSummaryByEliteQualification
     * @param MemberSummaryByExpiration $MemberSummaryByExpiration
     */
    public function __construct($PrizeEligibleMileageQuantity, $BaseMileageQuantity, $BonusMileageQuantity, $LoyaltyProgramTimePeriod, $MemberSummaryByEliteQualification, $MemberSummaryByExpiration)
    {
        $this->PrizeEligibleMileageQuantity = $PrizeEligibleMileageQuantity;
        $this->BaseMileageQuantity = $BaseMileageQuantity;
        $this->BonusMileageQuantity = $BonusMileageQuantity;
        $this->LoyaltyProgramTimePeriod = $LoyaltyProgramTimePeriod;
        $this->MemberSummaryByEliteQualification = $MemberSummaryByEliteQualification;
        $this->MemberSummaryByExpiration = $MemberSummaryByExpiration;
    }
}
