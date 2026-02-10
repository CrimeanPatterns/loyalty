<?php

namespace LMIV3;

include_once 'tTimestampFault.php';

include_once 'AttributedDateTime.php';

include_once 'AttributedURI.php';

include_once 'TimestampType.php';

include_once 'AttributedString.php';

include_once 'PasswordString.php';

include_once 'EncodedString.php';

include_once 'UsernameTokenType.php';

include_once 'BinarySecurityTokenType.php';

include_once 'KeyIdentifierType.php';

include_once 'ReferenceType.php';

include_once 'EmbeddedType.php';

include_once 'SecurityTokenReferenceType.php';

include_once 'SecurityHeaderType.php';

include_once 'TransformationParametersType.php';

include_once 'FaultcodeEnum.php';

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

class LoyaltyMemberInformationV3Service extends \TExtSoapClient
{
    /**
     * @var array The defined classes
     */
    private static $classmap = [
        'AttributedDateTime'                   => 'LMIV3\AttributedDateTime',
        'AttributedURI'                        => 'LMIV3\AttributedURI',
        'TimestampType'                        => 'LMIV3\TimestampType',
        'AttributedString'                     => 'LMIV3\AttributedString',
        'PasswordString'                       => 'LMIV3\PasswordString',
        'EncodedString'                        => 'LMIV3\EncodedString',
        'UsernameTokenType'                    => 'LMIV3\UsernameTokenType',
        'BinarySecurityTokenType'              => 'LMIV3\BinarySecurityTokenType',
        'KeyIdentifierType'                    => 'LMIV3\KeyIdentifierType',
        'ReferenceType'                        => 'LMIV3\ReferenceType',
        'EmbeddedType'                         => 'LMIV3\EmbeddedType',
        'SecurityTokenReferenceType'           => 'LMIV3\SecurityTokenReferenceType',
        'SecurityHeaderType'                   => 'LMIV3\SecurityHeaderType',
        'TransformationParametersType'         => 'LMIV3\TransformationParametersType',
        'AAdvantageAccountEnrollment'          => 'LMIV3\AAdvantageAccountEnrollment',
        'AAdvantageMember'                     => 'LMIV3\AAdvantageMember',
        'Benefits'                             => 'LMIV3\Benefits',
        'CustomerLoyaltyMember'                => 'LMIV3\CustomerLoyaltyMember',
        'EliteStatus'                          => 'LMIV3\EliteStatus',
        'EnrollmentSource'                     => 'LMIV3\EnrollmentSource',
        'FaultType'                            => 'LMIV3\FaultType',
        'ListResponseHdrStatusType'            => 'LMIV3\ListResponseHdrStatusType',
        'ListResponseHeaderType'               => 'LMIV3\ListResponseHeaderType',
        'LoyaltyMemberName'                    => 'LMIV3\LoyaltyMemberName',
        'LoyaltyProgram'                       => 'LMIV3\LoyaltyProgram',
        'LoyaltyProgramPartner'                => 'LMIV3\LoyaltyProgramPartner',
        'LoyaltyProgramTimePeriod'             => 'LMIV3\LoyaltyProgramTimePeriod',
        'MemberAccountMerger'                  => 'LMIV3\MemberAccountMerger',
        'MemberActivitySummary'                => 'LMIV3\MemberActivitySummary',
        'MemberInformationResponseStatus'      => 'LMIV3\MemberInformationResponseStatus',
        'MemberInformationRetrieveRequest'     => 'LMIV3\MemberInformationRetrieveRequest',
        'MemberInformationRetrieveRequestItem' => 'LMIV3\MemberInformationRetrieveRequestItem',
        'MemberInformationRetrieveResponse'    => 'LMIV3\MemberInformationRetrieveResponse',
        'MemberInformationRetrieveResult'      => 'LMIV3\MemberInformationRetrieveResult',
        'MemberMillionMileLevel'               => 'LMIV3\MemberMillionMileLevel',
        'MemberPartnerProgramProfile'          => 'LMIV3\MemberPartnerProgramProfile',
        'MemberSummaryByEliteQualification'    => 'LMIV3\MemberSummaryByEliteQualification',
        'MemberSummaryByExpiration'            => 'LMIV3\MemberSummaryByExpiration',
        'MemberSummaryDetail'                  => 'LMIV3\MemberSummaryDetail',
        'PartnerProgramParticipation'          => 'LMIV3\PartnerProgramParticipation',
        'PartnerStratification'                => 'LMIV3\PartnerStratification',
        'PartnerStratificationBenefits'        => 'LMIV3\PartnerStratificationBenefits',
        'ProviderSystemInfo'                   => 'LMIV3\ProviderSystemInfo',
        'RequestHeaderType'                    => 'LMIV3\RequestHeaderType',
        'ResponseHeaderType'                   => 'LMIV3\ResponseHeaderType',
        'ResponseStatusType'                   => 'LMIV3\ResponseStatusType',
        'ServiceInfoType'                      => 'LMIV3\ServiceInfoType',
        'CustomerMembership'                   => 'LMIV3\CustomerMembership', ];

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     */
    public function __construct(array $options = [], $wsdl = 'LoyaltyMemberInformationV3.wsdl')
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
