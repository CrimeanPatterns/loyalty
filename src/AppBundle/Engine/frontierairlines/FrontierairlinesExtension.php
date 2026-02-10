<?php

namespace AwardWallet\Engine\frontierairlines;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class FrontierairlinesExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://booking.flyfrontier.com/FrontierMiles/Profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[@class="slider-container slider-visible"]//input[@name="email"] | //span[@class="member-number tile-sub-header"]');

        return strstr($el->getNodeName(), "SPAN");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//span[@class="member-number tile-sub-header"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/Member #:\s(.*)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//div[@class="slider-container slider-visible"]//input[@name="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//div[@class="slider-container slider-visible"]//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[@class="slider-container slider-visible"]//div[@name="submit"]')->click();

        $submitResult = $tab->evaluate('//img[@class="user-avatar pointer"] | //div[@class="slider-container slider-visible"]//div[@class="error-message"]');

        if (strstr($submitResult->getAttribute('class'), "user-avatar")) {
            $tab->evaluate('//div[@class="user-background pointer logged-in"]')->click();
            $tab->evaluate('//div[@class="logout-container vertical center pointer visible"]//div[2]')->click();

            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Error: Your login information is incorrect. Please check your details and try again or")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="user-background pointer logged-in"]')->click();
        $tab->evaluate('//div[@class="logout-container vertical center pointer visible"]//div[3]')->click();
        $tab->evaluate('//a[@href="#findFlights-tab"]');
    }
}
