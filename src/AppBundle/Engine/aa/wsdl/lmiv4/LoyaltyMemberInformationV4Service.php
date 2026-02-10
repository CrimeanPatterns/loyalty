<?php

namespace LMIV4;

include_once 'AAdvantageAccountEnrollment.php';

include_once 'AAdvantageMember.php';

include_once 'Benefits.php';

include_once 'CustomerLoyaltyMember.php';

include_once 'EliteStatus.php';

include_once 'EnrollmentSource.php';

include_once 'FaultType.php';

include_once 'ListResponseHdrStatusCodeType.php';

include_once 'ListResponseHdrStatusMessageType.php';

include_once 'ListResponseHdrStatusType.php';

include_once 'ListResponseHeaderType.php';

include_once 'LoyaltyMemberName.php';

include_once 'LoyaltyProgram.php';

include_once 'LoyaltyProgramPartner.php';

include_once 'LoyaltyProgramTimePeriod.php';

include_once 'LoyaltyProgramTimePeriodNameEnum.php';

include_once 'MemberAccountMerger.php';

include_once 'MemberActivitySummary.php';

include_once 'MemberInformationResponseStatus.php';

include_once 'MemberInformationResponseStatusCodeEnum.php';

include_once 'MemberInformationResponseStatusMessageEnum.php';

include_once 'MemberInformationRetrieveRequest.php';

include_once 'MemberInformationRetrieveRequestItem.php';

include_once 'MemberInformationRetrieveResponse.php';

include_once 'MemberInformationRetrieveResult.php';

include_once 'MemberMillionMileLevel.php';

include_once 'MemberPartnerProgramProfile.php';

include_once 'MemberSummaryByEliteQualification.php';

include_once 'MemberSummaryByExpiration.php';

include_once 'MemberSummaryDetail.php';

include_once 'PartnerProgramParticipation.php';

include_once 'PartnerStratification.php';

include_once 'PartnerStratificationBenefits.php';

include_once 'ProviderSystemErrorTypeEnum.php';

include_once 'ProviderSystemInfo.php';

include_once 'RequestHeaderType.php';

include_once 'ResponseHeaderType.php';

include_once 'ResponseStatusCodeEnum.php';

include_once 'ResponseStatusMessageEnum.php';

include_once 'ResponseStatusType.php';

include_once 'ServiceInfoType.php';

include_once 'CustomerMembership.php';

class LoyaltyMemberInformationV4Service extends \TExtSoapClient
{
    /**
     * @var array The defined classes
     */
    private static $classmap = [
        'AAdvantageAccountEnrollment'          => 'LMIV4\AAdvantageAccountEnrollment',
        'AAdvantageMember'                     => 'LMIV4\AAdvantageMember',
        'Benefits'                             => 'LMIV4\Benefits',
        'CustomerLoyaltyMember'                => 'LMIV4\CustomerLoyaltyMember',
        'EliteStatus'                          => 'LMIV4\EliteStatus',
        'EnrollmentSource'                     => 'LMIV4\EnrollmentSource',
        'FaultType'                            => 'LMIV4\FaultType',
        'ListResponseHdrStatusType'            => 'LMIV4\ListResponseHdrStatusType',
        'ListResponseHeaderType'               => 'LMIV4\ListResponseHeaderType',
        'LoyaltyMemberName'                    => 'LMIV4\LoyaltyMemberName',
        'LoyaltyProgram'                       => 'LMIV4\LoyaltyProgram',
        'LoyaltyProgramPartner'                => 'LMIV4\LoyaltyProgramPartner',
        'LoyaltyProgramTimePeriod'             => 'LMIV4\LoyaltyProgramTimePeriod',
        'MemberAccountMerger'                  => 'LMIV4\MemberAccountMerger',
        'MemberActivitySummary'                => 'LMIV4\MemberActivitySummary',
        'MemberInformationResponseStatus'      => 'LMIV4\MemberInformationResponseStatus',
        'MemberInformationRetrieveRequest'     => 'LMIV4\MemberInformationRetrieveRequest',
        'MemberInformationRetrieveRequestItem' => 'LMIV4\MemberInformationRetrieveRequestItem',
        'MemberInformationRetrieveResponse'    => 'LMIV4\MemberInformationRetrieveResponse',
        'MemberInformationRetrieveResult'      => 'LMIV4\MemberInformationRetrieveResult',
        'MemberMillionMileLevel'               => 'LMIV4\MemberMillionMileLevel',
        'MemberPartnerProgramProfile'          => 'LMIV4\MemberPartnerProgramProfile',
        'MemberSummaryByEliteQualification'    => 'LMIV4\MemberSummaryByEliteQualification',
        'MemberSummaryByExpiration'            => 'LMIV4\MemberSummaryByExpiration',
        'MemberSummaryDetail'                  => 'LMIV4\MemberSummaryDetail',
        'PartnerProgramParticipation'          => 'LMIV4\PartnerProgramParticipation',
        'PartnerStratification'                => 'LMIV4\PartnerStratification',
        'PartnerStratificationBenefits'        => 'LMIV4\PartnerStratificationBenefits',
        'ProviderSystemInfo'                   => 'LMIV4\ProviderSystemInfo',
        'RequestHeaderType'                    => 'LMIV4\RequestHeaderType',
        'ResponseHeaderType'                   => 'LMIV4\ResponseHeaderType',
        'ResponseStatusType'                   => 'LMIV4\ResponseStatusType',
        'ServiceInfoType'                      => 'LMIV4\ServiceInfoType',
        'CustomerMembership'                   => 'LMIV4\CustomerMembership', ];

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     */
    public function __construct(array $options = [], $wsdl = 'LoyaltyMemberInformationV4.wsdl')
    {
        foreach (self::$classmap as $key => $value) {
            if (!isset($options['classmap'][$key])) {
                $options['classmap'][$key] = $value;
            }
        }

        if (isset($options['features']) == false) {
            $options['features'] = SOAP_SINGLE_ELEMENT_ARRAYS;
        }

        parent::__construct($wsdl, $options);
    }

    /**
     * @return MemberInformationRetrieveResponse
     */
    public function RetrieveLoyaltyMemberInformation(MemberInformationRetrieveRequest $Request)
    {
        return $this->__soapCall('RetrieveLoyaltyMemberInformation', [$Request]);
    }
}
