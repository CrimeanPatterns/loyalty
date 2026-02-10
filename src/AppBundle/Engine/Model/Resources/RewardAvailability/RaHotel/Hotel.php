<?php


namespace AppBundle\Model\Resources\RewardAvailability\RaHotel;


use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\EmbeddedDocument */
class Hotel
{
    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $name;

    /**
     * @MongoDB\Field(type="date")
     * @Type("DateTime<'Y-m-d\TH:i:sP'>")
     * @var \DateTime
     */
    private $checkInDate; // ??? array в примере строка

    /**
     * @MongoDB\Field(type="date")
     * @Type("DateTime<'Y-m-d\TH:i:sP'>")
     * @var \DateTime
     */
    private $checkOutDate;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $roomType;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $hotelDescription;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $numberOfNights;

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $pointsPerNight;

    /**
     * @MongoDB\Field(type="float")
     * @Type("double")
     * @var float
     */
    private $cashPerNight;

    /**
     * @MongoDB\Field(type="string")
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
    private $distance;

    /**
     * @MongoDB\Field(type="float")
     * @Type("double")
     * @var float
     */
    private $rating; // ?? float звездность бывает дробная?

    /**
     * @MongoDB\Field(type="integer")
     * @Type("integer")
     * @var integer
     */
    private $numberOfReviews;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $phone;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $url;

    /**
     * @MongoDB\Field
     * @Type("string")
     * @var string
     */
    private $preview;

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\RaHotel\DetailedAddress")
     * @Type("AppBundle\Model\Resources\RewardAvailability\RaHotel\DetailedAddress")
     * @var DetailedAddress
     */
    private $address;

    public function __construct(
        ?string $name,
        ?string $checkInDate,
        ?string $checkOutDate,
        ?string $roomType,
        ?string $hotelDescription,
        ?int $numberOfNights,
        ?int $pointsPerNight,
        ?float $cashPerNight,
        ?string $originalCurrency,
        ?float $conversionRate,
        ?float $distance,
        ?float $rating,
        ?int $numberOfReviews,
        ?string $phone,
        ?string $url,
        ?string $preview,
        DetailedAddress $address
    ) {
        $this->name = $name;
        if ($checkInDate) {
            $this->checkInDate = new \DateTime($checkInDate);
        }
        if ($checkOutDate) {
            $this->checkOutDate = new \DateTime($checkOutDate);
        }
        $this->roomType = $roomType;
        $this->hotelDescription = $hotelDescription;
        $this->numberOfNights = $numberOfNights;
        $this->pointsPerNight = $pointsPerNight;
        $this->cashPerNight = $cashPerNight;
        $this->originalCurrency = $originalCurrency;
        $this->conversionRate = $conversionRate;
        $this->distance = $distance;
        $this->rating = $rating;
        $this->numberOfReviews = $numberOfReviews;
        $this->phone = $phone;
        $this->url = $url;
        $this->preview = $preview;
        $this->address = $address;
    }

    /**
     * @return string
     */
    public function getPreview(): ?string
    {
        return $this->preview;
    }

    /**
     * @return string
     */
    public function getOriginalCurrency(): ?string
    {
        return $this->originalCurrency;
    }

    /**
     * @return float
     */
    public function getConversionRate(): ?float
    {
        return $this->conversionRate;
    }

}