<?php

namespace LMIV6;

class MemberAccountMerger
{
    /**
     * @var string
     */
    public $ToLoyaltyAccountNumber = null;

    /**
     * @param string $ToLoyaltyAccountNumber
     */
    public function __construct($ToLoyaltyAccountNumber)
    {
        $this->ToLoyaltyAccountNumber = $ToLoyaltyAccountNumber;
    }
}
