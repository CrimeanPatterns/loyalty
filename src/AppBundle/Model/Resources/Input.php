<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'Input'.
 */
class Input
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
    private $title;
                
    /**
     * @var PropertyInfo[]
     * @Type("array<AppBundle\Model\Resources\PropertyInfo>")
     */
    private $options;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $required;
        
    /**
     * @var string
     * @Type("string")
     */
    private $defaultValue;
            
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
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }
    
    /**
     * @param array
     *
     * @return $this
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }
    
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setRequired($required)
    {
        $this->required = $required;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setDefaultvalue($defaultValue)
    {
        $this->defaultValue = $defaultValue;

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
    public function getTitle()
    {
        return $this->title;
    }
    
    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
    
    /**
     * @return boolean
     */
    public function getRequired()
    {
        return $this->required;
    }
    
    /**
     * @return string
     */
    public function getDefaultvalue()
    {
        return $this->defaultValue;
    }
}
