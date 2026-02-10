<?php

namespace AwardWallet\Engine\cheapoair;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class CheapoairExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.cheapoair.com/profiles/#/my-account/my-details';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $tab->evaluate('//div[contains(@class, "login") and contains(@class, "form")] | //span[contains(@class, "username-welcome") and text()]');
        sleep(3);
        $el = $tab->evaluate('//div[contains(@class, "login") and contains(@class, "form")] | //span[contains(@class, "username-welcome") and text()]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//span[contains(@class, "username-welcome") and text()]');
        sleep(3);
        $el = $tab->evaluate('//span[contains(@class, "username-welcome") and text()]');

        return $this->findPreg('/Hi,\s(.*)/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//div[contains(@class, "login") and contains(@class, "form")]//input[@name="email"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//div[contains(@class, "login") and contains(@class, "form")]//button[@type="button"]')->click();

        $submitResult = $tab->evaluate('//input[@name="firstName"] | //div[contains(@class, "login") and contains(@class, "form")]//input[@name="password"] | //div[@class="validation-error"]');

        if ($submitResult->getNodeName() == 'DIV') {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Please enter a valid email address")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Technical error, please try again later")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        } elseif ($submitResult->getNodeName() == 'INPUT' && $submitResult->getAttribute('name') == 'firstName') {
            return new LoginResult(false, "You are not a member of this loyalty program", null, ACCOUNT_PROVIDER_ERROR);
        }

        $password = $submitResult;
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[contains(@class, "login") and contains(@class, "form")]//button[@type="button"]')->click();

        sleep(1);
        $submitResult = $tab->evaluate('//div[@class="validation-error"] | //div[contains(@class, "alerts-error")] | //span[contains(@class, "username-welcome") and text()]');

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The email or password you entered is incorrect")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Technical error, please try again later")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[contains(@class, "username-welcome") and text()]')->click();
        $tab->evaluate('//span[contains(text(), "Cerrar Sesión") or contains(text(), "Sign Out")]')->click();
        $tab->evaluate('//button[contains(@aria-label, "Sign In")]');
    }
}
