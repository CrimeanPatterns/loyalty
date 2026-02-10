<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 18/04/2018
 * Time: 15:37
 */

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class PasswordRequest
{
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
    protected $login;
    /**
     * @var string
     * @Type("string")
     */
    protected $note;
    /**
     * @var string
     * @Type("string")
     */
    protected $userId;

    /**
     * @return string
     */
    public function getPartner(): ?string
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
    public function getProvider(): ?string
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

    /**
     * @return string
     */
    public function getLogin(): ?string
    {
        return $this->login;
    }

    /**
     * @param string $login
     * @return $this
     */
    public function setLogin(string $login)
    {
        $this->login = $login;
        return $this;
    }

    /**
     * @return string
     */
    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * @param string $note
     * @return $this
     */
    public function setNote(string $note)
    {
        $this->note = $note;
        return $this;
    }

    /**
     * @return string
     */
    public function getUserId(): ?string
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

}