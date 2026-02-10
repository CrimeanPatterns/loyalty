<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use JMS\Serializer\Annotation\Type;

class AutoLoginRequest implements LoyaltyRequestInterface
{

    use BrowserExtensionRequestFields;

    /**
     * @var string
     * @Type("string")
     */
    private $provider;
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
     * @var string
     * @Type("string")
     */
    private $password;
    /**
     * @var string
     * @Type("string")
     */
    private $targetUrl;
    /**
     * @var string
     * @Type("string")
     */
    private $userId;
    /**
     * @var string
     * @Type("string")
     */
    private $userData;
    /**
     * @var string
     * @Type("string")
     */
    private $startUrl;
    /**
     * @var array
     * @Type("array<string>")
     */
    private $supportedProtocols;

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
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * @param string $login
     * @return $this
     */
    public function setLogin($login)
    {
        $this->login = $login;
        return $this;
    }

    /**
     * @return string
     */
    public function getLogin2()
    {
        return $this->login2;
    }

    /**
     * @param string $login2
     * @return $this
     */
    public function setLogin2($login2)
    {
        $this->login2 = $login2;
        return $this;
    }

    /**
     * @return string
     */
    public function getLogin3()
    {
        return $this->login3;
    }

    /**
     * @param string $login3
     * @return $this
     */
    public function setLogin3($login3)
    {
        $this->login3 = $login3;
        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return string
     */
    public function getTargetUrl()
    {
        return $this->targetUrl;
    }

    /**
     * @param string $targetUrl
     * @return $this
     */
    public function setTargetUrl($targetUrl)
    {
        $this->targetUrl = $targetUrl;
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
    public function getStartUrl()
    {
        return $this->startUrl;
    }

    /**
     * @param string $startUrl
     * @return $this
     */
    public function setStartUrl($startUrl)
    {
        $this->startUrl = $startUrl;
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
     * @return array
     */
    public function getSupportedProtocols()
    {
        return $this->supportedProtocols;
    }

    /**
     * @param array $supportedProtocols
     * @return $this
     */
    public function setSupportedProtocols(array $supportedProtocols)
    {
        $this->supportedProtocols = $supportedProtocols;
        return $this;
    }

}
