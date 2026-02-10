<?php

namespace AwardWallet\Engine\ace;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AceExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.acehardware.com/myaccount';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[@id="sign-in-customer-login-email"] | //h1[@id="welcomeTitle"]');

        return $el->getNodeName() == "H1";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//button[@class="signup-header"]')->click();
        $el = $tab->evaluate('//div[@class="member-number"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//input[@id="sign-in-customer-login-email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="sign-in-customer-login-password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-mz-action="login"]')->click();

        $submitResult = $tab->evaluate('//div[@class="signup-greeting"]/following-sibling::div[not(text())] | //div[@id="login-error-summary"]//p | //span[@class="mz-validationmessage" and text()]');

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your email or password is incorrect")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@href, "logout") and contains(@class, "nav-link")]')->click();
        $tab->evaluate('//div[@class="signup-greeting"]/following-sibling::div[text()]');
    }
}
