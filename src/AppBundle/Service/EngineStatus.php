<?php

namespace AppBundle\Service;

use Psr\Log\LoggerInterface;

class EngineStatus
{

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var int
     */
    private $engineUpdateDate;
    /**
     * @var int
     */
    private $maxDelay;

    public function __construct(LoggerInterface $logger, int $maxDelay)
    {
        $this->logger = $logger;
        $this->maxDelay = $maxDelay;
    }

    public function isFresh(): bool
    {
        $file = __DIR__ . '/../Engine/.sync-time';
        if (file_exists($file)) {
            $engineUpdateDate = strtotime(file_get_contents($file));
        } else {
            $engineUpdateDate = null;
        }

        if (empty($engineUpdateDate)) {
            return true;
        }

        if (empty($this->engineUpdateDate)) {
            $this->engineUpdateDate = $engineUpdateDate;
        }

        $delay = $engineUpdateDate - $this->engineUpdateDate;
        if ($delay < -2) {
            $this->logger->warning("engine update date jumped back in time, from my {$this->engineUpdateDate} to {$engineUpdateDate} on share");

            return false;
        }

        if ($delay === 0) {
            return true;
        }

        if (($delay * 1000) > rand(0, $this->maxDelay * 1000)) {
            $this->logger->info(sprintf("engine folder updated, my stamp: %s, server stamp: %s, delay: %d",
                is_numeric($this->engineUpdateDate) ? date("c", $this->engineUpdateDate) . "(cache)" : $this->engineUpdateDate . "(marker)",
                is_numeric($engineUpdateDate) ? date("c", $engineUpdateDate) . "(cache)" : $engineUpdateDate . "(marker)", $delay));

            return false;
        }

        return true;
    }

}