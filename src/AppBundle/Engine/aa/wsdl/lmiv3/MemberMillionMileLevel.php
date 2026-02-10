<?php

namespace LMIV3;

class MemberMillionMileLevel
{
    /**
     * @var string
     */
    public $MillionMileLevelCode = null;

    /**
     * @var date
     */
    public $MillionMileLevelEffectiveDate = null;

    /**
     * @param string $MillionMileLevelCode
     * @param date $MillionMileLevelEffectiveDate
     */
    public function __construct($MillionMileLevelCode, $MillionMileLevelEffectiveDate)
    {
        $this->MillionMileLevelCode = $MillionMileLevelCode;
        $this->MillionMileLevelEffectiveDate = $MillionMileLevelEffectiveDate;
    }
}
