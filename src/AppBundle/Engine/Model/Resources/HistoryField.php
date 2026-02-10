<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class HistoryField
{
    /**
     * @var string
     * @Type("string")
     */
    private $name;
        
    /**
     * @var string
     * @Type("string")
     */
    private $value;

    public function __construct(string $name, string $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

}
