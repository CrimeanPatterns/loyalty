<?php

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class CheckExtensionSupportRequest
{
    /**
     * @var string
     * @Type("string")
     */
    private $provider;
    /**
     * @var string
     * @Type("string")
     */
    private $id;
    /**
     * @var string
     * @Type("string")
     */
    private $login;
    /**
     * @var string
     * @Type("string")
     */
    private $login2;
    /**
     * @var string
     * @Type("string")
     */
    private $login3;
    /**
     * @var bool
     * @Type("boolean")
     */
    private $isMobile;

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setLogin(string $login): self
    {
        $this->login = $login;

        return $this;
    }

    public function getLogin2(): ?string
    {
        return $this->login2;
    }

    public function setLogin2(?string $login2): self
    {
        $this->login2 = $login2;

        return $this;
    }

    public function getLogin3(): ?string
    {
        return $this->login3;
    }

    public function setLogin3(?string $login3): self
    {
        $this->login3 = $login3;

        return $this;
    }

    public function isMobile(): ?bool
    {
        return $this->isMobile;
    }

    public function setIsMobile(?bool $isMobile): self
    {
        $this->isMobile = $isMobile;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }
}