<?php

namespace AwardWallet\Engine\ebates;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class EbatesExtensionDE extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://rakuten.de/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        sleep(3);
        $el = $tab->evaluate('//div[@id="nav-header-login-button"] | //div[contains(@class, "profile_profile") and @data-qa-id="profile-dropdown"]//span[@class="false"]');

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//div[contains(@class, "profile_profile") and @data-qa-id="profile-dropdown"]//span[@class="false"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/[a-zA-Z]+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="user_id"] | //div[contains(text(), "Sign in with a different account")] | //div[@id="nav-header-login-button"]');

        if ($login->getNodeName() == 'DIV') {
            $login->click();
        }

        $login = $tab->evaluate('//input[@id="user_id"]');
        sleep(3);
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//div[contains(text(), "Next")]')->click();

        $submitResult = $tab->evaluate('//div[@class="font-size-14 spacing-7-7 s p wf ie-flex-fix-320"] | //input[@id="password_current"]', EvaluateOptions::new()->timeout(30));

        if ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "That username and/or email could not be found")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }

        $password = $submitResult;
        sleep(3);
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[contains(text(), "Next")]')->click();

        $submitResult = $tab->evaluate('//div[@class="font-size-14 spacing-7-7 s p wf ie-flex-fix-320"] | //div[contains(@class, "profile_profile") and @data-qa-id="profile-dropdown"]//span[@class="false"]', EvaluateOptions::new()->timeout(60));

        if ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Password is incorrect. Please try again")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else {
            return new LoginResult(true);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[contains(@class, "profile_profile") and @data-qa-id="profile-dropdown"]//span[@class="false"]');
        $tab->gotoUrl('https://eu.login.account.rakuten.com/sso/logout?post_logout_redirect_uri=https://rakuten.de');
        $tab->evaluate('//div[@id="nav-header-login-button"]');
    }
}
