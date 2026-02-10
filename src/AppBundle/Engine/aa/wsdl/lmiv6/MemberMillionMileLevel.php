<?php

namespace LMIV6;

class MemberMillionMileLevel
{
    /**
     * @var string
     */
    public $TotalMillionMilerMiles = null;

    /**
     * @var string
     */
    public $MillionMileLevelCode = null;

    /**
     * @var date
     */
    public $MillionMileLevelEffectiveDate = null;

    /**
     * @param string $TotalMillionMilerMiles
     * @param string $MillionMileLevelCode
     * @param date $MillionMileLevelEffectiveDate
     */
    public function __construct($TotalMillionMilerMiles, $MillionMileLevelCode, $MillionMileLevelEffectiveDate)
    {
        $this->TotalMillionMilerMiles = $TotalMillionMilerMiles;
        $this->MillionMileLevelCode = $MillionMileLevelCode;
        $this->MillionMileLevelEffectiveDate = $MillionMileLevelEffectiveDate;
    }
}
