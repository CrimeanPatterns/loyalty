<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'LogItem'.
 */
class LogItem
{
    /**
     * @var string
     * @Type("string")
     */
    private $updateDate;
        
    /**
     * @var string
     * @Type("string")
     */
    private $fileName;
            
    /**
     * @param string
     *
     * @return $this
     */
    public function setUpdatedate($updateDate)
    {
        $this->updateDate = $updateDate;

        return $this;
    }
    
    /**
     * @param string
     *
     * @return $this
     */
    public function setFilename($fileName)
    {
        $this->fileName = $fileName;

        return $this;
    }
            
    /**
     * @return string
     */
    public function getUpdatedate()
    {
        return $this->updateDate;
    }
    
    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->fileName;
    }
}
