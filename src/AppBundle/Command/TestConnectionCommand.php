<?php

namespace AppBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestConnectionCommand extends Command
{

    protected static $defaultName = 'aw:test-connection';
    private Connection $connection;
    private DocumentManager $manager;

    public function __construct(Connection $connection, DocumentManager $manager)
    {
        parent::__construct();
        $this->connection = $connection;
        $this->manager = $manager;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->connection->executeQuery("select now()")->fetchColumn(0);
        $this->manager->find('AppBundle\\Document\\CheckAccount', 'noThisId');
        $output->writeln("ok");
    }

}