<?php

namespace AwardWallet\Engine\aeroplan\RewardAvailability;

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

        if (strpos($this->http->currentUrl(), '.aircanada.com') === false) {
            $this->http->Log('unexpected page');

            return false;
        }
        $this->saveResponse();
        $this->http->GetURL('https://www.aircanada.com/');

        if ($this->waitForElement(\WebDriverBy::xpath('//label[@for="bkmgFlights_tripTypeSelector_O"]'), 5)) {
            $this->saveResponse();

            $this->keepSession(true);

            return true;
        }

        $this->logger->error("unknown stage");

        return false;
    }
}
