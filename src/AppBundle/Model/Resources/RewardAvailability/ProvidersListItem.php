<?php


namespace AppBundle\Model\Resources\RewardAvailability;

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
    /**
     * @var array
     * @Type("array<string>")
     */
    private $supportedCurrencies;
    /**
     * @var int
     * @Type("integer")
     */
    private $supportedDateFlexibility;

    public function __construct(string $code, string $displayName, string $shortName, array $supportedCurrencies, int $supportedDateFlexibility)
    {
        $this->code = $code;
        $this->displayName = $displayName;
        $this->shortName = $shortName;
        $this->supportedCurrencies = $supportedCurrencies;
        $this->supportedDateFlexibility = $supportedDateFlexibility;
    }

}