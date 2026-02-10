<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * Indexes usage:
 *
 * - updatedate - ResponseCleanerCommand, MongoCollectionCleanCommand
 * - partner, request.provider, request.login, queuedate - AdminController, search logs by provider, provider+login
 * - partner, response.state, response.checkDate, queuedate - RabbitmqRetryCheckRequestCommand (with options)
**/

/**
 * @MongoDB\Document
 * @MongoDB\Indexes({
 *   @MongoDB\Index(keys={"updatedate"="desc"}),
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
 *   })
 * })
 */
class AutoLogin extends BaseDocument
{

    public const METHOD_KEY = 'autologin';

    /** @MongoDB\Field(type="string") */
    private $queueName;

    public function __construct(array $request, string $partner, string $queueName)
    {
        $this->request = $request;
        $this->partner = $partner;
        $this->queueName = $queueName;
        $this->updatedate = new \DateTime();
        $this->queuedate = new \DateTime();
        $this->retries = 0;
    }

    public function getQueueName(): ?string
    {
        return $this->queueName;
    }

    public function setQueueName(string $queueName): self
    {
        $this->queueName = $queueName;
        return $this;
    }

    public function getAccountId(): ?string
    {
        return $this->accountId;
    }

    public function getExecutorKey() : string
    {
        return self::METHOD_KEY;
    }

}