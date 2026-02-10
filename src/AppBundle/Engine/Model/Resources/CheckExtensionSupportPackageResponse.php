<?php

namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\Annotation\Type;

class CheckExtensionSupportPackageResponse implements LoyaltyResponseInterface
{
    /**
     * @var bool[]
     * @Type("array<string, boolean>")
     */
    private $package;

    public function getPackage(): array
    {
        return $this->package;
    }

    public function setPackage(array $package): self
    {
        $this->package = $package;

        return $this;
    }
}