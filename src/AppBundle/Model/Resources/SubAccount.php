<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'SubAccount'.
 */
class SubAccount
{
    /**
     * @var double
     * @Type("double")
     */
    private $balance;

    /**
     * @var \DateTime
     * @Type("DateTime<'Y-m-d'>")
     */
    private $expirationDate;

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
     * @var Property[]
     * @Type("array<AppBundle\Model\Resources\Property>")
     */
    private $properties;
                
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $neverExpires;
            
    /**
     * @param string
     *
     * @return $this
     */
    public function setBalance($balance)
    {
        $this->balance = $balance;

        return $this;
    }
    
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
    public function setDisplayname($displayName)
    {
        $this->displayName = $displayName;

        return $this;
    }
    
    /**
     * @param array
     *
     * @return $this
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;

        return $this;
    }
    
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setNeverexpires($neverExpires)
    {
        $this->neverExpires = $neverExpires;

        return $this;
    }

    /**
     * @param $expirationDate
     * @return $this
     */
    public function setExpirationDate($expirationDate)
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExpirationDate()
    {
        return $this->expirationDate;
    }

    /**
     * @return string
     */
    public function getBalance()
    {
        return $this->balance;
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
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }
    
    /**
     * @return boolean
     */
    public function getNeverexpires()
    {
        return $this->neverExpires;
    }
}
