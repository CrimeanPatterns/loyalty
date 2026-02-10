<?php

namespace AwardWallet\Engine\carnival;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class CarnivalExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.carnival.com/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//a[contains(@href, "accounts/login")] | //a[contains(@href, "accounts/logout")]');

        return strstr($el->getAttribute('href'), "accounts/logout");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//h6[contains(text(), "VIFP Club")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/VIFP Club # : (.*)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//a[contains(@href, "accounts/login")]')->click();

        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@data-testid="loginRegisterFormSubmitButton"]')->click();

        $submitResult = $tab->evaluate('//a[contains(@href, "accounts/logout")] | //li[@class="errf-item"] | //div[@class="ffr-val-msg"]/div');

        if ($submitResult->getNodeName() == "A") {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "DIV") {
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "We're sorry but the credentials entered do not match.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@href, "accounts/logout")]')->click();
        $tab->evaluate('//a[contains(@href, "accounts/login")]');
    }
}
