<?php

namespace AwardWallet\Engine\airbnb;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\ExtensionWorker\ElementNotFoundException;

class AirbnbExtension extends AbstractParser implements LoginWithIdInterface
{

    public string $host, $langEnUrl;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.airbnb.com/dashboard?locale=en';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//button[@id="headerNavUserButton"] | //form[@data-testid="auth-form" and not(contains(@action ,"/"))] | //form[@action="/authenticate"]');
        return strstr($el->getNodeName(), "BUTTON");
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.airbnb.com/account-settings/personal-info');
        $id = $tab->evaluate('//div[@id="email"]//span[contains(text(), "@")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
        return $id;
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        try {
            $this->logger->debug('removing saved account and preparing email login');
            $tab->evaluate('//button[contains(text(), "Use another account")] | //button[@data-testid="social-auth-button-email"]', EvaluateOptions::new()->timeout(5))->click();
        } catch (ElementNotFoundException $e) {
            $this->logger->debug('saved account not found');
        }

        $login = $tab->evaluate('//input[@data-testid="email-login-email"]');
        $login->setValue($credentials->getLogin());

        sleep(1);

        $tab->evaluate('//button[@data-testid="signup-login-submit-btn"]')->click();

        $submitResult = $tab->evaluate('//input[@data-testid="email-signup-password"] | //div[contains(text(), "Let\'s try that again")]');

        if (strstr($submitResult->getInnerText(), "Let\'s try that again")) {
            $error = $tab->evaluate('//div[contains(text(), "Let\'s try that again")]/parent::*/parent::*/div')->getInnerText();

            if (strstr($error, "Invalid email")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else  {
            $submitResult->setValue($credentials->getPassword());

            sleep(1);

            $tab->evaluate('//button[@data-testid="signup-login-submit-btn"]')->click();

            $submitResult = $tab->evaluate('//div[contains(text(), "Let\'s try that again")] | //*[@d="m12 4 11.3 11.3a1 1 0 0 1 0 1.4L12 28"]/../../../.. | //button[@id="headerNavUserButton"]');

            if (strstr($submitResult->getInnerText(), "Let\'s try that again")) {
                $error = $tab->evaluate('//div[contains(text(), "Let\'s try that again")]/parent::*/parent::*/div')->getInnerText();
    
                if (strstr($error, "Invalid login credentials. Please try again.")) {
                    return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
                }
    
                return new LoginResult(false, $error);
            } else if (strstr($submitResult->getNodeName(), "BUTTON")) {
                $submitResult->click();
                sleep(1);
                $questionOrSuccess = $tab->evaluate('//div[contains(text(), "Enter the code")] | //button[@id="headerNavUserButton"]');

                if(strstr($questionOrSuccess->getNodeName(), "BUTTON")) {
                    return new LoginResult(true);
                }

                $question = $questionOrSuccess->getInnerText();

                if (!isset($credentials->getAnswers()[$question])) {
                    return new LoginResult(false, null, $question);
                }

                $answer = $credentials->getAnswers()[$question];

                $this->logger->info("sending answer: $answer");
    
                $inputSegments = $tab->evaluateAll('//input[contains(@id, "airlock-code-input_codeinput")]');
    
                for ($i = 0; $i < count($inputSegments); $i++) {
                    $inputSegments[$i]->setValue($answer[$i]);
                }
    
                $otpResult = $tab->evaluate('//div[contains(text(), "The code you provided is incorrect. Please try again")] | //button[@id="headerNavUserButton"]');
                if(strstr($otpResult->getNodeName(), "BUTTON")) {
                    return new LoginResult(true);
                } else {
                    return new LoginResult(false, $otpResult->getInnerText(), $question);
                }
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@id="headerNavUserButton"]')->click();
        $tab->evaluate('//form[@action="/logout"]//button')->click();
        $tab->evaluate('//a[@href="/host/homes"]');
    }
}