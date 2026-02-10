<?php

namespace AwardWallet\Engine\copaair;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class CopaairExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://members.copaair.com/en/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//input[@id="username"] | //div[@class="connectMiles"]/span[contains(text(), "ConnectMiles No")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//div[@class="connectMiles"]/span[contains(text(), "ConnectMiles No")]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/\d+/', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="username"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[contains(@class, "button-login-id")]')->click();

        sleep(3);

        $submitResult = $tab->evaluate('//iframe[@title="reCAPTCHA"] | //input[@id="password"]');

        if ($submitResult->getNodeName() == "IFRAME") {
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
            $submitResult = $tab->evaluate('//input[@id="password"]', EvaluateOptions::new()->timeout(60));
        }

        $password = $submitResult;
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[contains(@class, "button-login-password")]')->click();

        $submitResult = $tab->evaluate('//span[contains(@id, "error-element")] | //div[@class="connectMiles"]');

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Wrong email or password")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[@data-ga="Header/Top Links/Login"]')->click();
        $tab->evaluate('//p[@aria-label="Log out"]')->click();
        $tab->evaluate('//button[contains(text(), "Log in")]');
    }
}
