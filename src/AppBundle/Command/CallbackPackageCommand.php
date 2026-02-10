<?php

declare(ticks = 1);

namespace AppBundle\Command;

use AppBundle\Extension\CallbackPackageProcessor;
use AppBundle\Extension\Loader;
use AppBundle\Extension\TimeCommunicator;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CallbackPackageCommand extends Command
{

    const DEFAULT_SLEEP_TIMEOUT = 30;

    protected static $defaultName = 'aw:callback-package';

    /** @var LoggerInterface */
    private $logger;

    /** @var CallbackPackageProcessor */
    private $processor;

    /** @var TimeCommunicator */
    private $time;

    public function __construct(LoggerInterface $logger, Loader $loader, CallbackPackageProcessor $processor, TimeCommunicator $time)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->processor = $processor;
        $this->time = $time;
    }

    protected function configure()
    {
        $this->setDescription('Partner callback package collector')
             ->addOption('sleep', 's', InputOption::VALUE_OPTIONAL, 'sleep timeout', self::DEFAULT_SLEEP_TIMEOUT);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $timeout = $input->getOption('sleep');

        $this->logger->pushProcessor([$this, "logProcessor"]);
        $this->logger->info("Callback Package Command started");

        pcntl_signal(SIGTERM, function() {
            $this->processor->stopProcess();
            exit();
        });

        while(true) {
            $canProcess = $this->processor->canProcess();
            if (!$canProcess) {
                $this->time->sleep($timeout);
                continue;
            }

            try {
                $this->logger->info("Callback Package processor run");
                $this->processor->run();
            } finally {
                $this->processor->stopProcess();
                $this->logger->info("Callback Package processor stop");
            }
        }

        $this->logger->popProcessor();
        return null;
    }

    public function logProcessor(array $record)
    {
        $record['extra']['worker'] = 'CallbackPackageProcessor';
        $record['extra']['WorkerPID'] = getmypid();
        return $record;
    }

}