<?php

namespace AppBundle\Command;

use AwardWallet\Common\Parsing\LuminatiProxyManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteOldLpmPorts extends Command
{
    protected static $defaultName = 'aw:delete-old-lpm-ports';

    private LuminatiProxyManager\Api $api;
    private LoggerInterface $logger;

    public function __construct(
        LuminatiProxyManager\Api $api,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->api = $api;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->setDescription(
            'Delete all old LPM ports'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Starting to delete luminati proxt ports');

        $this->logger->info('Getting all existing ports');
        $configs = $this->api->getProxiesEffectiveConfiguration();

        $this->logger->info('Start checking ports for expiration dates');
        $cnt = 0;

        foreach ($configs as $config) {
            $now = time();
            if ($now - (int) $config->internal_name > 900) {
                $this->logger->info("
                    Removing port {$config->port} after expiration date.\n
                    Exp date: {$config->internal_name}. Now: {$now}
                ");
                $this->api->deleteProxyPort($config->port);
                $cnt++;
            }
        }

        $this->logger->info("Number of remote ports: {$cnt}");
        return 0;
    }
}