<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 01.09.15
 * Time: 12:46
 */

namespace AppBundle\Command;

use AppBundle\Document\CheckAccount;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MQMessages\CheckPartnerMessage;
use AppBundle\Extension\MQSender;
use Doctrine\MongoDB\ArrayIterator;
use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RabbitmqRetryCheckRequestCommand extends Command
{

    /** @var DocumentManager */
    private $manager;
    /** @var LoggerInterface */
    private $logger;
    /** @var Loader */
    private $loader;
    /** @var MQSender */
    private $mqSender;

    protected function configure()
    {
        $this->addOption('repeat', null, InputOption::VALUE_NONE)
             ->addOption('partner', null, InputOption::VALUE_OPTIONAL, '', 'awardwallet')
             ->setDescription('Command checks requests in mongoDB with null queuedate field');
    }

    public function __construct(LoggerInterface $logger, Loader $loader, DocumentManager $manager, MQSender $mqSender)
    {
        $this->logger = $logger;
        $this->loader = $loader;
        $this->manager = $manager;
        $this->mqSender = $mqSender;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repeat = $input->getOption('repeat');
        $partner = $input->getOption('partner');

        $this->logger->pushProcessor([$this, "logProcessor"]);
        $this->logger->debug('RabbitmqRetryCheckRequestCommand started.');
        $documents = ['CheckAccount'/*, 'CheckConfirmation'*/];

        foreach ($documents as $clsItem){
            $this->logger->debug("Finding out of queue requests in {$clsItem} collection...");
            $count = $this->sendOutOfQueueRows($clsItem, $repeat, $partner);
            $this->logger->info("Out of queue {$clsItem} count rows = ".$count, ['repeated_requests_count' => $count]);
        }

        $this->logger->debug('RabbitmqRetryCheckRequestCommand finished.');
        $this->logger->popProcessor();
    }

    protected function sendOutOfQueueRows($odmClass, $repeat, $partner)
    {
        if ($repeat) {
            /** @var ArrayIterator $queryResult */
            $queryResult = $this->manager->createQueryBuilder('AppBundle\\Document\\'.$odmClass)
                ->field('partner')->equals($partner)
                ->field('response.state')->equals(ACCOUNT_UNCHECKED)
                ->field('response.checkDate')->equals(null)
                ->field('queuedate')->notEqual(null)
                ->getQuery()->execute();
        } else {
            /** @var ArrayIterator $queryResult */
            $queryResult = $this->manager->createQueryBuilder('AppBundle\\Document\\'.$odmClass)
                ->field('queuedate')->equals(null)
                ->getQuery()->execute();
        }


        $rows = $queryResult->toArray();
        if(empty($rows))
            return 0;

        $method = strtolower(substr($odmClass, 5));
        /** @var CheckAccount $row */
        foreach ($rows as $row) {
            $mqPartnerMsg = new CheckPartnerMessage($row->getId(), $method, $row->getPartner(), $row->getRequest()['priority']);

            $accountId = isset($row->getRequest()['userData']) ? $row->getRequest()['userData'] : 0;
            $inQueue = false;
            try {
                $this->mqSender->sendCheckPartner($mqPartnerMsg);
                $inQueue = true;
            } catch (\ErrorException $e){
                $this->logger->critical('Can not write message to rabbitMQ', [
                    'requestId' => $row->getId(), 'accountId' => $accountId, 'partner' => $row->getPartner()
                ]);
            }

            if($inQueue === false)
                continue;

            $this->logger->notice('repeatedly push to partner queue', [
                'requestId' => $row->getId(), 'accountId' => $accountId, 'partner' => $row->getPartner()
            ]);
            $row->setQueuedate(new \DateTime());
            $this->manager->persist($row);
            $this->manager->flush();
        }

        return count($rows);
    }

    public function logProcessor(array $record){
        $record['extra']['worker'] = 'aw:retry-check-requests';
        return $record;
    }

}