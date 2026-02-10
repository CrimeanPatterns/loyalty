<?php

namespace AwardWallet\Engine\delta;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class DeltaExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    /**
     * {@inheritDoc}
     */
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.delta.com/myprofile/';
    }

    /**
     * {@inheritDoc}
     */
    public function isLoggedIn(Tab $tab): bool
    {
        $loginButton = $tab->evaluate('//a[contains(normalize-space(), "VIEW YOUR BENEFITS") and contains(@href, "skymiles/medallion-program")] | //div[@class="login-button"]', EvaluateOptions::new()->nonEmptyString());

        return $loginButton->getNodeName() === 'A';
    }

    /**
     * {@inheritDoc}
     */
    public function getLoginId(Tab $tab): string
    {
        $loginId = $tab->evaluate('//text()[normalize-space()="SKYMILES #"]/following::text()[normalize-space()][1]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
        $this->logger->info('!' . $loginId);

        return $loginId;
    }

    /**
     * {@inheritDoc}
     */
    public function logout(Tab $tab): void
    {
        // TODO: Implement logout() method.
        $this->logger->info('!Try Logout');
        $tab->evaluate("//div[contains(@class,'logged-in-container logged-in-flyout')]")->click();
        sleep(1);
        $tab->evaluate("//a[contains(@id,'flyout-logOut-link')]")->click();
        $tab->evaluate("//button[contains(@id,'login-modal-button')]");
    }

    /**
     * {@inheritDoc}
     */
    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@aria-label="SkyMiles Number Or Username*"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@aria-label="Password*"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@role="button"]')->click();

        $errorOrTitle = $tab->evaluate('//div[@class="personalProfileSelectorScreen"] | //p[@class="ng-star-inserted"]', EvaluateOptions::new()->nonEmptyString());

        if ($errorOrTitle->getNodeName() === 'DIV') {
            $this->logger->info('!logged in');

            //url profile page
            $tab->gotoUrl("https://www.delta.com/myskymiles/overview");

            return new LoginResult(true);
        } else {
            $this->logger->info('!error logging in');

            $error = $errorOrTitle->getInnerText();

            return new LoginResult(false, $error);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        // TODO: Implement parse() method.
        $this->logger->info('!Try Parse');
    }
}
