<?php

namespace AwardWallet\Engine\airfrance\RewardAvailability;

use AwardWallet\Common\Selenium\HotSession\KeepActiveHotConfig;

class KeepHotConfig extends KeepActiveHotConfig
{
    use \SeleniumCheckerHelper;

    public function isActive(): bool
    {
        return true;
    }

    public function getCountToKeep(): int
    {
        return 5;
    }

    public function getInterval(): int
    {
        // refresh every N minutes
        return 20; // 20 minutes and ask continue
    }

    public function getLimitLifeTime(): ?int
    {
        // close after N minutes
        return 60;
    }

    public function getAfterDateTime(): ?int
    {
        // close before date
        return null;
    }

    public function run(): bool
    {
        $curUrl = $this->http->currentUrl();

        $this->logger->debug('[currentUrl]: ' . $curUrl);

        if (strpos($curUrl, '.airfrance.') === false) {
            $this->http->Log('unexpected page');

            return false;
        }

        $btn = $this->waitForElement(\WebDriverBy::id('accept_cookies_btn'), 0);

        if ($btn) {
            $btn->click();
        }
        $btn = $this->waitForElement(\WebDriverBy::xpath('//bwc-logo-header/div/mat-toolbar/div//a[contains(@class,"bwc-logo-header")]/span[contains(@role,"img")]'),
            1);

        if (!$btn) {
            $this->http->Log('unexpected data on the page');

            return false;
        }
        $btn->click();
        sleep(3);

        $this->logger->debug('[currentUrl]: ' . $this->http->currentUrl());

        if (strpos($curUrl, '.airfrance.') === false) {
            $this->http->Log('unexpected page');

            return false;
        }
        $btn = $this->waitForElement(\WebDriverBy::xpath('//bwc-logo-header/div/mat-toolbar/div/a[contains(@class,"bwc-logo-header")]/span[contains(@role,"img")]'),
            1);

        if ($btn) {
            $this->keepSession(true);

            return true;
        }

        return false;
    }
}
