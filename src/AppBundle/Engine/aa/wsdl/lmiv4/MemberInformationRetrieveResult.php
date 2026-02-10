<?php

namespace LMIV4;

class MemberInformationRetrieveResult
{
    /**
     * @var MemberInformationResponseStatus
     */
    public $MemberInformationResponseStatus = null;

    /**
     * @var MemberInformationRetrieveRequestItem
     */
    public $MemberInformationRetrieveRequestItem = null;

    /**
     * @var CustomerLoyaltyMember
     */
    public $CustomerLoyaltyMember = null;

    /**
     * @var CustomerMembership
     */
    public $CustomerMembership = null;

    /**
     * @param MemberInformationResponseStatus $MemberInformationResponseStatus
     * @param MemberInformationRetrieveRequestItem $MemberInformationRetrieveRequestItem
     * @param CustomerLoyaltyMember $CustomerLoyaltyMember
     * @param CustomerMembership $CustomerMembership
     */
    public function __construct($MemberInformationResponseStatus, $MemberInformationRetrieveRequestItem, $CustomerLoyaltyMember, $CustomerMembership)
    {
        $this->MemberInformationResponseStatus = $MemberInformationResponseStatus;
        $this->MemberInformationRetrieveRequestItem = $MemberInformationRetrieveRequestItem;
        $this->CustomerLoyaltyMember = $CustomerLoyaltyMember;
        $this->CustomerMembership = $CustomerMembership;
    }
}
