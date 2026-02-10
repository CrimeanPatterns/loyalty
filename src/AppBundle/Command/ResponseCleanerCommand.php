<?php


namespace AppBundle\Command;


use AppBundle\Document\CheckAccount;
use Doctrine\MongoDB\Cursor;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResponseCleanerCommand extends Command
{
    /** @var LoggerInterface */
    private $logger;
    /** @var DocumentManager */
    private $manager;

    protected static $defaultName = 'aw:response-cleaner';
    private const UPDATE_ROWS_LIMIT = 50;

    protected function configure()
    {
        $this
            ->setDescription('Mongo CheckAccount collection response cleaner');
    }

    public function __construct(LoggerInterface $logger, DocumentManager $manager)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->manager = $manager;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('Response cleaner is started');
        $startDate = (new \DateTime())->setTimestamp(strtotime('-1 day'));

        $chunk = 100000;
        $skip = 0;
        $amount = 0;
        $updated = 0;
        $logTime = time();
        $items = [];
        while (true) {
            $findQuery = $this->manager->createQueryBuilder(CheckAccount::class)
                ->select(['_id', 'isClearedRow'])
                ->field('updatedate')->lt($startDate)
                ->limit($chunk)
                ->skip($skip)
                ->getQuery();

            $iterator = $findQuery->getIterator();
            $iterator->next();
            $isFirst = true;
            if (!$iterator->valid()) {
                break;
            }
            do {
                if (time() - $logTime >= 30) {
                    $this->logger->info("Processed {$amount} documents. Updated {$updated}", [
                        'memory_MB' => round(memory_get_usage() / (1024 * 1024), 2)
                    ]);
                    $logTime = time();
                }

                if ($amount % 1000 === 0) {
                    $this->manager->clear();
                }

                if (!$isFirst) {
                    $iterator->next();
                }
                $isFirst = false;
                if (!$iterator->valid()) {
                    continue;
                }
                /** @var CheckAccount $item */
                $item = $iterator->current();
                $amount++;
                if (true === $item->getIsClearedRow()) {
                    continue;
                }
                $items[] = $item->getId();
                $updated++;
                if (count($items) < self::UPDATE_ROWS_LIMIT) {
                    continue;
                }
                $this->updatePackage($items);
                $items = [];
            } while ($iterator->valid());
            $skip += $chunk;
        }
        if (count($items) > 0) {
            $this->updatePackage($items);
        }

        $this->logger->info("Finished. Cleared {$updated} documents from CheckAccount collection\n", [
            'memory_MB' => round(memory_get_usage() / (1024 * 1024), 2),
            'processed' => $amount,
        ]);
    }

    private function updatePackage(array $items)
    {
        $qb = $this->manager->createQueryBuilder(CheckAccount::class)
            ->updateMany()
            ->field('_id')->in($items)
            ->field('request.browserState')->unsetField()
            ->field('response.browserState')->unsetField()
            ->field('response.history')->unsetField()
            ->field('response.login')->unsetField()
            ->field('response.properties')->unsetField()
            ->field('response.itineraries')->unsetField()
            ->field('isClearedRow')->set(true);

        $result = $qb->getQuery()->execute();

        if (isset($result['ok']) && $result['ok'] == 1) {
//            $this->manager->clear();
//            $isEnd = $result['n'] !== self::UPDATE_ROWS_LIMIT;
//            $amount += $result['n'];
        } else {
            throw new \Exception('Failed with mongo error (collection CheckAccount): ' . $result['errmsg']);
        }

    }
}