<?php

namespace AwardWallet\Engine\dsw;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class DswExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        if ($options->login2 == 'Canada') {
            return 'https://www.dsw.ca/profile';
        }

        return 'https://www.dsw.com/profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="username-field"] | //p[@id="profile-contact-email"]');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//p[@id="profile-contact-email"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="username-field"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@type="submit" and contains(text(), "SIGN IN")]')->click();

        $submitResult = $tab->evaluate('//p[@id="profile-contact-email"] | //div[@class="inline-server-error ng-star-inserted"] | //span[@id="form-error-text"] | //div[@class="error ng-star-inserted" and text()]');

        if ($submitResult->getNodeName() == 'P') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "This combination of user name and password is invalid.")
                || strstr($error, "The email address is incorrect. Make sure the format is correct (abc@wxyz.com) and try again.")
                || strstr($error, "Please enter a password.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@id="account-nav-menu-icon"]')->click();
        $tab->evaluate('//a[@role="option" and @href="#"]')->click();
        $tab->evaluate('//span[contains(text(), "Sign in")]');
    }
}
