<?php

namespace LMIV6;

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

class LoyaltyMemberInformationV6Service extends \TExtSoapClient
{
    /**
     * @var array The defined classes
     */
    private static $classmap = [
        'AAdvantageAccountEnrollment'          => 'LMIV6\AAdvantageAccountEnrollment',
        'AAdvantageMember'                     => 'LMIV6\AAdvantageMember',
        'Benefits'                             => 'LMIV6\Benefits',
        'CustomerLoyaltyMember'                => 'LMIV6\CustomerLoyaltyMember',
        'EliteStatus'                          => 'LMIV6\EliteStatus',
        'EnrollmentSource'                     => 'LMIV6\EnrollmentSource',
        'FaultType'                            => 'LMIV6\FaultType',
        'ListResponseHdrStatusType'            => 'LMIV6\ListResponseHdrStatusType',
        'ListResponseHeaderType'               => 'LMIV6\ListResponseHeaderType',
        'LoyaltyMemberName'                    => 'LMIV6\LoyaltyMemberName',
        'LoyaltyProgram'                       => 'LMIV6\LoyaltyProgram',
        'LoyaltyProgramPartner'                => 'LMIV6\LoyaltyProgramPartner',
        'LoyaltyProgramTimePeriod'             => 'LMIV6\LoyaltyProgramTimePeriod',
        'MemberAccountMerger'                  => 'LMIV6\MemberAccountMerger',
        'MemberActivitySummary'                => 'LMIV6\MemberActivitySummary',
        'MemberInformationResponseStatus'      => 'LMIV6\MemberInformationResponseStatus',
        'MemberInformationRetrieveRequest'     => 'LMIV6\MemberInformationRetrieveRequest',
        'MemberInformationRetrieveRequestItem' => 'LMIV6\MemberInformationRetrieveRequestItem',
        'MemberInformationRetrieveResponse'    => 'LMIV6\MemberInformationRetrieveResponse',
        'MemberInformationRetrieveResult'      => 'LMIV6\MemberInformationRetrieveResult',
        'MemberMillionMileLevel'               => 'LMIV6\MemberMillionMileLevel',
        'MemberPartnerProgramProfile'          => 'LMIV6\MemberPartnerProgramProfile',
        'MemberSummaryByEliteQualification'    => 'LMIV6\MemberSummaryByEliteQualification',
        'MemberSummaryByExpiration'            => 'LMIV6\MemberSummaryByExpiration',
        'MemberSummaryDetail'                  => 'LMIV6\MemberSummaryDetail',
        'PartnerProgramParticipation'          => 'LMIV6\PartnerProgramParticipation',
        'PartnerStratification'                => 'LMIV6\PartnerStratification',
        'PartnerStratificationBenefits'        => 'LMIV6\PartnerStratificationBenefits',
        'ProviderSystemInfo'                   => 'LMIV6\ProviderSystemInfo',
        'RequestHeaderType'                    => 'LMIV6\RequestHeaderType',
        'ResponseHeaderType'                   => 'LMIV6\ResponseHeaderType',
        'ResponseStatusType'                   => 'LMIV6\ResponseStatusType',
        'ServiceInfoType'                      => 'LMIV6\ServiceInfoType',
        'CustomerMembership'                   => 'LMIV6\CustomerMembership', ];

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     */
    public function __construct(array $options = [], $wsdl = '/www/awardwallet/src/engine/aa/wsdl/LoyaltyMemberInformationV6.wsdl')
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
