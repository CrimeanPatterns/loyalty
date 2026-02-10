<?php


namespace AppBundle\Model\Resources\RewardAvailability;


use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class SegmentPoint
{

    /**
     * @MongoDB\Field(type="date")
     * @Type("DateTime<'Y-m-d\TH:i:sP'>")
     * @var \DateTime
     */
    private $dateTime;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $airport;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $terminal;

    public function __construct(?string $dateTime, ?string $airport, ?string $terminal)
    {
        if ($dateTime) {
            $this->dateTime = new \DateTime($dateTime);
        }
        $this->airport = $airport;
        $this->terminal = $terminal;
    }

    /**
     * @return string
     */
    public function getAirport(): ?string
    {
        return $this->airport;
    }

}