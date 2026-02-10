<?php

namespace LMIV6;

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
     * @var int
     */
    public $MilesforNext500mUpgrades = null;

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
     * @param int $MilesforNext500mUpgrades
     * @param LoyaltyProgramTimePeriod $LoyaltyProgramTimePeriod
     * @param MemberSummaryByEliteQualification $MemberSummaryByEliteQualification
     * @param MemberSummaryByExpiration $MemberSummaryByExpiration
     */
    public function __construct($PrizeEligibleMileageQuantity, $BaseMileageQuantity, $BonusMileageQuantity, $MilesforNext500mUpgrades, $LoyaltyProgramTimePeriod, $MemberSummaryByEliteQualification, $MemberSummaryByExpiration)
    {
        $this->PrizeEligibleMileageQuantity = $PrizeEligibleMileageQuantity;
        $this->BaseMileageQuantity = $BaseMileageQuantity;
        $this->BonusMileageQuantity = $BonusMileageQuantity;
        $this->MilesforNext500mUpgrades = $MilesforNext500mUpgrades;
        $this->LoyaltyProgramTimePeriod = $LoyaltyProgramTimePeriod;
        $this->MemberSummaryByEliteQualification = $MemberSummaryByEliteQualification;
        $this->MemberSummaryByExpiration = $MemberSummaryByExpiration;
    }
}
