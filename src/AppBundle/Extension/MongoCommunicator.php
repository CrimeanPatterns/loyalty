<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 23/12/2016
 * Time: 12:45
 */

namespace AppBundle\Extension;

use AppBundle\Document\BaseDocument;
use AppBundle\Document\CheckAccount;
use Doctrine\MongoDB\ArrayIterator;
use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Logger;

class MongoCommunicator
{

    /** @var Logger */
    private $logger;
    /** @var DocumentManager */
    private $manager;
    /** @var TimeCommunicator */
    private $time;

    const MONGO_ROWS_LIMIT = 100;

    public function __construct(Logger $logger, DocumentManager $manager, TimeCommunicator $time)
    {
        $this->logger = $logger;
        $this->manager = $manager;
        $this->time = $time;
    }

    /**
     * Get checkAccount results needing to sent package callback
     * @param array $excludeIds
     * @return array
     */
    public function getPackageCallbacks($excludeIds = [])
    {
        $qb = $this->manager->createQueryBuilder(CheckAccount::class);
        $qb->select(['_id', 'partner', 'request.callbackUrl'])
           ->field('response.state')->notEqual(ACCOUNT_UNCHECKED)
           ->field('isPackageCallback')->equals(true)
           ->field('inCallbackQueue')->equals(false)
           ->field('callbackQueued')->equals(null);

        $queryResult1 = $qb->limit(self::MONGO_ROWS_LIMIT)
                           ->getQuery()->execute()->toArray();

        unset($qb);

        $qb = $this->manager->createQueryBuilder(CheckAccount::class);
        $qb->select(['_id', 'partner', 'request.callbackUrl'])
            ->field('response.state')->notEqual(ACCOUNT_UNCHECKED)
            ->field('isPackageCallback')->equals(true)
            ->field('inCallbackQueue')->equals(false)
            ->field('callbackQueued')->lt($this->time->getCurrentDateTime()->setTimestamp(strtotime("-2 minutes")));

        $queryResult2 = $qb->limit(self::MONGO_ROWS_LIMIT)
                           ->getQuery()->execute()->toArray();

        return array_merge($queryResult1, $queryResult2);
    }

    /**
     * @param array $package
     */
    public function updatePackageCallbacksIsSent(array $package)
    {
        foreach ($package as $id){
            $this->manager->createQueryBuilder(CheckAccount::class)
                          ->findAndUpdate()
                          ->field('_id')->equals($id)
                          // update
                          ->field('inCallbackQueue')->set(true)
                          ->getQuery()->execute();
        }
    }

    /**
     * @param string $id
     */
    public function setCallbackQueuedRow($id)
    {
        $this->manager->createQueryBuilder(CheckAccount::class)
                      ->findAndUpdate()
                      ->field('_id')->equals($id)
                      // update
                      ->field('callbackQueued')->set($this->time->getCurrentDateTime())
                      ->getQuery()->execute();
    }


    /**
     * Get rows from mongo collection by ids list
     * @param array $package
     * @param array|null $fields
     * @param $documentClass
     * @return BaseDocument[]
     */
    public function getRowsByIds(array $package, array $fields = null, $documentClass = CheckAccount::class)
    {
        if(empty($package))
            return [];

        $qb = $this->manager->createQueryBuilder($documentClass);
        if(isset($fields))
            $qb->select($fields);

        /** @var ArrayIterator $queryResult */
        $queryResult = $qb->field('_id')->in($package)
                          ->getQuery()->execute();

        return $queryResult->toArray();
    }
}