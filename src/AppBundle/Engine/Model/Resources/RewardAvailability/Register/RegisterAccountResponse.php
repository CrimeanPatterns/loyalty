<?php

namespace AppBundle\Model\Resources\RewardAvailability\Register;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation\Type;

/** @MongoDB\EmbeddedDocument */
class RegisterAccountResponse implements LoyaltyResponseInterface {

    /**
     * @MongoDB\Field(type="string")
     * @Type("string")
     * @var string
     */
    private $debugInfo;

    /**
     * @MongoDB\Field(type="string")
     * @Type("string")
     * @var string
     */
    private $requestId;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     */
    private $state;

    /**
     * @MongoDB\Field(type="string")
     * @Type("string")
     */
    private $message;

    /**
     * @MongoDB\Field(type="date")
     * @Type("DateTime<'Y-m-d\TH:i:sP'>")
     */
    private $requestDate;

    /**
     * @MongoDB\Field(type="string")
     * @Type("string")
     */
    private $userData;

    public function __construct(
        ?string $requestId = null,
        ?int $state = null,
        ?string $userData = null,
        ?string $message = null,
        ?\DateTime $requestDate = null
    ) {
        $this->requestId = $requestId;
        $this->state = $state;
        $this->userData = $userData;
        $this->message = $message;
        $this->requestDate = $requestDate;
    }

    /**
     * @return string
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * @param string $requestId
     * @return RegisterAccountResponse
     */
    public function setRequestId(string $requestId): RegisterAccountResponse
    {
        $this->requestId = $requestId;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getRequestdate()
    {
        return $this->requestDate;
    }

    /**
     * @param \DateTime $requestDate
     * @return RegisterAccountResponse
     */
    public function setRequestdate(?\DateTime $requestDate): RegisterAccountResponse
    {
        $this->requestDate = $requestDate;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param mixed $state
     * @return RegisterAccountResponse
     */
    public function setState($state)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     * @return RegisterAccountResponse
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @param mixed $userData
     * @return RegisterAccountResponse
     */
    public function setUserData($userData)
    {
        $this->userData = $userData;
        return $this;
    }

    public function getUserData()
    {
        return $this->userData;
    }

    public function getDebugInfo(): ?string
    {
        return $this->debugInfo;
    }

    public function setDebugInfo(?string $debugInfo): self
    {
        $this->debugInfo = $debugInfo;
        return $this;
    }

    public function addDebuginfo(string $debugInfo): self
    {
        if (!empty($this->debugInfo)) {
            $this->debugInfo .= "\n" . $debugInfo;
        } else {
            $this->debugInfo = $debugInfo;
        }

        return $this;
    }

}