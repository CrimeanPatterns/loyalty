<?php

namespace AppBundle\Document;

use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountResponse;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 * @MongoDB\Indexes({
 *   @MongoDB\Index(keys={"partner"="asc"}),
 *   @MongoDB\Index(keys={"updatedate"="desc"}),
 *   @MongoDB\Index(keys={"queuedate"="desc"}),
 * })
 */
class RegisterAccount extends BaseDocument
{

    public const METHOD_KEY = 'reward-availability-register';

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountRequest")
     * @var RegisterAccountRequest
     */
    protected $request;

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountResponse")
     * @var RegisterAccountResponse
     */
    protected $response;

    /** @MongoDB\Field(type="bool") */
    protected $isChecked = false;

    /** @MongoDB\Field(type="string") */
    protected $accountId;

    /**
     * @param RegisterAccountRequest $request
     * @return self
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return RegisterAccountRequest $request
     */
    public function getRequest(): ?RegisterAccountRequest
    {
        return $this->request;
    }

    /**
     * @param RegisterAccountResponse $response
     * @return self
     */
    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return RegisterAccountResponse $response
     */
    public function getResponse(): ?RegisterAccountResponse
    {
        return $this->response;
    }

    /**
     * @param bool $isChecked
     * @return self
     */
    public function setIsChecked(bool $isChecked): self
    {
        $this->isChecked = $isChecked;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsChecked(): bool
    {
        return $this->isChecked;
    }

    /**
     * @param string $accountId
     * @return self
     */
    public function setAccId(string $accountId): self
    {
        $this->accountId = $accountId;
        return $this;
    }

    /**
     * @return string
     */
    public function getAccId(): string
    {
        return $this->accountId;
    }

    public function getExecutorKey(): string
    {
        return self::METHOD_KEY;
    }
}
