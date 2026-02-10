<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class HistoryRow
{        
    /**
     * @var HistoryField[]
     * @Type("array<AppBundle\Model\Resources\HistoryField>")
     */
    protected $fields;
            
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }

    public function setFields($fields): self
    {
        $this->fields = $fields;

        return $this;
    }
            
    public function getFields(): ?array
    {
        return $this->fields;
    }
}
