<?php

namespace AwardWallet\Engine\amtrak;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\ExtensionWorker\ElementNotFoundException;

class AmtrakExtension extends AbstractParser implements LoginWithIdInterface
{

    public string $host, $langEnUrl;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.amtrak.com/guestrewards/account-overview';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//p[contains(@class, "my-account-summary") and contains(@class, "id")]/span | //form[@id="localAccountForm"]', EvaluateOptions::new()->timeout(30));
        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//p[contains(@class, "my-account-summary") and contains(@class, "id")]/span')->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="signInName"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="next"]')->click();

        $submitResult = $tab->evaluate('//p[contains(@class, "my-account-summary") and contains(@class, "id")]/span | //span[@id="lblUserFirstName"] | //div[@class="error pageLevel"] | //div[@id="forgotpassword-simple--subheading"]/div', EvaluateOptions::new()->timeout(30));
        if(!strstr($submitResult->getAttribute('class'), "error")) {
            return new LoginResult(true);
        } else if(strstr($submitResult->getAttribute('class'), "error")) {
            $error = $tab->evaluate('//div[@class="error pageLevel"]/p')->getInnerText();

            if(strstr($error, "We cannot find an account matching the email/Guest Rewards number.")) {
                return new LoginResult(false, $error, ACCOUNT_INVALID_PASSWORD);
            }

            if(strstr($error, 'The username or password provided in the request are invalid.')) {
                return new LoginResult(false, $error, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            $question = $tab->evaluate('//div[@id="forgotpassword-simple--subheading"]/div')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }

            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@id="VerificationCode"]');
            $input->setValue($answer);

            $button = $tab->evaluate('//button[@class="verifyCode"]');
            $button->click();

            try {
                $otpError = $tab->evaluate('//div[@class="verificationErrorText error"]', EvaluateOptions::new()->nonEmptyString()->timeout(5))->getInnerText();

                return new LoginResult(false, $otpError, $question);
            } catch (ElementNotFoundException $e) {
                $this->logger->info('otp error not found');

                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="parsys_column two-columns-c1"]//a[@data-log-out=""]')->click();
        sleep(1);
        $tab->evaluate('//div[@class="search-btn-container col-lg pl-0 pr-0 pl-lg-3 d-none d-lg-block"]');
    }
}