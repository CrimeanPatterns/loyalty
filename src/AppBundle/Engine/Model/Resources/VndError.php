<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'VndError'.
 */
class VndError
{
    /**
     * @var string
     * @Type("string")
     */
    private $message;
        
    /**
     * @var string
     * @Type("string")
     */
    private $logref;

    /**
     * @var array
     * @Type("array")
     */
    private $errors;

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
    public function setLogref($logref)
    {
        $this->logref = $logref;

        return $this;
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
    public function getLogref()
    {
        return $this->logref;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param array $errors
     * @return $this
     */
    public function setErrors($errors)
    {
        $this->errors = $errors;
        return $this;
    }

}
