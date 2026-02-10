<?php

namespace AppBundle\Service;

use AwardWallet\Common\Monolog\Processor\TraceProcessor;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class ExceptionLogger
{
    private \SplObjectStorage $throwableStorage;
    private LoggerInterface $logger;
    private array $errorLevelOverrideMap;

    public function __construct(LoggerInterface $logger, array $errorLevelOverrideMap = [])
    {
        $this->throwableStorage = new \SplObjectStorage();
        $this->logger = $logger;
        $this->errorLevelOverrideMap = $errorLevelOverrideMap;
    }

    public function tryLog(\Throwable $e, string $prefix = '', array $mixinContext = []): void
    {
        if ($this->throwableStorage->contains($e)) {
            return;
        }

        $this->logger->log(
            $this->findErrorLevel($e),
            $prefix . \get_class($e) . ': ' . TraceProcessor::filterMessage($e),
            \array_merge(
                $mixinContext,
                ['stack' => $e->getTrace()]
            )
        );
        $this->throwableStorage->attach($e);
    }

    protected function findErrorLevel(\Throwable $e): int
    {
        $errorLevel = Logger::CRITICAL;

        foreach ($this->errorLevelOverrideMap as $class => $newErrorLevel) {
            if ($e instanceof $class) {
                $errorLevel = $newErrorLevel;

                break;
            }
        }

        return $errorLevel;
    }
}