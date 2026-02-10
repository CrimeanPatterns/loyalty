<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 18/04/2018
 * Time: 15:37
 */

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class PasswordRequestResult
{
    use LoginFields;

    /**
     * @var string
     * @Type("string")
     */
    protected $partner;
    /**
     * @var string
     * @Type("string")
     */
    protected $provider;
    /**
     * @var string
     * @Type("string")
     */
    protected $userId;
    /**
     * @var string
     * @Type("string")
     */
    protected $note;

    /**
     * @return string
     */
    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * @param string $userId
     * @return $this
     */
    public function setUserId(string $userId)
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return string
     */
    public function getPartner(): string
    {
        return $this->partner;
    }

    /**
     * @param string $partner
     * @return $this
     */
    public function setPartner(string $partner)
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @return string
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * @param string $provider
     * @return $this
     */
    public function setProvider(string $provider)
    {
        $this->provider = $provider;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }

}