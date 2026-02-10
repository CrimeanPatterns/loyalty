<?php

namespace AppBundle\Controller\Common;


use AppBundle\Model\Resources\QueueInfoItem;
use AppBundle\Model\Resources\QueueInfoResponse;
use Doctrine\MongoDB\ArrayIterator;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class QueueInfoService
{

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var DocumentManager */
    private $manager;

    public function __construct(TokenStorageInterface $tokenStorage, DocumentManager $manager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->manager = $manager;
    }

    public function queueInfo(string $rowCls): QueueInfoResponse
    {
        $partner = $this->tokenStorage->getToken()->getUser()->getUsername();

        /* mongo query equal:
         * db.getCollection('CheckAccount').aggregate(
         *    {$match: {partner: "awardwallet", "response.state": 0}},
         *    {$project: {"request.provider":1, count: {$add: [1]}}},
         *    {$group: {_id: "$request.provider", number: {$sum: "$count"}}}
         * );
         */
        /** @var ArrayIterator $queryResult */
        $queryResult = $this->manager
            ->createQueryBuilder($rowCls)
            ->group(['request.provider' => 1, 'request.priority' => 1], ['count' => 0, 'ids' => []])
            ->reduce('
                        function (obj, prev) { 
                            prev.count++; 
                            prev.ids.push(obj._id.valueOf()); 
                        }
                    ')
            ->field('partner')->equals($partner)
            ->field('response.state')->equals(ACCOUNT_UNCHECKED)
            ->field('response.checkDate')->equals(null)
            ->field('queuedate')->notEqual(null)
            ->sort('count', 'desc')
            ->getQuery()->execute();

        $rows = $queryResult->toArray();

        $result = [];
        foreach ($rows as $row) {
            $item = (new QueueInfoItem())->setProvider($row['request.provider'])->setItemsCount($row['count'])->setPriority($row['request.priority'])->setIds($row['ids']);
            $result[] = $item;
        }

        return (new QueueInfoResponse())->setQueues($result);
    }

}