<?php

namespace AppBundle\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StatusController
{

    /** @var LoggerInterface */
    private $logger;

    /** @var Connection */
    private $connection;

    /** @var DocumentManager */
    private $manager;

    public function __construct(LoggerInterface $logger, Connection $connection, DocumentManager $manager)
    {
        $this->logger = $logger;
        $this->connection = $connection;
        $this->manager = $manager;
    }

    /**
     * @Route("/elb-check", name="status")
     */
    public function statusAction()
    {
//        try {
            $this->connection->executeQuery("select now()")->fetchColumn(0);
            $this->manager->find('AppBundle\\Document\\CheckAccount', 'noThisId');
//        }
//        catch(\Exception $e){
//            $this->logger->warning("failed health check: " . $e->getMessage());
//            return new Response('failed', 500);
//        }
        return new Response('healthy');
    }

}