<?php

namespace LMSV4;

include_once 'RequestHeaderType.php';

class UpdateMemberAccountPasswordRequest extends RequestHeaderType
{
    /**
     * @var UpdateMemberAccountPasswordRequestItem[]
     */
    public $UpdateMemberAccountPasswordRequestItem = null;

    /**
     * @var string
     */
    public $AuditID = null;

    /**
     * @param string $ClientID
     * @param string $WsdlVersion
     * @param string $ServiceType
     * @param string $ApplicationID
     * @param UpdateMemberAccountPasswordRequestItem[] $UpdateMemberAccountPasswordRequestItem
     * @param string $AuditID
     */
    public function __construct($ClientID, $WsdlVersion, $ServiceType, $ApplicationID, $UpdateMemberAccountPasswordRequestItem, $AuditID)
    {
        parent::__construct($ClientID, $WsdlVersion, $ServiceType, $ApplicationID);
        $this->UpdateMemberAccountPasswordRequestItem = $UpdateMemberAccountPasswordRequestItem;
        $this->AuditID = $AuditID;
    }
}
