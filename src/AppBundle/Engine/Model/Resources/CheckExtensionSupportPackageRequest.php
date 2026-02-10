<?php

namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use JMS\Serializer\Annotation\Type;

class CheckExtensionSupportPackageRequest implements LoyaltyRequestInterface
{
    /**
     * @var PostCheckResponse[]
     * @Type("array<AppBundle\Model\Resources\CheckExtensionSupportRequest>")
     */
    private $package;
    /**
     * @return CheckExtensionSupportRequest[]
     */
    public function getPackage(): array
    {
        return $this->package;
    }
    /**
     * @param ?CheckExtensionSupportRequest[] $package
     * @return $this
     */
    public function setPackage(?array $package): self
    {
        $this->package = $package;

        return $this;
    }
}