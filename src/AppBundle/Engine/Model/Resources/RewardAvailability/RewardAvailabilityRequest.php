<?php


namespace AppBundle\Model\Resources\RewardAvailability;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation\Type;


/** @MongoDB\EmbeddedDocument */
class RewardAvailabilityRequest implements LoyaltyRequestInterface
{

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $arrival;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $cabin;

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\Departure")
     * @Type("AppBundle\Model\Resources\RewardAvailability\Departure")
     * @var Departure
     */
    private $departure;

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\Travelers")
     * @Type("AppBundle\Model\Resources\RewardAvailability\Travelers")
     * @var Travelers
     */
    private $passengers;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $currency;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $provider;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $priority;

   /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $timeout;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $userData;

    /**
     * @MongoDB\Field(type="string")
     * @Type("string")
     * @var string
     */
    private $callbackUrl;

    public function getArrival(): ?string
    {
        return $this->arrival;
    }

    public function setArrival(string $arrival): self
    {
        $this->arrival = $arrival;
        return $this;
    }

    public function getCabin(): ?string
    {
        return $this->cabin;
    }

    public function setCabin(string $cabin): self
    {
        $this->cabin = $cabin;
        return $this;
    }

    public function getUserData(): ?string
    {
        return $this->userData;
    }

    public function setUserData(string $userData)
    {
        $this->userData = $userData;
        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    public function setCallbackUrl(string $callbackUrl)
    {
        $this->callbackUrl = $callbackUrl;
        return $this;
    }

    public function getDeparture(): Departure
    {
        return $this->departure;
    }

    public function setDeparture(Departure $departure): self
    {
        $this->departure = $departure;
        return $this;
    }

    public function getPassengers(): Travelers
    {
        return $this->passengers;
    }

    public function setPassengers(Travelers $passengers): self
    {
        $this->passengers = $passengers;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout ?? 85; // -5 seconds to complete the parse: default value 90 - for juicy miles (was 120)
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function getLogContext(): array
    {
        return [];
    }

    public function getUserId()
    {
        return null;
    }

}