<?php

namespace CPNRV5_1;

include_once 'Airline.php';

include_once 'CustomerIndividual.php';

include_once 'CustomerPNRPassenger.php';

include_once 'FaultType.php';

include_once 'Flight.php';

include_once 'GroupPNR.php';

include_once 'ListResponseHdrStatusType.php';

include_once 'ListResponseHeaderType.php';

include_once 'Name.php';

include_once 'NonGroupPNR.php';

include_once 'OAOperatingAirSegment.php';

include_once 'PNR.php';

include_once 'PNRAirSegment.php';

include_once 'PNRBasedLoyaltyMember.php';

include_once 'PNRBasedSpecialServiceRequest.php';

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

include_once 'PNRPhones.php';

include_once 'PNRPhone.php';

include_once 'PNRTicket.php';

include_once 'PNRTicketTransactionHistory.php';

include_once 'PNRTransactionBookingActivity.php';

include_once 'PNRTravelSegment.php';

include_once 'PNRTravelSegmentTransactionHistory.php';

include_once 'PNRUnkownSegment.php';

include_once 'RequestHeaderType.php';

include_once 'ResponseHeaderType.php';

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

include_once 'PNRHistorySegmentFilter.php';

include_once 'RetrieveCustomerPNRHistoryResponse.php';

include_once 'RetrieveCustomerPNRHistoryResponseStatus.php';

include_once 'RetrieveCustomerPNRHistoryResult.php';

include_once 'SegmentBasedLoyaltyMember.php';

include_once 'SegmentBasedSpecialServiceRequest.php';

include_once 'SegmentStatus.php';

include_once 'ServiceInfoType.php';

include_once 'Station.php';

include_once 'PingRequest.php';

include_once 'PingResponse.php';

