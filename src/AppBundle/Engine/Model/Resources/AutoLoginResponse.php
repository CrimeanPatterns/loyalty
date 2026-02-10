<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\Annotation\Type;

class AutoLoginResponse implements LoyaltyResponseInterface
{
    /**
     * @var string
     * @Type("string")
     */
    private $response;
    /**
     * @var string
     * @Type("string")
     */
    private $userData;

    /**
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param string $response
     * @return $this
     */
    public function setResponse($response)
    {
        $this->response = $response;
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
}
