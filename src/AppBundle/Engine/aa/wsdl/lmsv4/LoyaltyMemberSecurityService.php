<?php

namespace LMSV4;

include_once 'ErrorTypeEnum.php';

include_once 'FaultType.php';

include_once 'ListResponseHdrStatusType.php';

include_once 'ListResponseHeaderType.php';

include_once 'ProviderSystemErrorTypeEnum.php';

include_once 'ProviderSystemInfo.php';

include_once 'RequestHeaderType.php';

include_once 'ResponseHeaderType.php';

include_once 'ResponseStatusCodeEnum.php';

include_once 'ResponseStatusMessageEnum.php';

include_once 'ResponseStatusType.php';

include_once 'ServiceInfoType.php';

include_once 'ValidateLoyaltyCredentialsRequest.php';

include_once 'ValidateLoyaltyCredentialsRequestItem.php';

include_once 'LoyaltyMemberAccount.php';

include_once 'ValidateLoyaltyCredentialsResponse.php';

include_once 'ValidateLoyaltyCredentialsResult.php';

include_once 'ValidateLoyaltyCredentialsStatus.php';

include_once 'UpdateMemberAccountPassword.php';

include_once 'UpdateMemberAccountPasswordRequest.php';

include_once 'UpdateMemberAccountPasswordRequestItem.php';

include_once 'UpdateMemberAccountPasswordResponse.php';

include_once 'UpdateMemberAccountPasswordResult.php';

include_once 'UpdateMemberAccountPasswordStatus.php';

include_once 'FaultType1.php';

include_once 'ExtraIdData.php';

class LoyaltyMemberSecurityService extends \CurlSoapClient
{
    /**
     * @var array The defined classes
     */
    private static $classmap = [
        'FaultType'                              => 'LMSV4\FaultType',
        'ListResponseHdrStatusType'              => 'LMSV4\ListResponseHdrStatusType',
        'ListResponseHeaderType'                 => 'LMSV4\ListResponseHeaderType',
        'ProviderSystemInfo'                     => 'LMSV4\ProviderSystemInfo',
        'RequestHeaderType'                      => 'LMSV4\RequestHeaderType',
        'ResponseHeaderType'                     => 'LMSV4\ResponseHeaderType',
        'ResponseStatusType'                     => 'LMSV4\ResponseStatusType',
        'ServiceInfoType'                        => 'LMSV4\ServiceInfoType',
        'ValidateLoyaltyCredentialsRequest'      => 'LMSV4\ValidateLoyaltyCredentialsRequest',
        'ValidateLoyaltyCredentialsRequestItem'  => 'LMSV4\ValidateLoyaltyCredentialsRequestItem',
        'LoyaltyMemberAccount'                   => 'LMSV4\LoyaltyMemberAccount',
        'ValidateLoyaltyCredentialsResponse'     => 'LMSV4\ValidateLoyaltyCredentialsResponse',
        'ValidateLoyaltyCredentialsResult'       => 'LMSV4\ValidateLoyaltyCredentialsResult',
        'ValidateLoyaltyCredentialsStatus'       => 'LMSV4\ValidateLoyaltyCredentialsStatus',
        'UpdateMemberAccountPassword'            => 'LMSV4\UpdateMemberAccountPassword',
        'UpdateMemberAccountPasswordRequest'     => 'LMSV4\UpdateMemberAccountPasswordRequest',
        'UpdateMemberAccountPasswordRequestItem' => 'LMSV4\UpdateMemberAccountPasswordRequestItem',
        'UpdateMemberAccountPasswordResponse'    => 'LMSV4\UpdateMemberAccountPasswordResponse',
        'UpdateMemberAccountPasswordResult'      => 'LMSV4\UpdateMemberAccountPasswordResult',
        'UpdateMemberAccountPasswordStatus'      => 'LMSV4\UpdateMemberAccountPasswordStatus',
        'FaultType1'                             => 'LMSV4\FaultType1',
        'ExtraIdData'                            => 'LMSV4\ExtraIdData', ];

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     */
    public function __construct(array $options = [], $wsdl = 'LoyaltyMemberSecurityV4.wsdl')
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
     * @return ValidateLoyaltyCredentialsResponse
     */
    public function ValidateLoyaltyCredentials(ValidateLoyaltyCredentialsRequest $request)
    {
        return $this->__soapCall('ValidateLoyaltyCredentials', [$request]);
    }

    /**
     * @return UpdateMemberAccountPasswordResponse
     */
    public function UpdateMemberAccountPassword(UpdateMemberAccountPasswordRequest $request)
    {
        return $this->__soapCall('UpdateMemberAccountPassword', [$request]);
    }
}
