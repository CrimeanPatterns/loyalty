<?php

namespace AwardWallet\Engine\carlson;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\ElementNotFoundException;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class CarlsonExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.radissonhotels.com/en-us/radisson-rewards/login';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        // $el = $tab->evaluate('//div[contains(@class, "rewards-number-wrapper")]//span[contains(@class, "clipboard-copy")] | //form[@action="/en-us/" and contains(@class, "login-form")]');
        try {
            $el = $tab->evaluate('//div[contains(@class, "rewards-number-wrapper")]//span[contains(@class, "clipboard-copy")]', EvaluateOptions::new()->timeout(5));

            return strstr($el->getAttribute('class'), "clipboard-copy");
        } catch (ElementNotFoundException $e) {
            return false;
        }
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[contains(@class, "rewards-number-wrapper")]//span[contains(@class, "clipboard-copy")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//form[@action="/en-us/"]//input[@name="user"]');
        $password = $tab->evaluate('//form[@action="/en-us/"]//input[@name="password"]');

        $login->setValue($credentials->getLogin());
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//form[@action="/en-us/"]//button[@type="submit"]')->click();

        $loginResult = $tab->evaluate('//div[@id="modal-invalid-credentials-error"]//strong[@class="modal-body__title"] | //div[contains(@class, "rewards-number-wrapper")]//span[contains(@class, "clipboard-copy")]');

        if (strstr($loginResult->getAttribute('class'), "clipboard-copy")) {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } else {
            $this->logger->info('error logging in');
            $error = $loginResult->getInnerText();

            if (strstr($error, "The email address/Radisson Rewards number or the password is not correct. Please try again or click ‘Forgot password’ to reset it")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@class="rhg-avatar js-offcanvas-loyalty customer-info-button"]')->click();
        $tab->evaluate('//a[@id="customer-logout-btn"]')->click();
        $tab->evaluate('//button[@data-testid="login-button" and not(contains(@class, "mobile"))]');
    }
}
