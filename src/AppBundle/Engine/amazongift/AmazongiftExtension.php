<?php

namespace AwardWallet\Engine\amazongift;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class AmazongiftExtension extends AbstractParser implements LoginWithIdInterface
{
    use TextTrait;

    public function getStartingUrl(AccountOptions $options): string
    {
        $this->logger->debug("Region => {$options->login2}");

        switch ($options->login2) {
            case 'UK':
                return "https://www.amazon.co.uk/ref=ap_frn_logo";

            case 'France':
                return "https://www.amazon.fr/ref=ap_frn_logo";

            case 'Canada':
                return "https://www.amazon.ca/ref=nav_logo";

            case 'Germany':
                return "https://www.amazon.de/ref=ap_frn_logo";

            case 'Japan':
                return "https://www.amazon.co.jp/";

            case 'USA': default:
            return "https://www.amazon.com";
        }
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());

//        капчта на старте
//        <form method="get" action="/errors/validateCaptcha" name="">

        $checkLogin = $tab->evaluate("//a[@id='nav-link-accountList']");

        if (strpos($checkLogin->getInnerText(), 'Hello, sign in') !== false) {
            return false;
        }

        return strpos($checkLogin->getInnerText(), 'Hello,') !== false;
    }

    public function getLoginId(Tab $tab): string
    {
        $name = $tab->querySelector('#nav-link-accountList-nav-line-1')->getInnerText();

        return $this->findPreg("/^Hello, (.+)$/", trim($name)); // TODO other languages??? "/^\w+, (.+)$/"
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $login = $tab->evaluate('//input[@name="email"]');
        $login->setValue($credentials->getLogin());
//        $login = $tab->evaluate('//input[@name="username"]');
//        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="password"]');
        $password->setValue($credentials->getPassword());

        $tab->evaluate('//input[@id="signInSubmit"]')->click();
        $tab->evaluate('
            //form[@id="verification-code-form"]
            | //form[contains(@action,"verify")]
            | //div[@id="auth-error-message-box"]
            | //a[contains(@href,"=nav_youraccount_btn")]
        ')->click();
        //a[contains(@href,"=nav_youraccount_btn")]
        /*
                Hello, Alexi
          Account & Lists*/

        /*
         document.querySelector('#nav-link-accountList-nav-line-1').innerText
        'Hello, Alexi'

         * */
        return new LoginResult(true);
    }

    public function logout(Tab $tab): void
    {
        $tab->gotoUrl("https://www.amazon.com/gp/flex/sign-out.html?path=%2Fgp%2Fyourstore%2Fhome&useRedirectOnSuccess=1&signIn=1&action=sign-out&ref_=nav_AccountFlyout_signout");
        $tab->evaluate('//h1[normalize-space()="Sign in"]');
    }
}
