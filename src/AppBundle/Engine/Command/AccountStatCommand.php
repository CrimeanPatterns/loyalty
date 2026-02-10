<?php

namespace AppBundle\Command;

use AppBundle\Extension\Loader;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AccountStatCommand extends Command
{

    protected static $defaultName = 'aw:account-stat';
    /**
     * @var Database
     */
    private $database;
    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(Database $replicaDatabase, Loader $loader)
    {
        parent::__construct();
        $this->database = $replicaDatabase;
    }

    public function configure()
    {
        $this
            ->addOption('partner', null, InputOption::VALUE_REQUIRED)
            ->addOption('unprocessed', null, InputOption::VALUE_NONE)
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $collection = $this->database->selectCollection("CheckAccount");

        $criteria = [];
        if ($input->getOption('partner')) {
            $criteria['partner'] = $input->getOption('partner');
            $output->writeln("limiting search to partner: " . $criteria['partner']);
        }
        if ($input->getOption('unprocessed')) {
            $criteria['response.state'] = ACCOUNT_UNCHECKED;
        }

        $cursor = $collection->find($criteria, [
            'projection' => [
                'partner' => 1,
                'queuedate' => 1,
                'request.login' => 1,
                'request.login2' => 1,
                'request.provider' => 1,
                'request.priority' => 1,
                'request.userId' => 1,
            ],
            'maxTimeMS' => 600000,
        ]);

        $stats = $this->calcStats($cursor);
        $output->writeln(json_encode($stats, JSON_PRETTY_PRINT));
    }

    private function calcStats(\MongoDB\Driver\Cursor $cursor) : array
    {
        $this->output->writeln("loading accounts..");

        $byTime = [
            1 => 0,
            2 => 0,
            3 => 0,
            7 => 0,
            30 => 0,
            90 => 0,
            180 => 0,
            365 => 0,
        ];

        $uniqueAccounts = [];
        $total = 0;
        $progressTime = time();
        $priorities = [];
        $byUser = [];
        $byPartner = [];
        $byProvider = [];

        foreach ($cursor as $item) {
            /** @var UTCDateTime $item['queuedate'] */
            $date = $item["queuedate"]->toDateTime()->getTimestamp();
            $daysAgo = (int)floor((time() - $date) / 86400);
            foreach (array_keys($byTime) as $days) {
                if ($daysAgo <= $days || $days === 365) {
                    $byTime[$days]++;
                    break;
                }
            }

            $accountKey = $item["request"]["provider"] . "-" . $item["request"]["login"] . "-" . ($item["request"]["login2"] ?? '');
            $uniqueAccounts[$accountKey] = array_merge($uniqueAccounts[$accountKey] ?? [], [date("Y-m-d H:i:s", $date)]);

            $priorities[$item['request']['priority']] = ($priorities[$item['request']['priority']] ?? 0) + 1;

            $byUser[$item['request']['userId']] = ($byUser[$item['request']['userId']] ?? 0) + 1;
            $byPartner[$item['partner']] = ($byPartner[$item['partner']] ?? 0) + 1;
            $byProvider[$item["request"]["provider"]] = ($byProvider[$item["request"]["provider"]] ?? 0) + 1;

            $total++;
            if (time() > ($progressTime + 30)) {
                $progressTime = time();
                $this->output->writeln("loaded $total accounts");
            }
        }

        $this->output->writeln("done, processed $total accounts");

        uasort($uniqueAccounts, function(array $a, array $b){
            return $b <=> $a; // reverse sort, not bug
        });

        return [
            'byTime' => $byTime,
            'total' => $total,
            'uniqueAccounts' => [
                'total' => count($uniqueAccounts),
                'top20' => array_map(function(array $dates) {
                    rsort($dates);
                    return [
                        'checkCount' => count($dates),
                        'last10' => array_slice($dates, 0, 10),
                    ];
                }, array_slice($uniqueAccounts, 0, 20)),
            ],
            'priorities' => $priorities,
            'users' => count($byUser),
            'accountPerUser' => array_sum($byUser) / count($byUser),
            'byPartner' => $byPartner,
            'byProvider' => $byProvider,
        ];
    }

}