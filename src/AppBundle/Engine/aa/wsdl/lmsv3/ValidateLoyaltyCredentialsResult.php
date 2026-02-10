<?php

class ValidateLoyaltyCredentialsResult
{
    /**
     * @var ValidateLoyaltyCredentialsStatus
     */
    public $ValidateLoyaltyCredentialsStatus = null;

    /**
     * @var string
     */
    public $ValidationFailCount = null;

    /**
     * @var LoyaltyMemberAccount[]
     */
    public $LoyaltyMemberAccount = null;

    /**
     * @param ValidateLoyaltyCredentialsStatus $ValidateLoyaltyCredentialsStatus
     * @param string $ValidationFailCount
     * @param LoyaltyMemberAccount[] $LoyaltyMemberAccount
     */
    public function __construct($ValidateLoyaltyCredentialsStatus, $ValidationFailCount, $LoyaltyMemberAccount)
    {
        $this->ValidateLoyaltyCredentialsStatus = $ValidateLoyaltyCredentialsStatus;
        $this->ValidationFailCount = $ValidationFailCount;
        $this->LoyaltyMemberAccount = $LoyaltyMemberAccount;
    }
}
