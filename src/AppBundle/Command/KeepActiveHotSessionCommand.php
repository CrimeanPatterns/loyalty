<?php

namespace AppBundle\Command;

use AppBundle\Extension\Loader;
use AwardWallet\Common\Selenium\HotSession\KeepActiveHotSessionManager;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class KeepActiveHotSessionCommand extends Command
{
    /** @var LoggerInterface */
    private $logger;

    /** @var Connection */
    private $connection;

    /** @var KeepActiveHotSessionManager */
    private $keepHotManager;

    public function __construct(
        LoggerInterface $logger,
        Connection $connection,
        KeepActiveHotSessionManager $keepHotManager,
        Loader $loader
    ) {
        parent::__construct();

        $this->logger = $logger;
        $this->connection = $connection;
        $this->keepHotManager = $keepHotManager;
    }

    protected function configure()
    {
        $this->setDescription('Run keep active hot sessions for Rewards Availability providers');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->pushProcessor(function(array $record){
            $record['extra']['worker'] = 'KeepActiveHotSession';
            return $record;
        });

        $this->logger->info('keep active hot sessions is started');

        $providers = $this->getRAProviders();
        $result = 0;
        foreach ($providers as $providerCode) {
            $resKeepHot = $this->keepHotManager->runKeepHot($providerCode);
            if ($resKeepHot && $resKeepHot->getErrors() != []){
                $result = 1;
                $this->logger->error(var_export($resKeepHot->getErrors(), true));
            }
        }

        $this->logger->info('keep active hot sessions is finished');

        return $result;
    }

    private function getRAProviders(): array
    {
        $sql = <<<SQL
            SELECT Code
            FROM Provider
            WHERE CanCheckRewardAvailability <> 0	
SQL;
        return array_map(function ($s) {
            return $s['Code'];
        }, $this->connection->executeQuery($sql)->fetchAllAssociative());
    }

}