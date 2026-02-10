<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 18/04/2018
 * Time: 17:41
 */

namespace AppBundle\Listener;


use AppBundle\Event\CheckAccountFinishEvent;
use AppBundle\Model\Resources\CheckAccountRequest;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Serializer;

class PasswordRemoveListener
{
    /** @var DocumentManager */
    private $dm;
    /** @var Serializer */
    private $serializer;

    public function __construct(DocumentManager $dm, Serializer $serializer)
    {
        $this->dm = $dm;
        $this->serializer = $serializer;
    }

    public function onCheckAccountFinish(CheckAccountFinishEvent $event)
    {
        $row = $event->getRow();
        /** @var CheckAccountRequest $request */
        $request = $this->serializer->deserialize(json_encode($row->getRequest()), CheckAccountRequest::class, 'json');
        $request->setPassword(null);
        $row->setRequest(json_decode($this->serializer->serialize($request, 'json'), true));

        $this->dm->persist($row);
        $this->dm->flush();
    }

}