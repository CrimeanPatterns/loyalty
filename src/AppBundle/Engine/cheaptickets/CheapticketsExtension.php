<?php

namespace AwardWallet\Engine\cheaptickets;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class CheapticketsExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.cheaptickets.com/user/account?&langid=1033';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//button[@id="header-account-menu-signed-in"] | //form[@name="loginForm"]');

        return strstr($el->getNodeName(), "BUTTON");
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//article[@data-content-id="emailSegmentToggle"]')->click();

        return $tab->evaluate('//input[@id="new_email"]')->getValue();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="loginFormEmailInput"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="loginFormPasswordInput"]');
        $password->setValue($credentials->getPassword());

        sleep(1);

        $tab->evaluate('//button[@id="loginFormSubmitButton"]')->click();

        $submitResult = $tab->evaluate('//button[@id="header-account-menu-signed-in"] | //h3[@class="uitk-error-summary-heading"]');

        if (strstr($submitResult->getAttribute('class'), "uitk-error-summary-heading")) {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Email and password don't match. Please try again")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            return new LoginResult(true);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@id="header-account-menu-signed-in"]')->click();
        $tab->evaluate('//a[contains(@href, "/user/logout") and @id="account-signout"]')->click();
        $tab->evaluate('//button[@class="uitk-button uitk-button-medium uitk-button-has-text uitk-button-tertiary uitk-menu-trigger" and contains(text(), "Sign in")]');
    }
}
