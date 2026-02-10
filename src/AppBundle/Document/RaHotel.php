<?php


namespace AppBundle\Document;


use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelResponse;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 * @MongoDB\Indexes({
 *   @MongoDB\Index(keys={"partner"="asc"}),
 *   @MongoDB\Index(keys={"updatedate"="desc"}),
 *   @MongoDB\Index(keys={"queuedate"="desc"}),
 * })
 */
class RaHotel extends BaseDocument
{
    const METHOD_KEY = 'reward-availability-hotel';

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest")
     * @var RaHotelRequest
     */
    protected $request;

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelResponse")
     * @var RaHotelResponse
     */
    protected $response;

    /**
     * @return RaHotelRequest
     */
    public function getRequest(): ?RaHotelRequest
    {
        return $this->request;
    }

    /**
     * @param RaHotelRequest $request
     * @return RaHotel
     */
    public function setRequest($request): RaHotel
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return RaHotelResponse
     */
    public function getResponse(): ?RaHotelResponse
    {
        return $this->response;
    }

    /**
     * @param RaHotelResponse $response
     * @return RaHotel
     */
    public function setResponse($response): RaHotel
    {
        $this->response = $response;
        return $this;
    }

    public function getExecutorKey(): string
    {
        return self::METHOD_KEY;
    }
}