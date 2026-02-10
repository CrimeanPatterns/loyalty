<?php

namespace AwardWallet\Engine\ebates;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\SelectFrameOptions;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class EbatesExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        if ($options->login2 === 'UK') {
            return 'https://www.rakuten.co.uk/account/settings';
        }

        return 'https://www.rakuten.com/my-account.htm';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//*[contains(text(), "Sign In")] | //div[@data-testid="account-left-nav"]//div[@data-testid="account-left-member-name"]');

        return strstr($el->getNodeName(), "DIV");
    }

    public function getLoginId(Tab $tab): string
    {
        $el = $tab->evaluate('//div[@data-testid="account-left-nav"]//div[@data-testid="account-left-member-name"]', EvaluateOptions::new()->nonEmptyString());

        return $this->findPreg('/hi\s(.*)/i', $el->getInnerText());
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $frame = $tab->selectFrameContainingSelector('//input[@id="emailAddress"]', SelectFrameOptions::new()->method("evaluate"));

        $login = $frame->evaluate('//input[@id="emailAddress"]');
        $login->setValue($credentials->getLogin());

        $password = $frame->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $frame->evaluate('//button[@id="email-auth-btn"]')->click();

        $frame = $tab->selectFrameContainingSelector('//div[@data-testid="account-left-nav"]//div[@data-testid="account-left-member-name"] | //div[contains(@class, "rr-auth-web-error-box")]', SelectFrameOptions::new()->method("evaluate"));

        $submitResult = $frame->evaluate('//div[@data-testid="account-left-nav"]//div[@data-testid="account-left-member-name"] | //div[contains(@class, "rr-auth-web-error-box")]');

        if (strstr($submitResult->getAttribute('data-testid'), "account-left-member-name")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Oops. The email address and/or password you entered is incorrect. Remember, passwords are case-sensitive. Please try again")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);

        $tab->evaluate('//header//a[@href="/my-account.htm"] | //a[@href="/account" and @aria-haspopup="true"]')->focus();
        $tab->evaluate('//a[@role="menuitem" and @href="/"]')->click();
        $tab->evaluate('//button[contains(text(), "Sign In")]');
    }
}
