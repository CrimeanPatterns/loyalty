<?php


namespace Tests\Unit\Filters;


use AwardWallet\Common\Itineraries\Address;
use AwardWallet\Common\Itineraries\FlightPoint;
use AwardWallet\Common\Itineraries\FlightSegment;
use AwardWallet\Common\Parsing\Filter\FlightStats\AirportCodeFilter;
use Codeception\Specify;
use Codeception\Util\Stub;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;

/**
 * @backupGlobals disabled
 */
class AirportCodeFilterTest extends TestCase
{
    use Specify;

    /**
     * @var LoggerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;
    /**
     * @var Connection|PHPUnit_Framework_MockObject_MockObject
     */
    private $connection;
    /**
     * @var AirportCodeFilter
     */
    private $airportCodeFilter;
    /**
     * @var FlightSegment
     */
    private $flightSegment;
    /**
     * @var Statement
     */
    private $statement;

    public function setUp() : void
    {
        $this->logger = Stub::makeEmpty(LoggerInterface::class);
        $this->statement = Stub::makeEmpty(Statement::class, ['fetchAll' => [0 => ['AirCode' => 'NEW_CODE']]]);
        $this->connection = Stub::makeEmpty(Connection::class, ['executeQuery' => $this->statement]);
        $this->airportCodeFilter = new AirportCodeFilter($this->logger, $this->connection);
        $this->flightSegment = new FlightSegment($this->logger);
        $this->flightSegment->departure = new FlightPoint($this->logger);
        $this->flightSegment->arrival = new FlightPoint($this->logger);
        $this->flightSegment->departure->address = new Address($this->logger);
        $this->flightSegment->arrival->address = new Address($this->logger);
        $this->flightSegment->departure->address->text = 'Logan International (MA)';
        $this->flightSegment->arrival->address->text = 'Heathrow (London)';
    }
    public function testFilterTripSegment()
    {
        $this->specify('Airport code is being updated', function() {
            $this->airportCodeFilter->filterTripSegment(null, $this->flightSegment);
            verify($this->flightSegment->departure->airportCode)->equals('NEW_CODE');
            verify($this->flightSegment->arrival->airportCode)->equals('NEW_CODE');
        });

        $this->specify('Already have airport codes, should not be updated', function() {
            $this->flightSegment->departure->airportCode = 'OLD_CODE';
            $this->flightSegment->arrival->airportCode = 'OLD_CODE';
            $this->airportCodeFilter->filterTripSegment(null, $this->flightSegment);
            verify($this->flightSegment->departure->airportCode)->equals('OLD_CODE');
            verify($this->flightSegment->arrival->airportCode)->equals('OLD_CODE');
        });

        $this->specify('Unparsable address text, should do nothing', function() {
            $this->flightSegment->departure->address->text = '(()s034holS(8u3gbSD*OFS&#&@!*((*(';
            $this->flightSegment->arrival->address->text = '(()s034holS(8u3gbSD*OFS&#&@!*((*(';
            $this->airportCodeFilter->filterTripSegment(null, $this->flightSegment);
            verify($this->flightSegment->departure->airportCode)->isEmpty();
            verify($this->flightSegment->arrival->airportCode)->isEmpty();
        });

        $this->specify('No results from database, should do nothing', function () {
            $this->statement = Stub::makeEmpty(Statement::class, ['fetchAll' => []]);
            $this->connection = Stub::make(Connection::class, ['executeQuery' => $this->statement]);
            $this->airportCodeFilter = new AirportCodeFilter($this->logger, $this->connection);
            $this->airportCodeFilter->filterTripSegment(null, $this->flightSegment);
            verify($this->flightSegment->departure->airportCode)->isEmpty();
            verify($this->flightSegment->arrival->airportCode)->isEmpty();
        });

        $this->specify('2 results from database, should do nothing', function () {
            $this->statement = Stub::makeEmpty(Statement::class, ['fetchAll' => [1 => [], 2 => []]]);
            $this->connection = Stub::make(Connection::class, ['executeQuery' => $this->statement]);
            $this->airportCodeFilter = new AirportCodeFilter($this->logger, $this->connection);
            $this->airportCodeFilter->filterTripSegment(null, $this->flightSegment);
            verify($this->flightSegment->departure->airportCode)->isEmpty();
            verify($this->flightSegment->arrival->airportCode)->isEmpty();
        });
    }
}