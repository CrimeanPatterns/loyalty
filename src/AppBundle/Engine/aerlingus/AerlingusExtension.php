<?php

namespace AwardWallet\Engine\aerlingus;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class AerlingusExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.aerlingus.com/app/user-profile/my-overview';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//button[@data-test-id="test_button_login_page"] | //b[contains(@class, "membership-id")]');

        return $el->getNodeName() == "B";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//b[contains(@class, "membership-id")]', EvaluateOptions::new()->nonEmptyString()->timeout(30))->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//button[@data-test-id="test_button_login_page"]')->click();

        $login = $tab->evaluate('//input[@formcontrolname="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@formcontrolname="password"]');
        $password->setValue($credentials->getPassword());

        sleep(1);

        $tab->evaluate('//button[@data-test-id="test_button_login_page"]')->click();

        $submitResult = $tab->evaluate('//iframe[@title="reCAPTCHA"] | //p[@role="alert"] | //b[contains(@class, "membership-id")] | //ei-message//p[not(contains(text(), "aptcha"))] | //input[@name="code"] | //a[@data-test-id="test_login_error_page_contactus_link"]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'IFRAME') {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//p[@role="alert"] | //b[contains(@class, "membership-id")] | //ei-message//p[not(contains(text(), "aptcha"))] | //input[@name="code"] | //a[@data-test-id="test_login_error_page_contactus_link"]', EvaluateOptions::new()->timeout(60));
        }

        if ($submitResult->getNodeName() == 'A') {
            $error = $tab->evaluate('//a[@data-test-id="test_login_error_page_contactus_link"]/..');

            return new LoginResult(false, $error->getInnerText(), null, ACCOUNT_PROVIDER_ERROR);
        } elseif ($submitResult->getNodeName() == 'B') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'P') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "These log in details are incorrect. Please try again or recover your details.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            $question = $tab->evaluate('//div[@class="auth0-lock-form"]/p/span | //div[@id="custom-prompt-logo"]/..//p')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@name="code"]');
            $input->setValue($answer);

            $button = $tab->evaluate('//button[@class="auth0-lock-submit"] | //button[@type="submit"] and @name="action" and @data-action-button-primary="true"');
            $button->click();

            $otpSubmitResult = $tab->evaluate('//div[contains(@class, "auth0-global-message-error")]/span | //b[contains(@class, "membership-id")]');

            if ($otpSubmitResult->getNodeName() == 'B') {
                return new LoginResult(true);
            } else {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[@id="notCorporateUser"]')->click();
        $tab->evaluate('//a[@data-test-id="myAccountPersonalLogoutButton"]')->click();
        $tab->evaluate('//span[@id="login_button_label"]');
    }
}
