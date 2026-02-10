<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 26.01.16
 * Time: 17:26
 */

namespace AppBundle\Worker;

use AppBundle\Document\BaseDocument;
use AppBundle\Document\MethodMap;
use AppBundle\Service\EngineStatus;
use AppBundle\Worker\CheckExecutor\ExecutorInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use PhpAmqpLib\Message\AMQPMessage;
use Monolog\Logger;

class CheckWorker extends TaskWorker
{

    /** @var ExecutorInterface[] */
    private $executors = [];

    protected $executeTimeLimit = 0;
    protected $processedCount = 0;
    /**
     * @var EngineStatus
     */
    private $engineStatus;
    private DocumentManager $documentManager;

    public function __construct(Logger $logger, EngineStatus $engineStatus, DocumentManager $documentManager, int $memoryLimit)
    {
        parent::__construct($logger, $memoryLimit);
        $this->engineStatus = $engineStatus;
        $this->documentManager = $documentManager;
    }

    public function addExecutor(ExecutorInterface $executor): void
    {
        $this->executors[$executor->getMethodKey()] = $executor;
    }

    public function execute(AMQPMessage $msg)
    {
        $this->checkLimits();

        $msgArray = $this->unserialize($msg);
        ['id' => $id, 'method' => $methodKey] = $msgArray;

        $this->logger->pushProcessor(static function (array $record) use ($id, $methodKey) {
            $record['extra']['worker_executor'] = $methodKey;
            $record['extra']['requestId'] = $id;
            return $record;
        });
        try {
            if (!$this->engineStatus->isFresh()) {
                throw new ExitException("engine changed, exiting to reload scripts");
            }

            $executor = $this->executors[$methodKey] ?? null;
            if ($executor === null) {
                $this->logger->critical("No executor for method {$methodKey}", $msgArray);
                throw new SkipException("No executor for method {$methodKey}");
            }

            $row = $this->loadRow($methodKey, $id);
            $executor->execute($row);
            $this->processedCount++;
        } catch (SkipException $e) {
            $this->logger->notice("skip check message: " . $e->getMessage());
        } finally {
            $this->logger->popProcessor();
        }
    }

    private function loadRow(string $methodKey, string $id) : BaseDocument
    {
        $class = MethodMap::KEY_TO_CLASS[$methodKey] ?? null;
        if ($class === null) {
            throw new SkipException("Class not found for Method key: $methodKey");
        }

        $row = $this->documentManager->find($class, $id);
        if ($row === null) {
            throw new SkipException('Can not find row by requestId');
        }

        return $row;
    }

}