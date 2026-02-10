<?php

namespace AppBundle\Command;

use Aws\CloudWatch\CloudWatchClient;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PublishSeleniumMetricsCommand extends Command
{

    public static $defaultName = 'aw:publish-selenium-metrics';

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var \SeleniumFinderInterface
     */
    private $seleniumFinder;
    /**
     * @var CloudWatchClient
     */
    private $cloudWatchClient;

    public function __construct(LoggerInterface $logger, \SeleniumFinderInterface $seleniumFinder, CloudWatchClient $cloudWatchClient)
    {
        parent::__construct();

        $this->logger = new Logger('selenium-metrics', [new PsrHandler($logger)], [function (array $record) {
            $record['extra']['service'] = 'selenium-metrics';

            return $record;
        }]);
        $this->seleniumFinder = $seleniumFinder;
        $this->cloudWatchClient = $cloudWatchClient;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $servers = $this->seleniumFinder->getServers(new \SeleniumFinderRequest(\SeleniumFinderRequest::BROWSER_CHROMIUM, \SeleniumFinderRequest::CHROMIUM_80));
        $this->logger->info("selenium healthy servers", ["count" => count($servers)]);
        $this->cloudWatchClient->putMetricData([
            'Namespace' => 'AW/Loyalty',
            'MetricData' => [
                [
                    'MetricName' => "healthy-selenium-servers",
                    'Timestamp' => time(),
                    'Value' => count($servers),
                ]
            ],
        ]);
    }

}