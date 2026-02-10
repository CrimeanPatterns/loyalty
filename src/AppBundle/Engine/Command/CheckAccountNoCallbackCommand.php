<?php


namespace AppBundle\Command;


use AppBundle\Extension\Loader;
use Aws\CloudWatch\CloudWatchClient;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckAccountNoCallbackCommand extends Command
{

    /** @var Database */
    private $database;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var CloudWatchClient
     */
    private $cloudWatchClient;

    public function __construct(
        Database $replicaDatabase,
        LoggerInterface $logger,
        CloudWatchClient $cloudWatchClient,
        Loader $loader
    )
    {
        parent::__construct();

        $this->database = $replicaDatabase;
        $this->logger = $logger;
        $this->cloudWatchClient = $cloudWatchClient;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $collection = $this->database->selectCollection("CheckAccount");

        $cursor = $collection->aggregate(
            [
                [
                    '$match' => [
                        'method' => 'account',
                        'response.state' => ['$nin' => [ACCOUNT_UNCHECKED]],
                        'isPackageCallback' => true
                    ]
                ],
                [
                    '$match' => [
                        '$or' => [
                            ['inCallbackQueue' => ['$exists' => false]],
                            ['inCallbackQueue' => false]
                        ]
                    ]
                ],
                ['$count' => 'id']
            ]
        )->toarray();

        $count = !empty($cursor)? $cursor[0]->id : 0;
        $this->logger->info("check-account-no-callback", ["count" => $count]);
        $this->cloudWatchClient->putMetricData([
            'Namespace' => 'AW/Loyalty',
            'MetricData' => [
                [
                    'MetricName' => "check-account-no-callback",
                    'Timestamp' => time(),
                    'Value' => $count,
                ]
            ],
        ]);
    }

}