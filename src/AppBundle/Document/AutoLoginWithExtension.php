<?php

namespace AppBundle\Document;

use AppBundle\Model\Resources\AutologinWithExtensionRequest;
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
class AutoLoginWithExtension extends BaseDocument implements EmbeddedDocumentsInterface
{

    public const METHOD_KEY = 'autologin_with_extension';

    /**
     * @MongoDB\EmbedOne(targetDocument="AppBundle\Model\Resources\AutologinWithExtensionRequest")
     * @var AutologinWithExtensionRequest
     */
    protected $request;

    public function getExecutorKey() : string
    {
        return self::METHOD_KEY;
    }

}