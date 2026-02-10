<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'PropertyInfo'.
 */
class PropertyInfo
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
    private $name;
        
    /**
     * @var string
     * @Type("string")
     */
    private $kind;

    /**
     * @var boolean
     * @Type("boolean")
     */
    private $isHidden;

    /**
     * @param string
     *
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setKind($kind)
    {
        $this->kind = $kind;

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
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * @return string
     */
    public function getKind()
    {
        return $this->kind;
    }

    /**
     * @return bool
     */
    public function isHidden()
    {
        return $this->isHidden;
    }

    /**
     * @param bool $isHidden
     * @return $this
     */
    public function setIsHidden(bool $isHidden)
    {
        $this->isHidden = $isHidden;
        return $this;
    }

}
