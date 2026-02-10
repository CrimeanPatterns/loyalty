<?php


namespace AppBundle\Model\Resources\RewardAvailability;


use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class Departure
{

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $flexibility;

    /**
     * @MongoDB\Field(type="date")
     * @Type("DateTime<'Y-m-d', '', '!Y-m-d'>")
     * @var \DateTime
     */
    private $date;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $airportCode;

    public function getFlexibility(): ?int
    {
        return $this->flexibility;
    }

    public function setFlexibility(int $flexibility): self
    {
        $this->flexibility = $flexibility;
        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getAirportCode(): ?string
    {
        return $this->airportCode;
    }

    public function setAirportCode(string $airportCode): self
    {
        $this->airportCode = $airportCode;
        return $this;
    }

}