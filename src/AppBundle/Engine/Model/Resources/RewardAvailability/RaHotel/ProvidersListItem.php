<?php


namespace AppBundle\Model\Resources\RewardAvailability\RaHotel;

use JMS\Serializer\Annotation\Type;


class ProvidersListItem
{

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
    private $shortName;

    public function __construct(string $code, string $displayName, string $shortName)
    {
        $this->code = $code;
        $this->displayName = $displayName;
        $this->shortName = $shortName;
    }

}