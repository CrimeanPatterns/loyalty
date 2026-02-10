<?php

class UpdateMemberAccountPasswordRequestItem
{
    /**
     * @var string
     */
    public $LoyaltyAccountNumber = null;

    /**
     * @var string
     */
    public $LoyaltyAccountPasswordText = null;

    /**
     * @var bool
     */
    public $PwdTempIndicator = null;

    /**
     * @param string $LoyaltyAccountNumber
     * @param string $LoyaltyAccountPasswordText
     * @param bool $PwdTempIndicator
     */
    public function __construct($LoyaltyAccountNumber, $LoyaltyAccountPasswordText, $PwdTempIndicator)
    {
        $this->LoyaltyAccountNumber = $LoyaltyAccountNumber;
        $this->LoyaltyAccountPasswordText = $LoyaltyAccountPasswordText;
        $this->PwdTempIndicator = $PwdTempIndicator;
    }
}
