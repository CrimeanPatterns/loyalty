<?php

namespace AwardWallet\Engine\dominos;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class DominosExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    private $mainUrl = 'https://www.dominos.com/en/';

    public function getStartingUrl(AccountOptions $options): string
    {
        if (isset($options->login2) && $options->login2 == 'Canada') {
            $this->mainUrl = 'https://www.dominos.ca/en/';
        }

        return $this->mainUrl;
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//button[@data-quid="nav-sign-in-button"] | //li/a[@href="/en/pages/customer/#!/customer/profile/"] | //nav[@aria-label="primary"]//div[@class="profile-login-block"]//a[@href="/en/pages/customer/#!/customer/login/"]');

        return $el->getNodeName() == "A" && strstr($el->getAttribute('href'), "profile");
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl("{$this->mainUrl}pages/customer/#!/customer/profile/");

        return $tab->evaluate('//p[contains(@class, "profile-hub") and contains(text(), "@")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1); // prevent incorrect click;
        $tab->evaluate('//button[@data-quid="nav-sign-in-button"] | //nav[@aria-label="primary"]//div[@class="profile-login-block"]//a[@href="/en/pages/customer/#!/customer/login/"]')->click();

        $login = $tab->evaluate('//input[@name="Email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="Password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-quid="sign-in-remember-button"] | //button[@data-quid="pizza-profile-login-button-submit"]')->click();

        $submitResult = $tab->evaluate('//li/a[@href="/en/pages/customer/#!/customer/profile/"] | //div[@aria-label="Attention!" and @data-quid="login-modal"]//p | //p[contains(@class, "errorText")]');

        if ($submitResult->getNodeName() == 'A') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "We could not locate a Pizza Profile with that e-mail and password combination. Please make sure you are using the e-mail address associated with your Domino's Pizza Profile")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//*[@data-quid="nav-sign-out-button"]')->click();
        $tab->evaluate('//button[@data-quid="nav-sign-in-button"] | //nav[@aria-label="primary"]//div[@class="profile-login-block"]//a[@href="/en/pages/customer/#!/customer/login/"]');
    }
}
