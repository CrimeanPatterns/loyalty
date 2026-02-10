<?php

namespace LMIV4;

include_once 'CustomerLoyaltyMember.php';

class AAdvantageMember extends CustomerLoyaltyMember
{
    /**
     * @var AAdvantageAccountEnrollment
     */
    public $AAdvantageAccountEnrollment = null;

    /**
     * @var bool
     */
    public $PreferredSeatFlag = null;

    /**
     * @param LoyaltyProgram $LoyaltyProgram
     * @param LoyaltyMemberName $LoyaltyMemberName
     * @param AAdvantageAccountEnrollment $AAdvantageAccountEnrollment
     * @param bool $PreferredSeatFlag
     */
    public function __construct($LoyaltyProgram, $LoyaltyMemberName, $AAdvantageAccountEnrollment, $PreferredSeatFlag)
    {
        parent::__construct($LoyaltyProgram, $LoyaltyMemberName);
        $this->AAdvantageAccountEnrollment = $AAdvantageAccountEnrollment;
        $this->PreferredSeatFlag = $PreferredSeatFlag;
    }
}
