<?php

namespace CPNRV3;

include_once 'AirSegmentTypeCodeEnum.php';

include_once 'Airline.php';

include_once 'BaseCabinClassCodeEnum.php';

include_once 'CustomerIndividual.php';

include_once 'CustomerPNRPassenger.php';

include_once 'ErrorTypeEnum.php';

include_once 'FaultType.php';

include_once 'Flight.php';

include_once 'GroupPNR.php';

include_once 'ListResponseHdrStatusCodeType.php';

include_once 'ListResponseHdrStatusMessageType.php';

include_once 'ListResponseHdrStatusType.php';

include_once 'ListResponseHeaderType.php';

include_once 'Name.php';

include_once 'NonGroupPNR.php';

include_once 'OAOperatingAirSegment.php';

include_once 'PNR.php';

include_once 'PNRAirSegment.php';

include_once 'PNRBasedLoyaltyMember.php';

include_once 'PNRBasedSpecialServiceRequest.php';

include_once 'PNRHistoryRetrieveList.php';

include_once 'PNRHostAirSegment.php';

include_once 'PNRLoyaltyMember.php';

include_once 'PNRLoyaltyMemberTransactionHistory.php';

include_once 'PNROpenAirSegment.php';

include_once 'PNROtherAirSegment.php';

include_once 'PNRPassenger.php';

include_once 'PNRPassengerAirSegment.php';

include_once 'PNRPassengerSeat.php';

include_once 'PNRPassengerSeatTransactionHistory.php';

include_once 'PNRPassengerTransactionHistory.php';

include_once 'PNRSegmentLoyaltyMember.php';

include_once 'PNRSpecialServiceRequest.php';

include_once 'PNRSpecialServiceRqstTransactionHistory.php';

include_once 'PNRStandardAirSegment.php';

include_once 'PNRTicket.php';

include_once 'PNRTicketTransactionHistory.php';

include_once 'PNRTransactionBookingActivity.php';

include_once 'PNRTravelSegment.php';

include_once 'PNRTravelSegmentTransactionHistory.php';

include_once 'PNRTypeCodeEnum.php';

include_once 'PNRUnkownSegment.php';

include_once 'PassengerTypeCodeEnum.php';

include_once 'RequestHeaderType.php';

include_once 'ResponseHeaderType.php';

include_once 'ResponseStatusCodeEnum.php';

include_once 'ResponseStatusMessageEnum.php';

include_once 'ResponseStatusType.php';

include_once 'RetrieveCustomerPNRDetailsByNameAndFlightRequest.php';

include_once 'RetrieveCustomerPNRDetailsByNameAndFlightRequestItem.php';

include_once 'RetrieveCustomerPNRDetailsByNameAndFlightResponse.php';

include_once 'RetrieveCustomerPNRDetailsByNameAndFlightResponseStatus.php';

include_once 'RetrieveCustomerPNRDetailsByNameAndFlightResult.php';

include_once 'RetrieveCustomerPNRDetailsRequest.php';

include_once 'RetrieveCustomerPNRDetailsRequestItem.php';

include_once 'RetrieveCustomerPNRDetailsResponse.php';

include_once 'RetrieveCustomerPNRDetailsResponseStatus.php';

include_once 'RetrieveCustomerPNRDetailsResult.php';

include_once 'RetrieveCustomerPNRHistoryRequest.php';

include_once 'RetrieveCustomerPNRHistoryRequestItem.php';

include_once 'RetrieveCustomerPNRHistoryResponse.php';

include_once 'RetrieveCustomerPNRHistoryResponseStatus.php';

include_once 'RetrieveCustomerPNRHistoryResult.php';

include_once 'SegmentBasedLoyaltyMember.php';

include_once 'SegmentBasedSpecialServiceRequest.php';

include_once 'SegmentStatus.php';

include_once 'SegmentTypeCodeEnum.php';

include_once 'ServiceInfoType.php';

include_once 'Station.php';

include_once 'PingRequest.php';

include_once 'PingResponse.php';

