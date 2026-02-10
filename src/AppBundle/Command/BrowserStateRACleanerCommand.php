<?php

namespace AppBundle\Command;

use AppBundle\Document\BrowserState;
use Doctrine\MongoDB\Cursor;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class BrowserStateRACleanerCommand extends Command
{

    /** @var LoggerInterface */
    private $logger;
    /** @var DocumentManager */
    private $manager;

    protected static $defaultName = 'aw:browser-state-cleaner';
    private const DELETE_ROWS_LIMIT = 100;

    protected function configure()
    {
        $this
            ->setDescription('Mongo BrowserState collection cleaner');
    }

    public function __construct(LoggerInterface $logger, DocumentManager $manager)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->manager = $manager;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('BrowserState cleaner is started');
        $startDate = (new \DateTime())->setTimestamp(strtotime("-21 day"));

        $findQuery = $this->manager->createQueryBuilder(BrowserState::class)->select('_id')
            ->field('createDate')
            ->lt($startDate)
            ->limit(self::DELETE_ROWS_LIMIT)
            ->getQuery();

        $amount = 0;
        do {
            /** @var Cursor $removeItems */
            $removeItems = $findQuery->execute();

            $qb = $this->manager->createQueryBuilder(BrowserState::class)->remove()->field('_id')->in(array_keys($removeItems->toArray()));
            $result = $qb->getQuery()->execute();

            if (isset($result['ok']) && $result['ok'] == 1) {
                $this->logger->info("Removed {$result['n']} documents");
                $this->manager->clear();
                $isEnd = $result['n'] !== self::DELETE_ROWS_LIMIT;
                $amount += $result['n'];
            } else {
                throw new \Exception("Failed with mongo error (collection BrowserState: " . $result['errmsg']);
            }

        } while (!$isEnd);

        $this->logger->info("Finished. Removed {$amount} documents from BrowserState collection\n");
    }
}