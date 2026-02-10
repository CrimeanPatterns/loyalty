<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'PostCheckAccountResponse'.
 */
class AdminLogsResponse implements LoyaltyResponseInterface
{
    /**
     * @var LogItem[]
     * @Type("array<AppBundle\Model\Resources\LogItem>")
     */
    private $files;

    /**
     * @var string
     * @Type("string")
     */
    private $bucket;

    /**
     * @return LogItem[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param LogItem[] $files
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
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * @param string $bucket
     * @return $this
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
        return $this;
    }

}
