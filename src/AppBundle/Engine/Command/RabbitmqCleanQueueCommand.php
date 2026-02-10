<?php

namespace AppBundle\Command;

use PhpAmqpLib\Channel\AMQPChannel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RabbitmqCleanQueueCommand extends Command
{

    /** @var AMQPChannel */
    private $mqChanel;

    protected static $defaultName = 'aw:clean-rabbitmq';

    public function __construct(AMQPChannel $mqChanel)
    {
        parent::__construct();
        $this->mqChanel = $mqChanel;
    }

    protected function configure()
    {
        $this
            ->setDescription('RabbitMQ queue cleaner')
            ->addOption('queue', 'u', InputOption::VALUE_REQUIRED, 'clear this queue');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('queue'))) {
            $output->writeln("Undefined queue name param\n");
            return;
        }

        $queueName = $input->getOption('queue');

        $this->mqChanel->queue_purge($queueName);
        $output->writeln("OK. Queue \"{$queueName}\" cleared\n");
    }

}