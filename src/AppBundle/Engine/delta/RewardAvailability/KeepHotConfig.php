<?php

namespace AwardWallet\Engine\delta\RewardAvailability;

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
        return 10;
    }

    public function getLimitLifeTime(): ?int
    {
        // close after N minutes
        return null;
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

        if (strpos($this->http->currentUrl(), '.delta.com') === false) {
            $this->http->Log('unexpected page');

            return false;
        }
        $this->saveResponse();
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        $this->waitForElement(\WebDriverBy::xpath("//label[normalize-space(@for)='shopWithMiles']"), 0);

        $logoImg = $this->waitForElement(\WebDriverBy::xpath("//img[@alt='Delta Air Lines']"), 0, false);

        if (!$logoImg) {
            $this->http->Log('page is not load');

            return false;
        }
        $logoImg->click();
        $this->saveResponse();

        $logoImg = $this->waitForElement(\WebDriverBy::xpath("//img[@alt='Delta Air Lines']"), 20);

        if (!$logoImg) {
            $this->http->Log('main page is not load');

            return false;
        }
        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        $this->saveResponse();

        $this->keepSession(true);

        return true;
    }
}
