<?php


namespace Tests\Unit\Filters;


use AwardWallet\Common\Itineraries\Address;
use AwardWallet\Common\Itineraries\FlightPoint;
use AwardWallet\Common\Itineraries\FlightSegment;
use AwardWallet\Common\Parsing\Filter\FlightStats\AirportCodeByAliasFilter;
use Codeception\Specify;
use Codeception\Test\Unit;
use Codeception\Util\Stub;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use Psr\Log\LoggerInterface;

/**
 * @backupGlobals disabled
 */
class AirportCodeByAliasFilterTest extends Unit
{
    Use Specify;

    /**
     * @var AirportCodeByAliasFilter
     */
    private $filter;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var string
     */
    private $providerCode = 'TC';
    /**
     * @var FlightSegment
     */
    private $flightSegment;

    public function setUp() : void
    {
        $this->logger = Stub::makeEmpty(LoggerInterface::class);
        $statement = Stub::makeEmpty(Statement::class, ['fetch' => ['AirportCode' => 'NEW_CODE']]);
        $this->connection = Stub::makeEmpty(Connection::class, ['executeQuery' => $statement]);
        $this->filter = new AirportCodeByAliasFilter($this->logger, $this->connection);
        $this->flightSegment = new FlightSegment($this->logger);
        $this->flightSegment->departure = new FlightPoint($this->logger);
        $this->flightSegment->arrival = new FlightPoint($this->logger);
        $this->flightSegment->departure->address = new Address($this->logger);
        $this->flightSegment->arrival->address = new Address($this->logger);
    }

    public function testFilterTripSegment()
    {
        $this->specify('Airport code is not set, should be filled in', function () {
            $this->flightSegment->departure->airportCode = '';
            $this->flightSegment->arrival->airportCode = '';
            $this->flightSegment->departure->address->text = 'TEST_TEXT';
            $this->flightSegment->arrival->address->text = 'TEST_TEXT';
            $this->filter->filterTripSegment($this->providerCode, $this->flightSegment);
            verify($this->flightSegment->departure->airportCode)->equals('NEW_CODE');
            verify($this->flightSegment->arrival->airportCode)->equals('NEW_CODE');
        });

        $this->specify('Airport code is already set, should do nothing', function () {
            $this->flightSegment->departure->airportCode = 'OLD_CODE';
            $this->flightSegment->arrival->airportCode = 'OLD_CODE';
            $this->flightSegment->departure->address->text = 'TEST_TEXT';
            $this->flightSegment->arrival->address->text = 'TEST_TEXT';
            $this->filter->filterTripSegment($this->providerCode, $this->flightSegment);
            verify($this->flightSegment->departure->airportCode)->equals('OLD_CODE');
            verify($this->flightSegment->arrival->airportCode)->equals('OLD_CODE');
        });

        $this->specify('Address text is empty, should do nothing', function () {
            $this->flightSegment->departure->airportCode = '';
            $this->flightSegment->arrival->airportCode = '';
            $this->flightSegment->departure->address->text = '';
            $this->flightSegment->arrival->address->text = '';
            $this->filter->filterTripSegment($this->providerCode, $this->flightSegment);
            verify($this->flightSegment->departure->airportCode)->isEmpty();
            verify($this->flightSegment->arrival->airportCode)->isEmpty();
        });

        $this->specify('No result from database, should do nothing', function () {
            $this->flightSegment->departure->airportCode = '';
            $this->flightSegment->arrival->airportCode = '';
            $this->flightSegment->departure->address->text = 'TEST_TEXT';
            $this->flightSegment->arrival->address->text = 'TEST_TEXT';
            $statement = Stub::makeEmpty(Statement::class, ['fetch' => false]);
            $connection = Stub::makeEmpty(Connection::class, ['executeQuery' => $statement]);
            $filter = new AirportCodeByAliasFilter($this->logger, $connection);

            $filter->filterTripSegment($this->providerCode, $this->flightSegment);
            verify($this->flightSegment->departure->airportCode)->isEmpty();
            verify($this->flightSegment->arrival->airportCode)->isEmpty();
        });
    }
}