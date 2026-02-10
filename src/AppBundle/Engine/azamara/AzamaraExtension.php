<?php

namespace AwardWallet\Engine\azamara;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AzamaraExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.azamara.com/login';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

        $el = $tab->evaluate('//div[contains(@class, "logged-in") and @data-show-when-authenticated] | //input[@name="identifier"]');

        return $el->getNodeName() == "DIV";
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//a[@id="userDropdown"]', EvaluateOptions::new()->nonEmptyString()->visible(false));

        return $this->findPreg('/hi,\s(.*)!/i', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="identifier"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="credentials.passcode"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[@class="o-form-button-bar"]/input[@type="submit"]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "okta-form-infobox-error")]/p | //div[contains(@class, "logged-in") and @data-show-when-authenticated] | //p[contains(@id,"input-container-error")]');

        if ($submitResult->getNodeName() == 'DIV') {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "This field cannot be left blank")
                || strstr($error, "There is no account with the Username")
                || strstr($error, "Log in failed!")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl('https://www.azamara.com/logout');
        $tab->evaluate('//div[@class="account-circle logged-in hide"]', EvaluateOptions::new()->visible(false));
    }
}
