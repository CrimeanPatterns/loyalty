<?php


namespace Tests\Unit\Filters;


use AwardWallet\Common\FlightStats\Airport;
use AwardWallet\Common\FlightStats\Cache;
use AwardWallet\Common\FlightStats\Communicator;
use AwardWallet\Common\FlightStats\CommunicatorCallException;
use AwardWallet\Common\FlightStats\Schedule;
use AwardWallet\Common\FlightStats\ScheduleAppendix;
use AwardWallet\Common\FlightStats\ScheduledFlight;
use AwardWallet\Common\Itineraries\FlightPoint;
use AwardWallet\Common\Itineraries\FlightSegment;
use AwardWallet\Common\Parsing\Filter\FlightStats\AirportCodeByFlightStatsFilter;
use AwardWallet\Common\Parsing\Filter\FlightStats\ScheduledFlightConverter;
use Codeception\Specify;
use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Codeception\Util\Stub;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @backupGlobals disabled
 */
class AirportCodeByFlightStatsFilterTest extends Unit
{
    Use Specify;

    const DEP_DATE = '2017-06-15T04:45:00.000';

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Communicator
     */
    private $communicator;
    /**
     * @var AirportCodeByFlightStatsFilter
     */
    private $filter;

    public function setUp() : void
    {
        $departureAirport = Stub::makeEmpty(Airport::class, ['getIata' => 'DA']);
        $arrivalAirport = Stub::makeEmpty(Airport::class, ['getIata' => 'AA']);
        $flight = Stub::makeEmpty(ScheduledFlight::class, ['getDepartureAirport' => $departureAirport, 'getArrivalAirport' => $arrivalAirport, 'getDepartureTime' => self::DEP_DATE]);
        $schedule = Stub::makeEmpty(Schedule::class, ['getScheduledFlights' => [$flight], 'getAppendix' => new ScheduleAppendix([], [], [])]);
        $this->logger = Stub::makeEmpty(LoggerInterface::class);
        $this->communicator = Stub::makeEmpty(Communicator::class, ['getScheduleByCarrierFNAndDepartureDate' => $schedule]);
        $this->filter = new AirportCodeByFlightStatsFilter($this->logger, $this->communicator, new ScheduledFlightConverter($this->logger));
    }

    public function testFilterTripSegment()
    {
        $this->specify('Airport codes are empty, should fill them', function () {
            $departure = new FlightPoint($this->logger);
            $arrival = new FlightPoint($this->logger);
            $departure->airportCode = '';
            $departure->localDateTime = self::DEP_DATE;
            $arrival->airportCode = '';
            /** @var FlightSegment $flightSegment */
            $flightSegment = Stub::make(FlightSegment::class, ['flightNumber' => '1234', 'departure' => $departure, 'arrival' => $arrival, 'airlineName' => 'DL']);
            $this->filter->filterTripSegment('', $flightSegment);
            verify($flightSegment->departure->airportCode)->equals('DA');
            verify($flightSegment->arrival->airportCode)->equals('AA');
        });

        $this->specify('Arrival airport code is empty, should only fill it', function () {
            $departure = new FlightPoint($this->logger);
            $arrival = new FlightPoint($this->logger);
            $departure->airportCode = 'OLD_VALUE';
            $departure->localDateTime = self::DEP_DATE;
            $arrival->airportCode = '';
            /** @var FlightSegment $flightSegment */
            $flightSegment = Stub::make(FlightSegment::class, ['departure' => $departure, 'arrival' => $arrival, 'airlineName' => 'DL']);
            $this->filter->filterTripSegment('', $flightSegment);
            verify($flightSegment->departure->airportCode)->equals('OLD_VALUE');
            verify($flightSegment->arrival->airportCode)->equals('AA');
        });

        $this->specify('Departure airport code is empty, should only fill it', function () {
            $departure = new FlightPoint($this->logger);
            $arrival = new FlightPoint($this->logger);
            $departure->airportCode = '';
            $departure->localDateTime = self::DEP_DATE;
            $arrival->airportCode = 'OLD_VALUE';
            /** @var FlightSegment $flightSegment */
            $flightSegment = Stub::make(FlightSegment::class, ['departure' => $departure, 'arrival' => $arrival, 'airlineName' => 'DL']);
            $this->filter->filterTripSegment('', $flightSegment);
            verify($flightSegment->departure->airportCode)->equals('DA');
            verify($flightSegment->arrival->airportCode)->equals('OLD_VALUE');
        });

        $this->specify('Departure code differs from the one returned from flight stats, should log a notice', function () {
            $this->logger = Stub::makeEmpty(LoggerInterface::class, ['notice' => Expected::atLeastOnce()], $this);
            $this->filter = new AirportCodeByFlightStatsFilter($this->logger, $this->communicator, new ScheduledFlightConverter($this->logger));
            $departure = new FlightPoint($this->logger);
            $arrival = new FlightPoint($this->logger);
            $departure->airportCode = 'WRONG';
            $departure->localDateTime = self::DEP_DATE;
            $arrival->airportCode = '';
            /** @var FlightSegment $flightSegment */
            $flightSegment = Stub::make(FlightSegment::class, ['departure' => $departure, 'arrival' => $arrival, 'airlineName' => 'DL']);
            $this->filter->filterTripSegment('', $flightSegment);
            verify($flightSegment->departure->airportCode)->equals('WRONG');
            verify($flightSegment->arrival->airportCode)->equals('AA');
        });

        $this->specify('Communicator failure, should do nothing', function () {
            $http = Stub::makeEmpty(\HttpDriverInterface::class, [
                'request' => function(){ $result = new \HttpDriverResponse('400 error'); $result->httpCode = 400; return $result; },
            ]);
            $communicator = new Communicator($http, new NullLogger(), Stub::makeEmpty(SerializerInterface::class), Stub::makeEmpty(\Memcached::class), Stub::makeEmpty(EventDispatcher::class), Stub::makeEmpty(Cache::class), 'xxx', 'yyy');
            $this->filter = new AirportCodeByFlightStatsFilter($this->logger, $communicator, new ScheduledFlightConverter($this->logger));
            $departure = new FlightPoint($this->logger);
            $arrival = new FlightPoint($this->logger);
            $departure->airportCode = '';
            $departure->localDateTime = self::DEP_DATE;
            $arrival->airportCode = '';
            /** @var FlightSegment $flightSegment */
            $flightSegment = Stub::make(FlightSegment::class, ['departure' => $departure, 'arrival' => $arrival, 'airlineName' => 'DL']);
            $this->filter->filterTripSegment('', $flightSegment);
            verify($flightSegment->departure->airportCode)->isEmpty();
            verify($flightSegment->arrival->airportCode)->isEmpty();
        });
    }
}