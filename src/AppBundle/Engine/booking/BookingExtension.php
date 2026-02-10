<?php

namespace AwardWallet\Engine\booking;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use Exception;

class BookingExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://account.booking.com/mysettings/personal';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//button[@data-testid="header-profile"] | //form[@class="nw-signin"]');

        return !strstr($el->getAttribute('class'), "nw-signin");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@data-test-id="mysettings-row-email"]//div[contains(text(), "@")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@type="submit"]')->click();

        $errorOrPassword = $tab->evaluate('//div[@id="username-note"] | //input[@id="password"]');

        if (strstr($errorOrPassword->getAttribute('id'), "username-note")) {
            $error = $errorOrPassword->getInnerText();

            if (strstr($error, "Make sure the email address you entered is correct")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        $errorOrPassword->setValue($credentials->getPassword());

        $tab->evaluate('//button[@type="submit"]')->click();

        $errorOrSuccess = $tab->evaluate('//div[@id="password-note"] | //button[@data-testid="header-profile"]');

        try {
            if (strstr($errorOrSuccess->getAttribute('data-testid'), "header-profile")) { // todo
                return new LoginResult(true);
            }
        } catch (Exception $e) {
            $this->logger->debug('seems that password not completed');
        }

        if (strstr($errorOrSuccess->getAttribute('id'), "password-note")) {
            $error = $errorOrSuccess->getInnerText();

            if (strstr($error, "The email and password combination entered doesn't match")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        // 2fa. for future support
        /*
        if (strstr($errorOrSuccess->getAttribute('id'), "radio")) {
            $tab->evaluate('//input[@id="radio-EMAIL"]')->click();
            $tab->evaluate('//button[@id="form-button--primaryAction"]')->click();
            $tab->evaluate('//b[contains(text(), "@")]');

            $question = $tab->evaluate('//form[@id="form"]/legend/p/span')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $inputSegments = $tab->evaluateAll('//input[contains(@id, "form-input--code")]');

            for ($i = 0; $i < count($inputSegments); $i++) {
                $inputSegments[$i]->setValue($answer[$i]);
            }

            $tab->evaluate('//button[@id="form-button--primaryAction"]')->click();

            $otpErrorOrSuccess = $tab->evaluate('//div[contains(@id, "form-alert")] | //div[@id="header__profile-dropdown"]'); // todo

            if (strstr($otpErrorOrSuccess->getAttribute('id'), "header__profile-dropdown")) {
                $tab->evaluate('//div[@id="header__profile-dropdown"]//button')->click();
                $tab->evaluate('//a[@data-testid="header__profile__lnk-my-account--menuitem__content"]')->click();

                return new LoginResult(true);
            }

            if (strstr($errorOrPassword->getAttribute('id'), "form-alert")) {
                $error = $tab->evaluate('//div[contains(@id, "form-alert")]//div[@class="xp-Alert-Content"]/p')->getInnerText();

                return new LoginResult(false, $error, $question);
            }
        }
        */

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@data-testid="header-profile"]')->click();
        sleep(1);
        $tab->evaluate('//*[@d="m1.19 66.83 20 20a4.002 4.002 0 1 0 5.66-5.66L13.67 68H88a4 4 0 0 0 0-8H13.67l13.18-13.17a4.002 4.002 0 1 0-5.66-5.66l-20 20q-.275.28-.5.6s0 .11-.08.16a3 3 0 0 0-.28.53 2 2 0 0 0-.08.24 3 3 0 0 0-.15.51 3.9 3.9 0 0 0 0 1.58q.054.261.15.51.033.122.08.24.115.274.28.52c0 .06.05.11.08.16q.225.325.5.61m31.13 35c20.876 19.722 53.787 18.787 73.509-2.089 14.874-15.743 18.432-39.058 8.931-58.521-10.77-22.12-42-37.41-69.52-24a52 52 0 0 0-12.91 8.93 4.004 4.004 0 0 1-5.49-5.83 60 60 0 0 1 14.9-10.29C67.26-2.37 106.48 6 122 37.74c14.519 29.787 2.142 65.704-27.645 80.223-22.44 10.938-49.308 6.839-67.465-10.293a4 4 0 0 1 5.48-5.82z"]/../../../../..')->click();
        $tab->evaluate('//a[@data-testid="header-sign-in-button"]');
    }
}
