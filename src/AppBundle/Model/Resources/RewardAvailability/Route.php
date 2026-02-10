<?php


namespace AppBundle\Model\Resources\RewardAvailability;


use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class Route
{

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $numberOfStops;
    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $tickets;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $parsedDistance;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $awardTypes;
    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\Times")
     * @Type("AppBundle\Model\Resources\RewardAvailability\Times")
     * @var Times
     */
    private $times;
    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\Redemptions")
     * @Type("AppBundle\Model\Resources\RewardAvailability\Redemptions")
     * @var Redemptions
     */
    private $mileCost;
    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\Payments")
     * @Type("AppBundle\Model\Resources\RewardAvailability\Payments")
     * @var Payments
     */
    private $cashCost;
    /**
     * @MongoDB\EmbedMany(targetDocument="AppBundle\Model\Resources\RewardAvailability\Segment")
     * @Type("array<AppBundle\Model\Resources\RewardAvailability\Segment>")
     * @Accessor(getter="getSegmentsToSerialize", setter="setSegmentsFromSerialized")
     * @var Segment[]
     */
    private $segments;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $message;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $cabinType;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $classOfService;
    /**
     * @MongoDB\Field
     * @Type("bool")
     * @var bool
     */
    private $isFastest;
    /**
     * @Type("bool")
     * @var bool
     */
    private $isCheapest;
    /**
     * @MongoDB\Field(type="float")
     * @Type("double")
     * @var float
     */
    private $cabinPercentage;

    public function __construct(?int $numberOfStops, ?int $tickets, ?string $parsedDistance, ?string $awardTypes, Times $times, Redemptions $mileCost, Payments $cashCost, array $segments, ?string $message, ?string $classOfService)
    {
        $this->numberOfStops = $numberOfStops;
        $this->tickets = $tickets;
        $this->parsedDistance = $parsedDistance;
        $this->awardTypes = $awardTypes;
        $this->times = $times;
        $this->mileCost = $mileCost;
        $this->cashCost = $cashCost;
        $this->segments = new ArrayCollection($segments);
        $this->message = $message;
        $this->classOfService = $classOfService;
    }

    public function getSegmentsToSerialize()
    {
        return $this->segments->getValues();
    }

    public function setSegmentsFromSerialized($segments)
    {
        $this->segments = new ArrayCollection($segments);
    }

    /**
     * @return Redemptions
     */
    public function getMileCost(): Redemptions
    {
        return $this->mileCost;
    }

    /**
     * @return int
     */
    public function getNumberOfStops(): ?int
    {
        return $this->numberOfStops;
    }

    /**
     * @return int
     */
    public function getTickets(): ?int
    {
        return $this->tickets;
    }

    /**
     * @return string
     */
    public function getParsedDistance(): ?string
    {
        return $this->parsedDistance;
    }

    /**
     * @return string
     */
    public function getAwardTypes(): ?string
    {
        return $this->awardTypes;
    }

    /**
     * @return Times
     */
    public function getTimes(): Times
    {
        return $this->times;
    }

    /**
     * @return Payments
     */
    public function getCashCost(): Payments
    {
        return $this->cashCost;
    }

    /**
     * @param string $classOfService
     * @return Route
     */
    public function setClassOfService(?string $classOfService): Route
    {
        $this->classOfService = $classOfService;
        return $this;
    }

    /**
     * @param string $cabinType
     * @return Route
     */
    public function setCabinType(?string $cabinType): Route
    {
        $this->cabinType = $cabinType;
        return $this;
    }

    /**
     * @return string
     */
    public function getCabinType(): string
    {
        return $this->cabinType;
    }

    /**
     * @return float
     */
    public function getCabinPercentage(): float
    {
        return $this->cabinPercentage;
    }

    /**
     * @param float $cabinPercentage
     * @return Route
     */
    public function setCabinPercentage(?float $cabinPercentage): Route
    {
        $this->cabinPercentage = $cabinPercentage;
        return $this;
    }

    /**
     * @param bool $isFastest
     * @return Route
     */
    public function setIsFastest(?bool $isFastest): Route
    {
        $this->isFastest = $isFastest;
        return $this;
    }

    /**
     * @return bool
     */
    public function isFastest(): bool
    {
        return $this->isFastest ?? false;
    }

    /**
     * @param bool $isCheapest
     * @return Route
     */
    public function setIsCheapest(?bool $isCheapest): Route
    {
        $this->isCheapest = $isCheapest;
        return $this;
    }

}