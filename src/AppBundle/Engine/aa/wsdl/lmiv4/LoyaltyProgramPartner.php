<?php

namespace LMIV4;

class LoyaltyProgramPartner
{
    /**
     * @var string
     */
    public $LoyaltyProgramPartnerCode = null;

    /**
     * @var string
     */
    public $LoyaltyProgramPartnerName = null;

    /**
     * @param string $LoyaltyProgramPartnerCode
     * @param string $LoyaltyProgramPartnerName
     */
    public function __construct($LoyaltyProgramPartnerCode, $LoyaltyProgramPartnerName)
    {
        $this->LoyaltyProgramPartnerCode = $LoyaltyProgramPartnerCode;
        $this->LoyaltyProgramPartnerName = $LoyaltyProgramPartnerName;
    }
}
