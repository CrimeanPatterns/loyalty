<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 31/10/2016
 * Time: 19:53
 */

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class UserData
{

    public const SOURCE_OPERATIONS = 4;

    /**
     * @var integer
     * @Type("integer")
     */
    private $accountId;

    /**
     * @var integer
     * @Type("integer")
     */
    private $priority;

    /**
     * @var integer
     * @Type("integer")
     */
    private $source;
    /**
     * @var bool
     * @Type("boolean")
     */
    private $otcWait;

    /**
     * @return int
     */
    public function getAccountId()
    {
        return $this->accountId;
    }

    /**
     * @param int $accountId
     * @return $this
     */
    public function setAccountId($accountId)
    {
        $this->accountId = $accountId;
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
     * @return int
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param int $source
     * @return $this
     */
    public function setSource($source)
    {
        $this->source = $source;
        return $this;
    }

    /**
     * @return bool
     */
    public function getOtcWait()
    {
        return $this->otcWait;
    }

    /**
     * @param bool $otcWait
     *
     * @return $this
     */
    public function setOtcWait(bool $otcWait)
    {
        $this->otcWait = $otcWait;
        return $this;
    }

}