<?php

namespace AppBundle\Model\Resources\RewardAvailability;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\Accessor;
use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;


/** @MongoDB\EmbeddedDocument */
class RewardAvailabilityResponse implements LoyaltyResponseInterface
{

    /**
     * @MongoDB\Field(type="string")
     * @Type("string")
     * @var string
     */
    private $requestId;

    /**
     * @MongoDB\Field(type="string")
     * @Type("string")
     * @var string
     */
    private $userData;

    /**
     * @MongoDB\Field(type="string")
     * @Type("string")
     * @var string
     */
    private $debugInfo;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $state;

    /**
     * @MongoDB\Field(type="string")
     * @Type("string")
     * @var string
     */
    private $message;

    /**
     * @MongoDB\Field(type="date")
     * @Type("DateTime<'Y-m-d\TH:i:sP'>")
     * @var \DateTime
     */
    private $requestDate;

    /**
     * @MongoDB\EmbedMany(targetDocument="AppBundle\Model\Resources\RewardAvailability\Route")
     * @Type("array<AppBundle\Model\Resources\RewardAvailability\Route>")
     * @Accessor(getter="getRoutesToSerialize", setter="setRoutes")
     * @var Route[]
     */
    private $routes;

    public function __construct(
        ?string $requestId = null,
        ?int $state = null,
        ?string $userData = null,
        ?\DateTime $requestDate = null
    ) {
        $this->requestId = $requestId;
        $this->state = $state;
        $this->userData = $userData;
        $this->requestDate = $requestDate;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(string $requestId): self
    {
        $this->requestId = $requestId;
        return $this;
    }

    public function getUserData(): ?string
    {
        return $this->userData;
    }

    public function setUserData(?string $userData): self
    {
        $this->userData = $userData;
        return $this;
    }

    public function getDebugInfo(): ?string
    {
        return $this->debugInfo;
    }

    public function setDebugInfo(?string $debugInfo): self
    {
        $this->debugInfo = $debugInfo;
        return $this;
    }

    public function addDebuginfo(string $debugInfo): self
    {
        if (!empty($this->debugInfo)) {
            $this->debugInfo .= "\n" . $debugInfo;
        } else {
            $this->debugInfo = $debugInfo;
        }

        return $this;
    }

    public function getState(): ?int
    {
        return $this->state;
    }

    public function setState(int $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getRequestdate(): ?\DateTime
    {
        return $this->requestDate;
    }

    public function setRequestdate(\DateTime $requestDate): self
    {
        $this->requestDate = $requestDate;
        return $this;
    }

    public function getRoutesToSerialize()
    {
        return $this->routes ? $this->routes->getValues() : [];
    }

    public function setRoutes($routes)
    {
        $this->routes = new ArrayCollection($routes);
    }

}