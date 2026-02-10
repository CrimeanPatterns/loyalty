<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 05/08/16
 * Time: 12:27
 */

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class QueueInfoItem
{
    /**
     * @var string
     * @Type("string")
     */
    private $provider;

    /**
     * @var integer
     * @Type("integer")
     */
    private $itemsCount;

    /**
     * @var integer
     * @Type("integer")
     */
    private $priority;

    /**
     * @var array
     * @Type("array")
     */
    private $ids;

    /**
     * @return string
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @param string $provider
     * @return $this
     */
    public function setProvider($provider)
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * @return int
     */
    public function getItemsCount()
    {
        return $this->itemsCount;
    }

    /**
     * @param int $itemsCount
     * @return $this
     */
    public function setItemsCount($itemsCount)
    {
        $this->itemsCount = $itemsCount;
        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     * @return $this
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return array
     */
    public function getIds()
    {
        return $this->ids;
    }

    /**
     * @param array $ids
     * @return $this
     */
    public function setIds($ids)
    {
        $this->ids = $ids;
        return $this;
    }

}