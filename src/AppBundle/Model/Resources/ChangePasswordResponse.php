<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class ChangePasswordResponse extends BaseCheckResponse
{
    /**
     * @var string
     * @Type("string")
     */
    private $browserState;

    /**
     * @return string
     */
    public function getBrowserState()
    {
        return $this->browserState;
    }

    /**
     * @param string $browserState
     * @return $this
     */
    public function setBrowserState($browserState)
    {
        $this->browserState = $browserState;
        return $this;
    }

}
