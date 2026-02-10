<?php

namespace AppBundle\Document;


use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;


/**
 * @MongoDB\Document
 * @MongoDB\Indexes({
 *   @MongoDB\Index(keys={"partner"="asc"}),
 *   @MongoDB\Index(keys={"updatedate"="desc"}),
 *   @MongoDB\Index(keys={"queuedate"="desc"}),
 * })
 */
class RewardAvailability extends BaseDocument
{

    public const METHOD_KEY = 'reward-availability';

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest")
     * @var RewardAvailabilityRequest
     */
    protected $request;

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse")
     * @var RewardAvailabilityResponse
     */
    protected $response;

    /**
     * @return RewardAvailabilityRequest
     */
    public function getRequest(): ?RewardAvailabilityRequest
    {
        return $this->request;
    }

    /**
     * @param RewardAvailabilityRequest $request
     */
    public function setRequest($request): self
    {
        $this->request = $request;
        return $this;
    }

    public function getResponse(): ?RewardAvailabilityResponse
    {
        return $this->response;
    }

    /**
     * @param RewardAvailabilityResponse $response
     */
    public function setResponse($response): self
    {
        $this->response = $response;
        return $this;
    }


    public function getExecutorKey(): string
    {
        return self::METHOD_KEY;
    }
}