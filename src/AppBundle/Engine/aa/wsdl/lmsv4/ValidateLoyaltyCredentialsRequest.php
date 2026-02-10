<?php

namespace LMSV4;

include_once 'RequestHeaderType.php';

class ValidateLoyaltyCredentialsRequest extends RequestHeaderType
{
    /**
     * @var ValidateLoyaltyCredentialsRequestItem
     */
    public $ValidateLoyaltyCredentialsRequestItem = null;

    /**
     * @var string
     */
    public $AuditID = null;

    /**
     * @param string $ClientID
     * @param string $WsdlVersion
     * @param string $ServiceType
     * @param string $ApplicationID
     * @param ValidateLoyaltyCredentialsRequestItem $ValidateLoyaltyCredentialsRequestItem
     * @param string $AuditID
     */
    public function __construct($ClientID, $WsdlVersion, $ServiceType, $ApplicationID, $ValidateLoyaltyCredentialsRequestItem, $AuditID)
    {
        parent::__construct($ClientID, $WsdlVersion, $ServiceType, $ApplicationID);
        $this->ValidateLoyaltyCredentialsRequestItem = $ValidateLoyaltyCredentialsRequestItem;
        $this->AuditID = $AuditID;
    }
}
