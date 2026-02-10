<?php

namespace LMIV3;

class LoyaltyProgram
{
    /**
     * @var string
     */
    public $LoyaltyProgramCode = null;

    /**
     * @var string
     */
    public $AirpassCompanionIndicator = null;

    /**
     * @var EnrollmentSource[]
     */
    public $EnrollmentSource = null;

    /**
     * @param string $LoyaltyProgramCode
     * @param string $AirpassCompanionIndicator
     * @param EnrollmentSource[] $EnrollmentSource
     */
    public function __construct($LoyaltyProgramCode, $AirpassCompanionIndicator, $EnrollmentSource)
    {
        $this->LoyaltyProgramCode = $LoyaltyProgramCode;
        $this->AirpassCompanionIndicator = $AirpassCompanionIndicator;
        $this->EnrollmentSource = $EnrollmentSource;
    }
}
