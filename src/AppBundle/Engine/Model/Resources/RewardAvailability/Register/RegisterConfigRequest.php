<?php
namespace AppBundle\Model\Resources\RewardAvailability\Register;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;

/** @MongoDB\EmbeddedDocument */
class RegisterConfigRequest implements LoyaltyRequestInterface
{
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $provider;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $defaultEmail;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $ruleForEmail;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $minCountEnabled;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $minCountReserved;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $delay;

    /**
     * @MongoDB\Field(type="bool")
     * @Type("bool")
     * @var bool
     */
    private $isActive;

    /**
     * @MongoDB\Field(type="bool")
     * @Type("bool")
     * @var bool
     */
    private $is2Fa;

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getDefaultEmail(): string
    {
        return $this->defaultEmail;
    }

    public function setDefaultEmail(string $defaultEmail): self
    {
        $this->defaultEmail = $defaultEmail;
        return $this;
    }

    public function getRuleForEmail(): string
    {
        return $this->ruleForEmail;
    }

    public function setRuleForEmail(string $ruleForEmail): self
    {
        $this->ruleForEmail = $ruleForEmail;
        return $this;
    }

    public function getMinCountEnabled(): int
    {
        return $this->minCountEnabled;
    }

    public function setMinCountEnabled(string $minCountEnabled): self
    {
        $this->minCountEnabled = $minCountEnabled;
        return $this;
    }

    public function getMinCountReserved(): int
    {
        return $this->minCountReserved;
    }

    public function setMinCountReserved(string $minCountReserved): self
    {
        $this->minCountReserved = $minCountReserved;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getIs2Fa(): bool
    {
        return $this->is2Fa;
    }

    public function setIs2Fa(bool $is2Fa): self
    {
        $this->is2Fa = $is2Fa;
        return $this;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function setDelay(int $delay): self
    {
        $this->delay = $delay;
        return $this;
    }
}