<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Worker\CheckExecutor\BrowserExtensionRequestInterface;
use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'CheckAccountRequest'.
 */
class CheckAccountRequest extends BaseCheckRequest implements BrowserExtensionRequestInterface
{
    use LoginFields, BrowserExtensionRequestFields;

    /**
     * @var string
     * @Type("string")
     */
    private $state;
        
    /**
     * @var boolean
     * @Type("boolean")
     */
    private $parseItineraries;

    /**
     * @var boolean
     * @Type("boolean")
     */
    private $parsePastItineraries;

    /**
     * @var Answer[]
     * @Type("array<AppBundle\Model\Resources\Answer>")
     */
    private $answers;

    /**
     * @var RequestItemHistory
     * @Type("AppBundle\Model\Resources\RequestItemHistory")
     */
    private $history;
                
    /**
     * @var RequestItemFiles
     * @Type("AppBundle\Model\Resources\RequestItemFiles")
     */
    private $files;

    /**
     * @param string
     *
     * @return $this
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }
    
    /**
     * @param boolean
     *
     * @return $this
     */
    public function setParseitineraries($parseItineraries)
    {
        $this->parseItineraries = $parseItineraries;

        return $this;
    }

    /**
     * @param boolean
     * @return $this
     */
    public function setParsePastItineraries($parsePastItineraries)
    {
        $this->parsePastItineraries = $parsePastItineraries;

        return $this;
    }

    /**
     * @param array
     *
     * @return $this
     */
    public function setAnswers($answers)
    {
        $this->answers = $answers;

        return $this;
    }
    
    /**
     * @param RequestItemHistory
     *
     * @return $this
     */
    public function setHistory($history)
    {
        $this->history = $history;

        return $this;
    }
    
    /**
     * @param RequestItemFiles
     *
     * @return $this
     */
    public function setFiles($files)
    {
        $this->files = $files;

        return $this;
    }
    
    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @return boolean
     */
    public function getParseitineraries()
    {
        return $this->parseItineraries;
    }

    /**
     * @return boolean
     */
    public function getParsePastIineraries()
    {
        return $this->parsePastItineraries;
    }

    /**
     * @return array
     */
    public function getAnswers()
    {
        return $this->answers;
    }
    
    /**
     * @return RequestItemHistory
     */
    
    public function getHistory()
    {
        return $this->history;
    }
    /**
     * @return RequestItemFiles
     */
    
    public function getFiles()
    {
        return $this->files;
    }

    public function getLogContext() : array
    {
        return array_merge(parent::getLogContext(), [
            'browserStateSize' => strlen($this->browserState)
        ]);
    }

}
