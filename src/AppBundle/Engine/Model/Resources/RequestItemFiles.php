<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'RequestItemFiles'.
 */
class RequestItemFiles
{
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $parse;
        
    /**
     * @var integer
     * @Type("integer")
     */
    private $version;
        
    /**
     * @var \DateTime
     * @Type("DateTime<'Y-m-d'>")
     */
    private $lastDate;
            
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setParse($parse)
    {
        $this->parse = $parse;

        return $this;
    }
    
    /**
     * @param integer
     *
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }
    
    /**
     * @param \DateTime
     *
     * @return $this
     */
    public function setLastdate($lastDate)
    {
        $this->lastDate = $lastDate;

        return $this;
    }
            
    /**
     * @return boolean
     */
    public function getParse()
    {
        return $this->parse;
    }
    
    /**
     * @return integer
     */
    public function getVersion()
    {
        return $this->version;
    }
    
    /**
     * @return \DateTime
     */
    
    public function getLastdate()
    {
        return $this->lastDate;
    }
}
