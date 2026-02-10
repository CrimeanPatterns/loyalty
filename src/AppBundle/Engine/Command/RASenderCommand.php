<?php

namespace AppBundle\Command;

use AppBundle\Document\RewardAvailability;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Worker\CheckExecutor\RewardAvailabilityExecutor;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class RASenderCommand extends Command
{
    /** @var LoggerInterface */
    private $logger;
    /** @var Connection */
    private $connection;
    /** @var RewardAvailability */
    private $repo;
    /** @var DocumentManager */
    private $manager;
    /** @var \Memcached */
    private $cache;

    private $requestIds = [];
    private $sumErrors;
    private $memory;

    protected static $defaultName = 'aw:ra-sender';
    /**
     * @var OutputInterface
     */
    private $output;

    protected function configure()
    {
        $this
            ->setDescription('Reward Availability sender')
            ->addOption('auth', 'a', InputOption::VALUE_OPTIONAL, 'set auth login:pass')
            ->addOption('local-run', 'l', InputOption::VALUE_NONE, 'send to local url')
            ->addOption('provider', 'p', InputOption::VALUE_OPTIONAL, 'send only for provider (default:all)')
            ->addOption('cnt', 'c', InputOption::VALUE_REQUIRED, 'num requests per minutes', 60)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'time limit', 60 * 20)
            ->addOption('show-responses', null, InputOption::VALUE_REQUIRED, 'show N responses', 0)
        ;
    }

    public function __construct(LoggerInterface $logger, Connection $connection, DocumentManager $manager, \Memcached $cache)
    {
        parent::__construct();
        $this->logger = $logger;
        $this->connection = $connection;
        $this->cache = $cache;
        $this->manager = $manager;
        $this->repo = $manager->getRepository(RewardAvailability::class);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('RA-sender is started');
        $this->output = $output;

        $prov = $input->getOption('provider');
        if (!empty($prov) && preg_match("/^[a-z][a-z_\d]+$/", $prov)) {
            $providers = $this->connection->executeQuery("SELECT Code FROM Provider WHERE CanCheckRewardAvailability <> 0 AND Code = '{$prov}'"
            )->fetchAll(\PDO::FETCH_COLUMN);
            if (count($providers)!==1){
                $output->writeln("Wrong provider code " . $prov. " (no code or no CanCheckRewardAvailability)");

                return 1;
            }
        } else {
            $providers = $this->connection->executeQuery("SELECT Code FROM Provider WHERE CanCheckRewardAvailability <> 0 AND Code <> 'testprovider'"
            )->fetchAll(\PDO::FETCH_COLUMN);
        }

        $cnt = $input->getOption('cnt');
        $local = $input->getOption('local-run');
        $url = $local ? 'http://ra-loyalty.docker/v1/search' : 'https://ra.awardwallet.com/v1/search';
        $auth = $input->getOption('auth');
        if (empty($auth)) {
            $auth = 'awardwallet:awdeveloper';
        }
        $timeLimit = $input->getOption('limit');
        if (empty($timeLimit)) {
            $timeLimit = 60 * 20; // 20 min
        }
        $output->writeln('will send ' . $cnt . ' requests per minute');
        $output->writeln('to ' . $url . ' until there are 30% errors');
        $output->writeln('time limit ' . $timeLimit . ' sec');

        $this->sumErrors = 0;

        $parallel = (int)($cnt / 60) + 1;
        if ($parallel === 1 && $cnt < 60) {
            $seconds = (int)(60 / $cnt);
        } else {
            $seconds = 1;
        }

        $total = 0;
        $processTime = time();
        $this->memory = memory_get_usage();
        $this->logger->info('on starts memory: ' . $this->memory . ' byte(s)');
        $lastResponseCheckTime = 0;
        do {
            if ($this->cache->add("ra_sender_wait_time_" . $prov . $seconds . $parallel, gethostname(), $seconds)) {
                $requests = [];
                $startTime = microtime(true);
                $i = 0;
                do {
                    foreach ($providers as $provider) {
                        $i++;
                        $requests[] = $this->prepareRequest($provider, $auth, $url);
                        if ($parallel === $i) {
                            break 2;
                        }
                    }
                } while ($i < $cnt);
                $total += $i;
                $duration = microtime(true) - $startTime;
                $output->writeln("Prepare requests in " . round($duration, 3) . " seconds");
                $output->writeln($parallel . " per " . $seconds . " seconds");
                $this->logger->info('memory: ' . (memory_get_usage() - $this->memory) . ' byte(s)');

                $startTime = microtime(true);
                $results = $this->sendRequests($requests, $parallel);
                $duration = microtime(true) - $startTime;
                $this->showResults($results, $input->getOption('show-responses'));
                $output->writeln("#" . $total / $cnt . " finished in " . round($duration, 3) . " seconds");
                $this->logger->info("rate: " . round($this->sumErrors / $total, 3));
                $this->logger->info("total: " . $total);
                $this->logger->info("in process: " . count($this->requestIds));
                $this->logger->info('memory: ' . (memory_get_usage() - $this->memory) . ' byte(s)');
            }

            // we do not want to check responses too often
            $responseCheckTime = microtime(true);
            $timeBetweenChecks = $responseCheckTime - $lastResponseCheckTime;
            if (($timeBetweenChecks) < 1) {
                usleep(1000000 - $timeBetweenChecks * 1000000);
            }
            $lastResponseCheckTime = $responseCheckTime;

            $requestIds = $this->requestIds;
            foreach ($requestIds as $requestId) {
                $responseData = $this->getDataFromResponseById($requestId);
                if (null === $responseData) {
                    if (($key = array_search($requestId, $this->requestIds)) !== false) {
                        unset($this->requestIds[$key]);
                    }
                    $this->logger->info('[ERROR] not found row. requestId: ' . $requestId);
                    $this->sumErrors++;
                    continue;
                }
//                $this->logger->info('get requestId data'. $requestId);
//                $this->logger->info('memory: ' . (memory_get_usage() - $this->memory) . ' byte(s)');

                $checked = false;
                if ($responseData['state'] > 0) {
                    if (!in_array($responseData['state'], [1, 4, 9])) {
                        $this->logger->info('[ERROR] requestId: ' . $responseData['requestId'] . ' state: ' . $responseData['state']);
                        $this->sumErrors++;
                    }
                    $checked = true;
                } elseif (time() - $responseData['requestDateTime'] > 120) {
                    $this->logger->info('[ERROR] requestId: ' . $responseData['requestId'] . ' state: ' . $responseData['state']);
                    $this->sumErrors++;
                    $checked = true;
                }
                if ($checked && ($key = array_search($requestId, $this->requestIds)) !== false) {
                    unset($this->requestIds[$key]);
                }
            }
            if ($total && $this->sumErrors / $total > 0.3) {
                $this->logger->info("stop: errors over 30%. [" . "rate: " . round($this->sumErrors / $total, 3) . "]");
                break;
            }
            if (time() - $processTime > $timeLimit) {
                $this->logger->info("stop: time is over");
                break;
            }
        } while (true);

        return 0;
    }

    private function generateRoute($provider)
    {
        $airports = [
            'SFO', 'IAD', 'DFW', 'BOM', 'CDG', 'FCO', 'KIX', 'TLV', 'JFK', 'IST', 'SYD',
            'LAX', 'LHR', "CDG", 'MAD', 'ORD', 'NBO', 'MNL', 'MEX', 'BKK', 'COA', 'EZE',
            'BOM', 'ZRH', 'ALB', 'NRT', 'ADL', 'AUH', 'SIN', 'NRT', 'GLT', 'YVR', 'FLL'
        ];
        do {
            $randKeys = array_rand($airports, 2);
            $depCode = $airports[$randKeys[0]];
            $arrCode = $airports[$randKeys[1]];
        } while ($depCode == $arrCode);

        if ($provider === 'israel' && $depCode !== 'TLV' && $arrCode !== 'TLV') {
            $depCode = 'TLV';
        }
        return [$depCode, $arrCode];
    }

    private function prepareRequest($provider, $auth, $url) : array
    {
        $validRoutes = $this->cache->get(sprintf(RewardAvailabilityExecutor::KEY_VALID_ROUTES, $provider));
        if (is_array($validRoutes) && count($validRoutes) > 10) {
            switch (random_int(0, 3)) {
                case 3:
                {
                    [$depCode, $arrCode] = $this->generateRoute($provider);
                    break;
                }
                default:
                {
                    $validRoute = $validRoutes[array_rand($validRoutes)];
                    [$depCode, $arrCode] = explode('-', $validRoute);
                    break;
                }
            }
        } else {
            [$depCode, $arrCode] = $this->generateRoute($provider);
        }
        $daysPlus = random_int(2, 300);
        $date = date('Y-m-d', strtotime("+{$daysPlus} days"));

        $cabins = ['economy', 'business'];
        $cabin = $cabins[array_rand($cabins)];
        $request = [
            "provider" => $provider,
            "departure" => [
                "airportCode" => $depCode,
                "flexibility" => 0,
                "date" => $date
            ],
            "arrival" => $arrCode,
            "cabin" => $cabin,
            "passengers" => [
                "adults" => random_int(1,5)
            ],
            "callbackUrl" => "",
            "currency" => "USD",
            "priority" => 9,
            "userData" => "some_test_value"
        ];
        if ($provider === 'turkish') {
            $request['passengers']['adults'] = 1;
        }
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request),
            CURLOPT_HTTPHEADER => [
                'x-authentication: ' . $auth,
                'content-type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
        ];

        $curl = curl_init($url);
        curl_setopt_array($curl, $options);

        return [
            'curl' => $curl,
            'request' => $request,
        ];
    }

    private function showResults(array $results, int $showResponses) : void
    {
        $networkErrors = 0;
        $httpErrors = 0;
        $totalErrors = 0;
        $times = [];

        foreach ($results as $index => $result) {
            $error = false;

            if ($result['errno'] !== 0) {
                $networkErrors++;
                $error = true;
            }

            if ($result['info']['http_code'] !== 200) {
                $httpErrors++;
                $error = true;
            }

            if ($error) {
                $totalErrors++;
                $this->sumErrors++;
            } else {
                $this->requestIds[] = json_decode($result['response'], true)['requestId'];
            }

            if (($error && $totalErrors <= 5) || $index < $showResponses) {
                $this->output->writeln("--- response $index ---:\n" . json_encode($result, JSON_PRETTY_PRINT));
            }


            $times[] = $result['info']['total_time'];
        }

        $this->output->writeln("sent " . count($results) . " requests");
        $this->output->writeln("errors: " . $totalErrors . ", network: {$networkErrors}, http: {$httpErrors}");
        $this->logger->info('memory: ' . (memory_get_usage() - $this->memory) . ' byte(s)');
        if (count($times) > 0) {
            $this->output->writeln("average response time, sec: " . round(array_sum($times) / count($times), 3));
            $this->output->writeln("max response time, sec: " . round(max($times), 3));
            $this->output->writeln("min response time, sec: " . round(min($times), 3));
        }
    }

    private function sendRequests(array $requests, int $parallel) : array
    {
        $this->output->writeln("starting sending in $parallel threads");
        $multiHandler = curl_multi_init();
        $activeRequests = [];
        $responses = [];

        while (count($requests) > 0 || count($activeRequests) > 0) {

            while (false !== ($multiInfo = curl_multi_info_read($multiHandler))) {
                $request = $activeRequests[(int) $multiInfo['handle']];
                unset($activeRequests[(int) $multiInfo['handle']]);

                $responses[] = [
                    'request' => $request['request'],
                    'response' => curl_multi_getcontent($multiInfo['handle']),
                    'info' => curl_getinfo($multiInfo['handle']),
                    'errno' => curl_errno($multiInfo['handle']),
                    'error' => curl_error($multiInfo['handle']),
                ];

                curl_multi_remove_handle($multiHandler, $multiInfo['handle']);
                curl_close($multiInfo['handle']);
            }

            while(count($activeRequests) < $parallel && count($requests) > 0) {
                $request = array_shift($requests);
                curl_multi_add_handle($multiHandler, $request['curl']);
                $activeRequests[(int) $request['curl']] = $request;
            }

            $status = curl_multi_exec($multiHandler, $running);

            if ($status !== CURLM_OK) {
                throw new \Exception("multi curl error: $status");
            }

            if ($running) {
                curl_multi_select($multiHandler);
            }
        }
        $this->output->writeln('before close curl');
        $this->logger->info('memory: ' . (memory_get_usage() - $this->memory) . ' byte(s)');

        curl_multi_close($multiHandler);

        return $responses;
    }

    private function getDataFromResponseById(?string $requestId): ?array
    {
        if (!$requestId) {
            return null;
        }

        $row = $this->repo->find($requestId);
        if (empty($row)) {
            return null;
        }
//        $this->manager->refresh($row);
        $this->manager->clear();
        /** @var RewardAvailabilityResponse $response */
        $response = $row->getResponse();
        return [
            'state' => $response->getState(),
            'requestId' => $response->getRequestId(),
            'requestDateTime' => $response->getRequestdate()->getTimestamp()
        ];
    }

}