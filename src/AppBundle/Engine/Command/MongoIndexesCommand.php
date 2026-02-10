<?php

namespace AppBundle\Command;

use AppBundle\Document\CheckAccount;
use AppBundle\Document\CheckConfirmation;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MongoIndexesCommand extends Command
{

    /* Add Indexes into Mongo Document classes before executing */

    /** @var DocumentManager */
    private $manager;

    const TIMEOUT = null;

    public function __construct(DocumentManager $manager)
    {
        $this->manager = $manager;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Create mongo indexes from document classes. !!!Add Indexes into Mongo Document classes before executing');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->manager->getSchemaManager()->ensureIndexes(self::TIMEOUT);
        $output->writeln("OK. Indexes ensured successfully");
        $this->manager->getSchemaManager()->updateIndexes(self::TIMEOUT);
        $output->writeln("OK. Indexes updated successfully");
    }

}