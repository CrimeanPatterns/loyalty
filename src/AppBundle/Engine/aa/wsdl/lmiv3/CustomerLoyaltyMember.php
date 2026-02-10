<?php

namespace LMIV3;

class CustomerLoyaltyMember
{
    /**
     * @var LoyaltyProgram
     */
    public $LoyaltyProgram = null;

    /**
     * @var LoyaltyMemberName
     */
    public $LoyaltyMemberName = null;

    /**
     * @param LoyaltyProgram $LoyaltyProgram
     * @param LoyaltyMemberName $LoyaltyMemberName
     */
    public function __construct($LoyaltyProgram, $LoyaltyMemberName)
    {
        $this->LoyaltyProgram = $LoyaltyProgram;
        $this->LoyaltyMemberName = $LoyaltyMemberName;
    }
}
