<?php

namespace AwardWallet\Engine\aegean;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class AegeanExtension extends AbstractParser implements LoginWithIdInterface
{
    /**
     * {@inheritDoc}
     */
    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://en.aegeanair.com/milesandbonus/my-account/';
    }

    /**
     * {@inheritDoc}
     */
    public function isLoggedIn(Tab $tab): bool
    {
        $loginButton = $tab->evaluate('//h2[contains(normalize-space(), "Options for redeeming my award miles:")] | //a[normalize-space()="Register"]', EvaluateOptions::new()->nonEmptyString());

        return $loginButton->getNodeName() === 'H2';
    }

    /**
     * {@inheritDoc}
     */
    public function getLoginId(Tab $tab): string
    {
        $loginId = $tab->evaluate('//div[contains(@class, "number")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
        $this->logger->info('!' . $loginId);

        return $loginId;
    }

    /**
     * {@inheritDoc}
     */
    public function logout(Tab $tab): void
    {
        $this->logger->info('!Try Logout');
        $tab->gotoUrl("https://en.aegeanair.com/sys/member/logout");
        sleep(1);
    }

    /**
     * {@inheritDoc}
     */
    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[contains(@id, "UsernameloginPageFormId")]');
        $login->setValue($credentials->getLogin());
        $password = $tab->evaluate('//input[contains(@id, "PasswordloginPageFormId")]');
        $password->setValue($credentials->getPassword());
        $tab->evaluate('//text()[normalize-space()="Login with Facebook"]/preceding::button[contains(normalize-space(), "Login")][1]')->click();

        sleep(1);
        $result = $tab->evaluate("//button[contains(@id, 'otp-modal-send-button')]");

        if (stripos($result->getInnerText(), 'Send one-time password') !== false) {
            $errorOrTitle = $tab->evaluate('//h1[normalize-space()="My Miles+Bonus account"] | //p[@class="ng-star-inserted"]', EvaluateOptions::new()->nonEmptyString()->allowNull(true)->timeout(90));

            if ($errorOrTitle && $errorOrTitle->getNodeName() === 'H1') {
                $this->logger->info('!logged in');

                return new LoginResult(true);
            }
        }

        $this->logger->info('!error logging in');

        return new LoginResult(false);
    }
}
