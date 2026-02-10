<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 01.09.15
 * Time: 12:46
 */

namespace AppBundle\Command;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestCapacityCommand extends ContainerAwareCommand
{
    /** @var Connection */
    protected $connection;
    /** @var Logger */
    private $logger;

    const TIMEOUT = 30;

    protected function configure() {
        $this->setName('aw:test-capacity')
             ->setDescription('test command')
             ->addOption('login', 'l', InputOption::VALUE_REQUIRED, 'partner login')
             ->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'sending requests to this url')
             ->addOption('timelimit', 't', InputOption::VALUE_OPTIONAL, 'time limit for requests sec.', 60);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // Get all dependencies
        $this->logger = $this->getContainer()->get("logger");
        $this->connection = $this->getContainer()->get("database_connection");

        $this->logger->info('Start test capacity command');

        if(empty($input->getOption('url'))){
            $this->logger->notice("Undefined url param");
            return;
        }

        if(empty($input->getOption('login'))){
            $this->logger->notice("Undefined login param");
            return;
        }

        $url = $input->getOption('url');
        $login = $input->getOption('login');
        $timeLimit = $input->getOption('timelimit');

        $sql = <<<SQL
            SELECT PartnerID, Login, Pass, CanDebug FROM Partner
            WHERE Login = :LOGIN
            AND LoyaltyAccess = 1
            AND State = 1
            AND CanDebug = 1
SQL;
        $result = $this->connection->executeQuery($sql, [':LOGIN' => $login])->fetch();
        if(!$result){
            $this->logger->notice("Undefined url param");
            return;
        }

//----------

        $headers = [
            sprintf('X-Authentication: %s', $login.':'.$result['Pass']),
            'Content-Type: application/json'
        ];
        $query = curl_init($url);
        if (!$query){
            $this->logger->notice("Can not init curl");
            return;
        }

        curl_setopt($query, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
        curl_setopt($query, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($query, CURLOPT_HEADER, false);
        curl_setopt($query, CURLOPT_FAILONERROR, false);
        curl_setopt($query, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, true);
        if (isset($jsonData)) {
            curl_setopt($query, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($query, CURLOPT_POSTFIELDS, $jsonData);
            $headers[] = 'Content-Length: ' . strlen($jsonData);
        }
        curl_setopt($query, CURLOPT_HTTPHEADER, $headers);

        $startTime = time();
        while (true && time() - $startTime < $timeLimit){
            $this->logger->notice('Sending curl to Loyalty', ['url' => $url]);
            $response = curl_exec($query);
            $this->logger->notice('Result curl to Loyalty', ['url' => $url, 'result' => $response]);
//            usleep(50);
        }

//        $code = curl_getinfo($query, CURLINFO_HTTP_CODE);
//        $error = curl_error($query);
//        if ($response === false || $code != '200')
//            $this->logger->critical("Loyalty curl failed, http code: $code, network error: " . curl_errno($query) . ' ' . curl_error($query));

        curl_close($query);

//----------

        $this->logger->info('Stop test capacity command');
        return;
    }

}