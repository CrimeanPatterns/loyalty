<?php

namespace LMIV4;

include_once 'RequestHeaderType.php';

class MemberInformationRetrieveRequest extends RequestHeaderType
{
    /**
     * @var MemberInformationRetrieveRequestItem[]
     */
    public $MemberInformationRetrieveRequestItem = null;

    /**
     * @var AuditID
     */
    public $AuditID = null;

    /**
     * @var string
     */
    public $AuthorizationID = null;

    /**
     * @var string
     */
    public $AuthorizationPassword = null;

    /**
     * @param string $ClientID
     * @param string $WsdlVersion
     * @param string $ServiceType
     * @param string $ApplicationID
     * @param MemberInformationRetrieveRequestItem[] $MemberInformationRetrieveRequestItem
     * @param AuditID $AuditID
     * @param string $AuthorizationID
     * @param string $AuthorizationPassword
     */
    public function __construct($ClientID, $WsdlVersion, $ServiceType, $ApplicationID, $MemberInformationRetrieveRequestItem, $AuditID, $AuthorizationID, $AuthorizationPassword)
    {
        parent::__construct($ClientID, $WsdlVersion, $ServiceType, $ApplicationID);
        $this->MemberInformationRetrieveRequestItem = $MemberInformationRetrieveRequestItem;
        $this->AuditID = $AuditID;
        $this->AuthorizationID = $AuthorizationID;
        $this->AuthorizationPassword = $AuthorizationPassword;
    }
}
