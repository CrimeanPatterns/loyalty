<?php

namespace AwardWallet\Engine\ctrip;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class CtripExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.trip.com/membersinfo/profile/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form//div[@class="form_wrapper"]//input[@type="text"] | //div[@class="card-left-content" and contains(text(), "@")]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@class="card-left-content" and contains(text(), "@")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//form//div[@class="form_wrapper"]//input[@type="text"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//form//div[@class="form_wrapper"]//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "error") and contains(@class, "tips")] | //span[@class="password-login-btn-text"] | //form//div[@class="form_wrapper"]//input[@type="password"] | //div[@class="cpt-bg-bar"]');

        if ($submitResult->getNodeName() == 'DIV' && strstr($submitResult->getAttribute('class'), "cpt-bg-bar")) {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//div[contains(@class, "error") and contains(@class, "tips")] | //span[@class="password-login-btn-text"] | //form//div[@class="form_wrapper"]//input[@type="password"]', EvaluateOptions::new()->timeout(60));
        }

        if ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Please enter a valid email address to proceed")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == 'SPAN') {
            $submitResult->click();
        }

        $password = $tab->evaluate('//form//div[@class="form_wrapper"]//input[@type="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//form//div[@class="form_wrapper"]//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "card-left-content") and contains(text(), "@")] | //div[@class="toast_modal"]//*[text()] | //div[@class="cpt-bg-bar"]');

        if ($submitResult->getNodeName() == 'DIV' && strstr($submitResult->getAttribute('class'), "cpt-bg-bar")) {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//div[contains(@class, "card-left-content") and contains(text(), "@")] | //div[@class="toast_modal"]//*[text()]', EvaluateOptions::new()->timeout(60));
        }

        if ($submitResult->getNodeName() == 'DIV' && strstr($submitResult->getAttribute('class'), "card-left-content")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your password may be incorrect, or this account may not exist. Please check and try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[contains(@class, "account-username")]')->click();
        $tab->evaluate('//a[contains(@class, "a-logout")]')->click();
        $tab->evaluate('//form//div[@class="form_wrapper"]//input[@type="text"]');
    }
}
