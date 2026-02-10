<?php
namespace AppBundle\Model\Resources\RewardAvailability\Register;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation\Type;
use Symfony\Component\Validator\Constraints as Assert;

/** @MongoDB\EmbeddedDocument */
class RegisterAccountAutoRequest implements LoyaltyRequestInterface
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
    private $email;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $delay;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $count;

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
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return self
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return int
     */
    public function getDelay(): int
    {
        return $this->delay ?? 0;
    }

    /**
     * @param int $delay
     * @return self
     */
    public function setDelay(int $delay): self
    {
        $this->delay = $delay;
        return $this;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @param int $count
     * @return self
     */
    public function setCount(int $count): self
    {
        $this->count = $count;
        return $this;
    }
}