class CustomerPNRV3Service extends \CurlSoapClient
{
    /**
     * @var array The defined classes
     */
    private static $classmap = [
        'Airline'                                                 => 'CPNRV3\Airline',
        'CustomerIndividual'                                      => 'CPNRV3\CustomerIndividual',
        'CustomerPNRPassenger'                                    => 'CPNRV3\CustomerPNRPassenger',
        'FaultType'                                               => 'CPNRV3\FaultType',
        'Flight'                                                  => 'CPNRV3\Flight',
        'GroupPNR'                                                => 'CPNRV3\GroupPNR',
        'ListResponseHdrStatusType'                               => 'CPNRV3\ListResponseHdrStatusType',
        'ListResponseHeaderType'                                  => 'CPNRV3\ListResponseHeaderType',
        'Name'                                                    => 'CPNRV3\Name',
        'NonGroupPNR'                                             => 'CPNRV3\NonGroupPNR',
        'OAOperatingAirSegment'                                   => 'CPNRV3\OAOperatingAirSegment',
        'PNR'                                                     => 'CPNRV3\PNR',
        'PNRAirSegment'                                           => 'CPNRV3\PNRAirSegment',
        'PNRBasedLoyaltyMember'                                   => 'CPNRV3\PNRBasedLoyaltyMember',
        'PNRBasedSpecialServiceRequest'                           => 'CPNRV3\PNRBasedSpecialServiceRequest',
        'PNRHostAirSegment'                                       => 'CPNRV3\PNRHostAirSegment',
        'PNRLoyaltyMember'                                        => 'CPNRV3\PNRLoyaltyMember',
        'PNRLoyaltyMemberTransactionHistory'                      => 'CPNRV3\PNRLoyaltyMemberTransactionHistory',
        'PNROpenAirSegment'                                       => 'CPNRV3\PNROpenAirSegment',
        'PNROtherAirSegment'                                      => 'CPNRV3\PNROtherAirSegment',
        'PNRPassenger'                                            => 'CPNRV3\PNRPassenger',
        'PNRPassengerAirSegment'                                  => 'CPNRV3\PNRPassengerAirSegment',
        'PNRPassengerSeat'                                        => 'CPNRV3\PNRPassengerSeat',
        'PNRPassengerSeatTransactionHistory'                      => 'CPNRV3\PNRPassengerSeatTransactionHistory',
        'PNRPassengerTransactionHistory'                          => 'CPNRV3\PNRPassengerTransactionHistory',
        'PNRSegmentLoyaltyMember'                                 => 'CPNRV3\PNRSegmentLoyaltyMember',
        'PNRSpecialServiceRequest'                                => 'CPNRV3\PNRSpecialServiceRequest',
        'PNRSpecialServiceRqstTransactionHistory'                 => 'CPNRV3\PNRSpecialServiceRqstTransactionHistory',
        'PNRStandardAirSegment'                                   => 'CPNRV3\PNRStandardAirSegment',
        'PNRTicket'                                               => 'CPNRV3\PNRTicket',
        'PNRTicketTransactionHistory'                             => 'CPNRV3\PNRTicketTransactionHistory',
        'PNRTransactionBookingActivity'                           => 'CPNRV3\PNRTransactionBookingActivity',
        'PNRTravelSegment'                                        => 'CPNRV3\PNRTravelSegment',
        'PNRTravelSegmentTransactionHistory'                      => 'CPNRV3\PNRTravelSegmentTransactionHistory',
        'PNRUnkownSegment'                                        => 'CPNRV3\PNRUnkownSegment',
        'RequestHeaderType'                                       => 'CPNRV3\RequestHeaderType',
        'ResponseHeaderType'                                      => 'CPNRV3\ResponseHeaderType',
        'ResponseStatusType'                                      => 'CPNRV3\ResponseStatusType',
        'RetrieveCustomerPNRDetailsByNameAndFlightRequest'        => 'CPNRV3\RetrieveCustomerPNRDetailsByNameAndFlightRequest',
        'RetrieveCustomerPNRDetailsByNameAndFlightRequestItem'    => 'CPNRV3\RetrieveCustomerPNRDetailsByNameAndFlightRequestItem',
        'RetrieveCustomerPNRDetailsByNameAndFlightResponse'       => 'CPNRV3\RetrieveCustomerPNRDetailsByNameAndFlightResponse',
        'RetrieveCustomerPNRDetailsByNameAndFlightResponseStatus' => 'CPNRV3\RetrieveCustomerPNRDetailsByNameAndFlightResponseStatus',
        'RetrieveCustomerPNRDetailsByNameAndFlightResult'         => 'CPNRV3\RetrieveCustomerPNRDetailsByNameAndFlightResult',
        'RetrieveCustomerPNRDetailsRequest'                       => 'CPNRV3\RetrieveCustomerPNRDetailsRequest',
        'RetrieveCustomerPNRDetailsRequestItem'                   => 'CPNRV3\RetrieveCustomerPNRDetailsRequestItem',
        'RetrieveCustomerPNRDetailsResponse'                      => 'CPNRV3\RetrieveCustomerPNRDetailsResponse',
        'RetrieveCustomerPNRDetailsResponseStatus'                => 'CPNRV3\RetrieveCustomerPNRDetailsResponseStatus',
        'RetrieveCustomerPNRDetailsResult'                        => 'CPNRV3\RetrieveCustomerPNRDetailsResult',
        'RetrieveCustomerPNRHistoryRequest'                       => 'CPNRV3\RetrieveCustomerPNRHistoryRequest',
        'RetrieveCustomerPNRHistoryRequestItem'                   => 'CPNRV3\RetrieveCustomerPNRHistoryRequestItem',
        'RetrieveCustomerPNRHistoryResponse'                      => 'CPNRV3\RetrieveCustomerPNRHistoryResponse',
        'RetrieveCustomerPNRHistoryResponseStatus'                => 'CPNRV3\RetrieveCustomerPNRHistoryResponseStatus',
        'RetrieveCustomerPNRHistoryResult'                        => 'CPNRV3\RetrieveCustomerPNRHistoryResult',
        'SegmentBasedLoyaltyMember'                               => 'CPNRV3\SegmentBasedLoyaltyMember',
        'SegmentBasedSpecialServiceRequest'                       => 'CPNRV3\SegmentBasedSpecialServiceRequest',
        'SegmentStatus'                                           => 'CPNRV3\SegmentStatus',
        'ServiceInfoType'                                         => 'CPNRV3\ServiceInfoType',
        'Station'                                                 => 'CPNRV3\Station',
        'PingRequest'                                             => 'CPNRV3\PingRequest',
        'PingResponse'                                            => 'CPNRV3\PingResponse', ];

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     */
    public function __construct(array $options = [], $wsdl = 'CustomerPNRV3.wsdl')
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
     * @return RetrieveCustomerPNRDetailsResponse
     */
    public function RetrieveCustomerPNRDetails(RetrieveCustomerPNRDetailsRequest $Request)
    {
        return $this->__soapCall('RetrieveCustomerPNRDetails', [$Request]);
    }

    /**
     * @return RetrieveCustomerPNRDetailsByNameAndFlightResponse
     */
    public function RetrieveCustomerPNRDetailsByNameAndFlight(RetrieveCustomerPNRDetailsByNameAndFlightRequest $Request)
    {
        return $this->__soapCall('RetrieveCustomerPNRDetailsByNameAndFlight', [$Request]);
    }

    /**
     * @return RetrieveCustomerPNRHistoryResponse
     */
    public function RetrieveCustomerPNRTransactionHistory(RetrieveCustomerPNRHistoryRequest $request)
    {
        return $this->__soapCall('RetrieveCustomerPNRTransactionHistory', [$request]);
    }

    /**
     * @return PingResponse
     */
    public function Ping(PingRequest $Request)
    {
        return $this->__soapCall('Ping', [$Request]);
    }
}
