<?php


namespace AppBundle\Model\Resources\RewardAvailability\RaHotel;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation\Type;

/** @MongoDB\EmbeddedDocument */
class RaHotelRequest implements LoyaltyRequestInterface
{

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $provider;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $destination;

    /**
     * @MongoDB\Field(type="date")
     * @Type("DateTime<'Y-m-d', '', '!Y-m-d'>")
     * @var \DateTime
     */
    private $checkInDate;

    /**
     * @MongoDB\Field(type="date")
     * @Type("DateTime<'Y-m-d', '', '!Y-m-d'>")
     * @var \DateTime
     */
    private $checkOutDate;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $numberOfRooms;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $numberOfAdults;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $numberOfKids;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $priority;

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

    /**
     * @MongoDB\Field(type="bool")
     * @Type("boolean")
     * @var boolean
     */
    private $downloadPreview;

    /**
     * @return bool
     */
    public function isDownloadPreview(): bool
    {
        return $this->downloadPreview ?? false;
    }

    /**
     * @param bool $downloadPreview
     * @return RaHotelRequest
     */
    public function setDownloadPreview(bool $downloadPreview): RaHotelRequest
    {
        $this->downloadPreview = $downloadPreview;
        return $this;
    }

    /**
     * @return string
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * @param string $provider
     * @return RaHotelRequest
     */
    public function setProvider(string $provider): RaHotelRequest
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * @return string
     */
    public function getDestination(): string
    {
        return $this->destination;
    }

    /**
     * @param string $destination
     * @return RaHotelRequest
     */
    public function setDestination(string $destination): RaHotelRequest
    {
        $this->destination = $destination;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCheckInDate(): \DateTime
    {
        return $this->checkInDate;
    }

    /**
     * @param \DateTime $checkInDate
     * @return RaHotelRequest
     */
    public function setCheckInDate(\DateTime $checkInDate): RaHotelRequest
    {
        $this->checkInDate = $checkInDate;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCheckOutDate(): \DateTime
    {
        return $this->checkOutDate;
    }

    /**
     * @param \DateTime $checkOutDate
     * @return RaHotelRequest
     */
    public function setCheckOutDate(\DateTime $checkOutDate): RaHotelRequest
    {
        $this->checkOutDate = $checkOutDate;
        return $this;
    }

    /**
     * @return int
     */
    public function getNumberOfRooms(): int
    {
        return $this->numberOfRooms;
    }

    /**
     * @param int $numberOfRooms
     * @return RaHotelRequest
     */
    public function setNumberOfRooms(int $numberOfRooms): RaHotelRequest
    {
        $this->numberOfRooms = $numberOfRooms;
        return $this;
    }

    /**
     * @return int
     */
    public function getNumberOfAdults(): int
    {
        return $this->numberOfAdults;
    }

    /**
     * @param int $numberOfAdults
     * @return RaHotelRequest
     */
    public function setNumberOfAdults(int $numberOfAdults): RaHotelRequest
    {
        $this->numberOfAdults = $numberOfAdults;
        return $this;
    }

    /**
     * @return int
     */
    public function getNumberOfKids(): int
    {
        return $this->numberOfKids;
    }

    /**
     * @param int $numberOfKids
     * @return RaHotelRequest
     */
    public function setNumberOfKids(int $numberOfKids): RaHotelRequest
    {
        $this->numberOfKids = $numberOfKids;
        return $this;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     * @return RaHotelRequest
     */
    public function setPriority(int $priority): RaHotelRequest
    {
        $this->priority = $priority;
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
     * @return RaHotelRequest
     */
    public function setUserData(string $userData): RaHotelRequest
    {
        $this->userData = $userData;
        return $this;
    }

    /**
     * @return string
     */
    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    /**
     * @param string $callbackUrl
     * @return RaHotelRequest
     */
    public function setCallbackUrl(string $callbackUrl): RaHotelRequest
    {
        $this->callbackUrl = $callbackUrl;
        return $this;
    }

    public function getLogContext(): array
    {
        return [];
    }

    public function getTimeout()
    {
        return null;
    }

    public function getUserId()
    {
        return null;
    }


}