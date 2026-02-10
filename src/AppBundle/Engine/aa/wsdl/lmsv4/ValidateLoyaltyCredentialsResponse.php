<?php

namespace LMSV4;

include_once 'ListResponseHeaderType.php';

class ValidateLoyaltyCredentialsResponse extends ListResponseHeaderType
{
    /**
     * @var ValidateLoyaltyCredentialsResult[]
     */
    public $ValidateLoyaltyCredentialsResult = null;

    /**
     * @param ValidateLoyaltyCredentialsResult[] $ValidateLoyaltyCredentialsResult
     */
    public function __construct($ValidateLoyaltyCredentialsResult)
    {
        parent::__construct();
        $this->ValidateLoyaltyCredentialsResult = $ValidateLoyaltyCredentialsResult;
    }
}
