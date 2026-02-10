<?php
namespace Tests\Unit;

use AppBundle\Document\CheckAccount;
use AppBundle\Extension\MQSender;
use Codeception\Example;
use Codeception\Stub;
use PhpAmqpLib\Channel\AMQPChannel;
use Psr\Log\NullLogger;

/**
 * @backupGlobals disabled
 */
class MQSenderCest
{

    /**
     * @dataProvider dumpStatisticsProvider
     */
    public function testDumpPartnerStatistic(\UnitTester $I, Example $data)
    {
        $serializer = $I->grabService("jms_serializer");
        $delayedProducer = $I->grabService("old_sound_rabbit_mq.check_delayed_producer");
        $sender = new MQSender(new NullLogger(), Stub::makeEmpty(AMQPChannel::class), $serializer, true, $delayedProducer);
        $request = $serializer->deserialize(json_encode($data['row']->getRequest()), "AppBundle\\Model\\Resources\\CheckAccountRequest", 'json');
        $sender->dumpPartnerStatistic('CheckAccount', $request, $data['row'], 0, false);
    }

    private function dumpStatisticsProvider()
    {
        $rowV1 = new CheckAccount();
        $rowV1
            ->setId('5bcdc139e7deb6001e50e581')
            ->setApiVersion(2)
            ->setRequest(array (
                'provider' => 'testprovider',
                'userId' => '2110',
                'userData' => '{"accountId":2242824,"priority":7,"source":1,"checkIts":true}',
                'priority' => 7,
                'callbackUrl' => 'http://awardwallet2.docker/api/awardwallet/loyalty/callback/account/7',
                'timeout' => 300,
                'parseItineraries' => true,
                'history' =>
                array (
                  'range' => 'complete',
                ),
                'login' => 'Itineraries.SpecialChars',
                'browserState' => '',
            ))
            ->setResponse(          array (
                'requestId' => '5bcdc139e7deb6001e50e581',
                'userData' => '{"accountId":2242824,"priority":7,"source":1,"checkIts":true}',
                'debugInfo' => '',
                'state' => 1,
                'message' => '',
                'errorReason' => '',
                'checkDate' => '2018-10-22T12:23:25+00:00',
                'requestDate' => '2018-10-22T12:23:21+00:00',
                'itineraries' =>
                    array (
                        0 =>
                        array (
                          'providerDetails' =>
                          array (
                            'confirmationNumber' => 'TESTCN',
                            'confirmationNumbers' =>
                            array (
                              0 => '12345',
                              1 => '56789',
                            ),
                            'tripNumber' => '43545-67778-3424',
                            'reservationDate' => '2014-01-01T09:00:00',
                            'name' => 'Test Provider',
                            'code' => 'testprovider',
                            'earnedAwards' => '500 miles',
                          ),
                          'totalPrice' =>
                          array (
                            'total' => 100.0,
                            'spentAwards' => '100500 miles',
                            'currencyCode' => 'USD',
                            'tax' => 7.0,
                          ),
                          'segments' =>
                          array (
                            0 =>
                            array (
                              'departure' =>
                              array (
                                'airportCode' => 'JFK',
                                'name' => 'JF Kennedy Airport',
                                'localDateTime' => '2030-01-01T10:00:00',
                                'address' =>
                                array (
                                  'text' => 'JFK',
                                  'city' => 'New York',
                                  'stateName' => 'New York',
                                  'countryName' => 'United States',
                                  'lat' => '40.642335',
                                  'lng' => '-73.78817',
                                  'timezone' => '-18000',
                                ),
                              ),
                              'arrival' =>
                              array (
                                'airportCode' => 'LAX',
                                'name' => 'Los Angeles International Airport',
                                'localDateTime' => '2030-01-01T13:55:00',
                                'address' =>
                                array (
                                  'text' => 'LAX',
                                  'city' => 'Los Angeles',
                                  'stateName' => 'California',
                                  'countryName' => 'United States',
                                  'lat' => '33.943399',
                                  'lng' => '-118.408279',
                                  'timezone' => '-25200',
                                ),
                              ),
                              'seats' =>
                              array (
                                0 => '23',
                              ),
                              'flightNumber' => '223',
                              'airlineName' => 'Delta Air Lines',
                              'bookingClass' => 'C',
                              'duration' => '3:55',
                              'stops' => 0,
                            ),
                          ),
                          'travelers' =>
                          array (
                            0 =>
                            array (
                              'fullName' => 'John Smith',
                            ),
                            1 =>
                            array (
                              'fullName' => 'Katy Smith',
                            ),
                          ),
                          'type' => 'flight',
                        ),
                      ),
                'login' => 'Itineraries.SpecialChars',
                'balance' => 10.0,
                'noItineraries' => false,
            ))
            ->setPartner('awardwallet')
            ->setMethod('account')
            ->setUpdatedate(new \DateTime('2018-10-22 12:23:25.297000'))
            ->setQueuedate(new \DateTime('2018-10-22 12:23:22.028000'))
            ->setFirstCheckDate(new \DateTime('2018-10-22 12:23:22.926000'))
            ->setParsingTime(3)
            ->setInCallbackQueue(true)
            ->setAccountId(2242824)
        ;

        $rowV2 = new CheckAccount();
        $rowV2
            ->setId('5bcdc139e7deb6001e50e581')
            ->setApiVersion(2)
            ->setRequest(array (
                'provider' => 'testprovider',
                'userId' => '2110',
                'userData' => '{"accountId":2242824,"priority":7,"source":1,"checkIts":true}',
                'priority' => 7,
                'callbackUrl' => 'http://awardwallet2.docker/api/awardwallet/loyalty/callback/account/7',
                'timeout' => 300,
                'parseItineraries' => true,
                'history' =>
                array (
                  'range' => 'complete',
                ),
                'login' => 'Itineraries.SpecialChars',
                'browserState' => '',
            ))
            ->setResponse(          array (
                'requestId' => '5bcdc139e7deb6001e50e581',
                'userData' => '{"accountId":2242824,"priority":7,"source":1,"checkIts":true}',
                'debugInfo' => '',
                'state' => 1,
                'message' => '',
                'errorReason' => '',
                'checkDate' => '2018-10-22T12:23:25+00:00',
                'requestDate' => '2018-10-22T12:23:21+00:00',
                'itineraries' =>
                array (
                  0 =>
                  array (
                    'providerInfo' =>
                    array (
                      'code' => 'testprovider',
                      'name' => 'Test Provider',
                    ),
                    'segments' =>
                    array (
                      0 =>
                      array (
                        'departure' =>
                        array (
                          'airportCode' => 'JFK',
                          'name' => 'John F. Kennedy International Airport',
                          'localDateTime' => '2018-10-23T10:00:00',
                          'address' =>
                          array (
                            'text' => 'JFK',
                            'city' => 'New York',
                            'stateName' => 'New York',
                            'countryName' => 'United States',
                            'lat' => 40.642335,
                            'lng' => -73.78817,
                            'timezone' => -18000,
                          ),
                        ),
                        'arrival' =>
                        array (
                          'airportCode' => 'LAX',
                          'name' => 'Los Angeles International Airport',
                          'localDateTime' => '2018-10-23T13:00:00',
                          'address' =>
                          array (
                            'text' => 'LAX',
                            'city' => 'Los Angeles',
                            'stateName' => 'California',
                            'countryName' => 'United States',
                            'lat' => 33.943399,
                            'lng' => -118.408279,
                            'timezone' => -25200,
                          ),
                        ),
                        'marketingCarrier' =>
                        array (
                          'airline' =>
                          array (
                            'name' => 'Super © Airlines',
                          ),
                          'flightNumber' => '5138',
                          'confirmationNumber' => 'SPLCHR',
                        ),
                        'seats' =>
                        array (
                          0 => '9C',
                        ),
                        'aircraft' =>
                        array (
                          'name' => 'Super jet ©',
                        ),
                        'cabin' => 'No Smoking',
                        'bookingCode' => 'Q',
                      ),
                    ),
                    'type' => 'flight',
                  ),
                ),
                'login' => 'Itineraries.SpecialChars',
                'balance' => 10.0,
                'noItineraries' => false,
            ))
            ->setPartner('awardwallet')
            ->setMethod('account')
            ->setUpdatedate(new \DateTime('2018-10-22 12:23:25.297000'))
            ->setQueuedate(new \DateTime('2018-10-22 12:23:22.028000'))
            ->setFirstCheckDate(new \DateTime('2018-10-22 12:23:22.926000'))
            ->setParsingTime(3)
            ->setInCallbackQueue(true)
            ->setAccountId(2242824)
        ;

        return [
            ['row' => $rowV1],
            ['row' => $rowV2]
        ];
    }

}