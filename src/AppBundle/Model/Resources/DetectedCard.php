<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'DetectedCard'.
 */
class DetectedCard
{
    /**
     * @var string
     * @Type("string")
     */
    private $code;
        
    /**
     * @var string
     * @Type("string")
     */
    private $displayName;

    /**
     * @var string
     * @Type("string")
     */
    private $cardDescription;


    /**
     * @param string
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }
    
    /**
     * @param string
     * @return $this
     */
    public function setDisplayname($displayName)
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * @param string
     * @return $this
     */
    public function setCarddescription($cardDescription)
    {
        $this->cardDescription = $cardDescription;

        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }
    
    /**
     * @return string
     */
    public function getDisplayname()
    {
        return $this->displayName;
    }

    /**
     * @return string
     */
    public function getCarddescription()
    {
        return $this->cardDescription;
    }

}
