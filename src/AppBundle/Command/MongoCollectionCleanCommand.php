<?php

namespace AppBundle\Command;

use AppBundle\Document\AutoLogin;
use AppBundle\Document\CheckAccount;
use AppBundle\Document\CheckConfirmation;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RewardAvailability;
use Doctrine\ODM\MongoDB\Cursor;
use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MongoCollectionCleanCommand extends Command
{

    /** @var DocumentManager */
    private $manager;
    /** @var Logger */
    private $logger;

    private bool $rewardsAvailabilityMode;

    protected static $defaultName = 'aw:clean-mongo';

    const DELETE_ROWS_LIMIT = 1000;

    public function __construct(LoggerInterface $logger, DocumentManager $manager, bool $rewardsAvailabilityMode)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->manager = $manager;
        $this->rewardsAvailabilityMode = $rewardsAvailabilityMode;
    }

    protected function configure()
    {
        $this
             ->setDescription('Mongo collections old part cleaner');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->rewardsAvailabilityMode) {
            $this->cleanCollection(RewardAvailability::class);
            $this->cleanRegisterAccount();
        } else {
            $this->cleanCollection(CheckAccount::class);
            $this->cleanCollection(CheckConfirmation::class);
            $this->cleanCollection(AutoLogin::class);
            $this->cleanCollection(RaHotel::class);
        }

        // for debugging
        // $dt = (new \DateTime())->setTimestamp(strtotime("2016-11-17T10:00:38Z"));
        // $this->cleanCollection(CheckAccount::class, $dt);
    }

    protected function cleanCollection($class, \DateTime $startDate = null){
        $this->logger->info("Cleaning {$class} collection is started");
        $startDate = isset($startDate) ? $startDate : (new \DateTime())->setTimestamp(strtotime("-14 day"));

        $findQuery = $this->manager->createQueryBuilder($class)->select('_id')
                             ->field('updatedate')
                             ->lt($startDate)
                             ->limit(self::DELETE_ROWS_LIMIT)
                             ->getQuery();

        $amount = 0;
        do {
            /** @var Cursor $removeItems */
            $removeItems = $findQuery->execute();

            $qb = $this->manager->createQueryBuilder($class)->remove()->field('_id')->in(array_keys($removeItems->toArray()));
            $result = $qb->getQuery()->execute();

            if(isset($result['ok']) && $result['ok'] == 1){
                $this->logger->info("Removed {$result['n']} documents");
                $this->manager->clear();
                $isEnd = $result['n'] !== self::DELETE_ROWS_LIMIT;
                $amount += $result['n'];
            } else {
                throw new \Exception("Failed with mongo error (collection {$class}): ".$result['errmsg']);
            }

        } while(!$isEnd);

        $this->logger->info("Finished. Removed {$amount} documents from {$class} collection\n");
    }

    private function cleanRegisterAccount() {
        // TODO remake
        $class = RegisterAccount::class;
        $this->logger->info("Cleaning {$class} collection is started");
        $startDate = (new \DateTime())->setTimestamp(strtotime("-14 day"));

        $findQuery = $this->manager->createQueryBuilder($class)->select('_id')
                             ->field('updatedate')
                             ->lt($startDate)
                             ->field('isChecked')
                             ->equals(true)
                             ->limit(self::DELETE_ROWS_LIMIT)
                             ->getQuery();

        $amount = 0;
        do {
            /** @var Cursor $removeItems */
            $removeItems = $findQuery->execute();

            $qb = $this->manager->createQueryBuilder($class)->remove()->field('_id')->in(array_keys($removeItems->toArray()));
            $result = $qb->getQuery()->execute();

            if(isset($result['ok']) && $result['ok'] == 1){
                $this->logger->info("Removed {$result['n']} documents");
                $this->manager->clear();
                $isEnd = $result['n'] !== self::DELETE_ROWS_LIMIT;
                $amount += $result['n'];
            } else {
                throw new \Exception("Failed with mongo error (collection {$class}): ".$result['errmsg']);
            }

        } while(!$isEnd);

        $this->logger->info("Finished. Removed {$amount} documents from {$class} collection\n");
    }

}