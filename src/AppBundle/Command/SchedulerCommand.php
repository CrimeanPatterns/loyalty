<?php

namespace AppBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class SchedulerCommand extends Command
{

    /** @var LoggerInterface */
    private $logger;

    /** @var \Memcached */
    private $cache;
    private bool $partnerThreadLimits;
    private bool $rewardsAvailabilityMode;
    private bool $autoRegistrationOn;

    public function __construct(
        LoggerInterface $logger,
        \Memcached $cache,
        bool $partnerThreadLimits,
        bool $rewardsAvailabilityMode,
        bool $autoRegistrationOn
    ) {
        parent::__construct();

        $this->logger = $logger;
        $this->cache = $cache;
        $this->partnerThreadLimits = $partnerThreadLimits;
        $this->rewardsAvailabilityMode = $rewardsAvailabilityMode;
        $this->autoRegistrationOn = $autoRegistrationOn;
    }

    public function configure()
    {
        $this
            ->setDescription('run periodic jobs');
    }


    public function execute(InputInterface $input, OutputInterface $output)
    {
        declare(ticks=1);

        $output->writeln("scheduler started. this script will not stop, terminate it ctrl-c");

        pcntl_signal(SIGTERM, function () {
            $this->logger->info("scheduled stopped");
            exit();
        });


        do {
            if ($this->cache->add("loyalty_scheduler_1_hour", gethostname(), 3600)) {
                $this->logger->info("running 1 hour job");
                $this->exec("bin/console aw:clean-mongo -vv");
            }
            if ($this->cache->add("loyalty_scheduler_5_min", gethostname(), 300)) {
                $this->logger->info("running 5 minute jobs");
                $this->exec("bin/console aw:retry-check-requests -vv");
                $this->exec("bin/console aw:check-account-no-callback -vv");
                $this->exec("bin/console aw:delete-old-lpm-ports -vv");
            }
            if ($this->cache->add("loyalty_ua_cache_1_day", gethostname(), 86400)) { // 60 * 60 * 24
                $this->logger->info("running loyalty_ua_cache_1_day job");
                $this->exec("bin/console aw:check-accounts --count=1 --partner=awardwallet --provider=testprovider --login=top_user_agents -vv --synchronous");
            }
            if ($this->cache->add("loyalty_scheduler_30_sec", gethostname(), 30)) {
                $this->logger->info("running 30 sec jobs");
                // this command also clean up old threads, do not comment it out
                $this->exec("bin/console aw:thread-stats -vv");
                $this->exec("bin/console aw:publish-selenium-metrics -vv");
            }
            if ($this->rewardsAvailabilityMode) {
                if ($this->cache->add("loyalty_scheduler_1_day", gethostname(), 86400)) { // 60 * 60 * 24
                    $this->logger->info("running 1 day jobs");
                    $this->exec("bin/console aw:browser-state-cleaner -vv");
                }
                if ($this->cache->add("loyalty_scheduler_1_min", gethostname(), 60)) {
                    $this->logger->info("running 1 minute jobs");
                    $this->exec("bin/console aw:send-ra-to-aw -l 1 -c 100 -vv");
                    $this->exec("bin/console aw:reset-lockstate-ra-acc -vv");
                }
                if ($this->cache->add("loyalty_scheduler_7_min", gethostname(), 420)) {
                    $this->logger->info("running 7 minute jobs");
                    $this->exec("bin/console aw:keep-hot-session -vv");
                }
                if ($this->autoRegistrationOn && $this->cache->add("loyalty_scheduler_15_min", gethostname(), 900)) {
                    $this->logger->info("running 15 min jobs");
                    $this->exec("bin/console aw:auto-registration-ra -vv");
                }
            }
            if ($this->cache->add("loyalty_scheduler_retry_requests", gethostname(), 30 * 60)) {
                $this->logger->info("running aw:retry-check-requests");
                $this->exec("bin/console aw:retry-check-requests -vv");
            }
            if ($this->cache->add("loyalty_scheduler_response_cleaner", gethostname(), 86400)) {
                $this->exec("bin/console aw:response-cleaner -vv");
            }
            sleep(rand(3, 6));
        } while (true);
    }

    private function exec($command)
    {
        $this->logger->info($command);
        passthru($command, $exitCode);
        if ($exitCode != 0) {
            $this->logger->critical("{$command} failed with code $exitCode");
        }
    }

}