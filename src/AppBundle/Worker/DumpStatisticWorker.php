<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 18/01/2017
 * Time: 15:26
 */

namespace AppBundle\Worker;

use Elastica\Client;
use Elastica\Document;
use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;

class DumpStatisticWorker extends TaskWorker
{

    /** @var Client */
    private $client;
    /** @var string */
    private $index;
    /** @var string */
    private $type;

    public function __construct(Logger $logger, Client $client, $index, $type, int $memoryLimit)
    {
        parent::__construct($logger, $memoryLimit);
        $this->client = $client;
        $this->index = $index;
        $this->type = $type;
        $this->logger = $logger;

        $this->logger->info("worker " . $this->name . " started");
    }

    public function execute(AMQPMessage $msg)
    {
        $this->checkLimits();
        $this->logger->pushProcessor([$this, "logProcessor"]);

        try {
            $data = $this->unserialize($msg);

            if ($data['Partner'] === 'juicymiles') {
                // we do not need stats from juicymiles
                return;
            }

            foreach (['RequestDate', 'CheckStartDate', 'CheckCompleteDate'] as $key) {
                if (!isset($data[$key])) {
                    continue;
                }

                $newKey = $key . "Time";
                if (isset($data[$newKey]) && is_string($data[$newKey])) {
                    continue;
                }

                $dateTime = is_string($data[$key]) ? new \DateTime($data[$key]) : $data[$key];
                $data[$newKey] = $dateTime->format('Y-m-d H:i:s');
                unset($data[$key]);
            }

            $requestDate = new \DateTime($data["RequestDateTime"]);
            $data["RequestDate"] = $requestDate->format('c');
            $q = floor((intval($requestDate->format("m")) - 1) / 4) + 1;
            $indexName = $this->index . "-" . $requestDate->format("Y") . ".q" . $q;

            foreach ($data as $key => $row) {
                if ($row instanceof \DateTime) {
                    $data[$key] = date_format($row, 'c');
                }
            }
            $data["@timestamp"] = date('c');

            $this->client->addDocuments([new Document('', $data, $this->type, $indexName)]);
            $this->logger->info('Statistic dumped', [
                'requestId' => isset($data['RequestID']) ? $data['RequestID'] : null,
                'partner' => $data['Partner'],
                'index' => $indexName
            ]);
        } catch (SkipException $e) {
            $this->logger->notice("skip: " . $e->getMessage());
        } finally {
            $this->logger->popProcessor();
        }
    }

}