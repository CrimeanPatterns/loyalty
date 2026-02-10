<?php

namespace LMIV6;

class AAdvantageAccountEnrollment
{
    /**
     * @var string
     */
    public $LoyaltyAccountNumber = null;

    /**
     * @var date
     */
    public $LoyaltyProgramEnrollmentDate = null;

    /**
     * @var date
     */
    public $LastActivityDate = null;

    /**
     * @var string
     */
    public $MergeStatusCode = null;

    /**
     * @var string
     */
    public $AccountStatusCode = null;

    /**
     * @var string
     */
    public $SecurityStatusCode = null;

    /**
     * @var string
     */
    public $LoyaltyAccountCommentCode = null;

    /**
     * @var bool
     */
    public $LoyaltyAccountCommentExistsFlag = null;

    /**
     * @var MemberAccountMerger
     */
    public $MemberAccountMerger = null;

    /**
     * @var EliteStatus
     */
    public $EliteStatus = null;

    /**
     * @var MemberMillionMileLevel
     */
    public $MemberMillionMileLevel = null;

    /**
     * @var MemberPartnerProgramProfile[]
     */
    public $MemberPartnerProgramProfile = null;

    /**
     * @var MemberActivitySummary[]
     */
    public $MemberActivitySummary = null;

    /**
     * @var string
     */
    public $MarketingSalesCityCode = null;

    /**
     * @var PartnerStratificationBenefits[]
     */
    public $PartnerStratificationBenefits = null;

    /**
     * @param string $LoyaltyAccountNumber
     * @param date $LoyaltyProgramEnrollmentDate
     * @param date $LastActivityDate
     * @param string $MergeStatusCode
     * @param string $AccountStatusCode
     * @param string $SecurityStatusCode
     * @param string $LoyaltyAccountCommentCode
     * @param bool $LoyaltyAccountCommentExistsFlag
     * @param MemberAccountMerger $MemberAccountMerger
     * @param EliteStatus $EliteStatus
     * @param MemberMillionMileLevel $MemberMillionMileLevel
     * @param MemberPartnerProgramProfile[] $MemberPartnerProgramProfile
     * @param MemberActivitySummary[] $MemberActivitySummary
     * @param string $MarketingSalesCityCode
     * @param PartnerStratificationBenefits[] $PartnerStratificationBenefits
     */
    public function __construct($LoyaltyAccountNumber, $LoyaltyProgramEnrollmentDate, $LastActivityDate, $MergeStatusCode, $AccountStatusCode, $SecurityStatusCode, $LoyaltyAccountCommentCode, $LoyaltyAccountCommentExistsFlag, $MemberAccountMerger, $EliteStatus, $MemberMillionMileLevel, $MemberPartnerProgramProfile, $MemberActivitySummary, $MarketingSalesCityCode, $PartnerStratificationBenefits)
    {
        $this->LoyaltyAccountNumber = $LoyaltyAccountNumber;
        $this->LoyaltyProgramEnrollmentDate = $LoyaltyProgramEnrollmentDate;
        $this->LastActivityDate = $LastActivityDate;
        $this->MergeStatusCode = $MergeStatusCode;
        $this->AccountStatusCode = $AccountStatusCode;
        $this->SecurityStatusCode = $SecurityStatusCode;
        $this->LoyaltyAccountCommentCode = $LoyaltyAccountCommentCode;
        $this->LoyaltyAccountCommentExistsFlag = $LoyaltyAccountCommentExistsFlag;
        $this->MemberAccountMerger = $MemberAccountMerger;
        $this->EliteStatus = $EliteStatus;
        $this->MemberMillionMileLevel = $MemberMillionMileLevel;
        $this->MemberPartnerProgramProfile = $MemberPartnerProgramProfile;
        $this->MemberActivitySummary = $MemberActivitySummary;
        $this->MarketingSalesCityCode = $MarketingSalesCityCode;
        $this->PartnerStratificationBenefits = $PartnerStratificationBenefits;
    }
}
