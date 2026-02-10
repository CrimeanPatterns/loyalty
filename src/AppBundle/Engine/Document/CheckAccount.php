<?php

namespace AppBundle\Document;

use AppBundle\Model\Resources\CheckAccountRequest;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * Indexes usage:
 *
 * - updatedate - ResponseCleanerCommand, MongoCollectionCleanCommand
 * - queuedate - RabbitmqRetryCheckRequestCommand (without options)
 * - partner, accountId, queuedate - AdminController, search logs by accountId
 * - partner, request.provider, request.login, queuedate - AdminController, search logs by provider, provider+login
 * - partner, response.state, response.checkDate, queuedate - RabbitmqRetryCheckRequestCommand (with options)
 * - response.state, isPackageCallback, inCallbackQueue, callbackQueued - QueueInfoService
**/

/**
 * @MongoDB\Document
 * @MongoDB\Indexes({
 *   @MongoDB\Index(keys={"updatedate"="desc"}),
 *   @MongoDB\Index(keys={"queuedate"="desc"}),
 *   @MongoDB\Index(keys={
 *     "partner"="asc",
 *     "accountId"="asc",
 *     "queuedate"="desc"
 *   }),
 *   @MongoDB\Index(keys={
 *     "partner"="asc",
 *     "request.provider"="asc",
 *     "request.login"="asc",
 *     "queuedate"="desc"
 *   }),
 *   @MongoDB\Index(keys={
 *     "partner"="asc",
 *     "response.state"="asc",
 *     "response.checkDate"="asc",
 *     "queuedate"="desc"
 *   }),
 *   @MongoDB\Index(keys={
 *     "response.state"="asc",
 *     "isPackageCallback"="asc",
 *     "inCallbackQueue"="asc",
 *     "callbackQueued"="asc"
 *   })
 * })
 */
class CheckAccount extends BaseDocument {

    const METHOD_KEY = 'account';

    /** @MongoDB\Boolean */
    protected $isPackageCallback;

    /** @MongoDB\Date */
    protected $callbackQueued;

    /** @MongoDB\Field(type="bool") */
    protected $isClearedRow;

    /**
     * @return mixed
     */
    public function getIsPackageCallback()
    {
        return $this->isPackageCallback;
    }

    /**
     * @param mixed $isPackageCallback
     * @return $this
     */
    public function setIsPackageCallback($isPackageCallback)
    {
        $this->isPackageCallback = $isPackageCallback;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCallbackQueued()
    {
        return $this->callbackQueued;
    }

    /**
     * @param mixed $callbackQueued
     * @return $this
     */
    public function setCallbackQueued($callbackQueued)
    {
        $this->callbackQueued = $callbackQueued;
        return $this;
    }
    public function getIsClearedRow(): ?bool
    {
        return $this->isClearedRow;
    }

    public function setIsClearedRow($isClearedRow): self
    {
        $this->isClearedRow = $isClearedRow;
        return $this;
    }

    public function getExecutorKey() : string
    {
        return self::METHOD_KEY;
    }

}
