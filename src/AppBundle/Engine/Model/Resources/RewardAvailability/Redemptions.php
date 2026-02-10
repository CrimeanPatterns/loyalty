<?php


namespace AppBundle\Model\Resources\RewardAvailability;


use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class Redemptions
{
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $program;
    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $miles;

    public function __construct(?string $program, ?float $miles)
    {
        $this->program = $program;
        $this->miles = $miles;
    }

    /**
     * @return int
     */
    public function getMiles()
    {
        return $this->miles;
    }
}