<?php

namespace AwardWallet\Engine\asia;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AsiaExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.cathaypacific.com/cx/en_HK/membership/my-account.html';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//h1[@class="heading cmp-pageTitle__title"] | //p[@class="mpo_card-subheading"]', EvaluateOptions::new()->nonEmptyString());

        return strstr($el->getNodeName(), "P");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//p[@class="mpo_card-subheading"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $el = $tab->evaluate('//span[contains(text(), "Sign in with membership number")] | //input[@name="membernumber"]');

        if (strstr($el->getNodeName(), "SPAN")) {
            $el->click();
        }

        $login = $tab->evaluate('//input[@name="membernumber"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@class="button -primary"]')->click();

        $submitResult = $tab->evaluate('//p[@class="mpo_card-subheading"] | //div[@class="serverSideError__messages"]');

        if (strstr($submitResult->getAttribute('class'), "mpo_card-subheading")) {
            return new LoginResult(true);
        } else {
            $error = $tab->evaluate('//div[@class="serverSideError__messages"]/span')->getInnerText();

            if (
                strstr($error, "Your sign-in details are incorrect. Please check your details and try again")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[@class="welcomeLabel sensitive-data"]')->click();
        sleep(1);
        $tab->evaluate('//div[@class="headerLg__profileContainer -show"]//span[contains(text() ,"Sign out")]')->click();
        $tab->evaluate('//a[contains(text(), "Sign in / up")]');
    }
}
