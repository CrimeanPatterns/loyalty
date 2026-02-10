<?php

namespace AppBundle\Command;

use PhpAmqpLib\Channel\AMQPChannel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RabbitmqDeleteQueueCommand extends Command
{

    /** @var AMQPChannel */
    private $mqChanel;

    protected static $defaultName = 'aw:delete-rabbitmq';

    public function __construct(AMQPChannel $mqChanel)
    {
        parent::__construct();
        $this->mqChanel = $mqChanel;
    }

    protected function configure() {
        $this
            ->setDescription('RabbitMQ queue deleter')
            ->addOption('queue', 'u', InputOption::VALUE_REQUIRED, 'delete this queue');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* remove all partners queues */
//        /** @var ThrottlerService $throttler */
//        $throttler = $this->getContainer()->get('aw.loyalty_throttler');
//        $list = $throttler->getPartnersList();
//        foreach ($list as $partner => $vals){
//
//            $this->mqChanel->queue_delete(sprintf('loyalty_check_account_%s', $partner));
//            $this->mqChanel->queue_delete(sprintf('loyalty_check_account_%s_v2', $partner));
//        }
//        return;
        /* END remove all partners queues */


        if(empty($input->getOption('queue'))){
            $output->writeln("Undefined queue name param\n");
            return;
        }

        $queueName = $input->getOption('queue');

        $this->mqChanel->queue_delete($queueName);

        $output->writeln("OK. Queue \"{$queueName}\" deleted\n");

    }

}