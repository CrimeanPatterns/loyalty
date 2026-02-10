<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * Indexes usage:
 *
 * - updatedate - ResponseCleanerCommand, MongoCollectionCleanCommand
 * - queuedate - RabbitmqRetryCheckRequestCommand (without options)
 * - partner, request.provider, request.login, queuedate - AdminController, search logs by provider, provider+login
 * - partner, response.state, response.checkDate, queuedate - RabbitmqRetryCheckRequestCommand (with options)
**/

/**
 * @MongoDB\Document
 * @MongoDB\Indexes({
 *   @MongoDB\Index(keys={"updatedate"="desc"}),
 *   @MongoDB\Index(keys={"queuedate"="desc"}),
 *   @MongoDB\Index(keys={
 *     "partner"="asc",
 *     "request.provider"="asc",
 *     "request.login"="asc",
 *     "queuedate"="desc"
 *   }),
 *   @MongoDB\Index(keys={"request.provider"="asc"}),
 *   @MongoDB\Index(keys={
 *     "partner"="asc",
 *     "response.state"="asc",
 *     "response.checkDate"="asc",
 *     "queuedate"="desc"
 *   })
 * })
 */
class CheckConfirmation extends BaseDocument {

    public const METHOD_KEY = 'confirmation';

    public function getExecutorKey(): string
    {
        return self::METHOD_KEY;
    }
}
