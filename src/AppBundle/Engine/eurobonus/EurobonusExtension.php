<?php

namespace AwardWallet\Engine\eurobonus;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class EurobonusExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.flysas.com/en/profile/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="username"] | //div[contains(@class, "member-tag")]/../following-sibling::div');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[contains(@class, "member-tag")]/../following-sibling::div', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->showMessage(Tab::MESSAGE_RECAPTCHA);

        $submitResult = $tab->evaluate('//div[contains(@class, "member-tag")]/../following-sibling::div | //span[@id="error-element-password"]', EvaluateOptions::new()->timeout(60));

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "We couldn't find you using this login ID and password combination. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@element="login-btn"]');
        sleep(2);
        $tab->evaluate('//button[@element="login-btn"]')->click();
        $tab->evaluate('//a[@href="https://www.flysas.com/en/profile/settings"]/following-sibling::a')->click();
        $tab->evaluate('//div[@class="sas-main-market-selector"]');
    }
}
