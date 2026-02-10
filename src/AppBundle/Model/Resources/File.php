<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'File'.
 */
class File
{
    /**
     * @var date
     * @Type("date")
     */
    private $date;
        
    /**
     * @var string
     * @Type("string")
     */
    private $name;
        
    /**
     * @var string
     * @Type("string")
     */
    private $extension;
        
    /**
     * @var string
     * @Type("string")
     */
    private $kind;
        
    /**
     * @var string
     * @Type("string")
     */
    private $accountNumber;
        
    /**
     * @var string
     * @Type("string")
     */
    private $accountName;
        
    /**
     * @var string
     * @Type("string")
     */
    private $accountType;
        
    /**
     * @var string
     * @Type("string")
     */
    private $contents;
            
    /**
     * @param date
     *
     * @return $this
     */
    public function setDate($date)
    {
        $this->date = $date;

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
    public function setExtension($extension)
    {
        $this->extension = $extension;

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
     * @param string
     *
     * @return $this
     */
    public function setAccountnumber($accountNumber)
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setAccountname($accountName)
    {
        $this->accountName = $accountName;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setAccounttype($accountType)
    {
        $this->accountType = $accountType;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setContents($contents)
    {
        $this->contents = $contents;

        return $this;
    }
            
    /**
     * @return date
     */
    public function getDate()
    {
        return $this->date;
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
    public function getExtension()
    {
        return $this->extension;
    }
    
    /**
     * @return string
     */
    public function getKind()
    {
        return $this->kind;
    }
    
    /**
     * @return string
     */
    public function getAccountnumber()
    {
        return $this->accountNumber;
    }
    
    /**
     * @return string
     */
    public function getAccountname()
    {
        return $this->accountName;
    }
    
    /**
     * @return string
     */
    public function getAccounttype()
    {
        return $this->accountType;
    }
    
    /**
     * @return string
     */
    public function getContents()
    {
        return $this->contents;
    }
}
