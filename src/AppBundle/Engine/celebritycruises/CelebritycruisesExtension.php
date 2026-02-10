<?php

namespace AwardWallet\Engine\celebritycruises;

use AwardWallet\Engine\royalcaribbean\RoyalcaribbeanExtension;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Tab;

class CelebritycruisesExtension extends RoyalcaribbeanExtension
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.celebritycruises.com/account/upcoming-cruises';
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//cruise-planner-main-nav-profile')->click();
        $tab->evaluate('//a[@data-sel="option-logout"]')->click();
        $tab->evaluate('//button[@class="open-free-text-itinerary-search"]');
    }
}