class CustomerPNRService extends \CurlSoapClient
{
    /**
     * @var array The defined classes
     */
    private static $classmap = [
        'Airline'                                                 => 'CPNRV5_1\Airline',
        'CustomerIndividual'                                      => 'CPNRV5_1\CustomerIndividual',
        'CustomerPNRPassenger'                                    => 'CPNRV5_1\CustomerPNRPassenger',
        'FaultType'                                               => 'CPNRV5_1\FaultType',
        'Flight'                                                  => 'CPNRV5_1\Flight',
        'GroupPNR'                                                => 'CPNRV5_1\GroupPNR',
        'ListResponseHdrStatusType'                               => 'CPNRV5_1\ListResponseHdrStatusType',
        'ListResponseHeaderType'                                  => 'CPNRV5_1\ListResponseHeaderType',
        'Name'                                                    => 'CPNRV5_1\Name',
        'NonGroupPNR'                                             => 'CPNRV5_1\NonGroupPNR',
        'OAOperatingAirSegment'                                   => 'CPNRV5_1\OAOperatingAirSegment',
        'PNR'                                                     => 'CPNRV5_1\PNR',
        'PNRAirSegment'                                           => 'CPNRV5_1\PNRAirSegment',
        'PNRBasedLoyaltyMember'                                   => 'CPNRV5_1\PNRBasedLoyaltyMember',
        'PNRBasedSpecialServiceRequest'                           => 'CPNRV5_1\PNRBasedSpecialServiceRequest',
        'PNRHostAirSegment'                                       => 'CPNRV5_1\PNRHostAirSegment',
        'PNRLoyaltyMember'                                        => 'CPNRV5_1\PNRLoyaltyMember',
        'PNRLoyaltyMemberTransactionHistory'                      => 'CPNRV5_1\PNRLoyaltyMemberTransactionHistory',
        'PNROpenAirSegment'                                       => 'CPNRV5_1\PNROpenAirSegment',
        'PNROtherAirSegment'                                      => 'CPNRV5_1\PNROtherAirSegment',
        'PNRPassenger'                                            => 'CPNRV5_1\PNRPassenger',
        'PNRPassengerAirSegment'                                  => 'CPNRV5_1\PNRPassengerAirSegment',
        'PNRPassengerSeat'                                        => 'CPNRV5_1\PNRPassengerSeat',
        'PNRPassengerSeatTransactionHistory'                      => 'CPNRV5_1\PNRPassengerSeatTransactionHistory',
        'PNRPassengerTransactionHistory'                          => 'CPNRV5_1\PNRPassengerTransactionHistory',
        'PNRSegmentLoyaltyMember'                                 => 'CPNRV5_1\PNRSegmentLoyaltyMember',
        'PNRSpecialServiceRequest'                                => 'CPNRV5_1\PNRSpecialServiceRequest',
        'PNRSpecialServiceRqstTransactionHistory'                 => 'CPNRV5_1\PNRSpecialServiceRqstTransactionHistory',
        'PNRStandardAirSegment'                                   => 'CPNRV5_1\PNRStandardAirSegment',
        'PNRPhones'                                               => 'CPNRV5_1\PNRPhones',
        'PNRPhone'                                                => 'CPNRV5_1\PNRPhone',
        'PNRTicket'                                               => 'CPNRV5_1\PNRTicket',
        'PNRTicketTransactionHistory'                             => 'CPNRV5_1\PNRTicketTransactionHistory',
        'PNRTransactionBookingActivity'                           => 'CPNRV5_1\PNRTransactionBookingActivity',
        'PNRTravelSegment'                                        => 'CPNRV5_1\PNRTravelSegment',
        'PNRTravelSegmentTransactionHistory'                      => 'CPNRV5_1\PNRTravelSegmentTransactionHistory',
        'PNRUnkownSegment'                                        => 'CPNRV5_1\PNRUnkownSegment',
        'RequestHeaderType'                                       => 'CPNRV5_1\RequestHeaderType',
        'ResponseHeaderType'                                      => 'CPNRV5_1\ResponseHeaderType',
        'ResponseStatusType'                                      => 'CPNRV5_1\ResponseStatusType',
        'RetrieveCustomerPNRDetailsByNameAndFlightRequest'        => 'CPNRV5_1\RetrieveCustomerPNRDetailsByNameAndFlightRequest',
        'RetrieveCustomerPNRDetailsByNameAndFlightRequestItem'    => 'CPNRV5_1\RetrieveCustomerPNRDetailsByNameAndFlightRequestItem',
        'RetrieveCustomerPNRDetailsByNameAndFlightResponse'       => 'CPNRV5_1\RetrieveCustomerPNRDetailsByNameAndFlightResponse',
        'RetrieveCustomerPNRDetailsByNameAndFlightResponseStatus' => 'CPNRV5_1\RetrieveCustomerPNRDetailsByNameAndFlightResponseStatus',
        'RetrieveCustomerPNRDetailsByNameAndFlightResult'         => 'CPNRV5_1\RetrieveCustomerPNRDetailsByNameAndFlightResult',
        'RetrieveCustomerPNRDetailsRequest'                       => 'CPNRV5_1\RetrieveCustomerPNRDetailsRequest',
        'RetrieveCustomerPNRDetailsRequestItem'                   => 'CPNRV5_1\RetrieveCustomerPNRDetailsRequestItem',
        'RetrieveCustomerPNRDetailsResponse'                      => 'CPNRV5_1\RetrieveCustomerPNRDetailsResponse',
        'RetrieveCustomerPNRDetailsResponseStatus'                => 'CPNRV5_1\RetrieveCustomerPNRDetailsResponseStatus',
        'RetrieveCustomerPNRDetailsResult'                        => 'CPNRV5_1\RetrieveCustomerPNRDetailsResult',
        'RetrieveCustomerPNRHistoryRequest'                       => 'CPNRV5_1\RetrieveCustomerPNRHistoryRequest',
        'RetrieveCustomerPNRHistoryRequestItem'                   => 'CPNRV5_1\RetrieveCustomerPNRHistoryRequestItem',
        'PNRHistorySegmentFilter'                                 => 'CPNRV5_1\PNRHistorySegmentFilter',
        'RetrieveCustomerPNRHistoryResponse'                      => 'CPNRV5_1\RetrieveCustomerPNRHistoryResponse',
        'RetrieveCustomerPNRHistoryResponseStatus'                => 'CPNRV5_1\RetrieveCustomerPNRHistoryResponseStatus',
        'RetrieveCustomerPNRHistoryResult'                        => 'CPNRV5_1\RetrieveCustomerPNRHistoryResult',
        'SegmentBasedLoyaltyMember'                               => 'CPNRV5_1\SegmentBasedLoyaltyMember',
        'SegmentBasedSpecialServiceRequest'                       => 'CPNRV5_1\SegmentBasedSpecialServiceRequest',
        'SegmentStatus'                                           => 'CPNRV5_1\SegmentStatus',
        'ServiceInfoType'                                         => 'CPNRV5_1\ServiceInfoType',
        'Station'                                                 => 'CPNRV5_1\Station',
        'PingRequest'                                             => 'CPNRV5_1\PingRequest',
        'PingResponse'                                            => 'CPNRV5_1\PingResponse', ];

    /**
     * @param array $options A array of config values
     * @param string $wsdl The wsdl file to use
     */
    public function __construct(array $options = [], $wsdl = 'CustomerPNRV5_1.wsdl')
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
