<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

class BaseCheckRequest implements LoyaltyRequestInterface
{

    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    protected $provider;
        
    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    protected $userId;
        
    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    protected $userData;

    /**
     * @MongoDB\Field
     * @var integer
     * @Type("integer")
     */
    protected $priority;
        
    /**
     * @MongoDB\Field
     * @var string
     * @Type("string")
     */
    protected $callbackUrl;
        
    /**
     * @MongoDB\Field
     * @var integer
     * @Type("integer")
     */
    protected $retries;

    /**
     * @MongoDB\Field
     * @var integer
     * @Type("integer")
     */
    protected $timeout;

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
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param string $userId
     * @return $this
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserData()
    {
        return $this->userData;
    }

    /**
     * @param string $userData
     * @return $this
     */
    public function setUserData($userData)
    {
        $this->userData = $userData;

        return $this;
    }

    /**
     * @return integer
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param integer
     * @return $this
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return string
     */
    public function getCallbackUrl()
    {
        return $this->callbackUrl;
    }

    /**
     * @param string $callbackUrl
     * @return $this
     */
    public function setCallbackUrl($callbackUrl)
    {
        $this->callbackUrl = $callbackUrl;

        return $this;
    }

    /**
     * @return integer
     */
    public function getRetries()
    {
        return $this->retries;
    }

    /**
     * @param integer
     * @return $this
     */
    public function setRetries($retries)
    {
        $this->retries = $retries;

        return $this;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function getLogContext() : array
    {
        return [];
    }

}
