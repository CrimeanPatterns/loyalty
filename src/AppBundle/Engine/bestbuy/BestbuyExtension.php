<?php

namespace AwardWallet\Engine\bestbuy;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class BestbuyExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.bestbuy.com/site/customer/myaccount';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[contains(@class,"cia-form")] | //span[contains(@class, "member-id")]');

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//span[contains(@class, "member-id")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="fld-e"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="fld-p1"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-track="Sign In"]')->click();

        $submitResult = $tab->evaluate('//span[contains(@class, "member-id")] | //div[@class="c-alert-content rounded-r-100 flex-fill v-bg-pure-white p-200 pl-none"]//div | //input[@id="verificationCode"]');

        if (strstr($submitResult->getNodeName(), "SPAN")) {
            return new LoginResult(true);
        } elseif (strstr($submitResult->getNodeName(), "DIV")) {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Oops! The email or password did not match our records. Please try again")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            $question = $tab->evaluate('//p[@class="c-section-title cia-section-title cia-section-title__value-proposition c-section-title body-copy-lg font-weight-normal body-copy-lg v-fw-regular"]')->getInnerText();

            if (!isset($credentials->getAnswers()[$question])) {
                return new LoginResult(false, null, $question);
            }
            $answer = $credentials->getAnswers()[$question];

            $this->logger->info("sending answer: $answer");

            $input = $tab->evaluate('//input[@id="verificationCode"]');
            $input->setValue($answer);

            $button = $tab->evaluate('//button[@class="c-button c-button-secondary c-button-lg c-button-block c-button-icon c-button-icon-leading cia-form__controls__submit "]');
            $button->click();

            $otpSubmitResult = $tab->evaluate('//div[@class="c-alert-content rounded-r-100 flex-fill v-bg-pure-white p-200 pl-none"]//div | //span[contains(@class, "member-id")]');

            if (strstr($submitResult->getAttribute('class'), "c-alert-content")) {
                return new LoginResult(false, $otpSubmitResult->getInnerText(), $question);
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@id="account-menu-account-button"]')->click();
        $tab->evaluate('//form[@name="logoutForm"]/button')->click();
        $tab->evaluate('//form[contains(@class,"cia-form")]');
    }
}
