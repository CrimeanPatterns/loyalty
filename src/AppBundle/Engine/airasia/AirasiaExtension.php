<?php

namespace AwardWallet\Engine\airasia;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class AirasiaExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.airasia.com/account/personal-information/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[@id="aaw-login-tab"] | //input[@id="text-input-given-name"]');

        if ($el->getNodeName() == "INPUT") {
            $tab->evaluate('//div[@id="login-component"]//p')->click();

            return true;
        }

        return false;
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//div[@id="bigMemberId"]/p[not(contains(text(), "AirAsia member ID"))]', EvaluateOptions::new()->nonEmptyString()->visible(false))->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);
        $login = $tab->evaluate('//input[@id="text-input--login"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password-input--login"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@id="loginbutton"]')->click();

        $submitResult = $tab->evaluate('//div[@class="aaw-alert-message-content"] | //input[@id="text-input-given-name"] | //div[@class="aaw-otp-container"]', EvaluateOptions::new()->nonEmptyString()->visible(false));

        if ($submitResult->getNodeName() == 'DIV' && $submitResult->getAttribute('class') == 'aaw-alert-message-content') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Password must contain")
                || strstr($error, "Sorry, you have entered an invalid email and/or password. Please reconfirm your email and/or password and try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == 'DIV' && $submitResult->getAttribute('class') == 'aaw-otp-container') {
            $question = $tab->evaluate('//div[@class="aaw-otp-container"]/p')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[contains(@id, "secondFA")]');
            $input->setValue($answer);

            $button = $tab->evaluate('//div[@class="aaw-button-submit-otp"]/input[@class="aaw-button"]');
            $button->click();

            $otpSubmitResult = $tab->evaluate('//div[@class="aaw-alert-message-content"] | //input[@id="text-input-given-name"]');

            if ($otpSubmitResult->getNodeName() == 'DIV') {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            } else {
                $tab->evaluate('//div[@id="login-component"]//p')->click();

                return new LoginResult(true);
            }
        } elseif ($submitResult->getNodeName() == 'INPUT') {
            $tab->evaluate('//div[@id="login-component"]//p')->click();

            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@id="logout"]', EvaluateOptions::new()->visible(false))->click();
        $tab->evaluate('//div[@id="aaw-login-tab"]');
    }
}
