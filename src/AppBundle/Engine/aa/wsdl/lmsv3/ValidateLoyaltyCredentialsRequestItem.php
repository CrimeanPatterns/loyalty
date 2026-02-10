<?php

class ValidateLoyaltyCredentialsRequestItem
{
    /**
     * @var string
     */
    public $LoginPassword = null;

    /**
     * @var ExtraIdData[]
     */
    public $ExtraIdData = null;

    /**
     * @var string
     */
    public $LoginId = null;

    /**
     * @var LoyaltyMemberAccount
     */
    public $LoyaltyMemberAccount = null;

    /**
     * @param string $LoginPassword
     * @param ExtraIdData[] $ExtraIdData
     * @param string $LoginId
     * @param LoyaltyMemberAccount $LoyaltyMemberAccount
     */
    public function __construct($LoginPassword, $ExtraIdData, $LoginId, $LoyaltyMemberAccount)
    {
        $this->LoginPassword = $LoginPassword;
        $this->ExtraIdData = $ExtraIdData;
        $this->LoginId = $LoginId;
        $this->LoyaltyMemberAccount = $LoyaltyMemberAccount;
    }
}
