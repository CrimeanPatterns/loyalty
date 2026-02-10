<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 01.09.15
 * Time: 12:46
 */

namespace AppBundle\Command;

use AppBundle\Extension\Watchdog;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WatchdogCommand extends Command
{

    /** @var Logger */
    private $logger;
    /** @var Watchdog */
    private $watchdog;
    private $requestId;

    public function __construct(Watchdog $watchdog, LoggerInterface $logger)
    {
        parent::__construct();

        $this->watchdog = $watchdog;
        $this->logger = $logger;
    }

    public function logProcessor(array $record)
    {
        $record['extra']['watchdog'] = gethostname() . '_' . getmypid();
        $record['extra']['worker'] = 'Watchdog';
        return $record;
    }

    public function logRequestId(array $record)
    {
        $record['extra']['requestId'] = $this->requestId;
        return $record;
    }

    protected function configure()
    {
        $this->setDescription('Check Account and Retrive by ConfNo Watchdog. Needs to kill processes over processing timeout implementation');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->pushProcessor([$this, "logProcessor"]);
        $this->logger->info("Watchdog started");

        $this->watchdog->run();

        $this->logger->popProcessor();
        return null;
    }

}