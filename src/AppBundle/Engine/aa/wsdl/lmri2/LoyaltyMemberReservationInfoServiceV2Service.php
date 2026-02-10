<?php

namespace LMRI2;

include_once 'RetrieveCustomerReservationList.php';

include_once 'customerReservationListRequest.php';

include_once 'ecdbAbstractRequest.php';

include_once 'requestHeader.php';

include_once 'resEnvironment.php';

include_once 'ecdbAbstractAggregateRequest.php';

include_once 'RetrieveCustomerReservationListResponse.php';

include_once 'customerReservationListResponse.php';

include_once 'ecdbAbstractResponse.php';

include_once 'customerReservationSummary.php';

include_once 'ECDBServicesSystemException.php';

include_once 'Ping.php';

include_once 'PingResponse.php';

class LoyaltyMemberReservationInfoServiceV2Service extends \CurlSoapClient
{
    /**
     * @var array The defined classes
     */
    private static $classmap = [
        'RetrieveCustomerReservationList'         => 'LMRI2\RetrieveCustomerReservationList',
        'customerReservationListRequest'          => 'LMRI2\customerReservationListRequest',
        'ecdbAbstractRequest'                     => 'LMRI2\ecdbAbstractRequest',
        'requestHeader'                           => 'LMRI2\requestHeader',
        'resEnvironment'                          => 'LMRI2\resEnvironment',
        'ecdbAbstractAggregateRequest'            => 'LMRI2\ecdbAbstractAggregateRequest',
        'RetrieveCustomerReservationListResponse' => 'LMRI2\RetrieveCustomerReservationListResponse',
        'customerReservationListResponse'         => 'LMRI2\customerReservationListResponse',
        'ecdbAbstractResponse'                    => 'LMRI2\ecdbAbstractResponse',
        'customerReservationSummary'              => 'LMRI2\customerReservationSummary',
        'ECDBServicesSystemException'             => 'LMRI2\ECDBServicesSystemException',
        'Ping'                                    => 'LMRI2\Ping',
        'PingResponse'                            => 'LMRI2\PingResponse', ];

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     */
    public function __construct(array $options = [], $wsdl = 'LoyaltyMemberReservationInfoServiceV2.wsdl')
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
     * @return RetrieveCustomerReservationListResponse
     */
    public function RetrieveCustomerReservationList(RetrieveCustomerReservationList $parameters)
    {
        return $this->__soapCall('RetrieveCustomerReservationList', [$parameters]);
    }

    /**
     * @return PingResponse
     */
    public function ping(Ping $in)
    {
        return $this->__soapCall('ping', [$in]);
    }
}
