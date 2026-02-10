<?php


namespace AppBundle\Model\Resources\RewardAvailability;

use JMS\Serializer\Annotation\Type;

class RewardAvailabilityFlights
{
    /**
     * @var string
     * @Type("string")
     */
    private $requestId;

    /**
     * @var string
     * @Type("string")
     */
    private $provider;

    /**
     * @var int
     * @Type("int")
     */
    private $passengers;

    /** @var array
     * @Type("array")
     */
    private $flights;

    /** @var array
     * @Type("array")
     */
    private $stats;

    /**
     * @Type("DateTime<'Y-m-d\TH:i:sP'>")
     * @var \DateTime
     */
    private $requestDate;

    /**
     * @var string
     * @Type("string")
     */
    private $depCode;

    /**
     * @var string
     * @Type("string")
     */
    private $arrCode;

   /**
     * @var string
     * @Type("string")
     */
    private $cabin;

   /**
     * @var string
     * @Type("string")
     */
    private $partner;

    public function __construct($requestId, $provider, $passengers, $flights, $stats, $requestDate, $depCode, $arrCode, $cabin, $partner)
    {
        $this->requestId = $requestId;
        $this->provider = $provider;
        $this->passengers = $passengers;
        $this->flights = $flights;
        $this->stats = $stats;
        $this->requestDate = $requestDate;
        $this->depCode = $depCode;
        $this->arrCode = $arrCode;
        $this->cabin = $cabin;
        $this->partner = $partner;
    }
}