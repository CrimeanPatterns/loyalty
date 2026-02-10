<?php
namespace AppBundle\Model\Resources\V2;

use JMS\Serializer\Annotation\Type;

class BaseCheckResponse extends \AppBundle\Model\Resources\BaseCheckResponse
{
    /**
     * @var \AwardWallet\Schema\Itineraries\Itinerary[]
     * @Type("array<AwardWallet\Schema\Itineraries\Itinerary>")
     */
    protected $itineraries;

    /**
     * @return \AwardWallet\Schema\Itineraries\Itinerary[]
     */
    public function getItineraries()
    {
        return $this->itineraries;
    }

    /**
     * @param \AwardWallet\Schema\Itineraries\Itinerary[] $itineraries
     * @return $this
     */
    public function setItineraries($itineraries)
    {
        $this->itineraries = $itineraries;
        return $this;
    }

}
