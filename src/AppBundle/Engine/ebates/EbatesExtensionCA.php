<?php

namespace AwardWallet\Engine\ebates;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class EbatesExtensionCA extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.rakuten.ca/member/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $el = $tab->evaluate('//*[contains(text(), "Sign In")] | //span[contains(@class,"member-welcome-text")]//a[@href="/member/dashboard"]/span');

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[contains(@class,"member-welcome-text")]//a[@href="/member/dashboard"]/span', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="signin_email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="signin_password"]');
        $password->setValue($credentials->getPassword());

        sleep(3);

        $tab->evaluate('//button[@id="button-login"]')->click();

        $submitResult = $tab->evaluate('//span[contains(@class,"member-welcome-text")]//a[@href="/member/dashboard"]/span | //div[contains(@class, "error-box")]');

        if ($submitResult->getNodeName() == "SPAN") {
            return new LoginResult(true);
        }

        if ($submitResult->getNodeName() == "DIV") {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Member not found with email or username")
                || strstr($error, "Sorry, that username/password combination is not valid")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);

        $tab->evaluate('//a[@class="nav-signout mem-info-link"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//*[contains(text(), "Sign In")]');
    }
}
