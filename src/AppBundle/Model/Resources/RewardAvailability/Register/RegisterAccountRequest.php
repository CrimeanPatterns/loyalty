<?php
namespace AppBundle\Model\Resources\RewardAvailability\Register;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;

/** @MongoDB\EmbeddedDocument */
class RegisterAccountRequest implements LoyaltyRequestInterface
{
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $provider;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $priority;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $callbackUrl;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $userData;

    /**
     * @MongoDB\Field(type="date")
     * @Type("DateTime<'Y-m-d\TH:i:sP'>")
     * @var \DateTime
     */
    private $registerNotEarlierDate;

    /**
     * @MongoDB\Field(type="bool")
     * @Type("bool")
     * @var bool
     */
    private $isAuto = false;

    /**
     * Registration fields array (unique for each provider)
     * @MongoDB\Field(type="hash")
     * @Type("array")
     * @Assert\NotBlank()
     */
    public $fields;

    /**
     * @return string
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * @param string $provider
     * @return self
     */
    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * @return string
     */
    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    /**
     * @param string $callbackUrl
     * @return self
     */
    public function setCallbackUrl(string $callbackUrl): self
    {
        $this->callbackUrl = $callbackUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getUserData(): ?string
    {
        return $this->userData;
    }

    /**
     * @param string $userData
     * @return self
     */
    public function setUserData(string $userData): self
    {
        $this->userData = $userData;
        return $this;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority ?? 5;
    }

    /**
     * @param int $priority
     * @return self
     */
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return array
     */
    public function getFields(): ?array
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     * @return self
     */
    public function setFields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    public function getLogContext(): array
    {
        return [];
    }

    public function getTimeout()
    {
        return null;
    }

    public function getUserId()
    {
        return null;
    }

    /**
     * @return \DateTime
     */
    public function getRegisterNotEarlierDate(): ?\DateTime
    {
        return $this->registerNotEarlierDate;
    }

    /**
     * @param \DateTime $registerNotEarlierDate
     * @return self
     */
    public function setRegisterNotEarlierDate(\DateTime $registerNotEarlierDate): self
    {
        $this->registerNotEarlierDate = $registerNotEarlierDate;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsAuto(): bool
    {
        return $this->isAuto;
    }

    /**
     * @param bool $isAuto
     * @return self
     */
    public function setIsAuto(bool $isAuto): self
    {
        $this->isAuto = $isAuto;
        return $this;
    }

}