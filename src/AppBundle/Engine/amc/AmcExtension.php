<?php

namespace AwardWallet\Engine\amc;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AmcExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return 'https://www.amctheatres.com/';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//span[contains(text(), "My AMC")] | //button[contains(text(), "Sign In")]');

        return $el->getNodeName() == "SPAN";
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->gotoUrl('https://www.amctheatres.com/amcstubs/wallet');

        return $tab->evaluate('//div[@class="StubsCard-Info"]/span', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3);

        $tab->evaluate('//button[contains(text(), "Sign In")]')->click();

        $login = $tab->evaluate('//input[@name="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//div[@class="Password"]/input');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//div[contains(@class, "Submission-buttons")]/button[@type="submit" and not(@disabled)] | //span[contains(@class, "error-message")]')->click();

        $submitResult = $tab->evaluate('//span[contains(text(), "Hello")]/.. | //div[contains(@class, "ErrorMessageAlert")]/p | //span[contains(@class, "error-message")]');

        if ($submitResult->getNodeName() == 'DIV') {
            sleep(2);

            return new LoginResult(true);
        } elseif ($submitResult->getNodeName() == 'SPAN') {
            return new LoginResult(false, $submitResult->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Invalid form submission.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($error, "The information you entered doesn't match what we have on file. Please check the information you entered or create a new AMC Stubs Account.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//button[contains(text(), "Sign Out")]', EvaluateOptions::new()->visible(false))->click();
        sleep(1);
        $tab->evaluate('//button[contains(text(), "Sign In")]');
    }
}
