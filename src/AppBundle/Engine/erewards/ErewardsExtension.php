<?php

namespace AwardWallet\Engine\erewards;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class ErewardsExtension extends AbstractParser implements LoginWithIdInterface
{
    public $regionOptions = [
        ""       => "Select your region",
        "com.au" => "Australia",
        "com.br" => "Brazil",
        "ca"     => "Canada",
        "dk"     => "Denmark",
        "fr"     => "France",
        "de"     => "Germany",
        "com.mx" => "Mexico",
        "nl"     => "Netherlands",
        "es"     => "Spain",
        //        "in"     => "India",// closed ~7 Mar 2020
        //        "sa.com" => "Saudi Arabia",// closed ~7 Mar 2020
        "se"     => "Sweden",
        //        "ch"     => "Switzerland",// closed ~7 Mar 2020
        //        "ae"     => "United Arab Emirates",// closed ~15 Feb 2020
        "co.uk"  => "United Kingdom",
        "com"    => "United States",
    ];
    private $lastName;
    private $domain = 'com';

    public function getStartingUrl(AccountOptions $options): string
    {
        $this->domain = $this->checkRegionSelection($options->login3);
        $this->logger->notice('Domain => ' . $this->domain);

        return "https://www.e-rewards.{$this->domain}/auth/dashboard";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//form[@name="loginForm"] | //div[contains(@class, "auth-login")] | //div[@class="header-accountInfo-balance"]', EvaluateOptions::new()->nonEmptyString()->visible(false));

        return strstr($el->getAttribute('class'), "header-accountInfo-balance");
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->evaluate('//span[contains(@class, "account-name")]/span[text()]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(3); // prevent incorrect click

        $login = $tab->evaluate('//input[@id="username"] | //input[@id="email"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@type="submit"] | //button[contains(@class, "auth-button-submit")]')->click();

        $submitResult = $tab->evaluate('//div[contains(@class, "alert")] | //span[@ng-message="error_invalidCredentials"] | //div[@class="header-accountInfo-balance"]', EvaluateOptions::new()->nonEmptyString()->visible(false));

        if (strstr($submitResult->getAttribute('class'), "header-accountInfo-balance")) {
            return new LoginResult(true);
        } else {
            $error = $submitResult->getInnerText();

            if (
                strstr($error, "Incorrect login. Please try again")
                || strstr($error, "Login incorreto. Tente novamente.")
                || strstr($error, "Informations de connexion incorrectes. Merci de réessayer.")
                || strstr($error, "Fehler bei der Anmeldung. Bitte versuchen Sie es erneut.")
                || strstr($error, "Inicio de sesión incorrecto. Inténtelo de nuevo.")
                || strstr($error, "Onjuiste inloggegevens. Probeer het nog eens.")
            ) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//span[contains(@class, "account-link")]')->click();
        $tab->evaluate('//div[contains(@class, "account-menu")]//a[contains(@ng-click, "logout")]')->click();
        $tab->evaluate('//a[@href="/login"]');
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'com';
        }

        return $region;
    }
}
