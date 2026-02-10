<?php

namespace AwardWallet\Engine\europcar;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class EuropcarExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.europcar.com/EBE/module/driver/DriverSummary.do';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="loginForm"] | //label[@for="europcarId"]');

        return $el->getNodeName() == "LABEL";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//label[@for="europcarId"]/..', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/europcar driver id:\s(.*)/i', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="driverID"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[contains(@class, "login_tab_footer")]/a[span[contains(text(), "LOGIN")]]')->click();

        $submitResult = $tab->evaluate('//label[@for="europcarId"] | //div[@class="error normalFrame" and contains(text(), "Driver ID")]');

        if ($submitResult->getNodeName() == 'LABEL') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Driver ID (email address) and/or password are invalid: please double-check and try again")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://www.europcar.com/EBE/module/driver/AuthenticateDrivers1000.do?action=6');
        sleep(3);
    }
}
