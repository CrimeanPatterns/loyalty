<?php

namespace AwardWallet\Engine\british\RewardAvailability;

use AwardWallet\Common\Selenium\HotSession\KeepActiveHotConfig;

class KeepHotConfig extends KeepActiveHotConfig
{
    use \SeleniumCheckerHelper;

    private $closeOldSession = false;

    public function isActive(): bool
    {
        return true;
    }

    public function getCountToKeep(): int
    {
        if (time() < strtotime('04:00') || time() > strtotime('20:00')) {
            return 40;
        }

        return 20;
    }

    public function getInterval(): int
    {
        // refresh every N minutes
        return 8;
    }

    public function getLimitLifeTime(): ?int
    {
        return 30;
    }

    public function getAfterDateTime(): ?int
    {
        // все горячие созданные до указанного времени будут закрыты
        if ($this->closeOldSession) {
            $closeBefore = date('Y-m-d') . ' 11:45'; // UTC

            return strtotime($closeBefore);
        }

        return null;
    }

    public function run(): bool
    {
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        if (strpos($this->http->currentUrl(), '.britishairways.com') === false) {
            $this->http->Log('unexpected page');

            return false;
        }
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        if ($btn = $this->waitForElement(\WebDriverBy::xpath("//ba-button[contains(,'Stay logged in')]"), 0)) {
            $btn->click();
            sleep(2);
            $this->saveResponse();
        }

        $this->http->GetURL("https://www.britishairways.com");
        $this->waitForElement(\WebDriverBy::xpath("//a[contains(.,'Discover')]"), 10);
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());
        $this->saveResponse();

        if (strpos($this->http->currentUrl(), '.britishairways.com/travel/home') === false) {
            $this->logger->debug('unexpected check page');

            return false;
        }
        $script = "return sessionStorage.getItem('token');";
        $this->logger->debug("[run script]");
        $this->logger->debug($script, ['pre' => true]);
        $token = $this->driver->executeScript($script);

        if (!isset($token)) {
            $this->logger->debug('not found auth token');

            return false;
        }
        $this->logger->debug('token ' . $token);

        return true;
    }
}
