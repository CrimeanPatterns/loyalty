<?php


namespace AppBundle\Command;


use AppBundle\Document\RaAccount;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetLockAccountRACommand extends Command
{
    /** @var LoggerInterface */
    private $logger;
    /** @var DocumentManager */
    private $manager;

    protected static $defaultName = 'aw:reset-lockstate-ra-acc';
    private const RESET_TIME_LIMIT = 10;

    protected function configure()
    {
        $this
            ->setDescription('Reset LockState for RA-Account');
    }

    public function __construct(LoggerInterface $logger, DocumentManager $manager)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->manager = $manager;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('Reset LockState for RA-Account is started');

        $lastDate = (new \DateTime())->setTimestamp(strtotime(sprintf("-%d minutes", self::RESET_TIME_LIMIT)));

        $counter = 0;
        do {
            $findUpdExec = $this->manager->createQueryBuilder(RaAccount::class)
                ->findAndUpdate()
                ->returnNew()
                ->field('lockState')->equals(RaAccount::PARSE_LOCK)
                ->field('lastUseDate')->lt($lastDate)
                ->field('lockState')->set(RaAccount::PARSE_UNLOCK)
                ->getQuery()->execute();
            if ($findUpdExec) {
                $counter++;
            }
        } while ($findUpdExec);

        if ($counter){
            $this->logger->info("Reset LockState for {$counter} accounts");
        }

        $this->logger->info("Finished. Reset LockState is successful.\n");
    }
}