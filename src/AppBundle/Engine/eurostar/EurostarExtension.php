<?php

namespace AwardWallet\Engine\eurostar;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class EurostarExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.eurostar.com/customer-dashboard/en?market=uk-en';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[contains(@data-testid, "summary")] | //form[@name="login"]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[contains(text(), "Club Eurostar number")]/../../span', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $login = $tab->evaluate('//form[@name="login"]//input[contains(@id, "email")]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//form[@name="login"]//input[contains(@id, "password")]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//form[@name="login"]//button[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//form[@name="login"]//span[@id="email-error"] | //form[@name="login"]//span[@id="password-error"] | //div[@data-testid="notification-banner"]//p | //div[contains(@data-testid, "summary")]');

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == "SPAN") {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Sorry, we don't recognise that username or password.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[contains(@href, "logout")]')->click();
        $tab->evaluate('//form[@name="login"]//input[contains(@id, "email")]');
    }
}
