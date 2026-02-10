<?php

namespace AppBundle\Command;

use AppBundle\Extension\PartnerSource;
use AppBundle\Extension\ThreadFactory;
use AppBundle\Extension\ThreadStats;
use Aws\CloudWatch\CloudWatchClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ThreadStatsCommand extends Command
{

    /**
     * @var ThreadStats
     */
    private $threadStats;
    /**
     * @var ThreadFactory
     */
    private $threadFactory;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var CloudWatchClient
     */
    private $cloudWatchClient;
    /**
     * @var PartnerSource
     */
    private $partnerSource;

    public function __construct(
        ThreadStats $threadStats,
        ThreadFactory $threadFactory,
        LoggerInterface $logger,
        CloudWatchClient $cloudWatchClient,
        PartnerSource $partnerSource
    )
    {
        parent::__construct();

        $this->threadStats = $threadStats;
        $this->threadFactory = $threadFactory;
        $this->logger = $logger;
        $this->cloudWatchClient = $cloudWatchClient;
        $this->partnerSource = $partnerSource;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $threadStatsInfo = $this->threadStats->getStats();

        $totalThreads = $this->threadFactory->getStats();
        $time = time();

        $metrics = [
            [
                'MetricName' => "total_threads",
                'Timestamp' => $time,
                'Value' => $threadStatsInfo->getTotal(),
                'Unit' => 'Count',
                'StorageResolution' => 1,
            ],
            [
                'MetricName' => "free_threads",
                'Timestamp' => $time,
                'Value' => $threadStatsInfo->getFree(),
                'Unit' => 'Count',
                'StorageResolution' => 1,
            ],
        ];

        foreach ($threadStatsInfo->getByPartner() as $partner => $threads) {
            if ($threads === 0) {
                continue;
            }

            $metrics[] = [
                'MetricName' => "threads",
                'Timestamp' => $time,
                'Value' => $threads,
                'Unit' => 'Count',
                'StorageResolution' => 1,
                'Dimensions' => [
                    ['Name' => 'partner', 'Value' => $partner],
                ],
            ];
        }

        $this->logger->info("thread stats: total: {$threadStatsInfo->getTotal()}, free: {$threadStatsInfo->getFree()}");

        while (count($metrics)) {
            $packet = array_splice($metrics, 0, 20);
            $this->cloudWatchClient->putMetricData([
                'Namespace' => 'AW/Loyalty',
                'MetricData' => $packet,
            ]);
        }

        foreach ($totalThreads as $partner => $threads) {
            $this->logger->info("partner sticky threads", ["partner" => $partner, "threads" => $threads]);
        }
    }

}