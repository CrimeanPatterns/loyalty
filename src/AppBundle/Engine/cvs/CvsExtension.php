<?php

namespace AwardWallet\Engine\cvs;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class CvsExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.cvs.com/account/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//cvs-login | //p[contains(@class,"ExtraCare")]');

        return strstr($el->getNodeName(), "P");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//p[contains(@class,"ExtraCare")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="emailField"]');
        $login->setValue($credentials->getLogin());
        $tab->evaluate('//button[@class="continue-button primary"]')->click();

        $submitResult = $tab->evaluate('//cvs-alert-banner | //input[@id="cvs-password-field-input"]');

        if (strstr($submitResult->getNodeName(), "CVS-ALERT-BANNER")) {
            $error = $tab->evaluate('//cvs-alert-banner/p[@slot="description"]')->getInnerText();

            if (strstr($error, "Your email address does not match our records. Make sure you're typing your email address correctly")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        $password = $submitResult;
        $password->setValue($credentials->getPassword());
        $tab->evaluate('//div[@class="button primary"]')->click();

        $submitResult = $tab->evaluate('//cvs-alert-banner | //p[@class="cvs-biometric-enrollment-consent-description"] | //p[contains(@class,"ExtraCare")]/span | //cvs-otp');

        if (strstr($submitResult->getNodeName(), "SPAN")) {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "P") {
            $tab->evaluate('//button[@class="secondary-button"]')->click();

            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "CVS-ALERT-BANNER") {
            $error = $tab->evaluate('//cvs-alert-banner/p[@slot="description"]')->getInnerText();

            if (strstr($error, "Check your spelling and try again")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == "CVS-OTP") {
            $otpFormType = $tab->evaluate('//div[@aria-labelledby="otp-radio-title"] | //input[@id="forget-password-otp-input"]');

            if ($otpFormType->getNodeName() == 'DIV') {
                $tab->evaluate('//input[@type="radio" and contains(@id, "email")]')->click();
                $tab->evaluate('//button[@class="btn" and contains(text(),  "Send Code")]')->click();
            }

            $question = $tab->evaluate('//div[@class="code-container"]//h1/div')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }
            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@id="forget-password-otp-input"]');
            $input->setValue($answer);

            $button = $tab->evaluate('//button[@id="forgot-password-verify-submit"]');
            $button->click();

            $otpSubmitResult = $tab->evaluate('//div[@class="ps-alert-bar-content"]//p | //p[contains(@class,"ExtraCare")]/span');

            if ($submitResult->getNodeName() == "P") {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        }

        return new LoginResult(false);
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        sleep(3); // prevent incorrect click
        $tab->querySelector('cvs-header-desktop')->shadowRoot()->querySelector('cvs-header-acc-dropdown')->shadowRoot()->querySelector('button[aria-controls="account-dropdown"]')->click();
        $tab->querySelector('cvs-header-desktop')->shadowRoot()->querySelector('cvs-header-acc-dropdown')->shadowRoot()->querySelector('cvs-header-acc-content')->shadowRoot()->querySelector('a.redirect-link.last-link.link-top-border')->click();
        $tab->evaluate('//span[contains(text(), "Sign in")]');
    }
}
