<?php


namespace AppBundle\Model\Resources\RewardAvailability\RaHotel;

use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class DetailedAddress
{

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $text;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $addressLine;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $city;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $stateName;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $countryName;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $postalCode;

    /**
     * @MongoDB\Field(type="float")
     * @Type("double")
     * @var float
     */
    private $lat;

    /**
     * @MongoDB\Field(type="float")
     * @Type("double")
     * @var float
     */
    private $lng;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $timezone;

    public function __construct(
        ?string $text,
        ?string $addressLine,
        ?string $city,
        ?string $stateName,
        ?string $countryName,
        ?string $postalCode,
        ?string $lat,
        ?string $lng,
        ?string $timezone
    ) {
        $this->text = $text;
        $this->addressLine = $addressLine;
        $this->city = $city;
        $this->stateName = $stateName;
        $this->countryName = $countryName;
        $this->postalCode = $postalCode;
        $this->lat = $lat;
        $this->lng = $lng;
        $this->timezone = $timezone;
    }


}