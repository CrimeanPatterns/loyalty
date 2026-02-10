<?php

namespace AppBundle\Model\Resources\RewardAvailability\RaHotel;

use AppBundle\Extension\Loader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation as Serializer;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\Accessor;
use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;


/** @MongoDB\EmbeddedDocument */
class RaHotelResponse implements LoyaltyResponseInterface
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
     * @Serializer\Exclude()
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
     * @MongoDB\EmbedMany(targetDocument="AppBundle\Model\Resources\RewardAvailability\RaHotel\Hotel")
     * @Type("array<AppBundle\Model\Resources\RewardAvailability\RaHotel\Hotel>")
     * @Accessor(getter="getHotelsToSerialize", setter="setHotels")
     * @var Hotel[]
     */
    private $hotels;

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

    /**
     * @return string
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * @param string $requestId
     * @return RaHotelResponse
     */
    public function setRequestId(?string $requestId): RaHotelResponse
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return string
     */
    public function getUserData(): ?string
    {
        return $this->userData;
    }

    /**
     * @param string $userData
     * @return RaHotelResponse
     */
    public function setUserData(?string $userData): RaHotelResponse
    {
        $this->userData = $userData;
        return $this;
    }

    /**
     * @return string
     */
    public function getDebugInfo(): ?string
    {
        return $this->debugInfo;
    }

    /**
     * @param string $debugInfo
     * @return RaHotelResponse
     */
    public function setDebugInfo(?string $debugInfo): RaHotelResponse
    {
        $this->debugInfo = $debugInfo;
        return $this;
    }

    public function addDebuginfo(string $debugInfo): RaHotelResponse
    {
        if (!empty($this->debugInfo)) {
            $this->debugInfo .= "\n" . $debugInfo;
        } else {
            $this->debugInfo = $debugInfo;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getState(): ?int
    {
        return $this->state;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("state")
     */
    public function getStateString()
    {
        switch ($this->state) {
            case ACCOUNT_UNCHECKED:
                return 'queued_up';
            case ACCOUNT_CHECKED:
                return 'success';
            case ACCOUNT_INVALID_PASSWORD:
                return 'invalid_credentials';
            case ACCOUNT_LOCKOUT:
                return 'account_locked_out';
            case ACCOUNT_PROVIDER_ERROR:
                return 'provider_error';
            case ACCOUNT_ENGINE_ERROR:
            case ACCOUNT_QUESTION:
                return 'unknown_error';
            case ACCOUNT_WARNING:
                return 'warning';
            case ACCOUNT_TIMEOUT:
                return 'timeout';
            default:
                return null;
        }
    }

    /**
     * @param int $state
     * @return RaHotelResponse
     */
    public function setState(?int $state): RaHotelResponse
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     * @return RaHotelResponse
     */
    public function setMessage(string $message): RaHotelResponse
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getRequestDate(): ?\DateTime
    {
        return $this->requestDate;
    }

    /**
     * @param \DateTime $requestDate
     * @return RaHotelResponse
     */
    public function setRequestDate(?\DateTime $requestDate): RaHotelResponse
    {
        $this->requestDate = $requestDate;
        return $this;
    }

    public function getHotelsToSerialize()
    {
        return $this->hotels ? $this->hotels->getValues() : [];
    }

    /**
     * @param Hotel[] $hotels
     * @return RaHotelResponse
     */
    public function setHotels($hotels): RaHotelResponse
    {
        $this->hotels = new ArrayCollection($hotels);
        return $this;
    }

}