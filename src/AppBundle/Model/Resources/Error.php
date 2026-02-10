<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'Error'.
 */
class Error
{
    /**
     * @var integer
     * @Type("integer")
     */
    private $code;
        
    /**
     * @var string
     * @Type("string")
     */
    private $message;
        
    /**
     * @var string
     * @Type("string")
     */
    private $fields;
            
    /**
     * @param integer
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
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }
            
    /**
     * @return integer
     */
    public function getCode()
    {
        return $this->code;
    }
    
    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }
    
    /**
     * @return string
     */
    public function getFields()
    {
        return $this->fields;
    }
}
