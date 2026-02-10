<?php

namespace AppBundle\Document;

use DateTime;
use AppBundle\Repository\RaAccountRepository;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(repositoryClass=RaAccountRepository::class)
 * @MongoDB\Indexes({
 *     @MongoDB\UniqueIndex(keys={"provider" = "asc", "defaultEmail" = "asc", "ruleForEmail" = "asc"}),
 *     @MongoDB\Index(keys={"provider" = "asc"})
 * })
 */

class RegisterConfig
{

    /** @MongoDB\Id */
    protected $id;

    /** @MongoDB\Field(type="string") */
    protected $provider;

    /** @MongoDB\Field(type="string") */
    protected $defaultEmail;

    /** @MongoDB\Field(type="string") */
    protected $ruleForEmail;

    /** @MongoDB\Field(type="int") */
    protected $minCountEnabled;

    /** @MongoDB\Field(type="int") */
    protected $minCountReserved;

    /** @MongoDB\Field(type="int") */
    protected $delay;

    /** @MongoDB\Field(type="bool") */
    protected $isActive;

    /** @MongoDB\Field(type="bool") */
    protected $is2Fa;

    public function __construct(
        string $provider = null,
        string $defaultEmail = null,
        string $ruleForEmail = null,
        int $minCountEnabled = null,
        int $minCountReserved = null,
        int $delay = 120,
        bool $isActive = true,
        bool $is2Fa = false
    ) {
        $this->provider = $provider;
        $this->defaultEmail = $defaultEmail;
        $this->ruleForEmail = $ruleForEmail;
        $this->minCountEnabled = $minCountEnabled;
        $this->minCountReserved = $minCountReserved;
        $this->delay = $delay;
        $this->isActive = $isActive;
        $this->is2Fa = $is2Fa;
    }

    public function getId(): string
    {
        return $this->id;
    }

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