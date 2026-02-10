<?php


namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use JMS\Serializer\Annotation\Type;

class AdminRaAccount implements LoyaltyRequestInterface
{

    /**
     * @var string
     * @Type("string")
     */
    protected $provider;

    /**
     * @var string
     * @Type("string")
     */
    protected $login;

    /**
     * @var string
     * @Type("string")
     */
    protected $login2;

    /**
     * @var string
     * @Type("string")
     */
    protected $login3;

    /**
     * @var string
     * @Type("string")
     */
    protected $password;

    /**
     * @var string
     * @Type("string")
     */
    protected $email;

    /**
     * @var Answer[]
     * @Type("array<AppBundle\Model\Resources\Answer>")
     */
    protected $answers = [];

    /**
     * @var int
     * @Type("int")
     */
    protected $state;

    /**
     * @var string
     * @Type("string")
     */
    protected $reset;

    /**
     * @var RegisterInfo[]
     * @Type("array<AppBundle\Model\Resources\RegisterInfo>")
     */
    protected $registerInfo = [];

    /**
     * @return string
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * @return string
     */
    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * @return string
     */
    public function getLogin2(): string
    {
        return $this->login2;
    }

    /**
     * @return string
     */
    public function getLogin3(): string
    {
        return $this->login3;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return Answer[]
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function getReset(): ?string
    {
        return $this->reset;
    }

    /**
     * @return RegisterInfo[]
     */
    public function getRegisterInfo(): array
    {
        return $this->registerInfo;
    }

    public function clear()
    {
        $this->provider = strtolower(trim($this->provider));
        $this->login = trim($this->login);
        $this->login2 = trim($this->login2);
        $this->login3 = trim($this->login3);
        $this->password = trim($this->password);
        $this->email = trim($this->email);
        foreach($this->answers as $answer) {
            $answer->clear();
        }
        foreach($this->registerInfo as $data) {
            $data->clear();
        }
    }

    public function validate(): bool
    {
        $valid = !empty($this->provider)
            && !empty($this->login)
            && !empty($this->password)
            && !empty($this->email)
            && is_int($this->state);
        foreach($this->answers as $answer) {
            $valid = $valid && $answer->validate();
        }
        foreach($this->registerInfo as $data) {
            $valid = $valid && $data->validate();
        }
        return $valid;
    }

}