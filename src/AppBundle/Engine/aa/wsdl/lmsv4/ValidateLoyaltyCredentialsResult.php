<?php

namespace LMSV4;

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
     * @var string
     */
    public $ValidationFailMaxLimitCount = null;

    /**
     * @var bool
     */
    public $PasswordTemporaryIndicator = null;

    /**
     * @param ValidateLoyaltyCredentialsStatus $ValidateLoyaltyCredentialsStatus
     * @param string $ValidationFailCount
     * @param LoyaltyMemberAccount[] $LoyaltyMemberAccount
     * @param string $ValidationFailMaxLimitCount
     * @param bool $PasswordTemporaryIndicator
     */
    public function __construct($ValidateLoyaltyCredentialsStatus, $ValidationFailCount, $LoyaltyMemberAccount, $ValidationFailMaxLimitCount, $PasswordTemporaryIndicator)
    {
        $this->ValidateLoyaltyCredentialsStatus = $ValidateLoyaltyCredentialsStatus;
        $this->ValidationFailCount = $ValidationFailCount;
        $this->LoyaltyMemberAccount = $LoyaltyMemberAccount;
        $this->ValidationFailMaxLimitCount = $ValidationFailMaxLimitCount;
        $this->PasswordTemporaryIndicator = $PasswordTemporaryIndicator;
    }
}
