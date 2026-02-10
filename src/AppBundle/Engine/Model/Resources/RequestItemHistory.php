<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'RequestItemHistory'.
 */
class RequestItemHistory
{
    /**
     * @var string
     * @Type("string")
     */
    private $range;

    /**
     * @var string
     * @Type("string")
     */
    private $state;

    /**
     * @return string
     */
    public function getRange()
    {
        return $this->range;
    }

    /**
     * @param string $range
     * @return $this
     */
    public function setRange($range)
    {
        $this->range = $range;
        return $this;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param string $state
     * @return $this
     */
    public function setState($state)
    {
        $this->state = $state;
        return $this;
    }

}
