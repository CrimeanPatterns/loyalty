<?php

namespace AwardWallet\Engine\airmilesca;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class AirmilescaExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.airmiles.ca/en/profile.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[@id="login-page-user-id-field"] | //div[@data-testid="Collector Number"]//p');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@data-testid="Collector Number"]//p', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="login-page-user-id-field"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@id="login-submit-btn"]')->click();

        $submitResult = $tab->evaluate('//p[contains(@class, "hasError")] | //input[@id="login-page-password-field"]');

        $this->logger->debug("submitResult: " . $submitResult->getNodeName());

        if ($submitResult->getNodeName() == 'P') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        }

        $password = $submitResult;
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="login-submit-btn"]')->click();

        $submitResult = $tab->evaluate('//p[contains(@class, "hasError")] | //iframe[contains(@title, "recaptcha")] | //div[contains(@class, "V2Alert")]//span[text() and not(contains(text(), "Please wait..."))] | //div[@data-testid="Collector Number"]//p | //a[@data-track-id="skip-conversion"]', EvaluateOptions::new()->timeout(30));

        $this->logger->debug("submitResult: " . $submitResult->getNodeName());

        if ($submitResult->getNodeName() == 'IFRAME') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//p[contains(@class, "hasError") and not(contains(text(), "Please wait..."))] | //div[contains(@class, "V2Alert")]//span[text() and not(contains(text(), "Please wait..."))] | //div[@data-testid="Collector Number"] | //a[@data-track-id="skip-conversion"]', EvaluateOptions::new()->timeout(60));
        }

        $this->logger->debug("submitResult: " . $submitResult->getNodeName());

        if ($submitResult->getNodeName() == 'A') {
            $submitResult->click();
            $submitResult = $tab->evaluate('//p[contains(@class, "hasError") and not(contains(text(), "Please wait..."))] | //div[contains(@class, "V2Alert")]//span[text() and not(contains(text(), "Please wait..."))] | //div[@data-testid="Collector Number"]');
        }

        $this->logger->debug("submitResult: " . $submitResult->getNodeName());

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The sign-in credentials you've entered do not match our records.")
                || strstr($error, "Your PIN must be only 4 digits.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@href="/en/logout"]')->click();
        $tab->evaluate('//a[@data-track-id="sign-in"]');
    }
}
