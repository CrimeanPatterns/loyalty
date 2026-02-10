<?php

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

class LoyaltyMemberSecurityV3Service extends \CurlSoapClient
{
    /**
     * @var array The defined classes
     */
    private static $classmap = [
        'FaultType'                              => '\FaultType',
        'ListResponseHdrStatusType'              => '\ListResponseHdrStatusType',
        'ListResponseHeaderType'                 => '\ListResponseHeaderType',
        'ProviderSystemInfo'                     => '\ProviderSystemInfo',
        'RequestHeaderType'                      => '\RequestHeaderType',
        'ResponseHeaderType'                     => '\ResponseHeaderType',
        'ResponseStatusType'                     => '\ResponseStatusType',
        'ServiceInfoType'                        => '\ServiceInfoType',
        'ValidateLoyaltyCredentialsRequest'      => '\ValidateLoyaltyCredentialsRequest',
        'ValidateLoyaltyCredentialsRequestItem'  => '\ValidateLoyaltyCredentialsRequestItem',
        'LoyaltyMemberAccount'                   => '\LoyaltyMemberAccount',
        'ValidateLoyaltyCredentialsResponse'     => '\ValidateLoyaltyCredentialsResponse',
        'ValidateLoyaltyCredentialsResult'       => '\ValidateLoyaltyCredentialsResult',
        'ValidateLoyaltyCredentialsStatus'       => '\ValidateLoyaltyCredentialsStatus',
        'UpdateMemberAccountPassword'            => '\UpdateMemberAccountPassword',
        'UpdateMemberAccountPasswordRequest'     => '\UpdateMemberAccountPasswordRequest',
        'UpdateMemberAccountPasswordRequestItem' => '\UpdateMemberAccountPasswordRequestItem',
        'UpdateMemberAccountPasswordResponse'    => '\UpdateMemberAccountPasswordResponse',
        'UpdateMemberAccountPasswordResult'      => '\UpdateMemberAccountPasswordResult',
        'UpdateMemberAccountPasswordStatus'      => '\UpdateMemberAccountPasswordStatus',
        'FaultType1'                             => '\FaultType1',
        'ExtraIdData'                            => '\ExtraIdData', ];

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     */
    public function __construct(array $options = [], $wsdl = 'LoyaltyMemberSecurityV3.wsdl')
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
