<?php

namespace AppBundle\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/** @MongoDB\MappedSuperclass */
abstract class BaseDocument
{
    /** @MongoDB\Id */
    protected $id;

    /** @MongoDB\Integer */
    protected $apiVersion;

    /** @MongoDB\Hash */
    protected $request;

    /** @MongoDB\Hash */
    protected $response;

    /** @MongoDB\Field */
    protected $partner;

    /** @MongoDB\Field */
    protected $method;

    /** @MongoDB\Date */
    protected $updatedate;

    /** @MongoDB\Date */
    protected $queuedate;

    /**
     * @MongoDB\Date
     * @var \DateTime
     */
    protected $firstcheckdate;

    /** @MongoDB\Integer */
    protected $throttledtime;

    /** @MongoDB\Integer */
    protected $parsingtime = 0;

    /** @MongoDB\Integer */
    protected $captchaTime = 0;

    /** @MongoDB\Boolean */
    protected $killed = false;

    /** @MongoDB\Integer */
    protected $killedCounter = 0;

    /** @MongoDB\Boolean */
    protected $reviewed;

    /** @MongoDB\Integer */
    protected $retries = -1;

    /** @MongoDB\Boolean */
    protected $inCallbackQueue;

    /** @MongoDB\Raw */
    protected $accountId;

    /** @var int see UserData::SOURCE_ constants, filled in only for awardwallet */
    /** @MongoDB\Field(type="int") */
    protected $source;

    /** @MongoDB\EmbedOne(targetDocument="RetriesState") */
    protected $retriesState;

    /**
     * Get id
     *
     * @return string $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * @param int $apiVersion
     * @return $this
     */
    public function setApiVersion($apiVersion)
    {
        $this->apiVersion = $apiVersion;
        return $this;
    }

    /**
     * Set request
     *
     * @param array $request
     * @return self
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Get request
     *
     * @return array $request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set response
     *
     * @param array $response
     * @return self
     */
    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Get response
     *
     * @return array $response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Set partner
     *
     * @param string $partner
     * @return self
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * Get partner
     *
     * @return string $partner
     */
    public function getPartner()
    {
        return $this->partner;
    }

    /**
     * Set method
     *
     * @param string $method
     * @return self
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Get method
     *
     * @return string $method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Set updatedate
     *
     * @var \DateTime $updatedate
     * @return self
     */
    public function setUpdatedate($updatedate)
    {
        $this->updatedate = $updatedate;
        return $this;
    }

    /**
     * Get updatedate
     *
     * @return \DateTime
     */
    public function getUpdatedate()
    {
        return $this->updatedate;
    }

    /**
     * @return \DateTime
     */
    public function getQueuedate()
    {
        return $this->queuedate;
    }

    /**
     * @param mixed $queuedate
     * @return $this
     */
    public function setQueuedate($queuedate)
    {
        $this->queuedate = $queuedate;
        return $this;
    }

    /**
     * Set reviewed
     *
     * @param boolean $reviewed
     * @return self
     */
    public function setReviewed($reviewed)
    {
        $this->reviewed = $reviewed;
        return $this;
    }

    /**
     * Get reviewed
     *
     * @return boolean $reviewed
     */
    public function getReviewed()
    {
        return $this->reviewed;
    }

    /**
     * @return self
     */
    public function setKilled()
    {
        $this->killed = true;
        return $this;
    }

    /**
     * @return boolean $killed
     */
    public function isKilled()
    {
        return $this->killed === true;
    }

    /**
     * @return int
     */
    public function getKilledCounter()
    {
        return $this->killedCounter;
    }

    /**
     * @return $this
     */
    public function incKilledCounter()
    {
        $this->killedCounter++;

        return $this;
    }

    /**
     * @return int
     */
    public function getRetries()
    {
        return $this->retries;
    }

    /**
     * @param int $retries
     * @return $this
     */
    public function setRetries($retries)
    {
        $this->retries = $retries;

        return $this;
    }

    public function getFirstCheckDate() : ?\DateTime
    {
        return $this->firstcheckdate;
    }

    /**
     * @param mixed $firstCheckDate
     * @return $this
     */
    public function setFirstCheckDate($firstCheckDate)
    {
        $this->firstcheckdate = $firstCheckDate;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getThrottledTime()
    {
        return $this->throttledtime;
    }

    /**
     * @param mixed $throttledTime
     * @return $this
     */
    public function setThrottledTime($throttledTime)
    {
        $this->throttledtime = $throttledTime;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getParsingTime()
    {
        return $this->parsingtime;
    }

    /**
     * @param mixed $parsingTime
     * @return $this
     */
    public function setParsingTime($parsingTime)
    {
        $this->parsingtime = $parsingTime;
        return $this;
    }

    /**
     * @return int
     */
    public function getCaptchaTime()
    {
        return $this->captchaTime;
    }

    /**
     * @param int $captchaTime
     */
    public function setCaptchaTime($captchaTime)
    {
        $this->captchaTime = $captchaTime;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getInCallbackQueue()
    {
        return $this->inCallbackQueue;
    }

    /**
     * @param mixed $inCallbackQueue
     * @return $this
     */
    public function setInCallbackQueue($inCallbackQueue)
    {
        $this->inCallbackQueue = $inCallbackQueue;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAccountId()
    {
        return $this->accountId;
    }

    /**
     * @param mixed $accountId
     * @return $this
     */
    public function setAccountId($accountId)
    {
        $this->accountId = $accountId;
        return $this;
    }

    public function getRetriesState(): ?RetriesState
    {
        return $this->retriesState;
    }

    public function setRetriesState(?RetriesState $retriesState): self
    {
        $this->retriesState = $retriesState;
        return $this;
    }

    public function isCancelled() : bool
    {
        return
            isset($this->response)
            && array_key_exists("state", $this->response)
            && array_key_exists("message", $this->response)
            && $this->response["state"] === ACCOUNT_UNCHECKED
        ;
    }

    public function getSource() : ?int
    {
        return $this->source;
    }

    public function setSource(?int $source): void
    {
        $this->source = $source;
    }

    abstract public function getExecutorKey() : string;

}