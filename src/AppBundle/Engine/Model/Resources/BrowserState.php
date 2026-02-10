<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class BrowserState
{
    /**
     * @var string
     * @Type("string")
     */
    protected $browser;

    /**
     * @var string
     * @Type("string")
     */
    protected $loginHash;

    /**
     * @return string
     */
    public function getBrowser()
    {
        return $this->browser;
    }

    /**
     * @param string $browser
     * @return $this
     */
    public function setBrowser($browser)
    {
        $this->browser = $browser;
        return $this;
    }

    /**
     * @return string
     */
    public function getLoginHash()
    {
        return $this->loginHash;
    }

    /**
     * @param string $loginHash
     * @return $this
     */
    public function setLoginHash($loginHash)
    {
        $this->loginHash = $loginHash;
        return $this;
    }

}