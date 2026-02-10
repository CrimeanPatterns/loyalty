<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'AdminLogsRequest'.
 */
class AdminLogsRequest implements LoyaltyRequestInterface
{
    /**
     * @var string
     * @Type("string")
     */
    private $partner;
        
    /**
     * @var string
     * @Type("string")
     */
    private $method;
        
    /**
     * @var string
     * @Type("string")
     */
    private $provider;
        
    /**
     * @var string
     * @Type("string")
     */
    private $userData;
        
    /**
     * @var string
     * @Type("string")
     */
    private $requestId;
        
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
     * @param string
     *
     * @return $this
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setProvider($provider)
    {
        $this->provider = $provider;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setUserdata($userData)
    {
        $this->userData = $userData;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setRequestid($requestId)
    {
        $this->requestId = $requestId;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setLogin($login)
    {
        $this->login = $login;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setLogin2($login2)
    {
        $this->login2 = $login2;

        return $this;
    }
    
    /**
     * @param string
     *
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
    public function getPartner()
    {
        return $this->partner;
    }
    
    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }
    
    /**
     * @return string
     */
    public function getProvider()
    {
        return $this->provider;
    }
    
    /**
     * @return string
     */
    public function getUserdata()
    {
        return $this->userData;
    }
    
    /**
     * @return string
     */
    public function getRequestid()
    {
        return $this->requestId;
    }
    
    /**
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }
    
    /**
     * @return string
     */
    public function getLogin2()
    {
        return $this->login2;
    }
    
    /**
     * @return string
     */
    public function getLogin3()
    {
        return $this->login3;
    }
}
