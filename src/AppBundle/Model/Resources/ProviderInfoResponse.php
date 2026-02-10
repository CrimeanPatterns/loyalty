<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'ProviderInfoResponse'.
 */
class ProviderInfoResponse implements LoyaltyResponseInterface
{
    /**
     * @var integer
     * @Type("integer")
     */
    private $kind;
        
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
    private $providerName;
        
    /**
     * @var string
     * @Type("string")
     */
    private $programName;
                
    /**
     * @var Input
     * @Type("AppBundle\Model\Resources\Input")
     */
    private $login;
                
    /**
     * @var Input
     * @Type("AppBundle\Model\Resources\Input")
     */
    private $login2;
                
    /**
     * @var Input
     * @Type("AppBundle\Model\Resources\Input")
     */
    private $login3;
                
    /**
     * @var Input
     * @Type("AppBundle\Model\Resources\Input")
     */
    private $password;
                
    /**
     * @var PropertyInfo[]
     * @Type("array<AppBundle\Model\Resources\PropertyInfo>")
     */
    private $properties;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $autoLogin;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $deepLinking;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $canCheckConfirmation;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $canCheckItinerary;

    /**
     * @var boolean
     * @Type("boolean")
     */
    private $canCheckPastItinerary;

    /**
     * @var integer
     * @Type("integer")
     */
    private $canCheckExpiration;
                
    /**
     * @var Input[]
     * @Type("array<AppBundle\Model\Resources\Input>")
     */
    private $confirmationNumberFields;
                
    /**
     * @var PropertyInfo[]
     * @Type("array<AppBundle\Model\Resources\HistoryColumn>")
     */
    private $historyColumns;
        
    /**
     * @var integer
     * @Type("integer")
     */
    private $eliteLevelsCount;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $canParseHistory;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $canParseFiles;

    /**
     * @var boolean
     * @Type("boolean")
     */
    private $combineHistoryBonusToMiles;

    /**
     * @param integer
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
     * @param string
     *
     * @return $this
     */
    public function setProvidername($providerName)
    {
        $this->providerName = $providerName;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setProgramname($programName)
    {
        $this->programName = $programName;

        return $this;
    }
    
    /**
     * @param Input
     *
     * @return $this
     */
    public function setLogin($login)
    {
        $this->login = $login;

        return $this;
    }
    
    /**
     * @param Input
     *
     * @return $this
     */
    public function setLogin2($login2)
    {
        $this->login2 = $login2;

        return $this;
    }
    
    /**
     * @param Input
     *
     * @return $this
     */
    public function setLogin3($login3)
    {
        $this->login3 = $login3;

        return $this;
    }
    
    /**
     * @param Input
     *
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

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
    public function setAutologin($autoLogin)
    {
        $this->autoLogin = $autoLogin;

        return $this;
    }
    
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setDeeplinking($deepLinking)
    {
        $this->deepLinking = $deepLinking;

        return $this;
    }
    
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setCancheckconfirmation($canCheckConfirmation)
    {
        $this->canCheckConfirmation = $canCheckConfirmation;

        return $this;
    }
    
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setCancheckitinerary($canCheckItinerary)
    {
        $this->canCheckItinerary = $canCheckItinerary;

        return $this;
    }
    
    /**
     * @param integer
     *
     * @return $this
     */
    public function setCancheckexpiration($canCheckExpiration)
    {
        $this->canCheckExpiration = $canCheckExpiration;

        return $this;
    }
    
    /**
     * @param array
     *
     * @return $this
     */
    public function setConfirmationnumberfields($confirmationNumberFields)
    {
        $this->confirmationNumberFields = $confirmationNumberFields;

        return $this;
    }
    
    /**
     * @param array
     *
     * @return $this
     */
    public function setHistorycolumns($historyColumns)
    {
        $this->historyColumns = $historyColumns;

        return $this;
    }
    
    /**
     * @param integer
     *
     * @return $this
     */
    public function setElitelevelscount($eliteLevelsCount)
    {
        $this->eliteLevelsCount = $eliteLevelsCount;

        return $this;
    }
    
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setCanparsehistory($canParseHistory)
    {
        $this->canParseHistory = $canParseHistory;

        return $this;
    }
    
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setCanparsefiles($canParseFiles)
    {
        $this->canParseFiles = $canParseFiles;

        return $this;
    }
            
    /**
     * @return integer
     */
    public function getKind()
    {
        return $this->kind;
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
    public function getProvidername()
    {
        return $this->providerName;
    }
    
    /**
     * @return string
     */
    public function getProgramname()
    {
        return $this->programName;
    }
    /**
     * @return Input
     */
    
    public function getLogin()
    {
        return $this->login;
    }
    /**
     * @return Input
     */
    
    public function getLogin2()
    {
        return $this->login2;
    }
    /**
     * @return Input
     */
    
    public function getLogin3()
    {
        return $this->login3;
    }
    /**
     * @return Input
     */
    
    public function getPassword()
    {
        return $this->password;
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
    public function getAutologin()
    {
        return $this->autoLogin;
    }
    
    /**
     * @return boolean
     */
    public function getDeeplinking()
    {
        return $this->deepLinking;
    }
    
    /**
     * @return boolean
     */
    public function getCancheckconfirmation()
    {
        return $this->canCheckConfirmation;
    }
    
    /**
     * @return boolean
     */
    public function getCancheckitinerary()
    {
        return $this->canCheckItinerary;
    }
    
    /**
     * @return integer
     */
    public function getCancheckexpiration()
    {
        return $this->canCheckExpiration;
    }
    
    /**
     * @return array
     */
    public function getConfirmationnumberfields()
    {
        return $this->confirmationNumberFields;
    }
    
    /**
     * @return array
     */
    public function getHistorycolumns()
    {
        return $this->historyColumns;
    }
    
    /**
     * @return integer
     */
    public function getElitelevelscount()
    {
        return $this->eliteLevelsCount;
    }
    
    /**
     * @return boolean
     */
    public function getCanparsehistory()
    {
        return $this->canParseHistory;
    }
    
    /**
     * @return boolean
     */
    public function getCanparsefiles()
    {
        return $this->canParseFiles;
    }

    /**
     * @return bool
     */
    public function getCanCheckPastItinerary()
    {
        return $this->canCheckPastItinerary;
    }

    /**
     * @param bool $canCheckPastItinerary
     * @return $this
     */
    public function setCanCheckPastItinerary(bool $canCheckPastItinerary)
    {
        $this->canCheckPastItinerary = $canCheckPastItinerary;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCombineHistoryBonusToMiles()
    {
        return $this->combineHistoryBonusToMiles;
    }

    /**
     * @param bool $combineHistoryBonusToMiles
     * @return $this
     */
    public function setCombineHistoryBonusToMiles(?bool $combineHistoryBonusToMiles)
    {
        $this->combineHistoryBonusToMiles = $combineHistoryBonusToMiles;
        return $this;
    }

}
