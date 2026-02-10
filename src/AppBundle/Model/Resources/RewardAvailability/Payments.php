<?php


namespace AppBundle\Model\Resources\RewardAvailability;


use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class Payments
{
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $currency;
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $originalCurrency;
    /**
     * @MongoDB\Field(type="float")
     * @Type("double")
     * @var float
     */
    private $conversionRate;
    /**
     * @MongoDB\Field(type="float")
     * @Type("double")
     * @var float
     */
    private $taxes;
    /**
     * @MongoDB\Field(type="float")
     * @Type("double")
     * @var float
     */
    private $fees;

    public function __construct(?string $currency, ?string $originalCurrency, ?float $conversionRate, ?float $taxes, ?float $fees = null)
    {
        $this->currency = $currency;
        $this->originalCurrency = $originalCurrency;
        $this->conversionRate = $conversionRate;
        $this->taxes = $taxes;
        $this->fees = $fees;
    }

    /**
     * @return float
     */
    public function getTaxes(): ?float
    {
        return $this->taxes;
    }

    /**
     * @return float
     */
    public function getFees(): ?float
    {
        return $this->fees;
    }

}