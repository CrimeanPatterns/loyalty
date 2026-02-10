<?php

namespace AppBundle\Command;

use AppBundle\Document\RegisterAccount;
use AppBundle\Extension\Loader;
use Doctrine\MongoDB\Database;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ClearQueueCommand extends Command
{

    /** @var Logger */
    private $logger;

    /** @var Database */
    private $database;

    public function __construct(LoggerInterface $logger, Loader $loader, Database $database)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->database = $database;
    }

    public function logProcessor(array $record)
    {
        $record['extra']['worker'] = 'aw:clear-queue';
        return $record;
    }

    protected function configure()
    {
        $this
            ->addOption('partner', null, InputOption::VALUE_REQUIRED)
            ->addOption('priority', null, InputOption::VALUE_REQUIRED)
            ->setDescription('Clear queue');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->pushProcessor([$this, "logProcessor"]);
        try {
            $partner = $input->getOption('partner');
            if (empty($partner)) {
                throw new \Exception("partner parameter required");
            }

            $this->logger->info('looking for accounts, partner: ' . $partner);
            $count = 0;

            foreach (['CheckAccount', 'CheckConfirmation', 'RewardAvailability', 'RaHotel', 'RegisterAccount', 'AutoLogin', 'ChangePassword'] as $documentClass) {
                $this->logger->info("looking for {$documentClass}...");
                try {
                    $collection = $this->database->selectCollection($documentClass);
                    $criteria = [
                        'partner' => $partner,
                        'response.state' => ACCOUNT_UNCHECKED
                    ];
                    if ($input->getOption('priority') !== null) {
                        $criteria['request.priority'] = (int)$input->getOption('priority');
                        $this->logger->info("filtering by priority: " . $input->getOption('priority'));
                    }
                    $this->logger->info("looking for: " . json_encode($criteria));
                    do {
                        $fetched = 0;
                        $queryResult = $collection->find($criteria, ['_id'])
                            ->maxTimeMS(600000)
                            ->limit(50);

                        $this->logger->info("fetching...");
                        foreach ($queryResult as $item) {
                            $fetched++;
                            $this->logger->info("deleting $documentClass {$item['_id']}");
                            $collection->remove(['_id' => $item['_id']]);
                            $count++;
                        }
                    } while ($fetched > 0);
                } catch (ConnectionTimeoutException $exception) {
                    $this->logger->info("timeout, retry query");
                }
            }

            $this->logger->info("finished, deleted $count documents");
        } finally {
            $this->logger->popProcessor();
        }
    }

}