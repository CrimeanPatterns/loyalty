<?php


namespace AppBundle\Model\Resources\RewardAvailability;


use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class Segment
{

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\SegmentPoint")
     * @Type("AppBundle\Model\Resources\RewardAvailability\SegmentPoint")
     * @var SegmentPoint
     */
    private $departure;
    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\SegmentPoint")
     * @Type("AppBundle\Model\Resources\RewardAvailability\SegmentPoint")
     * @var SegmentPoint
     */
    private $arrival;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $meal;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $cabin;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $fareClass;
    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $tickets;
    /**
     * @MongoDB\Field(type="hash")
     * @Type("array<string>")
     * @var array
     */
    private $flightNumbers;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $airlineCode;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $aircraft;
    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\Times")
     * @Type("AppBundle\Model\Resources\RewardAvailability\Times")
     * @var Times
     */
    private $times;
    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $numberOfStops;
    /**
     * @MongoDB\Field(type="string")
     * @Type("string")
     * @var string
     */
    private $classOfService;

    public function __construct(SegmentPoint $departure, SegmentPoint $arrival, ?string $meal, ?string $cabin, ?string $fareClass, ?int $tickets, ?array $flightNumbers, ?string $airlineCode, ?string $aircraft, Times $times, ?int $numberOfStops, ?string $classOfService)
    {
        $this->departure = $departure;
        $this->arrival = $arrival;
        $this->meal = $meal;
        $this->cabin = $cabin;
        $this->fareClass = $fareClass;
        $this->tickets = $tickets;
        $this->flightNumbers = $flightNumbers;
        $this->airlineCode = $airlineCode;
        $this->aircraft = $aircraft;
        $this->times = $times;
        $this->numberOfStops = $numberOfStops;
        $this->classOfService = $classOfService;
    }

    /**
     * @return string
     */
    public function getAircraft(): ?string
    {
        return $this->aircraft;
    }

    /**
     * @return string
     */
    public function getAirlineCode(): ?string
    {
        return $this->airlineCode;
    }

    /**
     * @return string
     */
    public function getDepartAirport(): ?string
    {
        return $this->departure->getAirport();
    }

    /**
     * @return string
     */
    public function getArrivalAirport(): ?string
    {
        return $this->arrival->getAirport();
    }

    /**
     * @return string
     */
    public function getCabin(): ?string
    {
        return $this->cabin;
    }

    /**
     * @return Times
     */
    public function getTimes(): Times
    {
        return $this->times;
    }

    /**
     * @param string $classOfService
     * @return Segment
     */
    public function setClassOfService(?string $classOfService): Segment
    {
        $this->classOfService = $classOfService;
        return $this;
    }

}