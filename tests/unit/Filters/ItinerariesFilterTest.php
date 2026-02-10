<?php


namespace Tests\Unit\Filters;


use AwardWallet\Common\Itineraries\Flight;
use AwardWallet\Common\Itineraries\FlightSegment;
use AwardWallet\Common\Itineraries\HotelReservation;
use AwardWallet\Common\Itineraries\ItinerariesCollection;
use AwardWallet\Common\Itineraries\SegmentsCollection;
use AwardWallet\Common\Parsing\Filter\FlightStats\TripSegmentFilterInterface;
use AwardWallet\Common\Parsing\Filter\ItinerariesFilter;
use AwardWallet\Common\Parsing\Filter\ItineraryFilterInterface;
use Codeception\Specify;
use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Codeception\Util\Stub;
use Psr\Log\LoggerInterface;
/**
 * @backupGlobals disabled
 */
class ItinerariesFilterTest extends Unit
{
    use Specify;

    /**
     * @var ItinerariesFilter
     */
    private $itinerariesFilter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function _before()
    {
        parent::_before();
        $this->logger = Stub::makeEmpty(LoggerInterface::class);
        $this->itinerariesFilter = new ItinerariesFilter($this->logger);
    }

    public function testFilter()
    {
        $this->specify('Adding itinerary filters', function () {
            /** @var ItineraryFilterInterface $filter */
            $filter = Stub::makeEmpty(ItineraryFilterInterface::class);
            verify($this->itinerariesFilter->getItineraryFilters())->notContains($filter);
            $this->itinerariesFilter->addItineraryFilter($filter);
            verify($this->itinerariesFilter->getItineraryFilters())->contains($filter);
        });

        $this->specify('Adding segment filters', function() {
            /** @var TripSegmentFilterInterface $testFilter */
            $testFilter = Stub::makeEmpty(TripSegmentFilterInterface::class);
            verify($this->itinerariesFilter->getSegmentFilters())->notContains($testFilter);
            $this->itinerariesFilter->addSegmentFilter($testFilter);
            verify($this->itinerariesFilter->getSegmentFilters())->contains($testFilter);
        });

        $this->specify('Calling itinerary filters', function () {
            $itinerary1 = new Flight($this->logger);
            $itinerary2 = new HotelReservation($this->logger);
            $itineraries = [$itinerary1, $itinerary2];
            /** @var ItinerariesCollection $itinerariesCollection */
            $itinerariesCollection = Stub::makeEmpty(ItinerariesCollection::class, ['getCollection' => $itineraries]);
            /** @var ItineraryFilterInterface $filter */
            $filter = Stub::makeEmpty(ItineraryFilterInterface::class, ['filter' => Expected::exactly(2)], $this);
            $this->itinerariesFilter->addItineraryFilter($filter);
            $this->itinerariesFilter->filter($itinerariesCollection, '');
        });

        $this->specify('Calling segment filters', function () {
            $flightSegment1 = new FlightSegment($this->logger);
            $flightSegment2 = new FlightSegment($this->logger);
            $segments1 = new SegmentsCollection($this->logger);
            $segments1->setCollection([$flightSegment1]);
            $segments2 = new SegmentsCollection($this->logger);
            $segments2->setCollection([$flightSegment2]);
            $itinerary1 = new Flight($this->logger);
            $itinerary1->type = 'flight';
            $itinerary1->segments = $segments1;
            $itinerary2 = new Flight($this->logger);
            $itinerary2->type = 'flight';
            $itinerary2->segments = $segments2;
            $itinerary3 = new HotelReservation($this->logger);
            $itinerary4 = new Flight($this->logger);
            $itinerary4->type = 'not_a_flight';
            $itinerary4->segments = $segments2;
            $itineraries = [$itinerary1, $itinerary2, $itinerary3, $itinerary4];
            /** @var ItinerariesCollection 1$itinerariesCollection */
            $itinerariesCollection = Stub::makeEmpty(ItinerariesCollection::class, ['getCollection' => $itineraries], $this);
            /** @var TripSegmentFilterInterface $filter1 */
            $filter1 = Stub::makeEmpty(TripSegmentFilterInterface::class, ['filterTripSegment' => Expected::exactly(2)], $this);;
            /** @var TripSegmentFilterInterface $filter2 */
            $filter2 = Stub::makeEmpty(TripSegmentFilterInterface::class, ['filterTripSegment' => Expected::exactly(2)], $this);;
            $this->itinerariesFilter->addSegmentFilter($filter1);
            $this->itinerariesFilter->addSegmentFilter($filter2);
            $this->itinerariesFilter->filter($itinerariesCollection, 'testprovider');
        });
    }
}