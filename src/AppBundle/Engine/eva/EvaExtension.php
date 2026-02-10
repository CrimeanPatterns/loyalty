<?php

namespace AwardWallet\Engine\eva;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class EvaExtension extends AbstractParser implements LoginWithIdInterface
{
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://eservice.evaair.com/flyeva/eva/ffp/frequent-flyer.aspx';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[contains(@action, "login")] | //a[@href="personal-data.aspx"]/../dl//span[2]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//a[@href="personal-data.aspx"]/../dl//span[2]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="content_wuc_login_Account"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="content_wuc_login_Password"]');
        $password->setValue($credentials->getPassword());

        $tab->showMessage(Tab::MESSAGE_RECAPTCHA);

        $submitResult = $tab->evaluate('//a[@href="personal-data.aspx"]/../dl//span[2] | //div[@id="wuc_Error"]//li', EvaluateOptions::new()->timeout(60));

        if ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (strstr($error, "You have input wrong password") && strstr($error, "For privacy security policy, the log-in function will be inaccesssible until you finish the procedure of")) {
                return new LoginResult(false, $error, null, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($error, "You have input wrong password")
                || strstr($error, "Invalid Membership Number")
                || strstr($error, "Wrong CAPTCHA entry. Please try again.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[@class="header-main"]//li[contains(@class, "toolbar-item") and contains(@class, "login")]/a[not(contains(@href, "login"))]')->click();
        $tab->evaluate('//div[@class="header-main"]//li[contains(@class, "toolbar-item") and contains(@class, "login")]/a[contains(@href, "login")]');
    }
}
