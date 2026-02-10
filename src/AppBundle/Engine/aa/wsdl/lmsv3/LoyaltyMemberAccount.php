<?php

class LoyaltyMemberAccount
{
    /**
     * @var string
     */
    public $LoyaltyAccountNumber = null;

    /**
     * @var string
     */
    public $LoyaltyProgramCode = null;

    /**
     * @param string $LoyaltyAccountNumber
     * @param string $LoyaltyProgramCode
     */
    public function __construct($LoyaltyAccountNumber, $LoyaltyProgramCode)
    {
        $this->LoyaltyAccountNumber = $LoyaltyAccountNumber;
        $this->LoyaltyProgramCode = $LoyaltyProgramCode;
    }
}
