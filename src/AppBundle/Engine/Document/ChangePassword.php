<?php

namespace AppBundle\Document;

use AppBundle\Worker\CheckExecutor\ChangePasswordExecutor;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 * @MongoDB\Indexes({
 *   @MongoDB\Index(keys={"partner"="asc"}),
 *   @MongoDB\Index(keys={"updatedate"="desc"}),
 *   @MongoDB\Index(keys={"queuedate"="desc"}),
 *   @MongoDB\Index(keys={"firstcheckdate"="desc"}),
 *   @MongoDB\Index(keys={"request.provider"="asc"}),
 *   @MongoDB\Index(keys={"accountId"="asc"}),
 *   @MongoDB\Index(keys={
 *     "response.state"="asc",
 *     "response.checkDate"="asc",
 *     "partner"="asc",
 *     "queuedate"="desc"
 *   }),
 * })
 */
class ChangePassword extends BaseDocument {

    public const METHOD_KEY = 'change-password';

    public function getExecutorKey(): string
    {
        return self::METHOD_KEY;
    }
}
