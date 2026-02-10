<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 29.06.16
 * Time: 14:39
 */

namespace AppBundle\Extension\MQMessages;

use JMS\Serializer\Annotation\Type;


class WatchdogMessage
{

    /**
     * @var string
     * @Type("string")
     */
    private $type;
    /**
     * @var integer
     * @Type("integer")
     */
    private $pid;
    /**
     * @var string
     * @Type("string")
     */
    private $partner;
    /**
     * @var integer
     * @Type("integer")
     */
    private $stopTime;
    /**
     * @var integer
     * @Type("integer")
     */
    private $startTime;
    /**
     * @var integer
     * @Type("integer")
     */
    private $increaseTime;
    /**
     * @var array
     * @Type("array")
     */
    private $context = [
        'logContext' => [],
    ];

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @param int $pid
     * @return $this
     */
    public function setPid($pid)
    {
        $this->pid = $pid;

        return $this;
    }

    /**
     * @return string
     */
    public function getPartner()
    {
        return $this->partner;
    }

    /**
     * @param string $partner
     * @return $this
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;

        return $this;
    }

    /**
     * @return int
     */
    public function getStopTime()
    {
        return $this->stopTime;
    }

    /**
     * @param int $stopTime
     * @return $this
     */
    public function setStopTime($stopTime)
    {
        $this->stopTime = $stopTime;

        return $this;
    }

    /**
     * @return int
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @param int $startTime
     * @return $this
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
        return $this;
    }

    /**
     * @return int
     */
    public function getIncreaseTime()
    {
        return $this->increaseTime;
    }

    /**
     * @param int $increaseTime
     * @return $this
     */
    public function setIncreaseTime(int $increaseTime)
    {
        $this->increaseTime = $increaseTime;
        return $this;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array $context
     */
    public function setContext(array $context)
    {
        $this->context = $context;
        return $this;
    }

    public function addContext(array $context)
    {
        $this->context = array_merge_recursive($this->context, $context);
        return $this;
    }

}