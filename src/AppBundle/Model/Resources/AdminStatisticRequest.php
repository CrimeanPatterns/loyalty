<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use JMS\Serializer\Annotation\Type;

class AdminStatisticRequest implements LoyaltyRequestInterface
{
    /**
     * @var string
     * @Type("string")
     */
    private $partner;

    /**
     * @return string
     */
    public function getPartner()
    {
        return $this->partner;
    }

    /**
     * @param string $partner
     * @return $this
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;
        return $this;
    }

}
