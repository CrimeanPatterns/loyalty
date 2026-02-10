<?php

namespace AwardWallet\Engine\exxonmobil;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class ExxonmobilExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://rewards.exxon.com/profile/details";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@name="email"] | //a[@id="link_nav_logout"]');

        return str_contains($result->getAttribute('id'), 'link_nav_logout');
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//input[@name="userName"]/@value[normalize-space()!=""]');
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[@id="link_nav_logout"]')->click();
        $tab->evaluate('//a[@id="link_nav_login"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@name="email"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@name="password"]')->setValue($credentials->getPassword());

        $tab->evaluate('//a[@name="login"]')->click();
        $errorOrSuccess = $tab->evaluate('//input[@id="input_field_verification_1"] | //p[@class="alert-error"] | //a[@id="link_nav_logout"]');

        if (str_contains($errorOrSuccess->getAttribute('id'), 'link_nav_logout')) {
            return new LoginResult(true);
        }

        if (str_contains($errorOrSuccess->getAttribute('id'), 'input_field_verification_1')) {
            $this->logger->notice('Waiting 90 seconds for the passage of 2fa ');
            $errorOrSuccess = $tab->findTextNullable('//a[@id="link_nav_logout"]', FindTextOptions::new()->timeout(90));

            if ($errorOrSuccess) {
                return new LoginResult(true);
            }

            return LoginResult::question('To continue, please enter the verification code from the email we sent you.');
        }

        if (str_contains($errorOrSuccess->getInnerText(),
            'Oops, looks like your email or password is invalid. Try again.')) {
            return LoginResult::invalidPassword($errorOrSuccess->getInnerText());
        }

        if (str_contains($errorOrSuccess->getInnerText(), 'Something has gone wrong unexpectedly.')
            || str_contains($errorOrSuccess->getInnerText(),
                'Oops, looks like your login process cannot be continued')) {
            return LoginResult::providerError($errorOrSuccess->getInnerText());
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();

        // Name
        $st->addProperty('Name',
            beautifulName($tab->findText('//input[@name="userName"]/@value[normalize-space()!=""]')));

        // Card number
        $cardNumber = $tab->findTextNullable('//p[@class = "confirm-card-number" and contains(text(), "Card number")]',
            FindTextOptions::new()->preg("/\:\s*([^<]+)/"));

        if ($cardNumber) {
            $st->addProperty('CardNumber', $cardNumber);
        }

        $tab->gotoUrl("https://exxonandmobilrewardsplus.com/points/activity");
        $tab->findText('//p[contains(@class, "points-balance")] | //p[contains(text(), "Points available")]/preceding-sibling::h2');
        // Balance - Points
        $st->setBalance($tab->findText('//p[contains(@class, "points-balance")] | //p[contains(text(), "Points available")]/preceding-sibling::h2'));
    }
}
