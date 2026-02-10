<?php

namespace AwardWallet\Engine\agoda;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\SelectFrameOptions;
use AwardWallet\ExtensionWorker\Tab;

class AgodaExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.agoda.com/en-gb/account/profile.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//iframe[@title="Universal login"] | //span[@id="mmb-name-component-display-name-value"]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[@id="mmb-name-component-display-name-value"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $frame = $tab->selectFrameContainingSelector('//span[@data-cy="email_tab"]', SelectFrameOptions::new()->method("evaluate"));

        $frame->evaluate('//span[@data-cy="email_tab"]')->click();

        $login = $frame->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $frame->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $frame->evaluate('//button[@data-cy="signin-button"]')->click();

        $frame = $tab->selectFrameContainingSelector('//span[@id="mmb-name-component-display-name-value"] | //span[@data-cy="incorrect-login"] | //p[@data-cy="captcha-error"] | //form[@data-cy="verify-otp"]', SelectFrameOptions::new()->method("evaluate"));

        $submitResult = $frame->evaluate('//span[@id="mmb-name-component-display-name-value"] | //span[@data-cy="incorrect-login"] | //p[@data-cy="captcha-error"] | //form[@data-cy="verify-otp"]');

        if ($submitResult->getNodeName() == 'P' && strstr($submitResult->getAttribute('data-cy'), "captcha-error")) {
            $frame->showMessage(Tab::MESSAGE_RECAPTCHA);

            $frame = $tab->selectFrameContainingSelector('//span[@id="mmb-name-component-display-name-value"] | //span[@data-cy="incorrect-login"] | //form[@data-cy="verify-otp"]', SelectFrameOptions::new()->method("evaluate"));

            $submitResult = $frame->evaluate('//span[@id="mmb-name-component-display-name-value"] | //span[@data-cy="incorrect-login"] | //form[@data-cy="verify-otp"]', EvaluateOptions::new()->timeout(60));
        }

        if ($submitResult->getNodeName() == 'SPAN' && strstr($submitResult->getAttribute('id'), "mmb-name-component-display-name-value")) {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN' && strstr($submitResult->getAttribute('data-cy'), "incorrect-login")) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Email or Password is incorrect")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == 'FORM' && strstr($submitResult->getAttribute('data-cy'), "verify-otp")) {
            $question = $frame->evaluate('//form[@data-cy="verify-otp"]/div/span[contains(text(), "has been sent")]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $inputs = $frame->evaluateAll('//div[@data-cy="otp-input-boxes"]/input');

            foreach ($inputs as $i => $el) {
                $el->setValue($answer[$i]);
            }

            $frame->evaluate('//button[@data-cy="submit-otp-button" and not(@disabled)]')->click();

            $otpSubmitResult = $frame->evaluate('//span[@data-cy="invalid-otp-code"] | //span[@id="mmb-name-component-display-name-value"]');

            if ($otpSubmitResult->getNodeName() == 'SPAN' && strstr($submitResult->getAttribute('id'), "mmb-name-component-display-name-value")) {
                return new LoginResult(true);
            } else {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[@data-element-name="user-name"]')->click();
        $tab->evaluate('//p[contains(text(), "SIGN OUT")]')->click();
        $tab->evaluate('//iframe[@title="Universal login"]');
    }
}
