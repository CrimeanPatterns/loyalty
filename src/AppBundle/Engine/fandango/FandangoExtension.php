<?php

namespace AwardWallet\Engine\fandango;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class FandangoExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.fandango.com/accounts/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool // +
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@id="signin-form"] | //div[@class="dashboard-customer__info"]/h2');

        return strstr($el->getNodeName(), "H2");
    }

    public function getLoginId(Tab $tab): string // +
    {
        return $tab->evaluate('//div[@class="dashboard-customer__info"]/h2', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult // +
    {
        sleep(3); // prevent incorrect form submission

        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@id="sign-in-submit-btn"]')->click();

        $submitResult = $tab->evaluate('//div[@class="dashboard-customer__info"]/h2 | //p[@id="error-notification-msg"]');

        if ($submitResult->getNodeName() == 'H2') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "The account information you have entered does not match our records. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void // +
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//a[@href="/accounts/sign-out"]')->click();
        $tab->evaluate('//form[@id="signin-form"]');
    }
}
