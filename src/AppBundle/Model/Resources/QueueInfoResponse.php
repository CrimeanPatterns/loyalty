<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 05/08/16
 * Time: 16:38
 */

namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\Annotation\Type;

class QueueInfoResponse implements LoyaltyResponseInterface
{
    /**
     * @var QueueInfoItem[]
     * @Type("array<AppBundle\Model\Resources\QueueInfoItem>")
     */
    private $queues;

    /**
     * @return QueueInfoItem[]
     */
    public function getQueues()
    {
        return $this->queues;
    }

    /**
     * @param QueueInfoItem[] $queues
     * @return $this
     */
    public function setQueues($queues)
    {
        $this->queues = $queues;
        return $this;
    }

}