<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 01.09.15
 * Time: 12:46
 */

namespace AppBundle\Command;

use AppBundle\Document\CheckAccount;
use AppBundle\Extension\MQMessages\CheckPartnerMessage;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Serializer;
use Monolog\Handler\ElasticSearchHandler;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/* Developing scripts (some debug or fake behavior)*/
class TestCommand extends ContainerAwareCommand
{

    /** @var Logger */
    private $logger;
    /** @var Producer */
    protected $producer;
    /** @var Serializer */
    protected $serializer;
    /** @var AMQPChannel $mqChannel */
    private $mqChannel;

    protected function configure() {
        $this->setName('aw:test-delayed')
             ->setDescription('test delayed command');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null
     */
    protected function _dev_notify_execute(InputInterface $input, OutputInterface $output)
    {
        // Get all dependencies
        $logger = $this->getContainer()->get("logger");

        $title = 'aa new reservation type';
        $message = "Title: {$title}<br/>AccountID: " . 123321
            . "<br/>UserID: " . 9990
            . "<br/>Partner: " . 'awardwallet'
            . "<br/>Login: " . preg_replace('#^\d{12}(\d{4})$#ims', '...$1', 'MyNewLogin');


        $logger->info('Start test command');
        for ($i=0; $i<10; $i++)
            $logger->notice("Log item $i");

//        $logger->alert($message, ['DevNotification' => true, 'EmailSubject' => $title]);
        $this->logger->critical('Some critical error');
        return;
    }


//    protected function _filling_fake_partners_stat_to_elastic_execute(InputInterface $input, OutputInterface $output)
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Logger $logger */
        $logger = $this->getContainer()->get('monolog.logger.statistic');


        $a = 1;
    }

//    protected function execute(InputInterface $input, OutputInterface $output){ return; }
}