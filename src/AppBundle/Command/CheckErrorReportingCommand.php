<?php

namespace AppBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckErrorReportingCommand extends Command
{

    public static $defaultName = 'aw:check-error-reporting';
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();

        $this->logger = $logger;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->critical("testing critical");

        throw new \Exception("testing exception");
    }

}