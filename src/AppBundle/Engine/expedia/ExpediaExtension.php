<?php

namespace AwardWallet\Engine\expedia;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;

class ExpediaExtension extends AbstractParser implements LoginWithIdInterface
{

    public string $host, $langEnUrl;

    public function getStartingUrl(AccountOptions $options): string
    {
        switch ($options->login2 ?? null) {
            case 'AR':
                return 'https://www.expedia.com.ar/login?langid=1033';
            case 'AU':
                return 'https://www.expedia.com.au/login';
            case 'AT':
                return 'https://www.expedia.at/login';
            case 'BE':
                return 'https://www.expedia.be/login?langid=2057';
            case 'BR':
                return 'https://www.expedia.com.br/login?langid=1033';
            case 'CA':
                return 'https://www.expedia.ca/login';
            case 'EU':
                return 'https://euro.expedia.net/login';
            case 'DK':
                return 'https://www.expedia.dk/login';
            case 'FI':
                return 'https://www.expedia.fi/login';
            case 'FR':
                return 'https://www.expedia.de/login?langid=2057';
            case 'DE':
                return 'https://www.expedia.de/login?langid=2057';
            case 'HK':
                return 'https://www.expedia.com.hk/login?langid=2057';
            case 'IN':
                return 'https://www.expedia.co.in/login';
            case 'India':
            case 'ID':
                return 'https://www.expedia.co.id/login?langid=2057';
            case 'IE':
                return 'https://www.expedia.ie/login';
            case 'IT':
                return 'https://www.expedia.it/login';
            case 'JP':
                return 'https://www.expedia.co.jp/login';
            case 'MS':
            case 'MY':
                return 'https://www.expedia.com.my/login?langid=2057';
            case 'MX':
                return 'https://www.expedia.mx/login?langid=1033';
            case 'NL':
                return 'https://www.expedia.nl/login?langid=2057';
            case 'NZ':
                return 'https://www.expedia.co.nz/login';
            case 'NO':
                return 'https://www.expedia.no/login?langid=2057';
            case 'PH':
                return 'https://www.expedia.com.ph/login?langid=2057';
            case 'SG':
                return 'https://www.expedia.com.sg/login?langid=2057';
            case 'KR':
                return 'https://www.expedia.co.kr/login?langid=1033';
            case 'ES':
                return 'https://www.expedia.es/login?langid=2057';
            case 'SV':
            case 'SE':
                return 'https://www.expedia.se/login?langid=2057';
            case 'CH':
                return 'https://www.expedia.ch/login?langid=2057';
            case 'TW':
                return 'https://www.expedia.com.tw/login?langid=1033';
            case 'TH':
                return 'https://www.expedia.co.th/login?langid=2057';
            case 'GB':
            case 'UK':
                return 'https://www.expedia.co.uk/login';
            case 'VN':
                return 'https://www.expedia.com.vn/login?langid=2057';
            default:
                return 'https://www.expedia.com/login';
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $el = $tab->evaluate('//div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//button[@data-testid="header-menu-button" and not(contains(text(), "Sign in"))] | //form[@name="loginEmailForm"]');
        return strstr($el->getNodeName(), "BUTTON");
    }

    public function getLoginId(Tab $tab): string
    {
        $tab->evaluate('//div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//button[@data-testid="header-menu-button"]')->click();
        $id = $tab->evaluate('//div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//*[contains(text(), "@")]', EvaluateOptions::new()->nonEmptyString())->getInnerText();
        $tab->evaluate('//div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//button[@data-testid="header-menu-button"]')->click();
        return $id;
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@id="loginFormEmailInput"]');
        $login->setValue($credentials->getLogin());

        $tab->evaluate('//button[@id="loginFormSubmitButton"]')->click();

        $submitResult = $tab->evaluate('//button[@id="passwordButton"] | //div[contains(@class, "uitk-banner-description")]');

        if (strstr($submitResult->getAttribute('class'), "uitk-banner-description")) {
            $error = $submitResult->getInnerText();

            if (strstr($error, "Enter a valid email")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }

            return new LoginResult(false, $error);
        } else  {
            $submitResult->click();
            $password = $tab->evaluate('//input[@id="enterPasswordFormPasswordInput"]');
            sleep(1);
            $password->setValue($credentials->getPassword());
            sleep(1);
            $tab->evaluate('//button[@id="enterPasswordFormSubmitButton"]')->click();

            $submitResult = $tab->evaluate('//div[contains(@class, "uitk-banner-description")] | //div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//button[@data-testid="header-menu-button"]');

            if (strstr($submitResult->getAttribute('class'), "uitk-banner-description")) {
                $error = $submitResult->getInnerText();
    
                if (strstr($error, "Email and password don't match. Please try again")) {
                    return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
                }
    
                return new LoginResult(false, $error);
            } else {
                return new LoginResult(true);
            }
        }
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate('//div[contains(@class, "uitk-layout-flex-justify-content-flex-end")]//button[@data-testid="header-menu-button"]')->click();
        $tab->evaluate('//a[contains(@href, "/user/logout")]')->click();
        sleep(1);
        $tab->evaluate('//button[contains(text(), "Sign in")]');
    }
}
