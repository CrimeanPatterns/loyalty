<?php


namespace AppBundle\Model\Resources\RewardAvailability;


use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class Times
{

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $flight;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $layover;

    public function __construct(?string $flight, ?string $layover)
    {
        $this->flight = $flight;
        $this->layover = $layover;
    }

    /**
     * @return mixed
     */
    public function getFlightMinutes(): ?int
    {
        return $this->getMinutes($this->flight);
    }

    /**
     * @return mixed
     */
    public function getLayoverMinutes(): ?int
    {
        return $this->getMinutes($this->layover);
    }

    private function getMinutes(?string $time): ?int
    {
        if (strpos($time, ':') === false) {
            return null;
        }
        $items = explode(':', $time);
        return (int)$items[0] * 60 + (int)$items[1];
    }

}