<?php

namespace AwardWallet\Engine\costravel;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class CostravelExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.costcotravel.com/h=5001';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        if ($tab->getUrl() == 'https://www.costcotravel.com/') {
            $tab->gotoUrl('https://www.costcotravel.com/h=5001');
        }

        $el = $tab->evaluate('//form[@id="localAccountForm"] | //td[@id="spanMembershipNumber_desktop"]');

        return $el->getNodeName() == "TD";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//td[@id="spanMembershipNumber_desktop"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="signInName"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="next"]')->click();

        $submitResult = $tab->evaluate('//td[@id="spanMembershipNumber_desktop"] | //div[contains(@class, "error") and @style="display: block;"]/p', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'TD') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The email address and/or password you entered are invalid.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@id="loginContentDiv"]')->click();
        $tab->evaluate('//a[@id="linkLogout"]')->click();
        $tab->evaluate('//a[@id="yourItineraryMemberAccount"]');
    }
}
