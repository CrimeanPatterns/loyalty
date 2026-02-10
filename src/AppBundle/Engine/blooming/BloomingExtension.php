<?php

namespace AwardWallet\Engine\blooming;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class BloomingExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.bloomingdales.com/account/profile';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//input[@id="email"] | //p[@id="pm-curr-name"]');

        return $el->getNodeName() == "P";
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//p[@id="pm-curr-name"]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $tab->evaluate('//input[@id="email"]');

        sleep(3);

        $login = $tab->evaluate('//input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="pw-input"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//button[@id="sign-in"]')->click();

        $submitResult = $tab->evaluate('//div[@class="pm-edit-info" and p[@id="pm-curr-name"]] | //p[@class="notification-body"] | //small[@id="pw-input-error"] | //small[@id="email-error"] | //div[@id="accCompletionOverlay"]/..');

        if ($submitResult->getNodeName() == 'DIV' && strstr($submitResult->getAttribute('class'), 'overlay-content-container')) {
            $tab->evaluate('//button[@id="cancelForm"]')->click();
            $submitResult = $tab->evaluate('//div[@class="pm-edit-info" and p[@id="pm-curr-name"]] | //p[@class="notification-body"] | //small[@id="pw-input-error"] | //small[@id="email-error"]');
        }

        if ($submitResult->getNodeName() == 'DIV' && !strstr($submitResult->getAttribute('class'), 'overlay-content-container')) {
            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SMALL') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Your email address or password is incorrect.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Sorry, it looks like there's a problem on our end. For assistance, please call ")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);

        $tab->evaluate('//a[contains(@class, "signout")]')->click();

        $logoutResult = $tab->evaluate('//a[contains(@href, "signin") and contains(@class, "link-rail-item")] | //button[contains(@class, "overlay-close-btn")]');

        if ($logoutResult->getNodeName() == 'BUTTON') {
            $logoutResult->click();
        }

        $tab->evaluate('//a[contains(@href, "signin") and contains(@class, "link-rail-item")]');
    }
}
