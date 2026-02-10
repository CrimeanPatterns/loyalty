<?php

namespace Tests\Functional\RewardAvailability;


use AwardWallet\Common\Selenium\HotSession\KeepActiveHotConfig;

/**
 * @ignore
 */
class KeepHotConfigParser extends KeepActiveHotConfig
{
    public static $success;
    public static $count;
    public static $time;
    public static $lifeTime;
    public static $checkParseMode;

    public static function reset(): void
    {
        self::$success = false;
        self::$count = 2;
	    self::$time = null;
	    self::$lifeTime = null;
	    self::$checkParseMode = null;
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getInterval(): int
    {
        return 1;
    }

    public function getLimitLifeTime(): ?int
    {
        return self::$lifeTime;
    }

    public function run(): bool
    {
        $this->logger->debug('Hello from parser');
        if (self::$checkParseMode && $this->getParseMode() === 'testParseMode') {
            return false; // for debug scenario like close all hots for parseMode='testParseMode'
        }
        $this->logger->warning(json_decode($this->parseMode));
        if (!self::$success) {
            // 1st - false, other true
            self::$success = true;
            return false;
        }
        $this->keepSession(true);
        return self::$success;
    }

    public function getCountToKeep(): int
    {
        return self::$count;
    }

    public function getAfterDateTime(): ?int
    {
        return self::$time;
    }
}