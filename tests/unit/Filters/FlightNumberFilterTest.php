<?php


namespace Tests\Unit\Filters;


use AwardWallet\Common\FlightStats\Cache;
use AwardWallet\Common\FlightStats\Schedule;
use AwardWallet\Common\FlightStats\ScheduleAppendix;
use AwardWallet\Common\FlightStats\ScheduledFlight;
use AwardWallet\Common\Itineraries\FlightPoint;
use AwardWallet\Common\Itineraries\FlightSegment;
use AwardWallet\Common\FlightStats\Communicator;
use AwardWallet\Common\FlightStats\CommunicatorCallException;
use AwardWallet\Common\Parsing\Filter\FlightStats\FlightNumberFilter;
use AwardWallet\Common\Parsing\Filter\FlightStats\ScheduledFlightConverter;
use Codeception\Module\Symfony;
use Codeception\Specify;
use Codeception\Test\Unit;
use Codeception\Util\Stub;
use JMS\Serializer\Serializer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @backupGlobals disabled
 */
class FlightNumberFilterTest extends Unit
{
    use Specify;

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var FlightSegment
     */
    private $flightSegment;


    public function setUp() : void
    {
        $this->logger = Stub::makeEmpty(LoggerInterface::class);
        $this->flightSegment = new FlightSegment($this->logger);
        $this->flightSegment->departure = new FlightPoint($this->logger);
        $this->flightSegment->arrival = new FlightPoint($this->logger);
        $this->flightSegment->departure->airportCode = 'AAA';
        $this->flightSegment->arrival->airportCode = 'BBB';
        $this->flightSegment->departure->localDateTime = 'some date time';
    }

    public function testFilterTripSegment()
    {

        $this->specify('Empty flight number in flight segment, should be filled in', function () {
            $flight = Stub::makeEmpty(ScheduledFlight::class, ['getFlightNumber' => '100']);
            $schedule = Stub::makeEmpty(Schedule::class, ['getScheduledFlights' => [$flight], 'getAppendix' => new ScheduleAppendix([], [], [])]);
            $communicator = Stub::makeEmpty(Communicator::class, ['getScheduleByRouteAndDate' => $schedule]);
            $flightNumberFilter = new FlightNumberFilter($this->logger, $communicator, new ScheduledFlightConverter($this->logger));
            $flightNumberFilter->filterTripSegment(null, $this->flightSegment);
            verify($this->flightSegment->flightNumber)->equals('100');
        });

        $this->specify('Already have flight number, leave it', function() {
            $flight = Stub::makeEmpty(ScheduledFlight::class, ['getFlightNumber' => '100']);
            $schedule = Stub::makeEmpty(Schedule::class, ['getScheduledFlights' => [$flight], 'getAppendix' => new ScheduleAppendix([], [], [])]);
            $communicator = Stub::makeEmpty(Communicator::class, ['getScheduleByRouteAndDate' => $schedule]);
            $flightNumberFilter = new FlightNumberFilter($this->logger, $communicator, new ScheduledFlightConverter($this->logger));
            $this->flightSegment->flightNumber = '150';
            $flightNumberFilter->filterTripSegment(null, $this->flightSegment);
            verify($this->flightSegment->flightNumber)->equals('150');
        });

        $this->specify('Failed to get a new flight number, should do nothing', function () {
            $communicator = Stub::makeEmpty(Communicator::class, ['getScheduleByRouteAndDate' => function () { return null; }]);
            $flightNumberFilter = new FlightNumberFilter($this->logger, $communicator, new ScheduledFlightConverter($this->logger));
            $flightNumberFilter->filterTripSegment(null, $this->flightSegment);
            verify($this->flightSegment->flightNumber)->isEmpty();
        });
    }

    public function testFullData()
    {
        $this->specify("Flight filter should fill all params, not only flight number", function(){
            $response = str_replace('2017-03-11', date('Y-m-d'), file_get_contents(__DIR__ . '/../../_data/ScheduleByRouteAndDate.json'));
            $http = Stub::makeEmpty(\HttpDriverInterface::class, [
                'request' => new \HttpDriverResponse($response)
            ]);
            /** @var Symfony $symfony */
            $symfony = $this->getModule('Symfony');
            /** @var Serializer $serializer */
            $serializer = $symfony->grabService('jms_serializer');
            $communicator = new Communicator($http, $this->logger, $serializer, Stub::makeEmpty(\Memcached::class), Stub::makeEmpty(EventDispatcher::class), Stub::makeEmpty(Cache::class), 'xxx', 'yyy');

            $this->flightSegment->departure->localDateTime = date('Y-m-d') . 'T21:26:00.000';

            $flightNumberFilter = new FlightNumberFilter($this->logger, $communicator, new ScheduledFlightConverter($this->logger));
            $flightNumberFilter->filterTripSegment(null, $this->flightSegment);

            verify($this->flightSegment->flightNumber)->equals('626');
            verify($this->flightSegment->stops)->equals('0');
            verify($this->flightSegment->airlineName)->equals('American Airlines');
            verify($this->flightSegment->departure->terminal)->equals('4');
            verify($this->flightSegment->arrival->terminal)->equals('1');
            verify($this->flightSegment->aircraft)->equals('Airbus A321');
        });
    }

}