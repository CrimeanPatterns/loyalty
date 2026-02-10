<?php

namespace LMIV6;

class LoyaltyProgramTimePeriod
{
    /**
     * @var LoyaltyProgramTimePeriodNameEnum
     */
    public $LoyaltyProgramTimePeriodName = null;

    /**
     * @param LoyaltyProgramTimePeriodNameEnum $LoyaltyProgramTimePeriodName
     */
    public function __construct($LoyaltyProgramTimePeriodName)
    {
        $this->LoyaltyProgramTimePeriodName = $LoyaltyProgramTimePeriodName;
    }
}
