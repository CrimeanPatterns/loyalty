<?php

namespace AwardWallet\Engine\alamo;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AlamoExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.alamo.com/en/home.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $tab->evaluate('//section[@id="navigation_right"]//button[@aria-label="Sign In"] | //section[@id="navigation_right"]//span[@class="header-flyout__top__name"]');
        sleep(3);
        $el = $tab->evaluate('//section[@id="navigation_right"]//button[@aria-label="Sign In"] | //section[@id="navigation_right"]//span[@class="header-flyout__top__name"]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.alamo.com/en/alamo-insiders/profile.html');
        $el = $tab->evaluate('//p[contains(@class, "profile-account-member-badge")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/Member #\s(.*)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $tab->evaluate('//section[@id="navigation_right"]//button[@aria-label="Sign In"]')->click();

        $login = $tab->evaluate('//input[@name="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//form[contains(@class, "signin") and contains(@class, "form")]//button[@aria-label="Sign In"]')->click();

        $submitResult = $tab->evaluate('//div[@class="service-error"]/p | //section[@id="navigation_right"]//span[@class="header-flyout__top__name"]');

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "We're sorry, but there's something wrong with your email, member number or password. Please provide a valid email or member number and password to sign in to your account.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "We're sorry. Please try again later.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//section[@id="navigation_right"]//span[@class="header-flyout__top__name"]/../..')->click();
        sleep(3);
        $tab->evaluate('//section[@id="navigation_right"]//button[@aria-label="Sign Out"]')->click();
        sleep(3);
        $tab->evaluate('//section[@id="navigation_right"]//button[@aria-label="Sign In"]');
    }
}
