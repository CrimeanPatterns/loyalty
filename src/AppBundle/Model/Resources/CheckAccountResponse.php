<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'CheckAccountResponse'.
 */
class CheckAccountResponse extends BaseCheckResponse
{
    /**
     * @var string
     * @Type("string")
     */
    private $login;
        
    /**
     * @var string
     * @Type("string")
     */
    private $login2;
        
    /**
     * @var string
     * @Type("string")
     */
    private $login3;
        
    /**
     * @var string
     * @Type("string")
     */
    private $question;

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
     * @var Property[]
     * @Type("array<AppBundle\Model\Resources\Property>")
     */
    private $properties;

    /**
     * @var integer
     * @Type("integer")
     */
    private $eliteLevel;

    /**
     * @var boolean
     * @Type("boolean")
     */
    private $noItineraries;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $neverExpires;
                
    /**
     * @var SubAccount[]
     * @Type("array<AppBundle\Model\Resources\SubAccount>")
     */
    private $subAccounts;

    /**
     * @var DetectedCard[]
     * @Type("array<AppBundle\Model\Resources\DetectedCard>")
     */
    private $detectedCards;

    /**
     * @var string
     * @Type("string")
     */
    private $mode;
        
    /**
     * @var string
     * @Type("string")
     */
    private $browserState;
        
    /**
     * @var History
     * @Type("AppBundle\Model\Resources\History")
     */
    private $history;

    /**
     * @var File[]
     * @Type("array<AppBundle\Model\Resources\File>")
     */
    private $files;
        
    /**
     * @var integer
     * @Type("integer")
     */
    private $filesVersion;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $filesCacheValid;
                
    /**
     * @var Answer[]
     * @Type("array<AppBundle\Model\Resources\Answer>")
     */
    private $invalidAnswers;
        
    /**
     * @var string
     * @Type("string")
     */
    private $options;
            
    /**
     * @param string
     *
     * @return $this
     */
    public function setLogin($login)
    {
        $this->login = $login;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setLogin2($login2)
    {
        $this->login2 = $login2;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setLogin3($login3)
    {
        $this->login3 = $login3;

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setQuestion($question)
    {
        $this->question = $question;

        return $this;
    }
    
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
     * @param integer
     *
     * @return $this
     */
    public function setElitelevel($eliteLevel)
    {
        $this->eliteLevel = $eliteLevel;

        return $this;
    }
    
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setNoitineraries($noItineraries)
    {
        $this->noItineraries = $noItineraries;

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
     * @param array
     *
     * @return $this
     */
    public function setSubaccounts($subAccounts)
    {
        $this->subAccounts = $subAccounts;

        return $this;
    }
    
    /**
     * @param array
     *
     * @return $this
     */
    public function setDetectedcards($detectedCards)
    {
        $this->detectedCards = $detectedCards;

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setBrowserstate($browserState)
    {
        $this->browserState = $browserState;

        return $this;
    }

    /**
     * @param History $history
     * @return $this
     */
    public function setHistory($history)
    {
        $this->history = $history;
        return $this;
    }

    /**
     * @param array
     *
     * @return $this
     */
    public function setFiles($files)
    {
        $this->files = $files;

        return $this;
    }
    
    /**
     * @param integer
     *
     * @return $this
     */
    public function setFilesversion($filesVersion)
    {
        $this->filesVersion = $filesVersion;

        return $this;
    }
    
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setFilescachevalid($filesCacheValid)
    {
        $this->filesCacheValid = $filesCacheValid;

        return $this;
    }
    
    /**
     * @param array
     *
     * @return $this
     */
    public function setInvalidanswers($invalidAnswers)
    {
        $this->invalidAnswers = $invalidAnswers;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setOptions($options)
    {
        $this->options = $options;

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
     * @param \DateTime
     * @return $this
     */
    public function setExpirationDate($expirationDate)
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    /**
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }
    
    /**
     * @return string
     */
    public function getLogin2()
    {
        return $this->login2;
    }
    
    /**
     * @return string
     */
    public function getLogin3()
    {
        return $this->login3;
    }

    /**
     * @return string
     */
    public function getQuestion()
    {
        return $this->question;
    }
    
    /**
     * @return string
     */
    public function getBalance()
    {
        return $this->balance;
    }
    
    /**
     * @return Property[]
     */
    public function getProperties()
    {
        return $this->properties;
    }
    
    /**
     * @return integer
     */
    public function getElitelevel()
    {
        return $this->eliteLevel;
    }
    
    /**
     * @return boolean
     */
    public function getNoitineraries()
    {
        return $this->noItineraries;
    }
    
    /**
     * @return boolean
     */
    public function getNeverexpires()
    {
        return $this->neverExpires;
    }
    
    /**
     * @return array
     */
    public function getSubaccounts()
    {
        return $this->subAccounts;
    }

    /**
     * @return array
     */
    public function getDetectedcards()
    {
        return $this->detectedCards;
    }

    /**
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }
    
    /**
     * @return string
     */
    public function getBrowserstate()
    {
        return $this->browserState;
    }

    /**
     * @return History
     */
    public function getHistory()
    {
        return $this->history;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }
    
    /**
     * @return integer
     */
    public function getFilesversion()
    {
        return $this->filesVersion;
    }
    
    /**
     * @return boolean
     */
    public function getFilescachevalid()
    {
        return $this->filesCacheValid;
    }
    
    /**
     * @return array
     */
    public function getInvalidanswers()
    {
        return $this->invalidAnswers;
    }
    
    /**
     * @return string
     */
    public function getOptions()
    {
        return $this->options;
    }
}
