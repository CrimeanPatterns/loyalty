<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use JMS\Serializer\Annotation\Type;

class CheckAccountPackageRequest implements LoyaltyRequestInterface
{
    /**
     * @var CheckAccountRequest[]
     * @Type("array<AppBundle\Model\Resources\CheckAccountRequest>")
     */
    private $package;

    /**
     * @param array
     *
     * @return $this
     */
    public function setPackage($package)
    {
        $this->package = $package;

        return $this;
    }

    /**
     * @return array
     */
    public function getPackage()
    {
        return $this->package;
    }

}
