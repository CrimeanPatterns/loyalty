<?php


namespace AppBundle\Command;


use AppBundle\Extension\Loader;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerInterface;
use MongoDB\Database;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SearchViolationsByUserIdCommand extends Command
{
    protected static $defaultName = 'aw:search-violations';
    /** @var DocumentManager */
    private $manager;
    /** @var Connection */
    private $connection;
    /** @var Database */
    private $database;
    /** @var OutputInterface */
    private $output;
    /** @var Serializer */
    private $serializer;

    protected function configure()
    {
        $this
            ->setDescription('Search violations in a partner')
            ->addOption('partner', 'p', InputOption::VALUE_REQUIRED, 'partner login to scan')
            ->addOption('show-violations', 'c', InputOption::VALUE_OPTIONAL,
                'show top N userId sorted by names/userId ratio (default: 10)', 10);
    }

    public function __construct(
        Database $replicaDatabase,
        Connection $connection,
        DocumentManager $manager,
        SerializerInterface $serializer,
        Loader $loader
    ) {
        parent::__construct();
        $this->connection = $connection;
        $this->serializer = $serializer;
        $this->database = $replicaDatabase;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $collection = $this->database->selectCollection("CheckAccount");

        $this->output = $output;
        $partner = $input->getOption('partner');
        $output->writeln('Search violations in a ' . $partner . ' is started');


        $partner = $this->connection->executeQuery("SELECT Login FROM Partner WHERE Login = ?", [$partner])->fetchOne();
        if (!$partner) {
            $output->writeln("Wrong partner " . $partner . " (not found login in Partner)");
            $output->writeln("STOPPED");

            return 1;
        }
        $cnt = $input->getOption('show-violations');

        $cursor = $collection->aggregate([
            [
                '$match' => [
                    'partner' => ['$eq' => $partner],
                    'response.state' => ['$eq' => 1],
                    'response.checkDate' => ['$gte' => date("Y-m-d", strtotime("-14 day"))]
                ]
            ],
            [
                '$project' => [
                    '_id' => 1,
                    'updatedate' => 1,
                    'request.provider' => 1,
                    'request.userId' => 1,
                    'request.login' => 1,
                    'request.login2' => 1,
                    'response.checkDate' => 1,
                    'response.properties.name' => 1,
                    'response.properties.value' => 1
                ]
            ],
            ['$unwind' => ['path' => '$response.properties']],
            ['$match' => ['response.properties.name' => 'Name']],
            [
                '$group' => [
                    '_id' => [
                        'userId' => '$request.userId',
                        'name' => '$response.properties.value',
                        'provider' => '$request.provider',
                        'login' => '$request.login',
                        'login2' => '$request.login2'
                    ],
                    'cnt_name' => ['$sum' => 1],
                    'll' => ['$last' => ['id' => '$_id', 'checkDate' => '$response.checkDate']]
                ]
            ]
        ])->toarray();

        $output->writeln('start calculation');
        $stats = $this->calc($cursor, $output);
        $output->writeln('found records: ' . count($stats));

        if (count($stats) > $cnt) {
            $stats = array_slice($stats, 0, $cnt);
            $output->writeln('show first ' . $cnt);
        }
        $this->printResult($output, $stats);

        $output->writeln("STOPPED");
        return 0;
    }

    private function printResult($output, $stats){
        foreach ($stats as $row) {
            $output->writeln('');
            $output->writeln('userId: '.$row['userId'] . ' ' . $row['cnt'] .' names: '. json_encode($row['names']));
            $output->writeln(sprintf("%15s %40s %40s %40s %15s %24s", 'provider ', 'login ', 'login2 ', 'name ', 'normalizeName ',
                'requstId'));
            foreach ($row['data'] as $k => $item) {
                foreach ($item as $v) {
                    $output->writeln(sprintf("%15s %40s %40s %40s %15s %24s", $v['provider'] ?? '-', $v['login'] ?? '-',
                        $v['login'] ?? '-', $v['name'] ?? '-', $v['normalizeName'] ?? '-', $v['id'] ?? '-'));
                }
            }
        }
    }

    private function calc(array $cursor, $output): array
    {
        $preRes = [];

        foreach ($cursor as $item) {
            $normalizeName = $this->normalizeName(($item['_id']['name'] ?? ''));
            $preRes[$item['_id']['userId']][$normalizeName][] = [
                'userId' => $item['_id']['userId'],
                'provider' => $item['_id']['provider'],
                'login' => $item['_id']['login'],
                'login2' => $item['_id']['login2'] ?? null,
                'name' => $item['_id']['name'],
                'normalizeName' => $normalizeName,
                'id' => (string)$item['ll']['id'],
            ];
        }
        $result = [];
        $totalNames = 0;
        $totalNamesPlus = 0;
        foreach ($preRes as $userId => $item) {
            $names = array_keys($item);
            $totalNames += count ($names);
            if (count($names) > 1) {
                if ($this->notUnigueNames($names)) {
                    $totalNamesPlus += count($names);
                    $result[] = [
                        'userId' => $userId,
                        'data' => $item,
                        'cnt' => count($names),
                        'names' => $names
                    ];
                }
            }
        }

        if (count($preRes) > 0) {
            $output->writeln("AVG names per UserId: " . $totalNames / count($preRes));
        }
        if (count($result) > 0) {
            $output->writeln("AVG names per UserId (with 2+ names): " . $totalNamesPlus / count($result));
        }
        usort($result, [$this,'sortResult']);

        return $result;
    }

    private function sortResult($s1, $s2)
    {
        // discending
        return $s2['cnt'] <=> $s1['cnt'];
    }

    private function sort(string $s1, string $s2)
    {
        return mb_strlen($s1) <=> mb_strlen($s2);
    }

    private function notUnigueNames($names): bool
    {
        if (count($names)===1){
            return false;
        }
        $wrong = array_unique(array_filter($names, function ($s) {
            $len = mb_strlen($s);
            return $len > 0 &&  ($len % 2 == 0);
        }));
        if (!empty($wrong)) {
            return true;
        }
        usort($names, [$this,'sort']);
        $full = explode(' ', array_pop($names));
        foreach ($names as $name){
            $check = explode(' ', $name);
            if (count(array_intersect($check, $full))!==count($check)){
                return true;
            }
        }
        return false;
    }

    private function normalizeName(string $name): string
    {
        // from goldcrown/Transfer/titles.json
        $name = preg_replace("/\b(?:Captain|Dr|Miss|Mr|Mrs|Ms|Pastor|Prof|Prof\. Dr|Rev|Sr|D|Dra|Ing|NoPrefix|Sra|Srta|Frau|Frau Dipl\.-Ing|Frau Dr|Frau Ing|Frau Mag|Frau Prof|Frau Prof\. Dr|Herr|Herr Dipl\.-Ing|Herr Dr|Herr Ing|Herr Mag|Herr Mmag|Herr Prof|Herr Prof\. Dr|Sir|De Heer|Madame|Mevrouw|Monsieur|Frk|Fru|Hr|Mademoiselle|Dª|Eng|Engª|Exmo Sr|Exmª Srª|Canon|Dame|Lady|Lord|Dott|Dott\. ssa|Prof\. ssa|Sig|Sig\. ra|heer|Fr|Господин|Госпожа)\b\.?/iu",'',$name);
        $name = str_replace('*', '', $name);
        $nameArr = explode(' ', $name);
        $nameArr = array_map("trim", $nameArr);
        $nameArr = array_filter(array_map(function ($s) {
            return trim($s, "\t\n\r\0\x0B");
        }, $nameArr));
        $norm = array_map(function ($s) {
            return mb_strtoupper(mb_substr($s, 0, 1));
        }, $nameArr);
        $norm = array_unique($norm);
        sort($norm);
        return implode(' ', $norm);
    }
}